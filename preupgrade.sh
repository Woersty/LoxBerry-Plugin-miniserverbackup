#!/bin/sh

# Bash script which is executed in case of an update (if this plugin is already
# installed on the system). This script is executed as very first step (*BEFORE*
# preinstall.sh) and can be used e.g. to save existing configfiles to /tmp 
# during installation. Use with caution and remember, that all systems may be
# different!
#
# Exit code must be 0 if executed successfully.
#
# Will be executed as user "loxberry".
#
# We add 5 arguments when executing the script:
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# For logging, print to STDOUT. You can use the following tags for showing
# different colorized information during plugin installation:
#
# <OK> This was ok!"
# <INFO> This is just for your information."
# <WARNING> This is a warning!"
# <ERROR> This is an error!"
# <FAIL> This is a fail!"

# To use important variables from command line use the following code:
ARGV0=$0 # Zero argument is shell command
#echo "<INFO> Command is: $ARGV0"

ARGV1=$1 # First argument is temp folder during install
#echo "<INFO> Temporary folder is: $ARGV1"

ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
#echo "<INFO> (Short) Name is: $ARGV2"

ARGV3=$3 # Third argument is Plugin installation folder
#echo "<INFO> Installation folder is: $ARGV3"

ARGV4=$4 # Forth argument is Plugin version
#echo "<INFO> Installation folder is: $ARGV4"

ARGV5=$5 # Fifth argument is Base folder of LoxBerry
#echo "<INFO> Installation folder is: $ARGV5"

if [ -r /tmp/backupstate.txt ]
then
 while :
 do
  state=`head -c 1 /tmp/backupstate.txt`
  if [ $state = "-" ] 
  then
   echo "<OK> No backup seems in progress. Continue..."
   break
  else
   echo "<INFO> Backup seems in progress. Wait 10 s..."
   cat /tmp/backupstate.txt
   echo
  fi
  sleep 10
 done
else
 echo "<OK> No backup state file. Continue..."
fi


echo "<INFO> Creating temporary folders for upgrading"
mkdir -p /tmp/$ARGV1\_upgrade
mkdir -p /tmp/$ARGV1\_upgrade/config
mkdir -p /tmp/$ARGV1\_upgrade/.currentbackup
mkdir -p /tmp/$ARGV1\_upgrade/log
mkdir -p /tmp/$ARGV1\_upgrade/backups

echo "<INFO> Backing up existing config files"
mv $ARGV5/config/plugins/$ARGV3/* /tmp/$ARGV1\_upgrade/config

echo "<INFO> Backing up existing compare files"
mv $ARGV5/data/plugins/$ARGV3/.currentbackup /tmp/$ARGV1\_upgrade/

echo "<INFO> Backing up existing log files"
mv $ARGV5/log/plugins/$ARGV3/* /tmp/$ARGV1\_upgrade/log

echo "<INFO> Backing up existing backup archives"
mv $ARGV5/webfrontend/html/plugins/$ARGV3/backups/* /tmp/$ARGV1\_upgrade/backups/

# Exit with Status 0
exit 0
