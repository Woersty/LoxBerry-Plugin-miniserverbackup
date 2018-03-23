<?php
// LoxBerry Miniserverbackup Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de

// Header output
header('Content-Type: text/plain; charset=utf-8');

// Calculate running time
$start =  microtime(true);	

// Go to the right directory
chdir(dirname($_SERVER['PHP_SELF']));

// Link to logfile
// http://loxberrydev/admin/system/tools/logfile.cgi?logfile=plugins/miniserverbackup/backuplog.log&header=html&format=template

// Include System Lib
require_once "loxberry_system.php";

$plugin_config_file = $lbpconfigdir."/miniserverbackup.cfg";
$workdir_name		= "workdir";
$savedir_name		= "currentbackup";
$bakupdir_name		= "files";
$workdir_tmp		= $lbpdatadir."/"."tmp"; # <--- The $workdir_data folder will be linked to this target
$workdir_data		= $lbpdatadir."/".$workdir_name;
$savedir_path 		= $lbpdatadir."/".$savedir_name;
$bkpzipdestdir 		= $lbphtmldir."/".$bakupdir_name;
$backupstate_file	= $lbphtmldir."/"."backupstate.txt";
$backupstate_tmp    = "/tmp"."/"."backupstate.txt";
// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);        
ini_set("log_errors", 1);
ini_set("error_log" , $lbplogdir."/backuplog.log"); 
#ini_set("error_log", "/proc/self/fd/2"); 
$sys_callid 		= "CID:".time('U');
$callid 			= $sys_callid;
$summary			= array();

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
		        error_log( "[$callid] <DEBUG> PHP: ".$message );
		        break;
		}
	}
	return;
}

// Plugindata
$plugindata = LBSystem::plugindata();
debug("Loglevel: ".$plugindata['PLUGINDB_LOGLEVEL'],6);

// Plugin version
debug("Version: ".LBSystem::pluginversion(),5);

// Read language
$L = LBSystem::readlanguage("language.ini");
debug(count($L)." ".$L["MINISERVERBACKUP.INF_0001_NB_LANGUAGE_STRINGS_READ"],6);

// Warning if Loglevel > 4 (WARN)
if ($plugindata['PLUGINDB_LOGLEVEL'] > 5 && $plugindata['PLUGINDB_LOGLEVEL'] <= 7) debug($L["MINISERVERBACKUP.INF_0026_LOGLEVEL_WARNING"]." ".$L["MINISERVERBACKUP.LOGLEVEL".$plugindata['PLUGINDB_LOGLEVEL']]." (".$plugindata['PLUGINDB_LOGLEVEL'].")",4);

// Read Miniservers
debug($L["MINISERVERBACKUP.INF_0002_READ_MINISERVERS"]);
$ms = LBSystem::get_miniservers();
if (!is_array($ms)) 
{
	debug($L["MINISERVERBACKUP.ERR_0001_NO_MINISERVERS_CONFIGURED"],3);
	$runtime = microtime(true) - $start;
	debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
	exit(1);
}
else
{
	debug(count($ms)." ".$L["MINISERVERBACKUP.INF_0003_MINISERVERS_FOUND"],5);
}

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
  debug($L["MINISERVERBACKUP.ERR_0028_ERROR_READING_CFG"],4);
  touch($plugin_config_file);
}

#Prevent blocking / Recreate state file if missing or older than 30 min
if ( is_file($backupstate_tmp) ) 
{
	if ( ( time() - filemtime( $backupstate_tmp ) ) > (30 * 60) ) 
	{
		@file_put_contents($backupstate_tmp, "-");
	}
}
else
{
	@file_put_contents($backupstate_tmp, "-");
}
if ( ! is_link($backupstate_file) )
{
	if ( is_file($backupstate_file) )
	{
		@unlink($backupstate_file);
	}
	@symlink($backupstate_tmp, $backupstate_file);
}

if ( ! is_link($backupstate_file) || ! is_file($backupstate_tmp) ) debug($L["MINISERVERBACKUP.ERR_0029_PROBLEM_WITH_STATE_FILE"],3);

// Init Array for files to save
$curl = curl_init() or debug($L["MINISERVERBACKUP.ERR_0002_ERROR_INIT_CURL"],3);
curl_setopt($curl, CURLOPT_RETURNTRANSFER	, true);
curl_setopt($curl, CURLOPT_HTTPAUTH			, constant("CURLAUTH_ANY"));
curl_setopt($curl, CURLOPT_CUSTOMREQUEST	, "GET");

// Process all miniservers
set_time_limit(0);


debug ($L["MINISERVERBACKUP.INF_0032_CLEAN_WORKDIR_TMP"]." ".$workdir_tmp);
create_clean_workdir_tmp($workdir_tmp);
if (!realpath($workdir_tmp)) 
{
	debug ($L["MINISERVERBACKUP.ERR_0022_PROBLEM_WITH_WORKDIR_ON_RAMDISK"],3);
	$runtime = microtime(true) - $start;
	debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
	exit(1);
}

