#!/usr/bin/perl

# Copyright 2016-2019 
# Michael Schlenstedt, michael@loxberry.de
# Christian Woerstenfeld, loxberry@loxberry.woerstenfeld.de
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

##########################################################################
# Modules
##########################################################################
use LoxBerry::System;
use LoxBerry::Log;
my $logfile 					= "backuplog.log";
my $log 						= LoxBerry::Log->new ( name => 'Miniserverbackup', filename => $lbplogdir ."/". $logfile, append => 1 );
my %ERR 						= LoxBerry::System::readlanguage();

# Complete rededign - from now it's PHP and not Perl anymore
system ("/usr/bin/php -f $lbphtmldir/createmsbackup.php ".@ARGV[0]." >/dev/null 2>&1 &" );
# Wait a second and check if PHP process is there
sleep 1;
my $output_string = `ps -ef | grep "$lbphtmldir/createmsbackup.php"|grep -v grep |wc -l 2>/dev/null`;
if ( int $output_string ne 1 ) 
{
	LOGERR $ERR{'ERRORS.ERR_0037_UNABLE_TO_INITIATE_BACKUP'}; 
}