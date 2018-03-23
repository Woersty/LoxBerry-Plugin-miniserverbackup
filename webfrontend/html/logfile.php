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
  if (isset($_SESSION['offset'])) {
    $data = stream_get_contents($handle, -1, $_SESSION['offset']);
	$convert_data = "";
	foreach(explode("\n",$data) as $data_line)
	{
		
		if ( stristr($data_line,"<ALERT>") )  
		{
			$data_line = "<div class='logalert'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<CRITICAL>"))
		{
			$data_line = "<div class='logcrit'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<ERROR>"))
		{
			$data_line = "<div class='logerr'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<WARNING>"))
		{
			$data_line = "<div class='logwarn'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<OK>"))
		{
			$data_line = "<div class='logok'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<INFO>"))
		{
			$data_line = "<div class='loginf'>".htmlentities($data_line)."</div>";
		}
		elseif (stristr($data_line,"<DEBUG>"))
		{
			$data_line = htmlentities($data_line)."\n";
		}
		$convert_data .= $data_line;
	}
	$data = $convert_data;
    $search  = array('ERRORS', 'ERROR', 'FAILED');
    $replace = array('<FONT color=red><b>ERRORS</b></FONT>', '<FONT color=red><b>ERROR</b></FONT>', '<FONT color=red><b>FAILED</b></FONT>');
    $data = str_ireplace($search, $replace, $data);
    $data = nl2br($data);
    echo $data;
		 $_SESSION['offset'] = ftell($handle);
  } else {
    fseek($handle, 0, SEEK_END);
    $_SESSION['offset'] = ftell($handle);
  } 
 exit();
} 