debug($L["MINISERVERBACKUP.INF_0038_DEBUG_DIR_FILE_LINK_EXISTS"]." -> ".$workdir_data);
if ( is_file($workdir_data) || is_dir($workdir_data) || is_link( $workdir_data ) )
{
	debug($L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0039_DEBUG_IS_LINK"]." -> ".$workdir_data);
	if ( is_link( $workdir_data ) )
	{
		debug($L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0042_DEBUG_CORRECT_TARGET"]." -> ".$workdir_data." => ".$workdir_tmp);
		if ( readlink($workdir_data) == $workdir_tmp )
		{
			debug ($L["MINISERVERBACKUP.INF_0030_WORKDIR_IS_SYMLINK_TO_RAMDISK"]); 
			# Everything in place => ok!
		}
		else
		{
			debug($L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0043_DEBUG_DELETE_SYMLINK"]." -> ".$workdir_data);
			unlink($workdir_data);
			debug($L["MINISERVERBACKUP.INF_0044_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
			symlink ($workdir_tmp, $workdir_data);
		}
	}
	else
	{
		debug($L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0041_DEBUG_IS_DIR"]." -> ".$workdir_data);
		if (is_dir($workdir_data))
		{
			debug($L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0034_DEBUG_DIRECTORY_DELETE"]." -> ".$workdir_data);
			rrmdir($workdir_data);			
		}
		else
		{
			debug($L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0040_DEBUG_IS_FILE"]." -> ".$workdir_data);
			if (is_file($workdir_data))
			{
				debug($L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0045_DEBUG_DELETE_FILE"]." -> ".$workdir_data);
				unlink($workdir_data);
			}
			else
			{
				debug("Oh no! You should never read this",2);
			}
		}
		debug($L["MINISERVERBACKUP.INF_0044_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
		symlink($workdir_tmp, $workdir_data);
	}
} 
else
{
	debug($L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0044_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
	symlink($workdir_tmp, $workdir_data);
} 
if (readlink($workdir_data) == $workdir_tmp)
{
	chmod($workdir_tmp	, 0755);
	chmod($workdir_data	, 0755);
	chown($workdir_tmp	, "loxberry");
	chown($workdir_data	, "loxberry");
	debug ($L["MINISERVERBACKUP.INF_0031_SET_WORKDIR_AS_SYMLINK_TO_RAMDISK"]." (".$workdir_data.")",6); 
}
else
{
	debug ($L["MINISERVERBACKUP.ERR_0021_CANNOT_SET_WORKDIR_AS_SYMLINK_TO_RAMDISK"],3);
	$runtime = microtime(true) - $start;
	debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
	exit(1);
}

// Define and create save directories base folder
if (is_file($savedir_path))
{
	debug($L["MINISERVERBACKUP.INF_0040_DEBUG_IS_FILE"]." -> ".$L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0045_DEBUG_DELETE_FILE"]." -> ".$savedir_path);
	unlink($savedir_path);
}
if (!is_dir($savedir_path))
{
	debug($L["MINISERVERBACKUP.INF_0041_DEBUG_IS_DIR"]." -> ".$L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0035_DEBUG_DIRECTORY_CREATE"]." -> ".$savedir_path);
	mkdir($savedir_path, 0777, true);
}
if (!is_dir($savedir_path))
{
	debug ($L["MINISERVERBACKUP.ERR_0020_CREATE_BACKUP_BASE_FOLDER"]." ".$savedir_path,3); 
	$runtime = microtime(true) - $start;
	debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
	exit(1);
}
debug ($L["MINISERVERBACKUP.INF_0046_BACKUP_BASE_FOLDER_OK"]." (".$savedir_path.")",6); 

foreach ($ms as $msno => $miniserver ) 
{
	file_put_contents($backupstate_file,str_ireplace("<MS>",$msno,$L["MINISERVERBACKUP.INF_0068_STATE_RUN"]));
	$callid 	= $sys_callid." MS#".$msno;
    debug ($L["MINISERVERBACKUP.INF_0004_PROCESSING_MINISERVER"]." ".$msno."/".count($ms)." => ".$miniserver['Name'],5);
	$filetree["name"] 		= array();
	$filetree["size"] 		= array();
	$filetree["time"] 		= array();
	$save_ok_list["name"] 	= array();
	$save_ok_list["size"] 	= array();
	$save_ok_list["time"] 	= array();
	$percent_done 			= "100";
	$percent_displ 			= "";
	if ( $miniserver['IPAddress'] == "" ) 
	{
		debug( $L["MINISERVERBACKUP.ERR_0003_MS_CONFIG_NO_IP"],3);
	}
	else
	{
		debug( $L["MINISERVERBACKUP.INF_0005_MS_IP_HOST_PORT"]."=".$miniserver['IPAddress'].":".$miniserver['Port'],6);
	}
	curl_setopt($curl, CURLOPT_USERPWD, $miniserver['Credentials_RAW']);
	$url = "http://".$miniserver['IPAddress'].":".$miniserver['Port']."/dev/cfg/ip";
	curl_setopt($curl, CURLOPT_URL, $url);
	if(curl_exec($curl) === false)
	{
		debug($L["MINISERVERBACKUP.ERR_0018_ERROR_READ_LOCAL_MS_IP"]."\n".curl_error($curl),3);
		return;
	}	
	else
	{ 
		$read_line= curl_multi_getcontent($curl) or $read_line = ""; 
		if(preg_match("/.*dev\/cfg\/ip.*value.*\"(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\".*$/i", $read_line, $local_ip))
		{
			debug($L["MINISERVERBACKUP.INF_0028_LOCAL_MS_IP"]." ".$local_ip[1],6);
		}
		else
		{
			debug($L["MINISERVERBACKUP.ERR_0018_ERROR_READ_LOCAL_MS_IP"]."\n".curl_error($curl),3);
			return;
		}
	}
	
	$url = "http://".$miniserver['IPAddress'].":".$miniserver['Port']."/dev/cfg/version";
	curl_setopt($curl, CURLOPT_URL, $url);
	if(curl_exec($curl) === false)
	{
		debug($L["MINISERVERBACKUP.ERR_0019_ERROR_READ_LOCAL_MS_VERSION"]."\n".curl_error($curl),3);
		return;
	}	
	else
	{ 
		$read_line= curl_multi_getcontent($curl) or $read_line = ""; 
		if(preg_match("/.*dev\/cfg\/version.*value.*\"(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\".*$/i", $read_line, $ms_version))
		{
			$ms_version_dir = str_pad($ms_version[1],2,0,STR_PAD_LEFT).str_pad($ms_version[2],2,0,STR_PAD_LEFT).str_pad($ms_version[3],2,0,STR_PAD_LEFT).str_pad($ms_version[4],2,0,STR_PAD_LEFT);
			debug($L["MINISERVERBACKUP.INF_0029_LOCAL_MS_VERSION"]." ".$ms_version[1].".".$ms_version[2].".".$ms_version[3].".".$ms_version[4]." => ".$ms_version_dir,6);
		}
		else
		{
			debug($L["MINISERVERBACKUP.ERR_0019_ERROR_READ_LOCAL_MS_VERSION"]."\n".curl_error($curl),3);
			return;
		}
	}

	
	$bkpfolder 	= str_pad($msno,3,0,STR_PAD_LEFT)."_".$miniserver['Name'];
	$bkpdir 	= "Backup_".trim($local_ip[1])."_".date("YmdHis",time())."_".$ms_version_dir;
	debug($L["MINISERVERBACKUP.INF_0027_CREATE_BACKUPFOLDER"]." ".$bkpdir." + ".$bkpfolder,6);

	// Set root dir to / and read it
	$folder = "/";
	
	debug($L["MINISERVERBACKUP.INF_0006_READ_DIRECTORIES_AND_FILES"]." ".$folder,6);
	$filetree = read_ms_tree($folder);
	debug($L["MINISERVERBACKUP.INF_0015_BUILDING_FILELIST_COMPLETED"]." ".count($filetree["name"]),5);
	debug($L["MINISERVERBACKUP.INF_0048_REMOVING_ALREADY_SAVED_IDENTICAL_FILES_FROM_LIST"],5);
	if (!is_dir($savedir_path."/".$bkpfolder))
	{
		debug($L["MINISERVERBACKUP.INF_0041_DEBUG_IS_DIR"]." -> ".$L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0035_DEBUG_DIRECTORY_CREATE"]." -> ".$savedir_path."/".$bkpfolder);
		mkdir($savedir_path."/".$bkpfolder, 0777, true);
	}
	if (!is_dir($savedir_path."/".$bkpfolder))
	{
		debug ($L["MINISERVERBACKUP.ERR_0024_CREATE_BACKUP_SUB_FOLDER"]." ".$savedir_path."/".$bkpfolder,3); 
		$runtime = microtime(true) - $start;
		debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
		file_put_contents($backupstate_file, "-");
		exit(1);
	}
	debug ($L["MINISERVERBACKUP.INF_0047_BACKUP_SUB_FOLDER_OK"]." (".$savedir_path."/".$bkpfolder.")",6); 

	foreach (getDirContents($savedir_path."/".$bkpfolder) as &$file_on_disk) 
	{
		$short_name = str_replace($savedir_path."/".$bkpfolder, '', $file_on_disk);
		
		if ($short_name != "" ) 
		{
			$key_in_filetree = array_search($short_name,$filetree["name"],true);
		}
		else
		{
			$key_in_filetree = false;
		}
		if ( !($key_in_filetree === false) )
		{
			if ( $filetree["size"][$key_in_filetree] == filesize($file_on_disk) && $filetree["time"][$key_in_filetree] == filemtime($file_on_disk) )
			{
				debug ($L["MINISERVERBACKUP.INF_0049_COMPARE_FOUND_REMOVE_FROM_LIST"]." (".$short_name.")"); 
				unset($filetree["name"][$key_in_filetree]);
		    	unset($filetree["size"][$key_in_filetree]);
		    	unset($filetree["time"][$key_in_filetree]);
			}
			else
			{
				debug ($L["MINISERVERBACKUP.INF_0050_COMPARE_FOUND_DIFFER_KEEP_LIST"]." (".$short_name.")\nMS <=> LB ".$filetree["name"][$key_in_filetree]." <=> ".$short_name."\nMS <=> LB ".$filetree["size"][$key_in_filetree]." <=> ".filesize($file_on_disk)." Bytes \nMS <=> LB ".date("M d H:i",$filetree["time"][$key_in_filetree])." <=> ".date("M d H:i",filemtime($file_on_disk)),6);
				unlink($file_on_disk);
			}	
		}
		else
		{
			debug ($L["MINISERVERBACKUP.INF_0051_COMPARE_NOT_ON_MS_ANYMORE"]." (".$short_name.") ".filesize($file_on_disk)." Bytes [".filemtime($file_on_disk)."]",6);
			unlink($file_on_disk);
		}
	}
	debug($L["MINISERVERBACKUP.INF_0015_BUILDING_FILELIST_COMPLETED"]." ".count($filetree["name"]),5);
	$curl_save = curl_init() or debug($L["MINISERVERBACKUP.ERR_0002_ERROR_INIT_CURL"],3);
	curl_setopt($curl_save, CURLOPT_HTTPAUTH, constant("CURLAUTH_ANY"));

	if ( count($filetree["name"]) > 0 )
	{
		debug($L["MINISERVERBACKUP.INF_0021_START_DOWNLOAD"],5);
		// Calculate download time
		$start_dwl =  microtime(true);	
 		foreach( $filetree["name"] as $k=>$file_to_save)
		{
			$path = dirname($file_to_save);
			if (!is_dir($workdir_data."/".$bkpfolder.$path))
			{
				mkdir($workdir_data."/".$bkpfolder.$path, 0777, true);
			}
			if (!is_dir($workdir_data."/".$bkpfolder.$path)) 
			{
				debug($L["MINISERVERBACKUP.ERR_0007_PROBLEM_CREATING_BACKUP_DIR"]." ".$workdir_data."/".$bkpfolder.$path,3);
				continue;
			}
			$fp = fopen ($workdir_data."/".$bkpfolder.$file_to_save, 'w+') or debug($L["MINISERVERBACKUP.ERR_0008_PROBLEM_CREATING_BACKUP_FILE"]." ".$workdir_data."/".$bkpfolder.$file_to_save,3);
			if (!isset($fp))
			{
				continue;
			}
			$url = "http://".$miniserver['IPAddress'].":".$miniserver['Port']."/dev/fsget".$file_to_save;
			debug($L["MINISERVERBACKUP.INF_0016_READ_FROM_WRITE_TO"]."\n".$url ." => ".$workdir_data."/".$bkpfolder.$file_to_save); 
			$curl_save = curl_init(str_replace(" ","%20",$url));
			curl_setopt($curl_save, CURLOPT_USERPWD, $miniserver['Credentials_RAW']);
			curl_setopt($curl_save, CURLOPT_TIMEOUT, 50);
			curl_setopt($curl_save, CURLOPT_FILE, $fp) or debug($L["MINISERVERBACKUP.ERR_0008_PROBLEM_CREATING_BACKUP_FILE"]." ".$workdir_data."/".$bkpfolder.$file_to_save."\n".curl_error($curl),3);
			curl_setopt($curl_save, CURLOPT_FOLLOWLOCATION, true);
			$data = curl_exec($curl_save);
			fclose ($fp); 
			if ( $data === FALSE)
			{
				debug($L["MINISERVERBACKUP.ERR_0009_CURL_SAVE_FAILED"]." ".$workdir_data."/".$bkpfolder.$file_to_save."\n".curl_error($curl_save),3);
			}
			else
			{
				// Set file time to guessed value read from miniserver
				if (touch($workdir_data."/".$bkpfolder.$file_to_save, $filetree["time"][array_search($file_to_save,$filetree["name"],true)]) === FALSE )
				{
					debug($L["MINISERVERBACKUP.ERR_0016_FILETIME_ISSUE"]." ".$workdir_data."/".$bkpfolder.$file_to_save,4);
				}
				if ( filesize($workdir_data."/".$bkpfolder.$file_to_save) < 255 )
				{
					$read_data = file_get_contents($workdir_data."/".$bkpfolder.$file_to_save);
					if(stristr($read_data,'<html><head><title>error</title></head><body>') === FALSE && $read_data != "") 
					{
						# Content small but okay
					}
					else
					{
						if(stristr($read_data,'Forbidden')) 
						{
							debug($L["MINISERVERBACKUP.ERR_0015_FORBIDDEN"]." -".$file_to_save."-",6);
							$key = array_search($file_to_save,$filetree["name"],true);
							if ( $key === FALSE ) 
							{
								debug($L["MINISERVERBACKUP.ERR_0017_REMOVE_FORBIDDEN_FILE_FROM_LIST_FAILED"]." ".$file_to_save,4);
							}
							else
							{
								debug($L["MINISERVERBACKUP.INF_0025_REMOVE_FORBIDDEN_FILE_FROM_LIST"]." #$key (".$filetree["name"][$key].")",5);
	    						unset($filetree["name"][$key]);
	    						unset($filetree["size"][$key]);
	    						unset($filetree["time"][$key]);
	   							debug($L["MINISERVERBACKUP.INF_0015_BUILDING_FILELIST_COMPLETED"]." ".count($filetree["name"]),5);
							}
							continue;
						}
						else
						{
							debug($L["MINISERVERBACKUP.ERR_0005_CURL_GET_CONTENT_FAILED"]." ".$file_to_save." [".curl_error($curl_save).$read_data."]",4); 
							continue;
						}
					}
				}
				array_push($save_ok_list["name"], $file_to_save);
				array_push($save_ok_list["size"], filesize($workdir_data."/".$bkpfolder.$file_to_save));
				array_push($save_ok_list["time"], filemtime($workdir_data."/".$bkpfolder.$file_to_save));
				
				if ( filesize($workdir_data."/".$bkpfolder.$file_to_save)  != $filetree["size"][array_search($file_to_save,$filetree["name"],true)])
				{
					debug($L["MINISERVERBACKUP.ERR_0013_DIFFERENT_FILESIZE"]." ".$workdir_data."/".$bkpfolder.$file_to_save." => ".filesize($workdir_data."/".$bkpfolder.$file_to_save) ." != ".$filetree["size"][array_search($file_to_save,$filetree["name"],true)],4);
				}
				else
				{
					debug($L["MINISERVERBACKUP.INF_0017_CURL_SAVE_OK"]." ".$workdir_data."/".$bkpfolder.$file_to_save." (".filesize($workdir_data."/".$bkpfolder.$file_to_save)." Bytes)",6);
				}
				$percent_done = round((count($save_ok_list["name"]) *100 ) / count($filetree["name"]),0);
				file_put_contents($backupstate_file,str_ireplace("<MS>",$msno,$L["MINISERVERBACKUP.INF_0068_STATE_RUN"])." (".$L["MINISERVERBACKUP.INF_0066_STATE_DOWNLOAD"]." ".$percent_done."%)");
				if ( ! ($percent_done % 5) )
				{
					if ( $percent_displ != $percent_done )
					{
						if ($percent_done <= 95)
						{
						 	debug(str_pad($percent_done,3,"_",STR_PAD_LEFT).$L["MINISERVERBACKUP.INF_0022_PERCENT_DONE"]." (".str_pad(round(array_sum($save_ok_list["size"]),0),strlen(round(array_sum($filetree["size"]),0)),"_", STR_PAD_LEFT)."/".str_pad(round(array_sum($filetree["size"]),0),strlen(round(array_sum($filetree["size"]),0)),"_", STR_PAD_LEFT)." Bytes) [".str_pad(count($save_ok_list["name"]),strlen(count($filetree["name"])),"_", STR_PAD_LEFT)."/".str_pad(count($filetree["name"]),strlen(count($filetree["name"])),"_", STR_PAD_LEFT)."]",5);
						}
		 			}
		 			$percent_displ = $percent_done;
				}	
			}
		}
		debug($percent_done.$L["MINISERVERBACKUP.INF_0022_PERCENT_DONE"]." (".round(array_sum($save_ok_list["size"]),0)."/".round(array_sum($filetree["size"]),0)." Bytes) [".count($save_ok_list["name"])."/".count($filetree["name"])."]",5);
		file_put_contents($backupstate_file,str_ireplace("<MS>",$msno,$L["MINISERVERBACKUP.INF_0068_STATE_RUN"]));
		debug(count($save_ok_list["name"])." ".$L["MINISERVERBACKUP.INF_0018_BACKUP_COMPLETE"]." (".array_sum($save_ok_list["size"])." Bytes)",5);
		if ( (count($filetree["name"]) - count($save_ok_list["name"])) > 0 )
		{	
			debug($L["MINISERVERBACKUP.ERR_0010_SOME_FILES_NOT_SAVED"]." ".(count($filetree["name"]) - count($save_ok_list["name"])),4);
			debug($L["MINISERVERBACKUP.ERR_0011_SOME_FILES_NOT_SAVED_INFO"]."\n".implode("\n",array_diff($filetree["name"], $save_ok_list["name"])),6);
		}
		$runtime_dwl = (microtime(true) - $start_dwl);
		debug("Runtime: ".$runtime_dwl." s");
		if ( round($runtime_dwl,1,PHP_ROUND_HALF_UP) < 0.5 ) $runtime_dwl = 0.5;
		$size_dwl = array_sum($save_ok_list["size"]);
		$size_dwl_kBs = round(  ($size_dwl / 1024) / $runtime_dwl ,2);
		debug($L["MINISERVERBACKUP.INF_0053_DOWNLOAD_TIME"]." ".secondsToTime(round($runtime_dwl,0,PHP_ROUND_HALF_UP))." ".$size_dwl." Bytes => ".$size_dwl_kBs." kB/s",5);
	}
	else
	{
		debug($L["MINISERVERBACKUP.INF_0052_NOSTART_DOWNLOAD"],5);
	}
	curl_close($curl_save); 

	
	#Move to target dir
	if (!is_dir($lbpdatadir."/workdir"))
	{
		mkdir($lbpdatadir."/workdir", 0777, true);
	}
	if (!is_dir($lbpdatadir."/workdir")) 
	{
		debug($L["MINISERVERBACKUP.ERR_0012_PROBLEM_CREATING_SAVE_DIR"]." ".$lbpdatadir."/workdir",3);
	}
	else
	{
		debug($L["MINISERVERBACKUP.INF_0020_MOVING_TO_SAVE_DIR"]."\n".$workdir_data."/".$bkpfolder." =>".$savedir_path."/".$bkpfolder);
		rmove($workdir_data."/".$bkpfolder, $savedir_path."/".$bkpfolder);
		rrmdir($workdir_data."/".$bkpfolder);
		if (!is_dir($bkpzipdestdir."/".$bkpfolder))
		{
			mkdir($bkpzipdestdir."/".$bkpfolder, 0777, true);
		}
		if (!is_dir($bkpzipdestdir."/".$bkpfolder)) 
		{
			debug($L["MINISERVERBACKUP.ERR_0012_PROBLEM_CREATING_SAVE_DIR"]." ".$bkpzipdestdir."/".$bkpfolder,3);
		}
		else
		{
			debug($L["MINISERVERBACKUP.INF_0058_CREATE_ZIP_ARCHIVE"]." /".$bkpfolder."/".$bkpdir.".zip",5);
			debug($L["MINISERVERBACKUP.INF_0058_CREATE_ZIP_ARCHIVE"]." ".$savedir_path."/".$bkpfolder." => ".$bkpzipdestdir."/".$bkpfolder."/".$bkpdir.".zip");
			file_put_contents($backupstate_file,str_ireplace("<MS>",$msno,$L["MINISERVERBACKUP.INF_0068_STATE_RUN"])." (".$L["MINISERVERBACKUP.INF_0067_STATE_ZIP"].")");
			HZip::zipDir($savedir_path."/".$bkpfolder, $bkpzipdestdir."/".$bkpfolder."/".$bkpdir.".zip"); 
			debug($L["MINISERVERBACKUP.INF_0061_CREATE_ZIP_ARCHIVE_DONE"]." ".$bkpzipdestdir."/".$bkpfolder."/".$bkpdir.".zip (". round( intval( filesize($bkpzipdestdir."/".$bkpfolder."/".$bkpdir.".zip") ) / 1024 / 1024 ,2 ) ." MB)",5);
		}
	}
	debug ($L["MINISERVERBACKUP.INF_0032_CLEAN_WORKDIR_TMP"]." ".$workdir_tmp);
	create_clean_workdir_tmp($workdir_tmp);
	file_put_contents($backupstate_file,str_ireplace("<MS>",$msno,$L["MINISERVERBACKUP.INF_0068_STATE_RUN"]));
}
$callid = $sys_callid;
debug($L["MINISERVERBACKUP.INF_0019_BACKUPS_COMPLETE"],5);

curl_close($curl); 

class HZip 
{ 
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) { 
  	global $L,$summary,$callid;
    $handle = opendir($folder); 
    while (false !== $f = readdir($handle)) { 
      if ($f != '.' && $f != '..') 
      { 
        $filePath = "$folder/$f"; 
        // Remove prefix from file path before add to zip. 
        $localPath = substr($filePath, $exclusiveLength); 
        if (is_file($filePath)) 
        {
          debug("ZIP: ".$L["MINISERVERBACKUP.INF_0060_ADD_FILE_TO_ZIP"]." ".$filePath);
          if ( $localPath == "log/def.log" )
          {
			$deflog = explode("\n",file_get_contents($filePath));
			
			$lookfor = "PRG Reboot";
          	$matches = array_filter($deflog, function($var) use ($lookfor) { return preg_match("/\b$lookfor\b/i", $var); });
			$last_reboot_key = array_pop($matches);
			array_push($summary,"[$callid] <INFO> ".$L["MINISERVERBACKUP.INF_0062_LAST_MS_REBOOT"]." ".$last_reboot_key);
			$key_in_deflog = array_search($last_reboot_key,$deflog,true);
			$deflog = array_slice($deflog, $key_in_deflog, NULL, TRUE);
			$lookfor = "SDC number of ";
          	$matches = array_filter($deflog, function($var) use ($lookfor) { return preg_match("/\b$lookfor\b/i", $var); });
          	if ( $matches !== false )
          	{
				$error_count = array();
				$error_count_severe = array();
	          	foreach ($matches as $match)
	          	{
          			if ( preg_match("/\bSDC number of errors: \b(\d).*/i", $match, $founds) ) 
          			{
	          			array_push($error_count,$founds[1]);
					}
					else if ( preg_match("/\bSDC number of severe errors: \b(\d).*/i", $match, $founds_severe) )
					{
          				array_push($error_count_severe,$founds_severe[1]);
    	      			$match_severe = $match;
          			}
          		}
				if ( count($error_count) > 0 )
				{          		
					if ( count($error_count) > 10 )
					{
          				array_push($summary,"[$callid] <WARNING> ".$L["MINISERVERBACKUP.ERR_0025_SD_CARD_ERRORS_DETECTED"]." => ".array_sum($error_count)." => ".$L["MINISERVERBACKUP.ERR_0027_LAST_SD_CARD_ERROR_DETECTED"]." ".$match);
          			}
          			else
          			{
          				array_push($summary,"[$callid] <INFO> ".$L["MINISERVERBACKUP.ERR_0025_SD_CARD_ERRORS_DETECTED"]." => ".array_sum($error_count)." => ".$L["MINISERVERBACKUP.ERR_0027_LAST_SD_CARD_ERROR_DETECTED"]." ".$match);
          			}
				}
				if ( count($error_count_severe) > 0 )
				{         
					array_push($summary,"[$callid] <CRITICAL> ".$L["MINISERVERBACKUP.ERR_0026_SEVERE_SD_CARD_ERRORS_DETECTED"]." => ".array_sum($error_count_severe)." => ".$L["MINISERVERBACKUP.ERR_0027_LAST_SD_CARD_ERROR_DETECTED"]." ".$match_severe); 		
					array_push($summary,"[$callid] <ALERT> ".$L["MINISERVERBACKUP.INF_0063_SHOULD_REPLACE_SDCARD"]);
				}
			}
      	  }
          $zipFile->addFile($filePath, $localPath); 
        } 
        elseif (is_dir($filePath)) 
        { 
          // Add sub-directory. 
			debug("ZIP: ".$L["MINISERVERBACKUP.INF_0059_ADD_FOLDER_TO_ZIP"]." ".$filePath,6);
          	$zipFile->addEmptyDir($localPath); 
          	self::folderToZip($filePath, $zipFile, $exclusiveLength); 
        } 
      } 
    } 
    closedir($handle); 
  } 

  public static function zipDir($sourcePath, $outZipPath) 
  {
  	global $L;
    $z = new ZipArchive(); 
    $z->open($outZipPath, ZIPARCHIVE::CREATE); 
    self::folderToZip($sourcePath, $z, strlen("$sourcePath/")); 
	debug($L["MINISERVERBACKUP.INF_0065_COMPRESS_ZIP_WAIT"],5);
    $z->close(); 
  } 
} 

function getDirContents($path) 
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    $files = array(); 
    foreach ($rii as $file)
        if (!$file->isDir())
            $files[] = $file->getPathname();
    return $files;
}

function secondsToTime($seconds) 
{
	global $L;
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
	if ($seconds > 86400) return $dtF->diff($dtT)->format('%a '.$L["MINISERVERBACKUP.INF_0054_DAYS"].' %h:%i:%s '.$L["MINISERVERBACKUP.INF_0055_HOURS"]);
	if ($seconds > 3600)  return $dtF->diff($dtT)->format('%h:%i:%s '.$L["MINISERVERBACKUP.INF_0055_HOURS"]);
	if ($seconds > 60)    return $dtF->diff($dtT)->format('%i:%s '.$L["MINISERVERBACKUP.INF_0056_MINUTES"]);
                          return $dtF->diff($dtT)->format('%s '.$L["MINISERVERBACKUP.INF_0057_SECONDS"]);
}

function read_ms_tree ($folder)
{	
	global $L,$curl,$miniserver,$filetree;
	debug($L["MINISERVERBACKUP.INF_0007_FUNCTION"]." read_ms_tree => ".$folder);
	$LoxURL  = "http://".$miniserver['IPAddress'].":".$miniserver['Port']."/dev/fslist".$folder;
    debug($L["MINISERVERBACKUP.INF_0008_URL_TO_READ"]." ".$LoxURL);
	curl_setopt($curl, CURLOPT_URL, $LoxURL);
	if(curl_exec($curl) === false)
	{
		debug($L["MINISERVERBACKUP.ERR_0004_ERROR_EXEC_CURL"]."\n".curl_error($curl),4);
		return;
	}	
	else
	{ 
		$read_data = curl_multi_getcontent($curl) or $read_data = ""; 
		$read_data = trim($read_data);
		if(stristr($read_data,'<html><head><title>error</title></head><body>') === FALSE && $read_data != "") 
		{
			if(stristr($read_data,'Directory empty')) 
			{
				debug($L["MINISERVERBACKUP.INF_0013_DIRECTORY_EMPTY"].": ".$folder,6);
				return;
			}
			else
			{
				debug($L["MINISERVERBACKUP.INF_0009_GOT_DATA_FROM_MS"]."\n".$read_data);
			}
		}
		else
		{
			debug($L["MINISERVERBACKUP.ERR_0005_CURL_GET_CONTENT_FAILED"]." ".$folder."\n".curl_error($curl).$read_data,4); 
			return;
		}
	}
	foreach(explode("\n",$read_data) as $k=>$read_data_line)
	{
		if(preg_match("/^d.*/i", $read_data_line))
		{
			debug($L["MINISERVERBACKUP.INF_0010_DIRECTORY_FOUND"]." ".$read_data_line);
			if(preg_match("/^d\s*\d*\s[a-zA-z]{3}\s\d{1,2}\s\d{1,2}:\d{1,2}\s(.*)$/i", $read_data_line, $dirname))
			{
				$dirname[1] = trim($dirname[1]);
				debug($L["MINISERVERBACKUP.INF_0012_EXTRACTED_DIRECTORY_NAME"]." ".$folder.$dirname[1],6);
				read_ms_tree ($folder.$dirname[1]."/");
			}
			else
			{
				debug($L["MINISERVERBACKUP.ERR_0006_UNABLE_TO_EXTRACT_NAME"]." ".$read_data_line,4);
			}
		}
		else 
		{
			debug($L["MINISERVERBACKUP.INF_0011_FILE_FOUND"]." ".$read_data_line);
			if(preg_match("/^-\s*(\d*)\s([a-zA-z]{3})\s(\d{1,2})\s(\d{1,2}:\d{1,2})\s(.*)$/i", $read_data_line, $filename))
			{
				/*
				Array $filename[x]
				x=Value
				-------
				1=Size
				2=Month
				3=Day of month
				4=Time
				5=Filename
				*/
				$dtime = DateTime::createFromFormat("M d H:i", $filename[2]." ".$filename[3]." ".$filename[4]);
				$timestamp = $dtime->getTimestamp();
				if ($timestamp > time() )
				{
					// Filetime in future. As Loxone doesn't provide a year 
					// I guess the file was created last year or before and
					// subtract one year from the previously guessed filetime.
					$dtime = DateTime::createFromFormat("Y M d H:i", (date("Y") - 1)." ".$filename[2]." ".$filename[3]." ".$filename[4]);
					$timestamp = $dtime->getTimestamp();
					debug($L["MINISERVERBACKUP.INF_0023_FUTURE_TIMESTAMP"]." ".$folder.$filename[5],6);
				}
				debug($L["MINISERVERBACKUP.INF_0024_FILE_TIMESTAMP"]." ".date("d.m. H:i",$timestamp)." (".$timestamp.") ".$folder.$filename[5],6);
				$filename[5] = trim($filename[5]);
				if ($filename[1] == 0)
				{
					debug($L["MINISERVERBACKUP.ERR_0014_ZERO_FILESIZE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",5);
				}
				else
				{
					debug($L["MINISERVERBACKUP.INF_0014_EXTRACTED_NAME_FILE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",6);
					array_push($filetree["name"], $folder.$filename[5]);
					array_push($filetree["size"], $filename[1]);
					array_push($filetree["time"], $timestamp);
				}
			}
			else
			{
				debug($L["MINISERVERBACKUP.ERR_0006_UNABLE_TO_EXTRACT_NAME"]." ".$read_data_line,4);
			}
		}
  	}
 	return $filetree;
 }

function create_clean_workdir_tmp($workdir_tmp)
{
	global $L;
	debug($L["MINISERVERBACKUP.INF_0033_DEBUG_DIRECTORY_EXISTS"]." -> ".$workdir_tmp);
	if (is_dir($workdir_tmp))
	{
		debug($L["MINISERVERBACKUP.INF_0036_DEBUG_YES"]." -> ".$L["MINISERVERBACKUP.INF_0034_DEBUG_DIRECTORY_DELETE"]." -> ".$workdir_tmp);
		rrmdir($workdir_tmp);
		debug($L["MINISERVERBACKUP.INF_0035_DEBUG_DIRECTORY_CREATE"]." -> ".$workdir_tmp);
		mkdir($workdir_tmp, 0777, true);
	}
	else
	{
		debug($L["MINISERVERBACKUP.INF_0037_DEBUG_NO"]." -> ".$L["MINISERVERBACKUP.INF_0035_DEBUG_DIRECTORY_CREATE"]." -> ".$workdir_tmp);
		mkdir($workdir_tmp, 0777, true);
	}
	return;
}

/**
* Recursively move files from one directory to another
*
* @param String $src – Source of files being moved
* @param String $dest – Destination of files being moved
*/
function rmove($src, $dest)
{
	global $savedir_path,$bkpfolder;
	// If source is not a directory stop processing
	if(!is_dir($src)) 
	{
		return false;
	}
	
	// If the destination directory does not exist create it
	if(!is_dir($dest)) 
	{
		if(!mkdir($dest)) 
		{
			// If the destination directory could not be created stop processing
			return false;
		}
	}
	
	// Open the source directory to read in files
	$i = new DirectoryIterator($src);
	foreach($i as $f) 
	{
		if($f->isFile()) 
		{
			// Keep filetime
			$dt = filemtime($f->getRealPath());
			debug("Move file and set time ".$f->getRealPath()." => ". date("d.m. H:i",$dt),7);
  			rename($f->getRealPath(), "$dest/" .$f->getFilename());
  			touch("$dest/" . $f->getFilename(), $dt);
		} 
		else if(!$f->isDot() && $f->isDir()) 
		{
			rmove($f->getRealPath(), "$dest/$f");
			rmdir($f->getRealPath());
		}
	}
	if ( $src != $savedir_path."/".$bkpfolder."/" ) 
	{
		if (is_file($src)) unlink($src);
	}
}

function rrmdir($dir) 
{
	global $L,$start,$backupstate_file;
	if (is_dir($dir)) 
	{
		if (!is_writable($dir) ) 
		{
			debug($L["MINISERVERBACKUP.ERR_0023_PERMISSON_PROBLEM"]." -> ".$dir,3);
			$runtime = microtime(true) - $start;
			debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
			file_put_contents($backupstate_file, "-");
			exit(1);
		}
		$objects = scandir($dir);
		foreach ($objects as $object) 
		{
			if ($object != "." && $object != "..") 
			{
			  if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

$runtime = microtime(true) - $start;
if ($summary)
{
	error_log("[$callid] <WARNING> PHP: ".$L["MINISERVERBACKUP.INF_9999_SUMMARIZE_ERRORS"]);
}
foreach ($summary as &$errors) 
{
	error_log($errors);
}
file_put_contents($backupstate_file, "-");
debug($L["MINISERVERBACKUP.ERR_0000_EXIT"]." ".$runtime." s",5);
