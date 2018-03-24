<?php
if (isset($_GET['new_session'])) 
{
	if (isset($_SESSION['offset']))
	{ 
		unset ($_SESSION['offset']);
		echo("OK");
	  exit;
	}
	else
	{
		die("Failed");
	}
}
if (isset($_GET['ajax'])) 
{
  session_start();
  $handle = fopen('../../../../log/plugins/miniserverbackup/backuplog.log', 'r');
  if (isset($_SESSION['offset'])) 
  {
 	if ( $_SESSION['offset'] > filesize('../../../../log/plugins/miniserverbackup/backuplog.log') ) 
 	{
 		unset ($_SESSION['offset']);
    	fclose($handle);
    	$handle = fopen('../../../../log/plugins/miniserverbackup/backuplog.log', 'r');
    	fseek($handle, 0, SEEK_END);
    	$_SESSION['offset'] = ftell($handle);
 	}
 	$data = stream_get_contents($handle, -1, $_SESSION['offset']);
	$convert_data = "";
	foreach(explode("\n",$data) as $data_line)
	{
		
		if ( stristr($data_line,"<ALERT>") )  
		{
			$data_line = "<div class='logalert'>".$data_line."</div>";
		}
		elseif (stristr($data_line,"<CRITICAL>"))
		{
			$data_line = "<div class='logcrit'>".$data_line."</div>";
		}
		elseif (stristr($data_line,"<ERROR>"))
		{
			$data_line = "<div class='logerr'>".$data_line."</div>";
		}
		elseif (stristr($data_line,"<WARNING>"))
		{
			$data_line = "<div class='logwarn'>".$data_line."</div>";
		}
		elseif (stristr($data_line,"<OK>"))
		{
			$data_line = "<div class='logok'>".$data_line."</div>";
		}
		elseif (stristr($data_line,"<INFO>"))
		{
			$data_line = "<div class='loginf'>".$data_line."</div>";
		}
		else
		{
			if ($data_line)  $data_line = $data_line."\n";
		}
		$convert_data .= $data_line;
	}
	$data = $convert_data;
    $search  = array('<ALERT>','<CRITICAL>','<ERROR>','<WARNING>','<OK>','<INFO>','<DEBUG>');
    $replace = array('<font color="red"><b>ALERT:</b></font>','<font color="red"><b>CRITICAL:</b></font>','<font color="red"><b>ERROR:</b></font>','<font color="red"><b>WARNING:</b></font>','<font color="green"><b>OK:</b></font>','<font color="black"><b>INFO:</b></font>','<b>DEBUG:</b>');
    $data = str_ireplace($search, $replace, $data);
    $data = nl2br($data);
    echo $data;
	$_SESSION['offset'] = ftell($handle);
  } else {
    fseek($handle, 0, SEEK_END);
    $_SESSION['offset'] = ftell($handle);
  } 
 fclose($handle);
 exit();
} 
