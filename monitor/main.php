<?php

$hostname = "10.0.0.10";
$login    = "LOGIN";
$password = "PASSWORD";

$groups = array(
    1 => "/5[23]./",
    2 => "/5[45]./",
    3 => "/5[67]./",
    4 => "/5[89]./",
);



$extensions = array();



// prepare SOAP client for steps 1 & 2
$client = new SoapClient("https://$hostname:8443/realtimeservice/services/RisPort?wsdl",
            array('trace'=>true,
                  'exceptions'=>true,
                  'location'=>"https://$hostname:8443/realtimeservice/services/RisPort",
                  'login'=>$login,
                  'password'=>$password
                 ));



// 1. Get registration status
$response = $client->SelectCmDevice("", array(
                                          "SelectBy" => "DirNumber",
                                          "Status" => "Any"
                                        ));
$devices = $response['SelectCmDeviceResult']->CmNodes[1]->CmDevices;

foreach ($devices as $device)
{
    preg_match('/([0-9]+)/', $device->Description, $m);
    $extensions[$m[1]]['registered'] = ($device->Status == "Registered");
}



// 2. Get state of phones inside Hunt Group
$items = array();
$items[] = array('Name'=>'hlog');
$items[] = array('Name'=>'description');

$response = $client->ExecuteCCMSQLStatement("SELECT h.hlog, d.description FROM device AS d INNER JOIN devicehlogdynamic AS h ON d.pkid = h.fkdevice", $items);

$h = array();
foreach ($response as $r)
{
    if ( in_array($r->Value, array('t','f')) )
    {
        $hunt = ($r->Value=='t');
    }
    elseif ( preg_match('/([0-9]+)/', $r->Value, $m) )
    {
        $ext = $m[1];
        $extensions[$ext]['hunt'] = $hunt;
    }
}



// 3. Get state of phone line (available or busy)
$client = new SoapClient("https://$hostname:8443/perfmonservice/services/PerfmonPort?wsdl",
            array('trace'=>true,
                  'exceptions'=>true,
                  'location'=>"https://$hostname:8443/perfmonservice/services/PerfmonPort",
                  'login'=>$login,
                  'password'=>$password,
                ));
$response = $client->PerfmonCollectCounterData($hostname, "Cisco Lines");

$g = array();
for ($i=1; $i<=4; $i++)
    $g['t'][$i] = $g['f'][$i] = "";

foreach ($response as $r)
{
    if ( preg_match('/.*:([0-9]+)\).*/', $r->Name, $m) )
    {
        $ext = $m[1];
        $extensions[$ext]['available'] = ($r->Value==0);
    }
}



// 4. Summing up
foreach ( array_keys($extensions) as $ext)
{
        foreach ( array_keys($groups) as $j )
        {
            if ( preg_match($groups[$j], $ext) )
            {
                $group = $j;
                if ( $extensions[$ext]['registered'] )
                {
                    $hunt = $extensions[$ext]['hunt'];
                    $available = $extensions[$ext]['available'];

                    $status = ($available) ? "available" : "busy";
                    $g[$hunt][$j] .= "<p><img style='vertical-align: middle;' src='$status.png'/>&nbsp;{$ext}</p>";
                    continue;
                }

                $g[false][$j] .= "<p><img style='vertical-align: middle;' src='unregistered.png'/>&nbsp;{$ext}</p>";
                continue;
            }
        }
}



// 5. Printing out
date_default_timezone_set('Europe/Moscow');
print date('r');
?>
<br/><br/>
<table>
<thead><td colspan='4' align='center'>In hunt group</td><td colspan='4' align='center'>Out of hunt group</td></thead>
<thead>
<td width='100px'><center>Group #1</center></td><td width='100px'><center>Group #2</center></td><td width='100px'><center>Group #3</center></td><td width='100px'><center>Group #4</center></td>
<td width='100px'><center>Group #1</center></td><td width='100px'><center>Group #2</center></td><td width='100px'><center>Group #3</center></td><td width='100px'><center>Group #4</center></td>
</thead>
<tr>
<?php
print "
<td valign='top'><center>{$g[true] [1]}</center></td>
<td valign='top'><center>{$g[true] [2]}</center></td>
<td valign='top'><center>{$g[true] [3]}</center></td>
<td valign='top'><center>{$g[true] [4]}</center></td>
<td valign='top'><center>{$g[false][1]}</center></td>
<td valign='top'><center>{$g[false][2]}</center></td>
<td valign='top'><center>{$g[false][3]}</center></td>
<td valign='top'><center>{$g[false][4]}</center></td>
";
?>
</tr>
</table>
