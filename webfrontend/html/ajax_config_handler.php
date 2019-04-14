<?php

# Get notifications in html format 
require_once "loxberry_system.php";
$plugin_config_file = $lbpconfigdir."/miniserverbackup.cfg";
$logfilename		= "backuplog.log";
// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);        
ini_set("log_errors", 1);
ini_set("error_log" , $lbplogdir."/".$logfilename); 

$callid 			= "Config-Handler";
$summary			= array();
$output 			= "";

function debug($message = "", $loglevel = 7)
{
	global $L, $callid, $plugindata, $summary;
	if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) )  
	{
		$message = str_ireplace('"','',$message); // Remove quotes => https://github.com/mschlenstedt/Loxberry/issues/655
		switch ($loglevel)
		{
		    case 0:
		        // OFF
		        break;
		    case 1:
		        error_log(          "[$callid] <ALERT> PHP: ".$message );
				array_push($summary,"[$callid] <ALERT> PHP: ".$message);
		        break;
		    case 2:
		        error_log(          "[$callid] <CRITICAL> PHP: ".$message );
				array_push($summary,"[$callid] <CRITICAL> PHP: ".$message);
		        break;
		    case 3:
		        error_log(          "[$callid] <ERROR> PHP: ".$message );
				array_push($summary,"[$callid] <ERROR> PHP: ".$message);
		        break;
		    case 4:
		        error_log(          "[$callid] <WARNING> PHP: ".$message );
				array_push($summary,"[$callid] <WARNING> PHP: ".$message);
		        break;
		    case 5:
		        error_log( "[$callid] <OK> PHP: ".$message );
		        break;
		    case 6:
		        error_log( "[$callid] <INFO> PHP: ".$message );
		        break;
		    case 7:
		    default:
		        error_log( "[$callid] PHP: ".$message );
		        break;
		}
	}
	return;
}

// Read language
$L = LBSystem::readlanguage("language.ini");
// Plugindata
$plugindata = LBSystem::plugindata();
$plugin_cfg_handle = @fopen($plugin_config_file, "r");
if ($plugin_cfg_handle)
{
  while (!feof($plugin_cfg_handle))
  {
    $line_of_text = fgets($plugin_cfg_handle);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ($config_line[0])
      {
      	if (!isset($config_line[1])) $config_line[1] = "";
        
        if ( $config_line[1] != "" )
        {
	        $plugin_cfg[$config_line[0]]=preg_replace('/\r?\n|\r/','', str_ireplace('"','',$config_line[1]));
    	    debug($L["MINISERVERBACKUP.INF_0064_CONFIG_PARAM"]." ".$config_line[0]."=".$plugin_cfg[$config_line[0]]);
    	}
      }
    }
  }
  fclose($plugin_cfg_handle);
}
else
{
  touch($plugin_config_file);
}

foreach ($_REQUEST as $config_key => $config_value)
{
	$plugin_cfg[strtoupper($config_key)] = $config_value;
}

# If called from cli via createmsbackup.php
if (php_sapi_name() === 'cli')
{
	if ( isset($argv[1]) )
	{
		if ( substr($argv[1],0,9) == "LAST_SAVE" )
		{
			$cli_config = preg_split("/[=]+/",$argv[1]);
			$plugin_cfg[strtoupper($cli_config[0])] = $cli_config[1];
			$output .= "console.log(".strtoupper($cli_config[0]). "=" . $cli_config[1] . ");\n";
		}
		if ( substr($argv[1],0,11) == "LAST_REBOOT" )
		{
			$cli_config = preg_split("/[=]+/",$argv[1]);
			$cli_config_sub = preg_split("/[;]+/",$cli_config[1]);
			$date = strtotime($cli_config_sub[0]);
			$format = str_ireplace('%','',$L["GENERAL.DATE_TIME_FORMAT_PHP"]);
			$date_str = date($format ,$date);
			$cli_config_sub[1] = str_ireplace('PRG Reboot',' (Version',$cli_config_sub[1]);
			$plugin_cfg[strtoupper($cli_config[0])] = '"'.$date_str." ".trim($cli_config_sub[1]).')"';
			$output .= "console.log('".strtoupper($cli_config[0]). "=" .$date_str." ".$cli_config_sub[1]."');\n";
		}
	}
} 

