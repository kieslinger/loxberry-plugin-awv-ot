<?php
require_once "Config/Lite.php";
require_once "loxberry_system.php";
require_once "loxberry_io.php";

// load configfile
$cfg = new Config_Lite("$lbpconfigdir/pluginconfig.cfg");

// config data
$config_ort = @$cfg['MAIN']['ORT'];
$config_str = @$cfg['MAIN']['STR'];
$config_nr  = @$cfg['MAIN']['NR'];

$config_miniserver = @$cfg['MAIN']['MINISERVER'];
$config_http_send  = @$cfg['MAIN']['HTTPSEND'];
$config_mqtt_send  = @$cfg['MAIN']['MQTTSEND'];
$config_mqtt_topic = @$cfg['MAIN']['MQTT_TOPIC'];

$config_send = false;

// send http?
$http_activ = false;
if( ($config_http_send == 1) && isset($_GET['viname']) ) {
	$miniservers = LBSystem::get_miniservers();
	foreach ($miniservers as $index => $miniserver) {		
		if($miniserver['Name'] == $config_miniserver) {
			$miniserver_no = $index;
			if(@$miniserver['PreferHttps'] == 1) {
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
			$http_activ = $config_send = true;
			break;
		}
	}
}

// send mqtt?
$mqtt_activ = false;
if($config_mqtt_send == 1 && isset($config_mqtt_topic)) {
	$mqttcreds = mqtt_connectiondetails();
	if( is_array($mqttcreds) ) {		
		$mqtt_activ = $config_send = true;
		// MQTT requires a unique client id
		$mqttcreds['client_id'] = uniqid(gethostname()."_LoxBerry");
	}
}

?>
