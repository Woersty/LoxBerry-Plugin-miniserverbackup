<?php

# Get notifications in html format
require_once "loxberry_system.php";
require_once "loxberry_log.php";
// Read language
$L = LBSystem::readlanguage("language.ini");
$plugin_config_file = $lbpconfigdir."/miniserverbackup.cfg";
$params = [
    "name" => $L["MINISERVERBACKUP.INF_0132_CFG_HANDLER_NAME"]." "
];
$log = LBLog::newLog ($params);
LOGSTART ($L["MINISERVERBACKUP.INF_0133_CFG_HANDLER_CALLED"]);

// Error Reporting
error_reporting(E_ALL);
ini_set("display_errors", false);
ini_set("log_errors", 1);

$summary            = array();
$output             = "";

function debug($message = "", $loglevel = 7)
{
    global $L, $plugindata, $summary;
    if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) )
    {
        $message = str_ireplace('"','',$message); // Remove quotes => https://github.com/mschlenstedt/Loxberry/issues/655
        switch ($loglevel)
        {
            case 0:
                // OFF
                break;
            case 1:
                error_log(          "<ALERT> PHP: ".$message );
                array_push($summary,"<ALERT> PHP: ".$message);
                break;
            case 2:
                error_log(          "<CRITICAL> PHP: ".$message );
                array_push($summary,"<CRITICAL> PHP: ".$message);
                break;
            case 3:
                error_log(          "<ERROR> PHP: ".$message );
                array_push($summary,"<ERROR> PHP: ".$message);
                break;
            case 4:
                error_log(          "<WARNING> PHP: ".$message );
                array_push($summary,"<WARNING> PHP: ".$message);
                break;
            case 5:
                error_log( "<OK> PHP: ".$message );
                break;
            case 6:
                error_log( "<INFO> PHP: ".$message );
                break;
            case 7:
            default:
                error_log( "PHP: ".$message );
                break;
        }
    }
    return;
}

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
            LOGINF($L["MINISERVERBACKUP.INF_0064_CONFIG_PARAM"]." ".$config_line[0]."=".$plugin_cfg[$config_line[0]]);
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
        else if ( substr($argv[1],0,10) == "LAST_ERROR" )
        {
            $cli_config = preg_split("/[=]+/",$argv[1]);
            $plugin_cfg[strtoupper($cli_config[0])] = $cli_config[1];
            $output .= "console.log(".strtoupper($cli_config[0]). "=" . $cli_config[1] . ");\n";
        }
        else if ( substr($argv[1],0,11) == "LAST_REBOOT" )
        {
            $cli_config = preg_split("/[=]+/",$argv[1]);
            $cli_config_sub = preg_split("/[;]+/",$cli_config[1]);
            $date = strtotime($cli_config_sub[0]);
            $format = $L["GENERAL.DATE_TIME_FORMAT_PHP"];
            $date_str = date($format ,$date);
            $cli_config_sub[1] = str_ireplace('PRG Reboot',' (Version',$cli_config_sub[1]);
            $cli_config_sub[1] = str_ireplace('PRG Start',' (Version',$cli_config_sub[1]);
            $plugin_cfg[strtoupper($cli_config[0])] = '"'.$date_str." ".trim($cli_config_sub[1]).')"';
            $output .= "console.log('".strtoupper($cli_config[0]). "=" .$date_str." ".$cli_config_sub[1]."');\n";
        }
    }
}

$plugin_cfg["VERSION"] = LBSystem::pluginversion();


ksort($plugin_cfg);
$plugin_cfg_handle = fopen($plugin_config_file, 'w');

$lbversion = LBSystem::lbversion();
if (version_compare($lbversion, '2.0.2.0') >= 0)
{
    LOGINF("Version >= 2.0.2.0 (".$lbversion.")");
    LBSystem::get_miniservers();
}
else
{
    LOGINF("Version < 2.0.2.0 (".$lbversion.")");
    LBSystem::read_generalcfg();
}

$ms = $miniservers;

