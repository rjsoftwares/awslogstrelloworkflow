<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

/* === Created at RJS, Kolkata   === */
/* === R.Chaudhury, T.Chatterjee, R.Agarwalla === */

/*=============== Config Amazon Web Services Credentials ===================*/

define("aws_bucket_name", "YOUR_AWS_BUCKET"); // Your AWS Bucket name
define('awsAccessKey', 'YOUR_AWS_ACCESS_KEY_ID'); // Your AWS Access key
define('awsSecretKey', 'YOUR_AWS_SECRET_ACCESS_KEY'); // Your AWS Secret key
$aws_folder_path = "AWSLogs/[AWS-ACCOUNT-ID]/elasticloadbalancing/[REGION]/"; // Aws logfiles path
$sdk_path        = "/your/aws/sdk/path/filename.php"; //Aws SDK Path


/*========================================================================*/

/*=============== Config Trello Credentials ===================*/

$member_array = array(
    'YOUR_MEMBER_ID_1' => '@member_1_to_tag',
    'YOUR_MEMBER_ID_2' => '@member_2_to_tag'
);

// Need to Configure Trello memberids and members to tag 

$trello_api_key                     = ''; // Trello Developer Api Key.
$trello_api_endpoint                = 'https://api.trello.com/1'; // Trello Api endpoint.
$trello_list_id_for_newScriptErrors = 'TRELLO_LIST_ID_FOR_NEW_SCRIPT_ERRORS'; // List id of New Script errors
$trello_list_id_for_errorsResolved  = 'TRELLO_LIST_ID_FOR_RESOLVED_ERRORS'; // List id of Completed tasks
$trello_list_id_for_redoTasks       = 'TRELLO_LIST_ID_FOR_REDO_TASKS'; // List id of redo tasks 
$trello_list_id_for_ignore          = 'TRELLO_LIST_ID_FOR_IGNORE_ERRORS'; // List id of ignore tasks
$trello_access_token                = 'YOUR_TRELLO_ACCESS_TOKEN'; //Trello member access token for authentication
$completed_task_level_id            = "TRELLO_COMPLETED_TASK_LEBEL_ID"; //Lebel id for completed tasks

/*============================================================*/

/*=============== Need to config as required ===================*/

$errorcode_array = array(
    '404' => '56b42d06152c3f92fdd73ac3',
    '500' => '56b42d06152c3f92fdd73ac4',
    'slow_process' => '56b42d06152c3f92fdd73ac0'
);

//Errors to found with trello label id

$maxprocessing_time = 3; // Maximum Backend Processing Time You want to fetch from error log in seconds

/*============================================================*/

include_once($sdk_path);
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
$s3 = S3Client::factory(array(
    'key' => awsAccessKey,
    'secret' => awsSecretKey
));

$date          = date("Y/m/d/", strtotime("-1 hour"));
$prefix_folder = $aws_folder_path . $date;

$objects = $s3->getListObjectsIterator(array(
    'Bucket' => aws_bucket_name,
    'Prefix' => $prefix_folder
));
foreach ($objects as $object) {
    $log = $object['Key'];
    $ext = pathinfo($log, PATHINFO_EXTENSION);
    if ($ext == 'log') {
        $result     = $s3->getObject(array(
            'Bucket' => aws_bucket_name,
            'Key' => $log
        ));
        $logbody    = $result['Body'];
        $logbodyarr = array();
        $logbodyarr = explode("\n", $logbody);
        foreach ($logbodyarr as $line) {
            $parts           = explode(' ', $line);
            $errors_1        = trim($parts['7']);
            $errors_2        = trim($parts['8']);
            $background_time = trim($parts['5']);
            $site_name       = trim(urldecode($parts['12']));
            $site_name_arr   = explode("?", $site_name);
            $site_name       = $site_name_arr['0'];
            $query_string    = $site_name_arr['1'];
            if (array_key_exists($errors_1, $errorcode_array) && $errors_1 == $errors_2) {
                if (empty($error_log[$site_name])) {
                    $error_log[$site_name][$query_string] = array_fill_keys(array_keys($errorcode_array), 0);
                    $error_log[$site_name][$query_string][$errors_1]++;
                } else {
                    $error_log[$site_name][$query_string][$errors_1]++;
                }
            }
            
            if ($background_time > $maxprocessing_time && $maxprocessing_time > 0) {
                $error_log[$site_name][$query_string]['slow_process'] = $background_time;
            }
        }
        $result = $s3->deleteObject(array(
            'Bucket' => aws_bucket_name,
            'Key' => $log
        ));
        
    }
}

