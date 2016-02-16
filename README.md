# About this script

This is a simple script for improving error handling of your web service hosted on Amazon Web Services using Elastic Load Balancers. Here's how it works:

* Download the AWS ELB logs from the S3 bucket.
* Parse the log files and find out exceptions based on user defined parameters.
* Create Trello cards for the exceptions [execution time, 404 & 500 errors].
* In case card already present, add the exception as a comment.
* In case card was marked as "Completed", transfer it to a "Redo" list.
* Add the card to the developer who is going to fix it.

This script can be easily modified for any Apache log based on your requirements. 


# Enable Access Logs

To get AWS reports, first of all you will need to enable access logs for your load balancer. Details on how to enable these are present on the following link:

http://docs.aws.amazon.com/ElasticLoadBalancing/latest/DeveloperGuide/enable-access-logs.html

# Create AWS Access Key

To create an access key for your AWS account : 

* Use your AWS account email address and password to sign in to the AWS Management Console.
* In the upper-right corner of the console, click the arrow next to the account name or number and then click Security Credentials.
* On the AWS Security Credentials page, expand the Access Keys (Access Key ID and Secret Access Key) section.
* Click Create New Access Key. 
	** Note: you can have a maximum of two access keys (active or inactive) at a time.
* Click Download Key File to save the access key ID and secret key to a .csv file on your computer.
* Attach a security policy to the new access key that grants permissions for the appropriate S3 bucket that contains AWS logs.

# Get Trello Credentials

To get Api key follow the below steps:

* Log into your trello account.
* Visit https://trello.com/app-key
* Here you will find "Developer API Key" of Trello.

To get a write access token:
* Go to https://trello.com/1/connect?key=[DEVELOPER_APP_KEY]&name=MyApp&response_type=token&scope=read,write
* It will ask for permissions. Once you allow those, You will get the access token in response.

To get a list id:
* Open your trello board.
* append ".json" to the url and press enter.
* You will get all details of that board in a json format.
* Search for "lists" in the json. In this lists array you will get the details of your lists (like id, name etc).

# Configure Script

The script configuration is simple. Just setup the AWS and Trello credentials in the script.


# USAGE

Once the script is configured it should be run via cron every hour. To execute errorlog.php every 1 hour use the following command:

* crontab -e 00 * * * * /path/to/php /path/to/RJSErrorLog.php