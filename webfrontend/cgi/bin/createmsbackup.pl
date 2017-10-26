#!/usr/bin/perl

# Copyright 2016 Michael Schlenstedt, michael@loxberry.de
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

use FindBin;
use lib "$FindBin::RealBin/../perllib";
use LoxBerry::System;
use LoxBerry::Web;

use LWP::UserAgent;
use XML::Simple qw(:strict);
use Config::Simple;
#use Data::Dumper;
use File::Copy;
use File::Path qw(make_path remove_tree);
use strict;
use warnings;

##########################################################################
# Variables
##########################################################################

# Version of this script
my $version = "0.21";

our $cfg;
our $pcfg;
our $miniservers;
our $url;
our $ua;
our $response;
our $error = 0;
our $success;
our $rawxml;
our $xml;
our @fields;
our $mainver;
our $subver;
our $monver;
our $dayver;
our $miniserverftpport;
our $diryear;
our $dirmday;
our $dirhour;
our $dirmin;
our $dirsec;
our $dirwday;
our $diryday;
our $dirisdst;
our $bkpdir;
our $bkpbase;
our $lftpbin = "lftp";
our $sevenzipbin = "7z";
our $verbose;
our $debug;
our $maxfiles;
# our $installfolder;
our @Eintraege;
our @files;
our $i;
our $msno;
our $miniservercloudurl;
our $miniservercloudurlftpport;
our $logmessage;
our $clouddns;
our $quiet;
our $something_wrong;
our $retry_error = 0;
our $maxdwltries;
our $local_miniserver_ip;
our $foundfiles;
our $remotepath;
our $ext;
our $bkpfolder;
our $mscounter = 0;
my $dontzip;


##########################################################################
# Read Configuration
##########################################################################

print STDERR "Global variables from LoxBerry::System\n";
print STDERR "Homedir:     $lbhomedir\n";
print STDERR "Plugindir:   $lbplugindir\n";
print STDERR "CGIdir:      $lbcgidir\n";
print STDERR "HTMLdir:     $lbhtmldir\n";
print STDERR "Templatedir: $lbtemplatedir\n";
print STDERR "Datadir:     $lbdatadir\n";
print STDERR "Logdir:      $lblogdir\n";
print STDERR "Configdir:   $lbconfigdir\n";

our $bins = LoxBerry::System::get_binaries();
# print STDERR "grepbin: $bins->{GREP}\n";

our %miniservers = LoxBerry::System::get_miniservers();

print STDERR "Number of Miniservers: " . keys(%miniservers) . "\n";

our $lang = lblanguage();

print STDERR "Language: $lang\n";


# $cfg             = new Config::Simple("$home/config/system/general.cfg");
# $clouddns        = $cfg->param("BASE.CLOUDDNS");
# $lang            = $cfg->param("BASE.LANG");
$maxdwltries     = 5; # Maximale Download-Wiederholungen

$pcfg            = new Config::Simple("$lbconfigdir/miniserverbackup.cfg");
$debug           = $pcfg->param("MSBACKUP.DEBUG");
$dontzip		 = $pcfg->param("MSBACKUP.DONT_ZIP");
$maxfiles =	defined $pcfg->param("MSBACKUP.MAXFILES") ? $pcfg->param("MSBACKUP.MAXFILES") : 1;
$bkpbase = defined $pcfg->param("MSBACKUP.BASEDIR") ? $pcfg->param("MSBACKUP.BASEDIR") : "$lbdatadir/currentbackup";
my $bkpworkdir = defined $pcfg->param("MSBACKUP.WORKDIR") ? $pcfg->param("MSBACKUP.WORKDIR") : "$lbdatadir/workdir";
my $bkpziplinkdir = defined $pcfg->param("MSBACKUP.BACKUPDIR") ? $pcfg->param("MSBACKUP.BACKUPDIR") : undef;
my $compressionlevel = defined $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") ? $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") : 5;
my $zipformat = defined $pcfg->param("MSBACKUP.ZIPFORMAT") ? $pcfg->param("MSBACKUP.ZIPFORMAT") : "7z";
my $mailreport = defined $pcfg->param("MSBACKUP.MAILREPORT") ? $pcfg->param("MSBACKUP.MAILREPORT") : undef;

if (! -e "$lbtemplatedir/$lang/language.dat") {
	$lang = 'en';
}
my $languagefileplugin = "$lbtemplatedir/$lang/language.dat";

