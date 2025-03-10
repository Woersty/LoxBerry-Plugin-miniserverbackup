<?php

$curl_save = curl_init();
curl_setopt($curl_save, CURLOPT_NOPROGRESS            , 1);
curl_setopt($curl_save, CURLOPT_FOLLOWLOCATION        , 1);
curl_setopt($curl_save, CURLOPT_CONNECTTIMEOUT        , 15);
curl_setopt($curl_save, CURLOPT_TIMEOUT                , 15);
curl_setopt($curl_save, CURLOPT_SSL_VERIFYPEER        , 0);
curl_setopt($curl_save, CURLOPT_SSL_VERIFYSTATUS    , 0);
curl_setopt($curl_save, CURLOPT_SSL_VERIFYHOST        , 0);
curl_setopt($curl_save, CURLOPT_URL, "https://192.168.115.115:444/?action=export_miniservers");
curl_setopt($curl_save, CURLOPT_RETURNTRANSFER        , 1);
$data     = curl_exec($curl_save);
$code    = curl_getinfo($curl_save,CURLINFO_RESPONSE_CODE);
curl_close($curl_save);
if ( $code != 200 )
{
    die(curl_error($curl_save));
}
$teile = explode("\n", $data);

$i=1;
foreach ($teile as $line)
{
    if ( $line == "") continue;
    //echo "<hr>".$line."<hr>";
    $teile2 = explode("~", $line);
    $i=$i+1;
    echo "<br><br>[MINISERVER$i]";
    echo "<br>CLOUDURLFTPPORT=21";
    $before=array(" ",",","ä","ö","ü","ß","Ä","Ö","Ü","(",")");
    $after=array("_","","ae","oe","ue","ss","Ae","Oe","Ue","","");
    echo "<br>NAME=\"".str_replace($before,$after,explode("=", $teile2[2])[1])."\"";
    echo "<br>PASS=\"".str_replace($before,$after,explode("=", $teile2[4])[1])."\"";
    echo "<br>ADMIN=\"".str_replace($before,$after,explode("=", $teile2[6])[1])."\"";
    echo "<br>NOTE=\"".substr($teile2[5],5)."\"";
    echo "<br>USECLOUDDNS=".intval(str_replace($before,$after,explode("=", $teile2[13])[1]));
    echo "<br>IPADDRESS=\"".str_replace($before,$after,explode("=", $teile2[8])[1])."\"";
    echo "<br>CLOUDURL=\"".explode("=", $teile2[1])[1]."\"";
    echo "<br>PORT=\"\"";
    echo "<br>PREFERHTTPS=".intval(str_replace($before,$after,explode("=", $teile2[3])[1]));

    }
echo "";
