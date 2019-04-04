<?php
// LoxBerry Miniserverbackup Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de
// Free space in workdir
error_reporting(E_NONE);     
ini_set("display_errors", false);        
ini_set("log_errors", 0);
header('Content-Type: text/plain; charset=utf-8');
$path=file_get_contents("/tmp/msb_free_space");
if ($path == "") 
{
	echo "?";
}
else
{
	if ( !is_readable($path) )
	{
		$free = exec('df --output=avail '.$path);
		if ( $free == 0 )
		{
			$free = disk_free_space($path)/1024;
		}
		if ( $free == 0 )
		{
			$free = disk_free_space(dirname($path))/1024;
		}
		if ( $free == 0 )
		{
			$free = exec('df --output=avail '.dirname($path));
		}
		if ( $free == 0 )
		{
			$free = "?";
		}
		echo $free;
	}
	else
	{
		echo exec('df --output=avail '.$path);
	}
}
