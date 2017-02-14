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

#use lib '../perllib';
#use LoxBerry::System;


use LWP::UserAgent;
use XML::Simple qw(:strict);
use Config::Simple;
#use Data::Dumper;
use File::Copy;
use File::Path qw(make_path remove_tree);
#use strict;
#use warnings;
use File::HomeDir;
use Cwd 'abs_path';

##########################################################################
# Variables
##########################################################################

our $cfg;
our $pcfg;
our $miniserverip;
our $miniserverport;
our $miniserveradmin;
our $miniserverpass;
our $miniservers;
our $url;
our $ua;
our $response;
our $error = 0;
our $rawxml;
our $xml;
our @fields;
our $mainver;
our $subver;
our $monver;
our $dayver;
our $miniserverftpport;
our $wgetbin;
our $zipbin;
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
our $installfolder;
our $home = File::HomeDir->my_home;
our @Eintraege;
our @files;
our $i;
our $msno;
our $useclouddns;
our $miniservercloudurl;
our $miniservercloudurlftpport;
our $curlbin;
our $grepbin;
our $awkbin;
our $logmessage;
our $clouddns;
our $quiet;
our $something_wrong;
our $retry_error;
our $maxdwltries;
our $local_miniserver_ip;
our $foundfiles;
our $miniserverfoldername;

##########################################################################
# Read Configuration
##########################################################################

# Version of this script
my $version = "0.2";

# Figure out in which subfolder we are installed
$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/bin\/(.*)$/$2/g;

$cfg             = new Config::Simple("$home/config/system/general.cfg");
$installfolder   = $cfg->param("BASE.INSTALLFOLDER");
$miniservers     = $cfg->param("BASE.MINISERVERS");
$clouddns        = $cfg->param("BASE.CLOUDDNS");
$wgetbin         = $cfg->param("BINARIES.WGET");
$zipbin          = $cfg->param("BINARIES.ZIP");
$curlbin         = $cfg->param("BINARIES.CURL");
$grepbin         = $cfg->param("BINARIES.GREP");
$awkbin          = $cfg->param("BINARIES.AWK");
$lang            = $cfg->param("BASE.LANG");
$maxdwltries     = 15; # Maximale wget Wiederholungen
$pcfg            = new Config::Simple("$installfolder/config/plugins/$psubfolder/miniserverbackup.cfg");
$debug           = $pcfg->param("MSBACKUP.DEBUG");
$maxfiles =	defined $pcfg->param("MSBACKUP.MAXFILES") ? $pcfg->param("MSBACKUP.MAXFILES") : 1;
$bkpbase = defined $pcfg->param("MSBACKUP.BASEDIR") ? $pcfg->param("MSBACKUP.BASEDIR") : "$installfolder/data/plugins/$psubfolder/currentbackup";
$compressionlevel = defined $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") ? $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") : 1;
$zipformat = defined $pcfg->param("MSBACKUP.ZIPFORMAT") ? $pcfg->param("MSBACKUP.ZIPFORMAT") : "7z";

