<?php
// LoxBerry Miniserverbackup Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de
// Free space in workdir
error_reporting(0);
header('Content-Type: text/plain; charset=utf-8');
$path=str_replace("\n", "", file_get_contents("/tmp/msb_free_space"));
$base=dirname($path);
$free = @exec('if [ -d "'.$path.'" ]; then df --output=avail '.$path.' 2>/dev/null |grep -v Avail; fi');
if ( $free == "" ) $free = @exec('if [ -d "'.$base.'" ]; then df --output=avail '.$base.' 2>/dev/null |grep -v Avail; fi');
if ( $free == "" ) $free = "?";
echo $free;