our $phraseplugin 	= new Config::Simple($languagefileplugin);

# $bkzipdestdir is the static folder files/
# $bkpziplinkdir is the folder we link to
my $bkpzipdestdir = "$lbhtmldir/files";
# NOT implemented:
# To use a different backup destination, 
# 1. If $bkpziplinkdir IS set and $bkpzipdestdir IS NOT symlink
#	- Check if $bkpziplinkdir is available and writeable
# 	- Move $bkpzipdestdir to $bkpziplinkdir
#	- Create symlink from $bkpzipdestdir to $bkpziplinkdir
# 3. If $bkpziplinkdir IS NOT set and $bkpziplinkdir IS a symlink
#   - Move files to workdir
#	- Remove symlink
#	- Move files back to $bkpzipdestdir
# 4. If $bkpziplinkdir IS NOT set and $bkpziplinkdir IS NOT a symlink
#   - Recreate .htaccess if not existing

our $css = "";
#Error Style
#"<div style=\'text-align:left; width:100%; color:#000000; background-color:\'#FFE0E0\';\'>";
our $red_css     = "ERROR";

#Good Style
#"<div style=\'text-align:left; width:100%; color:#000000; background-color:\'#D8FADC\';\'>";
our $green_css     = "OK";

#Download Style
#"<div style=\'text-align:left; width:100%; color:#000000; background-color:\'#F8F4D6\';\'>";
our $dwl_css     = "DWL";

#MS Style
#"<div style=\'text-align:left; width:100%; color:#000000; background-color:\'#DDEFFF\';\'>";
our $ms_css     = "MS#";


##########################################################################
# Main program
##########################################################################

if ($debug == 1)
{
	$debug = 0;
	$verbose = 1;
}
elsif ($debug == 2)
{
	$debug = 1;
	$verbose = 1;
}
else
{
	$debug = 0;
	$verbose = 0;
}

# Start
$logmessage = keys(%miniservers) . " " . $phraseplugin->param("TXT1001") . " $0 (Version V$version)"; &log($green_css);  # ### Miniserver insgesamt - Starte Backup mit Script / Version 
if ($debug) { $logmessage = "LoxBerry::System version: #$LoxBerry::System::VERSION#  LoxBerry::Web version: #$LoxBerry::Web::VERSION#"; &log($green_css); }
# Start Backup of all Miniservers

