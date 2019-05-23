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
mv -v $ARGV5/data/plugins/tmp_miniserver_compare/* $ARGV5/data/plugins/$ARGV3/
rm -rf $ARGV5/data/plugins/tmp_miniserver_compare >/dev/null 2>&1

echo "<INFO> Moving back existing log files"
mv -v /tmp/$ARGV1\_upgrade/log/* $ARGV5/log/plugins/$ARGV3/
rm -rf $ARGV5/log/plugins/$ARGV3/backuplog.*

echo "<INFO> Moving back existing backup archives"
mkdir $ARGV5/webfrontend/html/plugins/$ARGV3/backups/ >/dev/null 2>&1
mv -v $ARGV5/data/plugins/tmp_miniserver_backups/* $ARGV5/webfrontend/html/plugins/$ARGV3/backups/
ln -s $ARGV5/webfrontend/html/plugins/$ARGV3/backups/ $ARGV5/data/plugins/$ARGV3/backups
rm -rf $ARGV5/data/plugins/tmp_miniserver_backups >/dev/null 2>&1

echo "<INFO> Remove temporary folders"
rm -r /tmp/$ARGV1\_upgrade

php -f $ARGV5/webfrontend/html/plugins/$ARGV3/ajax_config_handler.php >/dev/null 2>&1
php -f $ARGV5/webfrontend/html/plugins/$ARGV3/createmsbackup.php symlink >/dev/null 2>&1
 
# Exit with Status 0
exit 0