if (!is_array($ms) or empty($ms))
{
    LOGERR($L["ERRORS.ERR_0001_NO_MINISERVERS_CONFIGURED"]);
    LOGEND("");
    die($L["ERRORS.ERR_0001_NO_MINISERVERS_CONFIGURED"]);
}
$max_ms = max(array_keys($ms));
if (flock($plugin_cfg_handle, LOCK_EX))
{ // exklusive Sperre
    ftruncate($plugin_cfg_handle, 0); // k�rze Datei
    fwrite($plugin_cfg_handle, "[MINISERVERBACKUP]\r\n");
    foreach ($plugin_cfg as $config_key => $config_value)
    {
        if ( filter_var($config_key, FILTER_SANITIZE_NUMBER_INT) > $max_ms )
        {
            # This MS doesn't exists anymore, do not write into config file.
            LOGWARN($L["ERRORS.ERR_0038_REMOVE_PARAMETER_FROM_CONFIG"]." ".$config_key . '="' . $config_value);
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


        LOGINF($L["MINISERVERBACKUP.INF_0071_CONFIG_PARAM_WRITTEN"]. " ". $config_key. "=" . $config_value );
        $written = fwrite($plugin_cfg_handle, $config_key . '="' . $config_value .'"'."\r\n");

        if ( substr($config_key,0,11) == "LAST_REBOOT" || substr($config_key,0,9) == "LAST_SAVE" || substr($config_key,0,10) == "LAST_ERROR" )
        {
                    $output .= "";
        }
        else
        {
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
    }
    fflush($plugin_cfg_handle); // leere Ausgabepuffer bevor die Sperre frei gegeben wird
    flock($plugin_cfg_handle, LOCK_UN); // Gib Sperre frei
}
else
{
    $output .= "show_error('".$L["ERRORS.ERR_0035_ERROR_WRITE_CONFIG"]."');\n";
    LOGERR($L["ERRORS.ERR_0035_ERROR_WRITE_CONFIG"]);
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
#Config Cron-Job
if ( is_link(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR)  ) unlink(LBHOMEDIR."/system/cron/cron.30min/".LBPPLUGINDIR) or LOGERR("A".$L["ERRORS.ERR_0041_ERR_CFG_CRON_JOB"]); #Delete obsolete 30 min Cronjob
$cron  = 'SHELL=/bin/sh'."\n";
$cron .= 'PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin'."\n";
$cron .= 'MAILTO=""'."\n";
$cron .= '# m h dom mon dow user  command'."\n";
if ( $all_interval_used > 0 )
{
    # At least on Miniserver to Backup - create Cronjob
    $cron .= '*/30 * * * * loxberry '.LBPHTMLAUTHDIR.'/bin/createmsbackup.pl >/dev/null 2>&1'."\n";
    @file_put_contents("/tmp/cron_".LBPPLUGINDIR, $cron);
    $cmd = "sudo ".LBHOMEDIR."/sbin/installcrontab.sh ".$plugindata['PLUGINDB_NAME']." /tmp/cron_".LBPPLUGINDIR;
    @exec($cmd, $cmd_output, $retval);
    LOGDEB("Command: ".$cmd."\n".$cmd_output[0]);
    if ( $retval == 0 )
    {
        LOGOK($L["MINISERVERBACKUP.INF_0084_INFO_CRON_JOB_ACTIVE"]);
    }
    else
    {
        LOGERR($L["ERRORS.ERR_0041_ERR_CFG_CRON_JOB"]);
    }
}
else
{
    # No Miniserver to Backup - create empty Cronjob
    @file_put_contents("/tmp/cron_".LBPPLUGINDIR, $cron);

    $cmd = "sudo ".LBHOMEDIR."/sbin/installcrontab.sh ".$plugindata['PLUGINDB_NAME']." /tmp/cron_".LBPPLUGINDIR;
    @exec($cmd, $cmd_output, $retval);
    LOGDEB("Command: ".$cmd."\n".$cmd_output[0]);
    if ( $retval == 0 )
    {
        LOGOK($L["MINISERVERBACKUP.INF_0085_INFO_CRON_JOB_STOPPED"]);
    }
    else
    {
        LOGERR($L["ERRORS.ERR_0041_ERR_CFG_CRON_JOB"]);
    }
}
echo $output;
LOGEND("");