$plugin_cfg["VERSION"] = LBSystem::pluginversion();


ksort($plugin_cfg);
$plugin_cfg_handle = fopen($plugin_config_file, 'w');

LBSystem::read_generalcfg();
$ms = $miniservers;

if (!is_array($ms)) 
{
	debug($L["ERRORS.ERR_0001_NO_MINISERVERS_CONFIGURED"],3);
	die($L["ERRORS.ERR_0001_NO_MINISERVERS_CONFIGURED"]);
}
$max_ms = count($ms);
if (flock($plugin_cfg_handle, LOCK_EX)) 
{ // exklusive Sperre
    ftruncate($plugin_cfg_handle, 0); // kürze Datei
	fwrite($plugin_cfg_handle, "[MINISERVERBACKUP]\n");
	foreach ($plugin_cfg as $config_key => $config_value)
	{
		if ( filter_var($config_key, FILTER_SANITIZE_NUMBER_INT) > $max_ms )
		{
			# This MS doesn't exists anymore, do not write into config file.
			debug($L["ERRORS.ERR_0038_REMOVE_PARAMETER_FROM_CONFIG"]." ".$config_key . '="' . $config_value,4);
		}
		else
		{
			if ( $config_key == "WORKDIR_PATH" )
			{
				$subdir = "";
				if ( isset($plugin_cfg["WORKDIR_PATH_SUBDIR"]) )
				{
					if ( $plugin_cfg["WORKDIR_PATH_SUBDIR"] != "" )
					{
						$subdir = "/".$plugin_cfg["WORKDIR_PATH_SUBDIR"];
					}
				}
				system("echo '".$plugin_cfg["WORKDIR_PATH"].$subdir."' > /tmp/msb_free_space");
			}
			
	
		
		debug($L["MINISERVERBACKUP.INF_0071_CONFIG_PARAM_WRITTEN"]. " ". $config_key. "=" . $config_value );
		$written = fwrite($plugin_cfg_handle, $config_key . '="' . $config_value .'"'."\n");
		if ( !$written )
		{
			$output .= "show_error('".$L["ERRORS.ERR_0035_ERROR_WRITE_CONFIG"]." => ".$config_key."');\n";
			$output .= "$('#".strtolower($config_key)."').css('background-color','#FFC0C0');\n";
		}
		else
		{
			$output .= "$('#".strtolower($config_key)."').css('background-color','#C0FFC0');\n";
			$output .= "setTimeout( function() { $('#".strtolower($config_key)."').css('background-color',''); }, 3000);\n";
		}
		
			
		}
	}
    fflush($plugin_cfg_handle); // leere Ausgabepuffer bevor die Sperre frei gegeben wird
    flock($plugin_cfg_handle, LOCK_UN); // Gib Sperre frei
} 
else 
{
	$output .= "show_error('".$L["ERRORS.ERR_0035_ERROR_WRITE_CONFIG"]."');\n";
	debug($L["ERRORS.ERR_0035_ERROR_WRITE_CONFIG"],3);
}
fclose($plugin_cfg_handle);
$all_interval_used = 0;
foreach ($plugin_cfg as $config_key => $config_value)
{
	#If at least one job is configured, set cronjob
	if ( strpos($config_key, 'BACKUP_INTERVAL') !== false && intval($config_value) > 0 ) 
	{
		$all_interval_used = $all_interval_used + $config_value;
	}
}
#Create Cron-Job
if ( $all_interval_used > 0 )
{
	if ( ! is_link(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR)  )
	{
		@symlink(LBPHTMLAUTHDIR."/bin/createmsbackup.pl", LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR);
	}
		
	if ( ! is_link(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR) )
	{
		debug($L["ERRORS.ERR_0041_ERR_CFG_CRON_JOB"],3);	
	}
	else
	{
		debug($L["MINISERVERBACKUP.INF_0084_INFO_CRON_JOB_ACTIVE"],6);	
	}
}
else
{
	if ( is_link(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR) )
	{
		unlink(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR) or debug($L["ERRORS.ERR_0041_ERR_CFG_CRON_JOB"],3);
	}
	debug($L["MINISERVERBACKUP.INF_0085_INFO_CRON_JOB_STOPPED"],6);	
}
echo $output;