if (!empty($error_log)) {
    $member_to_tag        = implode(" ", $member_array);
    $membersid_to_add     = implode(",", array_keys($member_array));
    $comments_arr         = array();
    $card                 = getTrelloCards("$trello_api_endpoint/lists/$trello_list_id_for_newScriptErrors/cards?key=$trello_api_key&token=$trello_access_token");
    $completed_list_cards = getTrelloCards("$trello_api_endpoint/lists/$trello_list_id_for_errorsResolved/cards?key=$trello_api_key&token=$trello_access_token");
    $redo_list_cards      = getTrelloCards("$trello_api_endpoint/lists/$trello_list_id_for_redoTasks/cards?key=$trello_api_key&token=$trello_access_token");
    $ignore_list_cards    = getTrelloCards("$trello_api_endpoint/lists/$trello_list_id_for_ignore/cards?key=$trello_api_key&token=$trello_access_token");
    foreach ($error_log as $key => $value) {
        $trello_msg    = '';
        $labels        = '';
        $site_error    = array();
        $check_list    = 0;
        foreach ($value as $query_string => $errors) {
            $trello_msg .= $query_string . " ";
            foreach ($errors as $err_code => $total_err) {
                if ($total_err != 0) {
                    if ($err_code == 'slow_process')
                        $trello_msg .= "(Threshold time - " . $total_err . ") \n";
                    else
                        $trello_msg .= "($err_code Error - " . $total_err . ") \n";
                    $labels .= "," . $errorcode_array[$err_code];
                }
                $site_error[$err_code] = $total_err;
            }
            $trello_msg .= "\n \n";
            $check_list++;
            if ($check_list == 50)
                break;
        }
        $labels = trim($labels, ",");
        if (in_array($key, $card) || in_array($key, $completed_list_cards) || in_array($key, $redo_list_cards) || in_array($key, $ignore_list_cards)) {
            $card_id = array_search($key, $card);
            if (in_array($key, $redo_list_cards)) {
                $card_id = array_search($key, $redo_list_cards);
            }
            if (in_array($key, $completed_list_cards)) {
                $card_id          = array_search($key, $completed_list_cards);
                $postfields_array = array(
                    'key' => $trello_api_key,
                    'token' => $trello_access_token,
                    'value' => $trello_list_id_for_redoTasks
                );
                postToTrello("$trello_api_endpoint/cards/$card_id/idList", $postfields_array, "PUT");
                foreach ($site_error as $site_err => $total_error) {
                    if ($total_error != '0') {
                        $postfields_array = array(
                            'key' => $trello_api_key,
                            'token' => $trello_access_token,
                            'value' => $errorcode_array[$site_err]
                        );
                        postToTrello("$trello_api_endpoint/cards/$card_id/idLabels", $postfields_array, "POST");
                    }
                }
            }
            if (in_array($key, $ignore_list_cards)) {
                $card_id          = array_search($key, $ignore_list_cards);
                $postfields_array = array(
                    'key' => $trello_api_key,
                    'token' => $trello_access_token,
                    'idLabel' => $completed_task_level_id
                );
                postToTrello("$trello_api_endpoint/cards/$card_id/idLabels/$completed_task_level_id", $postfields_array, "DELETE");
                
                
            }
            if ($comments_arr[$card_id] < 50) {
                $postfields_array = array(
                    'key' => $trello_api_key,
                    'token' => $trello_access_token,
                    'text' => "$member_to_tag \n $trello_msg"
                );
                postToTrello("$trello_api_endpoint/cards/$card_id/actions/comments", $postfields_array, "POST");
            }
        } else {
            $postfields_array = array(
                'key' => $trello_api_key,
                'token' => $trello_access_token,
                'idList' => $trello_list_id_for_newScriptErrors,
                'name' => $key,
                'idMembers' => $membersid_to_add,
                'idLabels' => $labels
            );
            $add_new_card     = postToTrello("$trello_api_endpoint/cards", $postfields_array, "POST");
            $new_card_id      = $add_new_card['id'];
            $postfields_array = array(
                'key' => $trello_api_key,
                'token' => $trello_access_token,
                'text' => "$member_to_tag \n $trello_msg"
            );
            postToTrello("$trello_api_endpoint/cards/$new_card_id/actions/comments", $postfields_array, "POST");
            
        }
        
    }
} else {
    echo "No data found";
}

function postToTrello($url, $curlopt_postfields, $request){
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST => $request,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($curlopt_postfields)
    ));
    $result = curl_exec($ch);
    $result = json_decode($result, true);
    return $result;
}

function getTrelloCards($url){
    global $comments_arr;
    $trello_cards  = array();
    $all_cards     = file_get_contents($url);
    $all_cards_arr = json_decode($all_cards, true);
    foreach ($all_cards_arr as $key => $value) {
        $trello_cards[$value['id']] = $value['name'];
        $comments_arr[$value['id']] = $value['badges']['comments'];
    }
    return $trello_cards;
}

?>