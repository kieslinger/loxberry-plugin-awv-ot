<?php
require_once "Config/Lite.php";
require_once "loxberry_system.php";

// load configfile
$cfg = new Config_Lite("$lbpconfigdir/pluginconfig.cfg");

// config data
$config_ort = $cfg['MAIN']['ORT'];
$config_str = $cfg['MAIN']['STR'];
$config_nr  = $cfg['MAIN']['NR'];

$config_miniserver = $cfg['MAIN']['MINISERVER'];
$config_http_send = $cfg['MAIN']['HTTPSEND'];

// send http?
$found = false;
if($config_http_send == 1) {	
	$miniservers = LBSystem::get_miniservers();
	foreach ($miniservers as $i=>$miniserver) {		
		if($miniserver['Name'] == $config_miniserver) {
			$miniserver_no = $i;
			if($miniserver['PreferHttps'] == 1) {
				LOGDEB("sending encrypted in https-Mode");
				$response_endpoint = "https://";
				$miniserver_port = $miniserver['PortHttps'];	
			} else {
				LOGDEB("sending not encrypted in http-Mode");
				$response_endpoint = "http://";
				$miniserver_port = $miniserver['Port'];
			}		
			$response_endpoint = $response_endpoint.$miniserver['Credentials']."@".
								 $miniserver['IPAddress'].":".$miniserver_port."/dev/sps/io/";								 
			break;
		}
	}
}

?>
