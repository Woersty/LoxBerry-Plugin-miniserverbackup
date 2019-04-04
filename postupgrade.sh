#!/bin/sh

ARGV0=$0 # Zero argument is shell command
ARGV1=$1 # First argument is temp folder during install
ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
ARGV3=$3 # Third argument is Plugin installation folder
ARGV4=$4 # Forth argument is Plugin version
ARGV5=$5 # Fifth argument is Base folder of LoxBerry

echo "<INFO> Copy back existing config files"
mv /tmp/$ARGV1\_upgrade/config/* $ARGV5/config/plugins/$ARGV3/

echo "<INFO> Copy back existing compare files"
mv /tmp/$ARGV1\_upgrade/currentbackup $ARGV5/data/plugins/$ARGV3/

echo "<INFO> Copy back existing log files"
mv /tmp/$ARGV1\_upgrade/log/* $ARGV5/log/plugins/$ARGV3/*

echo "<INFO> Copy back existing backup archives"
mv /tmp/$ARGV1\_upgrade/files/* $ARGV5/webfrontend/html/plugins/$ARGV3/files/ 

echo "<INFO> Remove temporary folders"
rm -r /tmp/$ARGV1\_upgrade

# Exit with Status 0
exit 0
