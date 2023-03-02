<?php
$mqtt_values = array();

LOGDEB("Response parameter: viname=\"".@$_GET['viname']."\" viseparator=\"".@$_GET['viseparator']."\" viparam=\"".@$_GET['viparam']."\"");
$contents = explode("<br>", ob_get_contents());

// Check parameter
if(($http_activ == true) and !isset($_GET['viseparator'])) {
	LOGDEB("Response: \"viseparator\" empty. Set to \"-\"");
	$_GET['viseparator'] = "-";
}

if(isset($_GET['viparam'])) {
	// only send to certain parameters
	$viparams = explode(";", $_GET['viparam']);
}

// get data from from memory
foreach($contents AS $content) {
	$values = explode("@", $content);
	if(count($values) != 3 or empty($values[0])) {
		// must be 3 values
		continue;
	}
	
	if(empty($viparams) OR in_array($values[1], $viparams) OR in_array($values[0].$values[1], $viparams)) {
		if($http_activ == true) {
			// set vi_endpoint
			$vi_endpoint = $_GET['viname'].$_GET['viseparator'].$values[0].$_GET['viseparator'].$values[1];
			LOGDEB("Try to send to: \"$vi_endpoint\" value \"$values[2]\"");
			
			// no memory - interval is every hour
			$response = mshttp_send($miniserver_no, $vi_endpoint, $values[2]);
			if (!empty($response)) {
				print "Send value \"$values[2]\" to \"$vi_endpoint\" successful!<br>";
				LOGINF("Send value \"$values[2]\" to \"$vi_endpoint\" successful!");
			}
		}
		if($mqtt_activ == true) {
			// build array
			$mqtt_values[strtolower($values[0])][strtolower($values[1])] = $values[2];
		}
	}
}

if($mqtt_activ == true) {
	// Connect to the MQTT-brocker -> use phpMQTT to minimize loxberry version to 2.0
	require_once "phpMQTT/phpMQTT.php";
	$mqtt = new Bluerhinos\phpMQTT($mqttcreds['brokerhost'], $mqttcreds['brokerport'],$mqttcreds['client_id']);
	if( $mqtt->connect(true, NULL, $mqttcreds['brokeruser'], $mqttcreds['brokerpass'] ) ) {
		// send mqtt data
		foreach($mqtt_values AS $mqtt_sub => $mqtt_value) {
			// build a topic
			$mqtt_topic = $config_mqtt_topic."/".$mqtt_sub;
			// encode the data to json
			$mqtt_json_value = json_encode($mqtt_value);	
			// publish json data
			LOGDEB("Publish: \"$mqtt_topic\" value \"$mqtt_json_value\"");
			$mqtt->publish( $mqtt_topic, $mqtt_json_value );
		}
		$mqtt->close();
	}
}
?>