$languagefileplugin = "$installfolder/templates/plugins/$psubfolder/$lang/language.dat";
our $phraseplugin 	= new Config::Simple($languagefileplugin);


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
if ($verbose) { $logmessage = $miniservers." ".$phraseplugin->param("TXT1001")." $0($version)"; &log($green_css); } # ### Miniserver insgesamt - Starte Backup mit Script / Version 
# Start Backup of all Miniservers
for($msno = 1; $msno <= $miniservers; $msno++) 
{
  # Set Backup Flag
	open(F,">$installfolder/webfrontend/html/plugins/$psubfolder/backupstate.txt");
  print F "$msno";
  close (F);

  $logmessage = $phraseplugin->param("TXT1002").$msno; &log($green_css); #Starte Backup for Miniserver 

  $miniserverip       				= $cfg->param("MINISERVER$msno.IPADDRESS");
  $miniserveradmin    				= $cfg->param("MINISERVER$msno.ADMIN");
  $miniserverpass     				= $cfg->param("MINISERVER$msno.PASS");
  $miniserverport     				= $cfg->param("MINISERVER$msno.PORT");
  $useclouddns        				= $cfg->param("MINISERVER$msno.USECLOUDDNS");
  $miniservercloudurl 				= $cfg->param("MINISERVER$msno.CLOUDURL");
  $miniservercloudurlftpport 	= $cfg->param("MINISERVER$msno.CLOUDURLFTPPORT");
  # Kein Parameter der general.cfg
  # $miniserverfoldername       = $cfg->param("MINISERVER$msno.FOLDERNAME");

  $miniserverfoldername = $cfg->param("MINISERVER$msno.NAME");
  
  if ( ${useclouddns} eq "1" )
  {
	   $logmessage = $phraseplugin->param("TXT1003")." http://$clouddns/$miniservercloudurl ".$phraseplugin->param("TXT1004"); &log($green_css); # Using Cloud-DNS xxx for Backup 
	   
	   $dns_info = `$home/webfrontend/cgi/system/tools/showclouddns.pl $miniservercloudurl`;
	   $logmessage = $phraseplugin->param("TXT1005")." $dns_info ($home/webfrontend/cgi/system/tools/showclouddns.pl $miniservercloudurl)"; &log($green_css); # DNS Data
	   
	   my @dns_info_pieces = split /:/, $dns_info;
	   if ($dns_info_pieces[1])
	   {
	    	$dns_info_pieces[1] =~ s/^\s+|\s+$//g;
	    	$miniserverport = $dns_info_pieces[1];
	   }
	   else
	   {
	    	$miniserverport  = 80;
	   }
	   if ($dns_info_pieces[0])
	   {
	    	$dns_info_pieces[0] =~ s/^\s+|\s+$//g;
	    $miniserverip = $dns_info_pieces[0]; 
	   }
	   else
	   {
	    	$miniserverip = "127.0.0.1"; 
	   }
  }
  else
  {
	   if ( $miniserverport eq "" )
	   {
	 	  	$miniserverport = 80;
	   } 
  }
  $url = "http://$miniserveradmin:$miniserverpass\@$miniserverip\:$miniserverport/dev/cfg/version";
	if ( $useclouddns eq "1" ) { $logmessage = $phraseplugin->param("TXT1029");	&log($red_css);	} # Local Access Only Setting Info
  $logmessage = $phraseplugin->param("TXT1006"); &log($green_css); # Try to read MS Firmware Version
  $ua = LWP::UserAgent->new;
  $ua->timeout(10);
  local $SIG{ALRM} = sub { die };
  eval {
    alarm(1);
    $response = $ua->get($url);
    if (!$response->is_success) 
    {
      $error        = 1;
  		$logmessage = $phraseplugin->param("TXT2001"); &error;	# Unable to fetch Firmware Version. Giving up.
	    next;
    }
    else 
    {
      $success = 1;
    }
  };
  alarm(0);
  if (!$success) 
  {
    $error=1;
 		$logmessage = $phraseplugin->param("TXT2001"); &error; # Unable to fetch Firmware Version. Giving up.
  	&error;  
    next;
  }
  $success = 0;
  $rawxml  = $response->decoded_content();
  $xml     = XMLin($rawxml, KeyAttr => { LL => 'value' }, ForceArray => [ 'LL', 'value' ]);
  @fields  = split(/\./,$xml->{value});
  $mainver = sprintf("%02d", $fields[0]);
  $subver  = sprintf("%02d", $fields[1]);
  $monver  = sprintf("%02d", $fields[2]);
  $dayver  = sprintf("%02d", $fields[3]);
  $logmessage = $phraseplugin->param("TXT1007").$xml->{value}; &log($green_css); # Miniserver Version

  $url = "http://$miniserveradmin:$miniserverpass\@$miniserverip\:$miniserverport/dev/cfg/ip";
  $logmessage = $phraseplugin->param("TXT1026"); &log($green_css); # Try to read MS Local IP
  $ua = LWP::UserAgent->new;
  $ua->timeout(10);
  local $SIG{ALRM} = sub { die };
  eval {
    alarm(1);
    $response = $ua->get($url);
    if (!$response->is_success) 
    {
      $error        = 1;
      $logmessage = $phraseplugin->param("TXT2008"); &error; # Unable to fetch local IP. Giving up.
      next;
    }
    else 
    {
      $success = 1;
    }
  };
  alarm(0);
  if (!$success) 
  {
    $error=1;
		$logmessage = $phraseplugin->param("TXT2008"); &error; # Unable to fetch local IP. Giving up.
    next;
  }
  $success = 0;
  $rawxml  = $response->decoded_content();
  $xml     = XMLin($rawxml, KeyAttr => { LL => 'value' }, ForceArray => [ 'LL', 'value' ]);
  @fields  = split(/\./,$xml->{value});
  $mainver = sprintf("%02d", $fields[0]);
  $subver  = sprintf("%02d", $fields[1]);
  $monver  = sprintf("%02d", $fields[2]);
  $dayver  = sprintf("%02d", $fields[3]);
  $logmessage = $phraseplugin->param("TXT1027").$xml->{value}; &log($green_css); # Miniserver IP local
  $local_miniserver_ip = $xml->{value};
  if ( $useclouddns eq "1" ) 
	{
	  $logmessage = $phraseplugin->param("TXT1030").$miniserverip; &log($green_css); # Miniserver IP public
	} 	

  # Get FTP Port from Miniserver
  $url = "http://$miniserveradmin:$miniserverpass\@$miniserverip\:$miniserverport/dev/cfg/ftp";
  $ua = LWP::UserAgent->new;
  $ua->timeout(10);
  local $SIG{ALRM} = sub { die };
  eval {
    alarm(1);
    $response = $ua->get($url);
    if (!$response->is_success) 
    {
      $error=1;
      $logmessage = $phraseplugin->param("TXT2002"); &error; # Unable to fetch FTP Port. Giving up. 
      next;
    }
    else
    {
      $success = 1;
    }
  };
  alarm(0);

  if (!$success) 
  {
    $error=1;
    $logmessage = $phraseplugin->param("TXT2002"); &error; # Unable to fetch FTP Port. Giving up. 
    next;
  }
  $success = 0;
  $rawxml = $response->decoded_content();
  $xml = XMLin($rawxml, KeyAttr => { LL => 'value' }, ForceArray => [ 'LL', 'value' ]);
  $miniserverftpport = $xml->{value};
  if ($miniserverftpport ne "21") { $logmessage = $phraseplugin->param("TXT1028"); &log($red_css); } #Warning if local FTP-Port is not 21 
 	
 	# If CloudDNS is used, switch to configured public FTP Port
 	if ( $useclouddns eq "1" ) 
 	{ 
 		if (  sprintf("%d", $miniservercloudurlftpport) ne 0) 
 		{ 
 			$miniserverftpport = $miniservercloudurlftpport; 
 		}
 		else
 		{ 
 			$miniserverftpport = 21; 
 		}
 	}
	else
 	{
 		if ( sprintf("%d", $miniserverftpport) eq 0) 
 		{ 
 			$miniserverftpport = 21; 
 		}
	}
  $logmessage = $phraseplugin->param("TXT1008").$miniserverftpport; &log($green_css); #Using this FTP-Port for Backup: xxx
  # Backing up to temorary directory
  ($dirsec,$dirmin,$dirhour,$dirmday,$dirmon,$diryear,$dirwday,$diryday,$dirisdst) = localtime();
  $diryear = $diryear+1900;
  $dirmon = $dirmon+1;
  $dirmon = sprintf("%02d", $dirmon);
  $dirmday = sprintf("%02d", $dirmday);
  $dirhour = sprintf("%02d", $dirhour);
  $dirmin = sprintf("%02d", $dirmin);
  $dirsec = sprintf("%02d", $dirsec);
  # Create temporary dir
  $bkpfolder = sprintf("%03d", $msno)."_".$miniserverfoldername;
  
  # For incremental backup, we don't use $bkpdir now, but we create a symbolic link in /tmp/ instead
  $bkpdir = "Backup_$local_miniserver_ip\_$diryear$dirmon$dirmday$dirhour$dirmin$dirsec\_$mainver$subver$monver$dayver";
  
  our $symlinkpath = "/tmp/miniserverbackup";
    
  $response = make_path ("$bkpbase/$bkpfolder", {owner=>'loxberry', group=>'loxberry', chmod => 0777});
  # This directory may already exist, therefore just check if it is writeable
  if (! -w "$bkpbase/$bkpfolder") {
    $error=1;
    $logmessage = $phraseplugin->param("TXT2003")." $bkpbase/$bkpfolder"; &error; # Could not write to temporary folder /tmp/$bkpfolder/$bkpdir. Giving up.
    next;
  }
  $response = make_path ("$symlinkpath", {owner=>'loxberry', group=>'loxberry', chmod => 0777});
  # This directory may already exist, therefore just check if it is writeable
  if (! -w "$symlinkpath") {
    $error=1;
    $logmessage = $phraseplugin->param("TXT2003")." $symlinkpath"; &error; # Could not write temporary folder /tmp/$bkpfolder/$bkpdir. Giving up.
    next;
  }
   
  my $response = eval { symlink("$bkpbase/$bkpfolder", "$symlinkpath/$bkpdir"); 1 };
  if ($response == 0) {
    $error=1;
    $logmessage = $phraseplugin->param("TXT2003")." Symlink $bkpbase/$bkpfolder to $symlinkpath"; &error; # Could not create temporary folder /tmp/$bkpfolder/$bkpdir. Giving up.
    next;
  }

#############################################
# Some performance tuning of ZIP creation
# We do an archive cleanup and caching before
#############################################
#
# Doesn't work as the subdirectory of the archive has changed!
# Will always create a new archive....

  # Get the oldest zip archive
  $i = 0;
  @files = "";
  @Eintraege = "";
  opendir(DIR, "$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder."/");
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
  if ($verbose) { $logmessage = $foundfiles." ".$phraseplugin->param("TXT1016")." $installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder "; &log($green_css); } # x files found in dir y
  if ($debug)   { $logmessage = "Files: $installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder :".join(" + ", @files); &log($green_css); }

  foreach(@files) 
  {
    s/[\n\r]//g;
    $i++;
    if ($i > ($maxfiles-1) && $_ ne "") 
    {
	$ext = substr($_, rindex ($_, '.')+1); 
    
	  if (! -e "/tmp/miniserverbackup/$bkpdir.$zipformat" && $ext eq $zipformat) {
		# We use the first file that would be deleted to make an incremental ZIP update
		$logmessage = $phraseplugin->param("TXT1031")." $_"; &log($green_css); # Moving old Backup $_
		move("$installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder/$_", "/tmp/miniserverbackup/$bkpdir.$zipformat");
	  } else {
		$logmessage = $phraseplugin->param("TXT1017")." $_"; &log($green_css); # Deleting old Backup $_
		unlink("$installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder/$_");
  	  }
	} 
  }
  # If we have no old file, take the last backup
  
  #$logmessage = "DEBUG: Filename $files[0]"; &log($green_css); # DEBUG
  # print STDERR "DEBUG: Filename $files[0]\n";

  $ext = substr($files[0], rindex ($files[0], '.')+1); 
  
  # print STDERR "DEBUG: Extension -->" . $ext . "<--\n";
  if (! -e "/tmp/miniserverbackup/$bkpdir.$zipformat" && -e "$installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder/$files[0]" && $ext eq $zipformat) {
	$logmessage = $phraseplugin->param("TXT1032")." $_"; &log($green_css); # Copying last Backup 
	copy("$installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder/$files[0]", "/tmp/miniserverbackup/$bkpdir.$zipformat");
  }
  if (! -e "/tmp/miniserverbackup/$bkpdir.$zipformat") {
	$logmessage = $phraseplugin->param("TXT1033"); &log($green_css); # Tell the people that we have no backup yet
  }
  
 # We now possibly have a backup file in /tmp/miniserverbackup
#############################################

  
  if ($verbose) { $logmessage = $phraseplugin->param("TXT1009")." $bkpbase/$bkpfolder/$bkpdir"; &log($green_css); } # "Temporary folder created: /tmp/$bkpfolder/$bkpdir."
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

  $logmessage = $phraseplugin->param("TXT1011")." /tmp/miniserverbackup/$bkpdir.$zipformat"; &log($green_css); # Compressing Backup xxx ...
  
  # Zipping
  
  # 7zip Example
  #  7z u -l -uq0 -u!newarchive.7z -t7z -mx=3 -ms=off Backup_192.168.0.77_20170214024819_1921680077.7z Backup_192.168.0.77_20170214024819_1921680077/*

  my $sevenzip_options = "-l -uq0 -t$zipformat -mx=$compressionlevel -ms=off";
  our $output = qx(cd /tmp/miniserverbackup/$bkpdir && $sevenzipbin u $sevenzip_options -- /tmp/miniserverbackup/$bkpdir.$zipformat *);
  my $exitcode = $? >> 8;
  if ($debug) { $logmessage = $output; &log($dwl_css); }
  

  	# Zip Syntax (obsolete):
	# zip [-options] [-b path] [-t mmddyyyy] [-n suffixes] [zipfile list] [-xi list]
	#our @output = qx(cd $bkpbase/$bkpfolder && $zipbin -q -p -r $bkpdir.$zipformat $bkpdir );
	#our @output = qx(cd /tmp/miniserverbackup && $zipbin -Z bzip2 -FS --quiet --paths --recurse-paths $bkpdir.$zipformat $bkpdir );

	


  if ($exitcode ne 0) 
  {
    $error=1;
  	$logmessage = $phraseplugin->param("TXT2004")." /tmp/miniserverbackup/$bkpdir (Errorcode: $exitcode)"; &error; # Compressing error
    next;
  } 
  else
  {
    if ($verbose) { $logmessage = $phraseplugin->param("TXT1012"); &log($green_css); } # ZIP-Archive /tmp/$bkpfolder/$bkpdir/$bkpdir.$zipformat created successfully.
  }
  $logmessage = $phraseplugin->param("TXT1013"); &log($green_css); #Moving Backup to Download folder..."
  
  # Moving ZIP to files section
  if (!-d "$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder) 
  {
  $response = make_path ("$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder, {owner=>'loxberry', group=>'loxberry', chmod => 0777});
	  if ($response == 0) 
	  {
	    $error=1;
	    $logmessage = $phraseplugin->param("TXT2009")." ".$bkpfolder; &error; # Could not create download folder.
	    next;
	  }
  } 
  else 
  {
    if ($verbose) { $logmessage = $phraseplugin->param("TXT2010")." $bkpfolder"; &log($green_css); }  # Folder exists => ok
  }
  move("/tmp/miniserverbackup/$bkpdir.$zipformat","$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder."/"."$bkpdir.$zipformat");
  if (!-e "$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder."/$bkpdir.$zipformat") 
  {
    $error=1;
  	$logmessage = $phraseplugin->param("TXT2005")." ($bkpdir.$zipformat)" ; &error; # "Moving Error!"
    next;
  } 
  else 
  {
    if ($verbose) { $logmessage = $phraseplugin->param("TXT1014")." ($bkpdir.$zipformat)"; &log($green_css); }  # Moved ZIP-Archive to Files-Section successfully.
  }

  ABBRUCH:

  # Clean up /tmp folder
  if ($verbose) 
  {
    $logmessage = $phraseplugin->param("TXT1015"); &log($green_css);  # Cleaning up temporary and old stuff.
  }
  
  # Incremental - do NOT cleanup backup, but /tmp/miniserverbackup
  $output = qx(rm -r /tmp/miniserverbackup > /dev/null 2>&1);
  
  # # Delete old backup archives
  # $i = 0;
  # @files = "";
  # @Eintraege = "";
  # opendir(DIR, "$installfolder/webfrontend/html/plugins/$psubfolder/files/".$bkpfolder."/");
    # @Eintraege = readdir(DIR);
  # closedir(DIR);
  
  # foreach(@Eintraege) 
  # {
    # if ($_ =~ m/Backup_$local_miniserver_ip/) 
    # {
     # push(@files,$_);
    # }
  # }
  # @files = sort {$b cmp $a}(@files);

  # $foundfiles = scalar(@files) - 1; # There seems to be one blank entry in @files? This is not a real file...

  # if ($verbose) { $logmessage = $foundfiles." ".$phraseplugin->param("TXT1016")." $installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder "; &log($green_css); } # x files found in dir y
  # if ($debug)   { $logmessage = "Files: $installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder :".join(" + ", @files); &log($green_css); }

  # foreach(@files) 
  # {
    # s/[\n\r]//g;
    # $i++;
    # if ($i > $maxfiles && $_ ne "") 
    # {
      # $logmessage = $phraseplugin->param("TXT1017")." $_"; &log($green_css); # Deleting old Backup $_
      # unlink("$installfolder/webfrontend/html/plugins/$psubfolder/files/$bkpfolder/$_");
  	# } 
  # }
  
  
  
  if ($error eq 0) { $logmessage = $phraseplugin->param("TXT1018")." $bkpdir.$zipformat "; &log($green_css); } # New Backup $bkpdir.$zipformat created successfully.
  $error = 0;
}
$msno = "1 => #".($msno - 1); # Minisever x ... y saved
if ($something_wrong  eq 1)
{
  $logmessage = $phraseplugin->param("TXT1019"); &log($red_css); # Not all Backups created without errors - see log. 
}
else
{
  $logmessage = $phraseplugin->param("TXT1020"); &log($green_css); # All Backups created successfully. 
}
# Remove Backup Flag
open(F,">$installfolder/webfrontend/html/plugins/$psubfolder/backupstate.txt");
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
  open(F,">>$installfolder/log/plugins/$psubfolder/backuplog.log");
  print F "<$css> $year-$mon-$mday $hour:$min:$sec Miniserver #$msno: $logmessage</$css>\n";
  close (F);

  return ();
}

# Error Message
sub error {
  our $error = "1";
  &log($red_css);
  # Clean up /tmp folder
  @output = qx(rm -r $bkpbase/Backup_* > /dev/null 2>&1);
  if ( $retry_error eq $maxdwltries ) { $something_wrong = "1"; } 
  return ();
}

#################################################
# Download
#################################################
sub download 
{
	my $lftplog = "$home/log/plugins/miniserverbackup/backuplog.log";

	if ($debug eq 1) 
	{
		#Debug
		$quiet="debug -o $lftplog -t -c 3 ";
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


	my $lftpoptions = "
	set cmd:parallel 1; set ftp:passive-mode true; set ftp:sync-mode true;
	set net:limit-total-rate 3M:3M; set ftp:stat-interval 10;
	";
	
  if ($verbose) { $logmessage = $phraseplugin->param("TXT1021")." $remotepath ..."; &log($green_css); } # Downloading xxx ....
  for(my $versuche = 1; $versuche < 16; $versuche++) 
	{
			# system("$wgetbin $quiet -a $home/log/plugins/miniserverbackup/backuplog.log --retry-connrefused --tries=$maxdwltries --waitretry=5 --timeout=10 --passive-ftp -nH -r $url -P $bkpbase/$bkpfolder/$bkpdir ");
			$lftpcommand = "$lftpbin -c \"$quiet; $lftpoptions; open -u $miniserveradmin,$miniserverpass -p $miniserverftpport $miniserverip; mirror --continue --use-cache --parallel=1 --no-perms --no-umask --delete $remotepath $bkpbase/$bkpfolder$remotepath\"";
			# $logmessage = $lftpcommand; &log($dwl_css);
			system($lftpcommand);
			if ($? ne 0) 
			{
				$logmessage = $phraseplugin->param("TXT2006")." $remotepath ".$phraseplugin->param("TXT1022")." $versuche ".$phraseplugin->param("TXT1023")." $maxdwltries (Errorcode: $?)"; &log($red_css); # Try x of y failed 
			    $retry_error = $versuche;
			} 
			else 
			{
				$logmessage = $phraseplugin->param("TXT1024")." $versuche ".$phraseplugin->param("TXT1023")." $maxdwltries ".$phraseplugin->param("TXT1025")." $url"; &log($dwl_css); # Download ok
			    $retry_error = 0;
			}
			if ($retry_error eq 0) { last; }
	}
	if ($retry_error eq $maxdwltries)
	{ 
      $error = 1;
 	    $logmessage = $phraseplugin->param("TXT2007")." $remotepath (Errorcode: $?)"; &error; # "Wiederholter Fehler $? beim Speichern von $url. GEBE AUF!!"
    	if ( $retry_error eq $maxdwltries ) { goto ABBRUCH; }
	}
  return ();
}
