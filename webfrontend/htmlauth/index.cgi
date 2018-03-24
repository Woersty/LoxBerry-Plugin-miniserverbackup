#!/usr/bin/perl

# Copyright 2016-2018 Christian Woerstenfeld, git@loxberry.woerstenfeld.de
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
use LoxBerry::Web;
use LoxBerry::Log;
use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use Config::Simple '-strict';
use HTML::Entities;
#use Cwd 'abs_path';
use warnings;
use strict;
no  strict "refs"; 

##########################################################################
# Variables
##########################################################################
my %Config;
my $logfile 					= "backuplog.log";
my $pluginconfigfile 			= "miniserverbackup.cfg";
my $languagefile				= "language.ini";
my $maintemplatefilename 		= "settings.html";
my $helptemplatefilename		= "help.html";
my $errortemplatefilename 		= "error.html";
my $successtemplatefilename 	= "success.html";
my $backupstate_name 			= "backupstate.txt";
my $backupstate_file 			= $lbphtmldir."/".$backupstate_name;
my $backupstate_tmp_file 		= "/tmp/".$backupstate_name;

my $error_message				= "";
my $no_error_template_message	= "<b>Miniserver-Backup:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my @pluginconfig_bletags;
my $template_title;
my $helpurl 					= "http://www.loxwiki.eu/display/LOXBERRY/Miniserverbackup";
my @tagrow;
my @tag;
my @known_tags;
my @tag_cfg_data;
my @msrow;
my @ms;
my $log 						= LoxBerry::Log->new ( name => 'Miniserverbackup', filename => $lbplogdir ."/". $logfile, append => 1 );
my $saveformdata=0;
my $do="form";
our $tag_id;
our $ms_id;
#our $tag_select;


#our $error;
#our $output;
#our $message;
#our $nexturl;
#my  $home = File::HomeDir->my_home;
#our $bkpcounts;
#our $languagefileplugin;
#our @known_ms;
our @config_params;
#our @miniserver_id;
#our $miniserver_data;
our @language_strings;

##########################################################################
# Read Settings
##########################################################################

# Version 
my $version = LoxBerry::System::pluginversion();
my $plugin = LoxBerry::System::plugindata();
my $cgi 	= CGI->new;
$cgi->import_names('R');

LOGSTART "New admin call."      if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::System::DEBUG 	= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::Web::DEBUG 		= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$log->loglevel($plugin->{PLUGINDB_LOGLEVEL});

LOGDEB "Init CGI and import names in namespace R::";
$R::delete_log if (0);
$R::do if (0);

if ( $R::delete_log )
{
	LOGWARN "Delete Logfile: ".$lbplogdir ."/". $logfile;
	$log->close;
	open(my $fh, '>', $lbplogdir ."/". $logfile) or die "Could not open file '$logfile' $!";
	print $fh "\n";
	close $fh;
	$log->open;
	LOGSTART "Logfile restarted.";
	LOGOK "Version: ".$version;
	print "Content-Type: text/plain\n\nOK";
	exit;
}

#Prevent blocking / Recreate state file if missing
if ( -f $backupstate_tmp_file )
{
	if ((time - (stat $backupstate_tmp_file)[9]) > (30 * 60)) 
	{
	  	my $filename = $backupstate_tmp_file;
		open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
		print $fh "-";
		close $fh;
	}
}
else
{
  	my $filename = $backupstate_tmp_file;
	open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
	print $fh "-";
	close $fh;
}
if (! -l $backupstate_file)
{
	if ( -f $backupstate_file)
	{
		unlink($backupstate_file);
	}
	symlink($backupstate_tmp_file, $backupstate_file);
}

stat($lbptemplatedir . "/" . $errortemplatefilename);
if ( !-r _ )
{
	$error_message = $no_error_template_message;
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	print $error_message;
	LOGCRIT $error_message;
	LoxBerry::Web::lbfooter();
	LOGCRIT "Leaving Plugin due to an unrecoverable error";
	exit;
}

my $errortemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $errortemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
my %ERR = LoxBerry::System::readlanguage($errortemplate, $languagefile);

