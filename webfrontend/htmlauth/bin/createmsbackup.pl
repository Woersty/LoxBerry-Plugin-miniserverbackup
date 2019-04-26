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
my $backupstate_name 			= "backupstate.txt";
my $backupstate_tmp_file 		= "/tmp/".$backupstate_name;
my $log 						= LoxBerry::Log->new ( name => 'Miniserverbackup CronJob' ); 
my %ERR 						= LoxBerry::System::readlanguage();
LOGSTART $ERR{'MINISERVERBACKUP.INF_0128_CRON_CALLED'};
# Complete rededign - from now it's PHP and not Perl anymore
my $output_string = `ps -ef | grep "$lbphtmldir/createmsbackup.php"|grep -v grep |wc -l 2>/dev/null`;
if ( -f $backupstate_tmp_file && int $output_string eq 0 )
{
	$data="";
	open my $fh, '<', $backupstate_tmp_file or LOGERR $ERR{'ERRORS.ERR_0029_PROBLEM_WITH_STATE_FILE'};
	my $data = do { local $/; <$fh> };
	close $fh;
	if ( $data ne "-" )
	{
		notify( $lbpplugindir, $ERR{'GENERAL.MY_NAME'}, $ERR{'ERRORS.ERR_0048_ERR_STATE_FILE_REINIT'}." ".$data,1);
		LOGWARN $ERR{'ERRORS.ERR_0048_ERR_STATE_FILE_REINIT'}." ".$data;
		open(my $fh, '>', $backupstate_tmp_file) or exit;
		print $fh "-";
		close $fh;
	}
}
my $which = 0;
$which = @ARGV[1] if (@ARGV[1]);
system ("/usr/bin/php -f $lbphtmldir/createmsbackup.php ".@ARGV[0]." $which >/dev/null 2>&1 &" );
# Wait a second and check if PHP process is there
sleep 1;
my $output_string = `ps -ef | grep "$lbphtmldir/createmsbackup.php"|grep -v grep |wc -l 2>/dev/null`;
if ( int $output_string == 0 ) 
{
	notify( $lbpplugindir, $ERR{'GENERAL.MY_NAME'}, $ERR{'ERRORS.ERR_0037_UNABLE_TO_INITIATE_BACKUP'},1);
	LOGERR $ERR{'ERRORS.ERR_0037_UNABLE_TO_INITIATE_BACKUP'}; 
}
LOGEND ""; 