for $msno (sort keys %miniservers)
{
	# Set Backup Flag
	open(F,">$lbhtmldir/backupstate.txt");
	print F "$msno";
	print STDERR "LBHTMLDIR: $lbhtmldir - msno $msno\n";
	close (F);

	$logmessage = $phraseplugin->param("TXT1002") . " $msno ($miniservers{$msno}{Name})"; &log($green_css); #Starte Backup for Miniserver 

	# if ( ${useclouddns} eq "1" ) {
		# $logmessage = $phraseplugin->param("TXT1003")." http://$clouddns/$miniservercloudurl ".$phraseplugin->param("TXT1004"); &log($green_css); # Using Cloud-DNS xxx for Backup 
		# $dns_info = `$home/webfrontend/cgi/system/tools/showclouddns.pl $miniservercloudurl`;
		# $logmessage = $phraseplugin->param("TXT1005")." $dns_info ($home/webfrontend/cgi/system/tools/showclouddns.pl $miniservercloudurl)"; &log($green_css); # DNS Data
		# my @dns_info_pieces = split /:/, $dns_info;
		# if ($dns_info_pieces[1]) {
			# $dns_info_pieces[1] =~ s/^\s+|\s+$//g;
			# $miniserverport = $dns_info_pieces[1];
		# } else {
		# 	$miniserverport  = 80;
		# }
		# if ($dns_info_pieces[0]) {
			# $dns_info_pieces[0] =~ s/^\s+|\s+$//g;
			# $miniserverip = $dns_info_pieces[0]; 
		# } else {
			# $miniserverip = "127.0.0.1"; 
		# }
	# } else {
	   # if ( $miniserverport eq "" ) {
			# $miniserverport = 80;
	   # } 
	# }
	  
	# Get Miniserver Firmware version
	$url = "http://$miniservers{$msno}{Credentials}\@$miniservers{$msno}{IPAddress}\:$miniservers{$msno}{Port}/dev/cfg/version";
	if ( is_enabled($miniservers{$msno}{UseCloudDNS}) ) { $logmessage = $phraseplugin->param("TXT1029");	&log($red_css);	} # Local Access Only Setting Info
	$logmessage = $phraseplugin->param("TXT1006"); &log($green_css); # Try to read MS Firmware Version
	$ua = LWP::UserAgent->new;
	$ua->timeout(10);
	foreach my $try (0..5) {
		$response = $ua->get($url);
		if (!$response->is_success) {
			$success = undef;
			$logmessage = "TRY $try: " . $phraseplugin->param("TXT1034"); &log($dwl_css);# Unable to fetch Firmware Version. Retry.
			sleep 3;
		} else {
			$success = 1;
			last;
		}
	}
	if (!$success) {
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2001"); &error; # Unable to fetch Firmware Version. Giving up.
		&error;  
		next;
	}
	$success = undef;
	$rawxml  = $response->decoded_content();
	$xml     = XMLin($rawxml, KeyAttr => { LL => 'value' }, ForceArray => [ 'LL', 'value' ]);
	@fields  = split(/\./,$xml->{value});
	$mainver = sprintf("%02d", $fields[0]);
	$subver  = sprintf("%02d", $fields[1]);
	$monver  = sprintf("%02d", $fields[2]);
	$dayver  = sprintf("%02d", $fields[3]);
	$logmessage = $phraseplugin->param("TXT1007").$xml->{value}; &log($green_css); # Miniserver Version

	# Get Miniserver Local IP
	$url = "http://$miniservers{$msno}{Credentials}\@$miniservers{$msno}{IPAddress}\:$miniservers{$msno}{Port}/dev/cfg/ip";
	$logmessage = $phraseplugin->param("TXT1026"); &log($green_css); # Try to read MS Local IP
	$ua = LWP::UserAgent->new;
	$ua->timeout(10);
  	foreach my $try (0..5) {
		$response = $ua->get($url);
		if (!$response->is_success) {
			$success = undef;
			$logmessage = "TRY $try: " . $phraseplugin->param("TXT1035"); &log($dwl_css); # Unable to fetch local IP. Retry.
			sleep(3);
		} else {
		  $success = 1;
		  last;
		}
	}
	if (!$success) {
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2008"); &error; # Unable to fetch local IP. Giving up.
		next;
	}
	$success = undef;
	$rawxml  = $response->decoded_content();
	$xml     = XMLin($rawxml, KeyAttr => { LL => 'value' }, ForceArray => [ 'LL', 'value' ]);
	@fields  = split(/\./,$xml->{value});
	$mainver = sprintf("%02d", $fields[0]);
	$subver  = sprintf("%02d", $fields[1]);
	$monver  = sprintf("%02d", $fields[2]);
	$dayver  = sprintf("%02d", $fields[3]);
	$logmessage = $phraseplugin->param("TXT1027").$xml->{value}; &log($green_css); # Miniserver IP local
	my $local_miniserver_ip = $xml->{value};
	if ( is_enabled($miniservers{$msno}{UseCloudDNS}) ) 
		{
		  $logmessage = $phraseplugin->param("TXT1030") . $miniservers{$msno}{IPAddress}; &log($green_css); # Miniserver IP public
		} 	

	# Get FTP Port from Miniserver
	$miniserverftpport = LoxBerry::System::get_ftpport($msno);
	if (! $miniserverftpport) {
		$logmessage = $phraseplugin->param("TXT2002"); &error; # Unable to fetch FTP port. Giving up.
		$error = 1;
		$something_wrong = 1;
		next;
	}
	
	if ($miniserverftpport ne "21") { $logmessage = $phraseplugin->param("TXT1028"); &log($red_css); } #Warning if local FTP-Port is not 21 

	$logmessage = $phraseplugin->param("TXT1008").$miniserverftpport; &log($green_css); #Using this FTP-Port for Backup: xxx
	
	# Backing up to temporary directory
	my ($dirsec,$dirmin,$dirhour,$dirmday,$dirmon,$diryear,$dirwday,$diryday,$dirisdst) = localtime();
	$diryear = $diryear+1900;
	$dirmon = $dirmon+1;
	$dirmon = sprintf("%02d", $dirmon);
	$dirmday = sprintf("%02d", $dirmday);
	$dirhour = sprintf("%02d", $dirhour);
	$dirmin = sprintf("%02d", $dirmin);
	$dirsec = sprintf("%02d", $dirsec);
	# Create temporary dir
	$bkpfolder = sprintf("%03d", $msno)."_".$miniservers{$msno}{Name};
	  
	$bkpdir = "Backup_$local_miniserver_ip\_$diryear$dirmon$dirmday$dirhour$dirmin$dirsec\_$mainver$subver$monver$dayver";
	  
	$response = make_path ("$bkpbase/$bkpfolder", {owner=>'loxberry', group=>'loxberry', chmod => 0777});
	# This directory may already exist, therefore just check if it is writeable
	if (! -w "$bkpbase/$bkpfolder") {
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2003")." $bkpbase/$bkpfolder"; &error; # Could not write to temporary folder $bkpbase/$bkpfolder. Giving up.
		next;
	}
	$response = make_path ("$bkpworkdir", {owner=>'loxberry', group=>'loxberry', chmod => 0777});
	# This directory may already exist, therefore just check if it is writeable
	if (! -w "$bkpworkdir") {
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2003")." $bkpworkdir"; &error; # Could not write temporary folder $bkpworkdir. Giving up.
		next;
	}
	   
	#############################################
	# Some performance tuning of ZIP creation
	# We do an archive cleanup and caching before
	#############################################
	#
	# Get the oldest zip archive
	$i = 0;
	@files = "";
	@Eintraege = "";
	opendir(DIR, "$bkpzipdestdir/$bkpfolder/");
	@Eintraege = readdir(DIR);
	closedir(DIR);
	  
	foreach(@Eintraege) 
	{
		if ($_ =~ m/Backup_$local_miniserver_ip/) 
		{
		 push(@files,$_);
		}
	}
	@files = sort {$b cmp $a}(@files);

	$foundfiles = scalar(@files) - 1; # There seems to be one blank entry in @files? This is not a real file...
	  
	# Now we have the filelist
	if ($verbose) { $logmessage = $foundfiles." ".$phraseplugin->param("TXT1016")." $bkpzipdestdir/$bkpfolder "; &log($green_css); } # x files found in dir y
	if ($debug)   { $logmessage = "Files: $bkpzipdestdir/$bkpfolder :".join(" + ", @files); &log($green_css); }

	foreach(@files) 
	{
		s/[\n\r]//g;
		$i++;
		if ($i > ($maxfiles-1) && $_ ne "") 
		{
		$ext = substr($_, rindex ($_, '.')+1); 
		
		  if (! -e "$bkpworkdir/$bkpdir.$zipformat" && $ext eq $zipformat) {
			# We use the first file that would be deleted to make an incremental ZIP update
			$logmessage = $phraseplugin->param("TXT1031")." $_"; &log($green_css); # Moving old Backup $_
			move("$bkpzipdestdir/$bkpfolder/$_", "$bkpworkdir/$bkpdir.$zipformat");
		  } else {
			$logmessage = $phraseplugin->param("TXT1017")." $_"; &log($green_css); # Deleting old Backup $_
			unlink("$bkpzipdestdir/$bkpfolder/$_");
		  }
		} 
	}
	# If we have no old file, take the last backup
	  
	#$logmessage = "DEBUG: Filename $files[0]"; &log($green_css); # DEBUG
	# print STDERR "DEBUG: Filename $files[0]\n";

	$ext = substr($files[0], rindex ($files[0], '.')+1); 
	  
	# print STDERR "DEBUG: Extension -->" . $ext . "<--\n";
	if (! -e "$bkpworkdir/$bkpdir.$zipformat" && -e "$bkpzipdestdir/$bkpfolder/$files[0]" && $ext eq $zipformat) {
		$logmessage = $phraseplugin->param("TXT1032"). " $files[0]"; &log($green_css); # Copying last Backup 
		copy("$bkpzipdestdir/$bkpfolder/$files[0]", "$bkpworkdir/$bkpdir.$zipformat");
	}
	if (! -e "$bkpworkdir/$bkpdir.$zipformat") {
		$logmessage = $phraseplugin->param("TXT1033"); &log($green_css); # Tell the people that we have no backup yet
	}
	  
	 # We now possibly have a backup file in $bkpworkdir
	#############################################

	  
	if ($verbose) { $logmessage = $phraseplugin->param("TXT1009")." $bkpbase/$bkpfolder/$bkpdir"; &log($green_css); } # "Temporary folder created: /$bkpbase/$bkpfolder/$bkpdir."
	$logmessage = $phraseplugin->param("TXT1010"); &log($green_css); # Starting Download
	# Download files from Miniserver
	# /log
	$remotepath = "/log";
	download(); 
	# /prog
	$remotepath = "/prog";
	download();
	# /sys
	$remotepath = "/sys";
	download();
	# /stats
	$remotepath = "/stats";
	download();
	# /temp
	$remotepath = "/temp";
	download();
	# /update
	$remotepath = "/update";
	download();
	# /web
	$remotepath = "/web";
	download();
	# /user
	$remotepath = "/user";
	download();

	$logmessage = $phraseplugin->param("TXT1011")." $bkpworkdir/$bkpdir.$zipformat"; &log($green_css); # Compressing Backup xxx ...
	  
	# Zipping
	
	# 7zip Example
	#  7z u -l -uq0 -u!newarchive.7z -t7z -mx=3 -ms=off Backup_192.168.0.77_20170214024819_1921680077.7z Backup_192.168.0.77_20170214024819_1921680077/*

	my $sevenzip_options = "-l -uq0 -t$zipformat -mx=$compressionlevel -ms=off";
	my $sevenzip_call = "$sevenzipbin u $sevenzip_options -- $bkpworkdir/$bkpdir.$zipformat *";
	
	if ($debug) { $logmessage = "7-ZIP Call: $sevenzip_call"; &log($green_css); }
	our $output = qx(cd $bkpbase/$bkpfolder && $sevenzip_call);
	my $exitcode = $? >> 8;
	if ($debug) { $logmessage = $output; &log($dwl_css); }
	if ($exitcode ne 0) 
	{
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2004")." $bkpworkdir/$bkpdir (Errorcode: $exitcode)"; &error; # Compressing error
		next;
	} 
	  else
	{
		if ($verbose) { $logmessage = $phraseplugin->param("TXT1012"); &log($green_css); } # ZIP-Archive created successfully.
	}

	$logmessage = $phraseplugin->param("TXT1013"); &log($green_css); #Moving Backup to Download folder..."
	  
	# Moving ZIP to files section
	if (!-d "$bkpzipdestdir/$bkpfolder") 
	{
		$response = make_path ("$bkpzipdestdir/$bkpfolder", {owner=>'loxberry', group=>'loxberry', chmod => 0777});
		if ($response == 0) 
		{
			$error=1;
			$something_wrong = 1;
			$logmessage = $phraseplugin->param("TXT2009")." ".$bkpfolder; &error; # Could not create download folder.
			next;
		}
	} 
	else 
	{
		if ($verbose) { $logmessage = $phraseplugin->param("TXT2010")." $bkpfolder"; &log($green_css); }  # Folder exists => ok
	}
	move("$bkpworkdir/$bkpdir.$zipformat","$bkpzipdestdir/$bkpfolder/$bkpdir.$zipformat");
	if (!-e "$bkpzipdestdir/$bkpfolder/$bkpdir.$zipformat") 
	{
		$error=1;
		$something_wrong = 1;
		$logmessage = $phraseplugin->param("TXT2005")." ($bkpdir.$zipformat)" ; &error; # "Moving Error!"
		next;
	} 
	else 
	{
		if ($verbose) { $logmessage = $phraseplugin->param("TXT1014")." ($bkpdir.$zipformat)"; &log($green_css); }  # Moved ZIP-Archive to Files-Section successfully.
	}

	ABBRUCH:

	# Clean up $bkpworkdir folder
	if ($verbose) 
	{
		$logmessage = $phraseplugin->param("TXT1015"); &log($green_css);  # Cleaning up temporary and old stuff.
	}
	  
	# Incremental - do NOT cleanup backup, but /tmp/miniserverbackup
	$output = qx(rm $bkpworkdir/* > /dev/null 2>&1);
	if ($error eq 0) { 
		$logmessage = $phraseplugin->param("TXT1018")." $bkpdir.$zipformat "; &log($green_css);  # New Backup $bkpdir.$zipformat created successfully.
		$mscounter++;
	}
	$error = 0;

}
## END of MINISERVER Loop

if ($something_wrong)
{
  $logmessage = $phraseplugin->param("TXT1019"); &log($red_css); # Not all Backups created without errors - see log. 
}
else
{
  $logmessage = $phraseplugin->param("TXT1020"); &log($green_css); # All Backups created successfully. 
}
  $logmessage = "============================================================================================== "; &log($dwl_css); # All Backups created successfully. 

# Remove Backup Flag
open(F,">$lbhtmldir/backupstate.txt");
print F "";
close (F);
exit;

##########################################################################
# Subroutinen
##########################################################################

# Logfile
sub log {

  $css = shift;

# Today's date for logfile
  (my $sec,my $min,my $hour,my $mday,my $mon,my $year,my $wday,my $yday,my $isdst) = localtime();
  $year = $year+1900;
  $mon = $mon+1;
  $mon = sprintf("%02d", $mon);
  $mday = sprintf("%02d", $mday);
  $hour = sprintf("%02d", $hour);
  $min = sprintf("%02d", $min);
  $sec = sprintf("%02d", $sec);

  # Clean Username/Password for Logfile
  if ( $debug ne 1 )
  { 
  	$logmessage =~ s/\/\/(.*)\:(.*)\@/\/\/xxx\:xxx\@/g;
	}
  # Logfile
  open(F,">>$lblogdir/backuplog.log");
  
  my $msnumber = defined $msno ? "Miniserver #$msno" : "";
  print F "<$css> $year-$mon-$mday $hour:$min:$sec $msnumber: $logmessage</$css>\n";
  close (F);

  return ();
}

# Error Message
sub error {
  our $error = "1";
  &log($red_css);
  # Clean up /tmp folder
  # @output = qx(rm -r $bkpbase/Backup_* > /dev/null 2>&1);
  if ( $retry_error eq $maxdwltries ) { $something_wrong = "1"; } 
  return ();
}

#################################################
# Download
#################################################
sub download 
{
	my $lftplog = "$lblogdir/backuplog.log";
	
	if ($debug eq 1) 
	{
		#Debug
		$quiet="debug -o $lftplog -t -c 5 ";
	}
	elsif  ($verbose eq 1) 
	{
		#Verbose
		$quiet="debug -o $lftplog -t -c 1";
	}
	else   
	{
		#None
		$quiet="debug off";
	}

	my $lftpoptions = 
	"set net:reconnect-interval-base 5; " .
	"set net:reconnect-interval-multiplier 3; " .
	"set net:reconnect-interval-max 180; " .
	"set net:timeout 5; " .
	"set net:max-retries 15; " .
	"net:persist-retries 15; " .
	"set ftp:passive-mode true; ". 
	"set ftp:sync-mode true; " .
	"set net:limit-total-rate 3M:3M; " .
	"set ftp:use-stat 0 " .
	"log:enabled " . 
	"log:file $lblogdir/backuplog.log ";
  
  my $ftppass = quotemeta($miniservers{$msno}{Pass_RAW});
  my $ftpuser = quotemeta($miniservers{$msno}{Admin_RAW});
    
  if ($verbose) { $logmessage = $phraseplugin->param("TXT1021")." $remotepath ..."; &log($green_css); } # Downloading xxx ....
  for(my $versuche = 1; $versuche < 6; $versuche++) 
	{
			my $lftpcommand = "$lftpbin -c \"$quiet; $lftpoptions; open -u $ftpuser,$ftppass -p $miniserverftpport $miniservers{$msno}{IPAddress}; mirror --continue --use-cache --parallel=1 --no-perms --no-umask --delete $remotepath $bkpbase/$bkpfolder$remotepath\"";
			
			if ($debug) { $logmessage = "LFTP Call: $lftpcommand"; &log($green_css); }
			system($lftpcommand);
			if ($? ne 0) 
			{
				$logmessage = $phraseplugin->param("TXT2006")." $remotepath ".$phraseplugin->param("TXT1022")." $versuche ".$phraseplugin->param("TXT1023")." $maxdwltries (Errorcode: $?)"; &log($red_css); # Try x of y failed 
			    $retry_error = $versuche;
			} 
			else 
			{
				$logmessage = $phraseplugin->param("TXT1024") . " $remotepath ($versuche " . $phraseplugin->param("TXT1025") . " $maxdwltries)"; &log($dwl_css); # Download ok
			    $retry_error = 0;
			}
			if ($retry_error eq 0) { last; }
	}
	if ($retry_error eq $maxdwltries)
	{ 
      $error = 1;
 	    $logmessage = $phraseplugin->param("TXT2007")." $remotepath (Errorcode: $?)"; &error; # "Wiederholter Fehler $? beim Speichern von $remotepath. GEBE AUF!!"
    	if ( $retry_error eq $maxdwltries ) { goto ABBRUCH; }
	}
  return ();
}
