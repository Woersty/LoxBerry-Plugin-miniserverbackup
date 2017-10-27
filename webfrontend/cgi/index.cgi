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
use lib "$FindBin::Bin/./perllib";
use LoxBerry::System;
use LoxBerry::Web;

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use Config::Simple;
use File::HomeDir;
use Cwd 'abs_path';
use warnings;
use strict;
no strict "refs"; # we need it for template system

##########################################################################
# Variables
##########################################################################

our $pcfg;
our $phrase;
our $namef;
our $value;
our %query;
our $template_title;
our $help;
our @help;
our $helptext;
our $helplink;
our $languagefile;
our $version;
our $error;
our $saveformdata=0;
our $output;
our $message;
our $nexturl;
our $do="form";
our $pname;
our $verbose;
our $debug;
our $maxfiles;
our $autobkp;
our $bkpcron;
our $bkpcounts;
our $selectedauto1;
our $selectedauto2;
our $selectedcron1;
our $selectedcron2;
our $selectedcron3;
our $selectedcron4;
our $selectedcron5;
our $selectedcron6;
our $languagefileplugin;
our $phraseplugin;
our $selectedverbose;
our $selecteddebug;
our $header_already_sent=0;
my $dontzip;

##########################################################################
# Read Settings
##########################################################################

# Version of this script
$version = "0.21";

our %miniservers = LoxBerry::System::get_miniservers();
our $lang = lblanguage();

$pcfg             = new Config::Simple("$lbconfigdir/miniserverbackup.cfg");
$debug           = $pcfg->param("MSBACKUP.DEBUG");
$maxfiles        = $pcfg->param("MSBACKUP.MAXFILES");
$pname           = $pcfg->param("MSBACKUP.SCRIPTNAME");
$autobkp         = $pcfg->param("MSBACKUP.AUTOBKP");
$bkpcron         = $pcfg->param("MSBACKUP.CRON");
$bkpcounts       = $pcfg->param("MSBACKUP.MAXFILES");
$dontzip		 = $pcfg->param("MSBACKUP.DONT_ZIP");

my $bkpbase = defined $pcfg->param("MSBACKUP.BASEDIR") ? $pcfg->param("MSBACKUP.BASEDIR") : "$lbdatadir/currentbackup";
my $bkpworkdir = defined $pcfg->param("MSBACKUP.WORKDIR") ? $pcfg->param("MSBACKUP.WORKDIR") : "$lbdatadir/workdir";
my $bkpziplinkdir = defined $pcfg->param("MSBACKUP.BACKUPDIR") ? $pcfg->param("MSBACKUP.BACKUPDIR") : undef;
my $compressionlevel = defined $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") ? $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") : 5;
my $zipformat = defined $pcfg->param("MSBACKUP.ZIPFORMAT") ? $pcfg->param("MSBACKUP.ZIPFORMAT") : "7z";
my $mailreport = defined $pcfg->param("MSBACKUP.MAILREPORT") ? $pcfg->param("MSBACKUP.MAILREPORT") : undef;


#########################################################################
# Parameter
#########################################################################

# For Debugging with level 3 
sub apache()
{
  if ($debug eq 3)
  {
		if ($header_already_sent eq 0) {$header_already_sent=1; print header();}
		my $debug_message = shift;
		# Print to Browser 
		print $debug_message."<br>\n";
		# Write in Apache Error-Log 
		print STDERR $debug_message."\n";
	}
	return();
}

# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'}))
{
  ($namef,$value) = split(/=/,$_,2);
  $namef =~ tr/+/ /;
  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $value =~ tr/+/ /;
  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $query{$namef} = $value;
}

# Set parameters coming in - get over post
	if ( !$query{'saveformdata'} ) { if ( param('saveformdata') ) { $saveformdata = quotemeta(param('saveformdata')); } else { $saveformdata = 0;      } } else { $saveformdata = quotemeta($query{'saveformdata'}); }
#	if ( !$query{'lang'} )         { if ( param('lang')         ) { $lang         = quotemeta(param('lang'));         } else { $lang         = "de";   } } else { $lang         = quotemeta($query{'lang'});         }
	if ( !$query{'do'} )           { if ( param('do')           ) { $do           = quotemeta(param('do'));           } else { $do           = "form"; } } else { $do           = quotemeta($query{'do'});           }

# Clean up saveformdata variable
	$saveformdata =~ tr/0-1//cd; $saveformdata = substr($saveformdata,0,1);

# Init Language
#	# Clean up lang variable
#	$lang         =~ tr/a-z//cd; $lang         = substr($lang,0,2);
  # If there's no language phrases file for choosed language, use English as default
if (! -e "$lbhomedir/templates/system/$lang/language.dat") 
	{
  		$lang = 'en';
	}

# Read English language as default
# Missing phrases in foreign language will fall back to English	
	$languagefile 			= "$lbhomedir/templates/system/en/language.dat";
	$languagefileplugin 	= "$lbtemplatedir/en/language.dat";
	$phrase = new Config::Simple($languagefile);
	$phrase->import_names('T');
	our $plglang = new Config::Simple($languagefileplugin);
	$plglang->import_names('T');

	
#	$lang = 'en'; # DEBUG
	