stat($lbpconfigdir . "/" . $pluginconfigfile);
if (!-r _ ) 
{
	LOGWARN "Plugin config file not readable.";
	$error_message = $ERR{'ERRORS.ERR_CREATE_CONFIG_DIRECTORY'};
	mkdir $lbpconfigdir unless -d $lbpconfigdir or &error; 
	LOGDEB "Try to create a default config";
	$error_message = $ERR{'ERRORS.ERR_CREATE CONFIG_FILE'};
	open my $configfileHandle, ">", $lbpconfigdir . "/" . $pluginconfigfile or &error;
		print $configfileHandle "\n";
	close $configfileHandle;
	LOGWARN "Default config created. Display error anyway to force a page reload";
	$error_message = $ERR{'ERRORS.ERR_NO_CONFIG_FILE'};
	&error; 
}


# Get known Tags from plugin config
my $plugin_cfg 		= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
$plugin_cfg 		= Config::Simple->import_from($lbpconfigdir . "/" . $pluginconfigfile,  \%Config)  or die Config::Simple->error();


my %miniservers;
%miniservers = LoxBerry::System::get_miniservers();
  
if (! %miniservers) 
{
	$error_message = $ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP'}."<br>".$ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP_SUGGESTION'};
	&error;
}
else
{
	$error_message = $ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP'}."<br>".$ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP_SUGGESTION'};
	&error if ( $miniservers{1}{IPAddress} eq "" );
	$error_message = "";
}
  
# Get through all the config options
foreach (sort keys %Config) 
{
	 # If option is a TAG process it
	 if ( substr($_, 0, 11) eq "default.TAG" ) 
	 { 
		  # Split config line into pieces - MAC, Comment and so on
	 	  @tag_cfg_data		 = split /:/, $Config{$_};
      
      # Remove spaces from MAC
      $tag_cfg_data[0] =~ s/^\s+|\s+$//g;
      
      # Put the current Tag info into the @known_tags array (MAC, Used, Miniservers, Comment and rest of the line)
	 		push (@known_tags, [ shift @tag_cfg_data, shift @tag_cfg_data, shift @tag_cfg_data, join(" ", @tag_cfg_data)]); 
	 }
}

$cgi->import_names('R');
$saveformdata = $R::saveformdata;
$do = $R::do;

LOGDEB "Get language";
my $lang	= lblanguage();
LOGDEB "Resulting language is: " . $lang;

my $maintemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $maintemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		%htmltemplate_options,
		debug => 1
		);
my %L = LoxBerry::System::readlanguage($maintemplate, $languagefile);
$maintemplate->param( "LBPPLUGINDIR", $lbpplugindir);
$maintemplate->param( "MINISERVERS"	, int( keys(%miniservers)) );
$maintemplate->param( "LOGO_ICON"	, get_plugin_icon(64) );
$maintemplate->param( "VERSION"		, $version);
$maintemplate->param( "LOGLEVEL" 	, $L{"LOGGING.LOGLEVEL".$plugin->{PLUGINDB_LOGLEVEL}});
$maintemplate->param( "ELFINDER_LANG"		, $lang);
$lbplogdir =~ s/$lbhomedir\/log\///; # Workaround due to missing variable for Logview

LOGDEB "Check for pending notifications for: " . $lbpplugindir . " " . $L{'GENERAL.MY_NAME'};
my $notifications = LoxBerry::Log::get_notifications_html($lbpplugindir, $L{'GENERAL.MY_NAME'});
LOGDEB "Notifications are:\n".encode_entities($notifications) if $notifications;
LOGDEB "No notifications pending." if !$notifications;
$maintemplate->param( "NOTIFICATIONS" , $notifications);

$maintemplate->param( "LOGFILE" 	, $lbplogdir ."/". $logfile);
	
LOGDEB "Check, if filename for the successtemplate is readable";
stat($lbptemplatedir . "/" . $successtemplatefilename);
if ( !-r _ )
{
	LOGDEB "Filename for the successtemplate is not readable, that's bad";
	$error_message = $ERR{'ERRORS.ERR_SUCCESS_TEMPLATE_NOT_READABLE'};
	&error;
}
LOGDEB "Filename for the successtemplate is ok, preparing template";
my $successtemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $successtemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
LOGDEB "Read success strings from " . $languagefile . " for language " . $lang;
my %SUC = LoxBerry::System::readlanguage($successtemplate, $languagefile);

# Clean up saveformdata variable
	$saveformdata =~ tr/0-1//cd; 
	$saveformdata = substr($saveformdata,0,1);
	
##########################################################################
# Main program
##########################################################################

$R::do if 0; # Prevent errors

if ($do eq "backup") 
{
	  &backup;
}

