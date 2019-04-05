<?php
// LoxBerry Miniserverbackup Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de
// Free space in workdir
error_reporting(0);
header('Content-Type: text/plain; charset=utf-8');
$path=file_get_contents("/tmp/msb_free_space");
$free = "?";
if ($path != "") 
{
	if ( stripos($path, "/system/storage/smb/") )
	{
		$free = @exec('df --output=avail '.$path.' 2>/dev/null |grep -v Avail');
		if ( $free == "" ) $free = @exec('df --output=avail "'.dirname($path).'" 2>/dev/null |grep -v Avail');
	}
	else
	{
		if ( !is_readable($path) )
		{
			if ( is_readable(dirname($path)) )
			{
				$free = @exec('df --output=avail '.dirname($path).' 2>/dev/null |grep -v Avail');
			}
		}
		else
		{
			$free = @exec('df --output=avail '.$path.' 2>/dev/null |grep -v Avail');
		}
	}
}
echo $free;