# Read foreign language if exists and not English
	$languagefile 			= "$lbhomedir/templates/system/$lang/language.dat";
	$languagefileplugin 	= "$lbtemplatedir/$lang/language.dat";
	if ((-e $languagefile) and ($lang ne 'en')) {
		# Now overwrite phrase variables with user language
		$phrase = new Config::Simple($languagefile);
		$phrase->import_names('T');
	}
	if ((-e $languagefileplugin) and ($lang ne 'en')) {
		# Now overwrite phrase variables with user language
		$phraseplugin = new Config::Simple($languagefileplugin);
		$phraseplugin->import_names('T');
	}
	
#	$lang = 'de'; # DEBUG

	
##########################################################################
# Main program
##########################################################################

	if ($saveformdata) 
	{
	  &save;
	  &form;
	  
	}
	elsif ($do eq "backup") 
	{
	  &backup;
	}
	else 
	{
	  &form;
	}
	exit;

#####################################################
# 
# Subroutines
#
#####################################################

#####################################################
# Form-Sub
#####################################################

	sub form 
	{
		# Filter
		$debug     = quotemeta($debug);
		$maxfiles  = quotemeta($maxfiles);
		$autobkp   = quotemeta($autobkp);
		$bkpcron   = quotemeta($bkpcron);
		$bkpcounts = quotemeta($bkpcounts);
		
		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
		
		# Print Template
		LoxBerry::Web::lbheader("Miniserver Backup", "http://www.loxwiki.eu:80/x/jIKO", "help.html");
		
		open(F,"$lbtemplatedir/multi/settings.html") || die "Missing template $lbtemplatedir/$lang/settings.html";
		  while (<F>) 
		  {
		    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
		    print $_;
		  }
		close(F);
		LoxBerry::Web::lbfooter();
		exit;
	}

#####################################################
# Save-Sub
#####################################################

	sub save 
	{
		# Everything from Forms
		$autobkp    = param('autobkp');
		$bkpcron    = param('bkpcron');
		$bkpcounts  = param('bkpcounts');
		$debug      = param('debug');
		
		# Filter
		$autobkp   = quotemeta($autobkp);
		$bkpcron   = quotemeta($bkpcron);
		$bkpcounts = quotemeta($bkpcounts);
		$debug     = quotemeta($debug);
		
		# Write configuration file(s)
		$pcfg->param("MSBACKUP.AUTOBKP", "$autobkp");
		$pcfg->param("MSBACKUP.CRON",	"$bkpcron");
		$pcfg->param("MSBACKUP.MAXFILES","$bkpcounts");
		$pcfg->param("MSBACKUP.DEBUG", 	"$debug");
		$pcfg->save();
		
		# Create Cronjob
		if (is_enabled($autobkp)) 
		{
		  if ($bkpcron eq "15min") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		  }
		  if ($bkpcron eq "30min") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		  }
		  if ($bkpcron eq "60min") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		  }
		  if ($bkpcron eq "1d") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		  }
		  if ($bkpcron eq "1w") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.weekly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		  }
		  if ($bkpcron eq "1m") 
		  {
		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.monthly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		  }
		} 
		else
		{
		  unlink ("$lbhomedir/system/cron/cron.15min/$pname");
		  unlink ("$lbhomedir/system/cron/cron.30min/$pname");
		  unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
		  unlink ("$lbhomedir/system/cron/cron.daily/$pname");
		  unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
		  unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
		}
		
		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
		
		$template_title = $phrase->param("TXT0000") . ": " . $phrase->param("TXT0040");
		$message 				= $phraseplugin->param("TXT0002");
		$nexturl 				= "./index.cgi?do=form";
		
		# Print Template
		#&lbheader;
		#open(F,"$lbhomedir/templates/system/$lang/success.html") || die "Missing template system/$lang/succses.html";
		#  while (<F>) 
		#  {
		#    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
		#    print $_;
		#  }
		#close(F);
		#&footer;
		return;
	}

#####################################################
# Manual backup-Sub
#####################################################

	sub backup 
	{
		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
		$message = $phraseplugin->param("TXT0003");
		print $message;
		# Create Backup
		# Without the following workaround
		# the script cannot be executed as
		# background process via CGI
		my $pid = fork();
		die "Fork failed: $!" if !defined $pid;
		if ($pid == 0) 
		{
			 # do this in the child
			 open STDIN, "</dev/null";
			 open STDOUT, ">/dev/null";
			 open STDERR, ">/dev/null";
			 system("$lbcgidir/bin/createmsbackup.pl &");
		}
		exit;
	}

#####################################################
# Error-Sub
#####################################################

	sub error 
	{
		$template_title = $phrase->param("TXT0000") . " - " . $phrase->param("TXT0028");
		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
		LoxBerry::Web::lbheader("Miniserver Backup", "http://www.loxwiki.eu:80/x/jIKO", "help.html");
		open(F,"$lbhomedir/templates/system/$lang/error.html") || die "Missing template system/$lang/error.html";
		while (<F>) 
		{
		  $_ =~ s/<!--\$(.*?)-->/${$1}/g;
		  print $_;
		}
		close(F);
		LoxBerry::Web::lbfooter();
		exit;
	}