$R::saveformdata if 0; # Prevent errors
LOGDEB "Is it a save call?";
if ( $R::saveformdata ) 
{
	LOGDEB "Yes, is it a save call";
	@config_params = param; 
	our $save_config = 0;
	our $tag_id = 1;
	for my $tag_number (0 .. 256)
	{
		$plugin_cfg->delete("default.TAG$tag_number"); 
	}
	for our $config_id (0 .. $#config_params)
	{
		if (substr($config_params[$config_id],0,4) eq "BLE_")
		{
			LOGDEB "BLE_... found: ".$config_params[$config_id];
			our $miniserver_data ='';
			for my $msnumber (1 .. param('MINISERVERS'))
			{
				$miniserver_data .= $msnumber.'^'.param('MS_'.$config_params[$config_id].$msnumber).'~';
				LOGDEB "Processing MS $msnumber of ".param('MINISERVERS')." - ".$miniserver_data;
			}		 		  
			$miniserver_data = substr ($miniserver_data ,0, -1);
			$plugin_cfg->param("default.TAG$tag_id", $config_params[$config_id].':'.param($config_params[$config_id]).':'.$miniserver_data.':'.param(('comment'.$config_params[$config_id])));
			LOGDEB "Config line for default.TAG$tag_id: ".$config_params[$config_id].':'.param($config_params[$config_id]).':'.$miniserver_data.':'.param(('comment'.$config_params[$config_id]));
			$tag_id ++;
 		}
		$config_id ++;
	}
	$plugin_cfg->delete("default.saveformdata"); 
	$plugin_cfg->delete("default.SUBFOLDER"); 
	$plugin_cfg->delete("default.MINISERVERS"); 
	$plugin_cfg->delete("default.SCRIPTNAME"); 
	LOGDEB "Write config to file";
	$error_message = $ERR{'ERRORS.ERR_SAVE_CONFIG_FILE'};
	$plugin_cfg->save() or &error; 
	LOGDEB "Set page title, load header, parse variables, set footer, end";
	$template_title = $SUC{'SAVE.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$successtemplate->param('SAVE_ALL_OK'		, $SUC{'SAVE.SAVE_ALL_OK'});
	$successtemplate->param('SAVE_MESSAGE'		, $SUC{'SAVE.SAVE_MESSAGE'});
	$successtemplate->param('SAVE_BUTTON_OK' 	, $SUC{'SAVE.SAVE_BUTTON_OK'});
	$successtemplate->param('SAVE_NEXTURL'		, $ENV{REQUEST_URI});
	print $successtemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Plugin after saving the configuration.";
	exit;
}
else
{
	LOGDEB "No, not a save call";
}
LOGDEB "Call default page";
&form;
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
		# The page title read from language file + our name
		$template_title = $L{"GENERAL.MY_NAME"};

		# Print Template header
		LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);

		# Parse Tags into template
		for our $tag_id (0 .. $#known_tags)
		{
			my %tag;
			# Parse variable tag_mag into template
			$tag{TAG_MAC} = "BLE_00_00_00_00_00_00";
			if (defined($known_tags[$tag_id]->[0]))
			{
				$tag{TAG_MAC}     = $known_tags[$tag_id]->[0];
			}

			# Parse tag_use values into template
			$tag{TAG_USE}    = "unchecked";
			$tag{TAG_USE_HIDDEN} = "off";
			if (defined($known_tags[$tag_id]->[1]))
			{
			 if ($known_tags[$tag_id]->[1] eq "on")
			 	{
					$tag{TAG_USE}        = "checked";
					$tag{TAG_USE_HIDDEN} = "on";
				}
			}
						
			# Parse comment values into template
			$tag{TAG_COMMENT} = "-";
			if (defined($known_tags[$tag_id]->[3]))
			{
					$tag{TAG_COMMENT} = encode_entities($known_tags[$tag_id]->[3]);
			}

      # Parse miniserver Matrix
	foreach my $ms_id (  sort keys  %miniservers) 
	{
		my %ms;
		$ms{MS_NUMBER}        = $ms_id;
		$ms{MS_USED}          = $tag{TAG_USE}; # Default value from Tag-Checkbox 
		$ms{MS_DISPLAY_NAME}  = $miniservers{$ms_id}{Name};
		LOGERR "MS".$ms{MS_NUMBER}." ".$ms{MS_DISPLAY_NAME}." ".$ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP'}."<br>".$ERR{'ERRORS.ERR_0007_MS_CONFIG_NO_IP_SUGGESTION'} if ( $miniservers{$ms_id}{IPAddress} eq "" );
		$ms{MS_USED_HIDDEN}   = $ms{MS_USED};
				if (defined($known_tags[$tag_id]->[2]))
				{
						our @tag_ms_use_list_data = split /\~/, $known_tags[$tag_id]->[2];
						foreach (sort keys @tag_ms_use_list_data) 
						{
							our @this_ms_use_data = split /\^/, $tag_ms_use_list_data[$_];
							if (defined($this_ms_use_data[0])) 
							{
								if ($ms{MS_NUMBER} eq $this_ms_use_data[0])
								{
									if (defined($this_ms_use_data[1]))
									{
										if ($this_ms_use_data[1] eq "on")
										{
												$ms{MS_USED} 				= "checked";
												$ms{MS_USED_HIDDEN}         = "on";
										}
										else
										{
												$ms{MS_USED} 				= "unchecked";
												$ms{MS_USED_HIDDEN}			= "off";
										}
									}
								}										
							}
							else
							{
								$ms{MS_USED} 				= "unchecked";
								$ms{MS_USED_HIDDEN}			= "off";
							}
						}
      	}
				$ms_id ++;
				push @{ $tag{'MSROW'} }, \%ms;
	   	}
			push(@tagrow, \%tag);
		  	$tag_id ++;
		}

    # Parse some strings from language file into template 
		our $str_tags    	  	= $L{"default.TXT_BLUETOOTH_TAGS"};
	
		
		# Parse page

		# Parse page footer		
		$maintemplate->param("TAGROW" => \@tagrow);
		$maintemplate->param("HTMLPATH" => "/plugins/".$lbpplugindir."/");
		$maintemplate->param("BACKUPSTATEFILE" => $backupstate_name);
	
    	print $maintemplate->output();
		LoxBerry::Web::lbfooter();
		exit;
	}

#####################################################
# Error-Sub
#####################################################
sub error 
{
	LOGDEB "Sub error";
	LOGERR $error_message;
	LOGDEB "Set page title, load header, parse variables, set footer, end with error";
	$template_title = $ERR{'ERRORS.MY_NAME'} . " - " . $ERR{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Miniserverbackup Plugin with an error";
	exit;
}




##########################################################################
# Variables
##########################################################################

#our $pcfg;
#our $phrase;
#our $namef;
#our $value;
#our %query;
#our $template_title;
#our $help;
#our @help;
#our $helptext;
#our $helplink;
#our $languagefile;
#our $version;
#our $error;
#our $saveformdata=0;
#our $output;
#our $message;
#our $nexturl;
#our $do="form";
#our $pname;
#our $verbose;
#our $debug;
#our $maxfiles;
#our $autobkp;
#our $bkpcron;
#our $bkpcounts;
#our $selectedauto1;
#our $selectedauto2;
#our $selectedcron1;
#our $selectedcron2;
#our $selectedcron3;
#our $selectedcron4;
#our $selectedcron5;
#our $selectedcron6;
#our $languagefileplugin;
#our $phraseplugin;
#our $selectedverbose;
#our $selecteddebug;
#our $header_already_sent=0;
#my $dontzip;


##########################################################################
# Read Settings
##########################################################################





#
#
#my $bkpbase = defined $pcfg->param("MSBACKUP.BASEDIR") ? $pcfg->param("MSBACKUP.BASEDIR") : "$lbdatadir/currentbackup";
#my $bkpworkdir = defined $pcfg->param("MSBACKUP.WORKDIR") ? $pcfg->param("MSBACKUP.WORKDIR") : "$lbdatadir/workdir";
#my $bkpziplinkdir = defined $pcfg->param("MSBACKUP.BACKUPDIR") ? $pcfg->param("MSBACKUP.BACKUPDIR") : undef;
#my $compressionlevel = defined $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") ? $pcfg->param("MSBACKUP.COMPRESSION_LEVEL") : 5;
#my $zipformat = defined $pcfg->param("MSBACKUP.ZIPFORMAT") ? $pcfg->param("MSBACKUP.ZIPFORMAT") : "7z";
#my $mailreport = defined $pcfg->param("MSBACKUP.MAILREPORT") ? $pcfg->param("MSBACKUP.MAILREPORT") : undef;
#
##Prevent blocking / Recreate state file if missing
#if ( -f '/tmp/backupstate.txt')
#{
#	if ((time - (stat '/tmp/backupstate.txt')[9]) > (30 * 60)) 
#	{
#	  	my $filename = '/tmp/backupstate.txt';
#		open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
#		print $fh "-";
#		close $fh;
#	}
#}
#else
#{
#  	my $filename = '/tmp/backupstate.txt';
#	open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
#	print $fh "-";
#	close $fh;
#}
#if (! -l "$lbhtmldir/backupstate.txt")
#{
#	if ( -f "$lbhtmldir/backupstate.txt")
#	{
#		unlink("$lbhtmldir/backupstate.txt");
#	}
#	symlink('/tmp/backupstate.txt', "$lbhtmldir/backupstate.txt");
#}
#
##########################################################################
## Parameter
##########################################################################
#
## For Debugging with level 3 
#sub apache()
#{
#  if ($debug eq 3)
#  {
#		if ($header_already_sent eq 0) {$header_already_sent=1; print header();}
#		my $debug_message = shift;
#		# Print to Browser 
#		print $debug_message."<br>\n";
#		# Write in Apache Error-Log 
#		print STDERR $debug_message."\n";
#	}
#	return();
#}
#
## Everything from URL
#foreach (split(/&/,$ENV{'QUERY_STRING'}))
#{
#  ($namef,$value) = split(/=/,$_,2);
#  $namef =~ tr/+/ /;
#  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
#  $value =~ tr/+/ /;
#  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
#  $query{$namef} = $value;
#}
#
## Set parameters coming in - get over post
#	if ( !$query{'saveformdata'} ) { if ( param('saveformdata') ) { $saveformdata = quotemeta(param('saveformdata')); } else { $saveformdata = 0;      } } else { $saveformdata = quotemeta($query{'saveformdata'}); }
##	if ( !$query{'lang'} )         { if ( param('lang')         ) { $lang         = quotemeta(param('lang'));         } else { $lang         = "de";   } } else { $lang         = quotemeta($query{'lang'});         }
#	if ( !$query{'do'} )           { if ( param('do')           ) { $do           = quotemeta(param('do'));           } else { $do           = "form"; } } else { $do           = quotemeta($query{'do'});           }
#
## Clean up saveformdata variable
#	$saveformdata =~ tr/0-1//cd; $saveformdata = substr($saveformdata,0,1);
#
## Init Language
##	# Clean up lang variable
##	$lang         =~ tr/a-z//cd; $lang         = substr($lang,0,2);
#  # If there's no language phrases file for choosed language, use English as default
#if (! -e "$lbhomedir/templates/system/$lang/language.dat") 
#	{
#  		$lang = 'en';
#	}
#
## Read English language as default
## Missing phrases in foreign language will fall back to English	
#	$languagefile 			= "$lbhomedir/templates/system/en/language.dat";
#	$languagefileplugin 	= "$lbtemplatedir/en/language.dat";
#	$phrase = new Config::Simple($languagefile);
#	$phrase->import_names('T');
#	our $plglang = new Config::Simple($languagefileplugin);
#	$plglang->import_names('T');
#
#	
##	$lang = 'en'; # DEBUG
#	
## Read foreign language if exists and not English
#	$languagefile 			= "$lbhomedir/templates/system/$lang/language.dat";
#	$languagefileplugin 	= "$lbtemplatedir/$lang/language.dat";
#	if ((-e $languagefile) and ($lang ne 'en')) {
#		# Now overwrite phrase variables with user language
#		$phrase = new Config::Simple($languagefile);
#		$phrase->import_names('T');
#	}
#	if ((-e $languagefileplugin) and ($lang ne 'en')) {
#		# Now overwrite phrase variables with user language
#		$phraseplugin = new Config::Simple($languagefileplugin);
#		$phraseplugin->import_names('T');
#	}
#	
##	$lang = 'de'; # DEBUG
#
#	
###########################################################################
## Main program
###########################################################################
#
#	if ($saveformdata) 
#	{
#	  &save;
#	  &form;
#	  
#	}
#	elsif ($do eq "backup") 
#	{
#	  &backup;
#	}
#	else 
#	{
#	  &form;
#	}
#	exit;
#
######################################################
## 
## Subroutines
##
######################################################
#
######################################################
## Form-Sub
######################################################
#
#	sub form 
#	{
#		# Filter
#		$debug     = quotemeta($debug);
#		$maxfiles  = quotemeta($maxfiles);
#		$autobkp   = quotemeta($autobkp);
#		$bkpcron   = quotemeta($bkpcron);
#		$bkpcounts = quotemeta($bkpcounts);
#		
#		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
#		
#		# Print Template
#		LoxBerry::Web::lbheader("Miniserver Backup", "http://www.loxwiki.eu:80/x/jIKO", "help.html");
#		
#		open(F,"$lbtemplatedir/multi/settings.html") || die "Missing template $lbtemplatedir/$lang/settings.html";
#		  while (<F>) 
#		  {
#		    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
#		    print $_;
#		  }
#		close(F);
#		LoxBerry::Web::lbfooter();
#		exit;
#	}
#
######################################################
## Save-Sub
######################################################
#
#	sub save 
#	{
#		# Everything from Forms
#		$autobkp    = param('autobkp');
#		$bkpcron    = param('bkpcron');
#		$bkpcounts  = param('bkpcounts');
#		$debug      = param('debug');
#		
#		# Filter
#		$autobkp   = quotemeta($autobkp);
#		$bkpcron   = quotemeta($bkpcron);
#		$bkpcounts = quotemeta($bkpcounts);
#		$debug     = quotemeta($debug);
#		
#		# Write configuration file(s)
#		$pcfg->param("MSBACKUP.AUTOBKP", "$autobkp");
#		$pcfg->param("MSBACKUP.CRON",	"$bkpcron");
#		$pcfg->param("MSBACKUP.MAXFILES","$bkpcounts");
#		$pcfg->param("MSBACKUP.DEBUG", 	"$debug");
#		$pcfg->save();
#		
#		# Create Cronjob
#		if (is_enabled($autobkp)) 
#		{
#		  if ($bkpcron eq "15min") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		  }
#		  if ($bkpcron eq "30min") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		  }
#		  if ($bkpcron eq "60min") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		  }
#		  if ($bkpcron eq "1d") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		  }
#		  if ($bkpcron eq "1w") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.weekly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		  }
#		  if ($bkpcron eq "1m") 
#		  {
#		    system ("ln -s $lbcgidir/bin/createmsbackup.pl $lbhomedir/system/cron/cron.monthly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		    unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		  }
#		} 
#		else
#		{
#		  unlink ("$lbhomedir/system/cron/cron.15min/$pname");
#		  unlink ("$lbhomedir/system/cron/cron.30min/$pname");
#		  unlink ("$lbhomedir/system/cron/cron.hourly/$pname");
#		  unlink ("$lbhomedir/system/cron/cron.daily/$pname");
#		  unlink ("$lbhomedir/system/cron/cron.weekly/$pname");
#		  unlink ("$lbhomedir/system/cron/cron.monthly/$pname");
#		}
#		
#		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
#		
#		$template_title = $T::TXT0000 . ": " . $T::TXT0040;
#		$message 				= $T::TXT0002;
#		$nexturl 				= "./index.cgi?do=form";
#		
#		# Print Template
#		#&lbheader;
#		#open(F,"$lbhomedir/templates/system/$lang/success.html") || die "Missing template system/$lang/succses.html";
#		#  while (<F>) 
#		#  {
#		#    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
#		#    print $_;
#		#  }
#		#close(F);
#		#&footer;
#		return;
#	}
#
######################################################
## Manual backup-Sub
######################################################
#
	sub backup 
	{
		print "Content-Type: text/plain\n\n";
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
		print ($L{"GENERAL.MY_NAME"}." OK");
		exit;
	}
#
######################################################
## Error-Sub
######################################################
#
#	sub error 
#	{
#		$template_title = $T::TXT0000 . " - " . $T::TXT0028;
#		if ( !$header_already_sent ) { print "Content-Type: text/html\n\n"; }
#		LoxBerry::Web::lbheader("Miniserver Backup", "http://www.loxwiki.eu:80/x/jIKO", "help.html");
#		open(F,"$lbhomedir/templates/system/$lang/error.html") || die "Missing template system/$lang/error.html";
#		while (<F>) 
#		{
#		  $_ =~ s/<!--\$(.*?)-->/${$1}/g;
#		  print $_;
#		}
#		close(F);
#		LoxBerry::Web::lbfooter();
#		exit;
#	}
