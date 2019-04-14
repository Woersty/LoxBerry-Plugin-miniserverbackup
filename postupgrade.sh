#!/bin/bash

ARGV0=$0 # Zero argument is shell command
ARGV1=$1 # First argument is temp folder during install
ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
ARGV3=$3 # Third argument is Plugin installation folder
ARGV4=$4 # Forth argument is Plugin version
ARGV5=$5 # Fifth argument is Base folder of LoxBerry
shopt -s dotglob

echo "<INFO> Moving back existing config files"
mv -v /tmp/$ARGV1\_upgrade/config/* $ARGV5/config/plugins/$ARGV3/

echo "<INFO> Moving back existing compare files"
mv -v /tmp/$ARGV1\_upgrade/data/* $ARGV5/data/plugins/$ARGV3/

echo "<INFO> Moving back existing log files"
mv -v /tmp/$ARGV1\_upgrade/log/* $ARGV5/log/plugins/$ARGV3/

echo "<INFO> Moving back existing backup archives"
mv -v /tmp/$ARGV1\_upgrade/backups/* $ARGV5/webfrontend/html/plugins/$ARGV3/backups/
ln -s $ARGV5/webfrontend/html/plugins/$ARGV3/backups/ $ARGV5/data/plugins/$ARGV3/backups

echo "<INFO> Remove temporary folders"
rm -r /tmp/$ARGV1\_upgrade

php -f $ARGV5/webfrontend/html/plugins/$ARGV3/ajax_config_handler.php
 
# Exit with Status 0
exit 0
