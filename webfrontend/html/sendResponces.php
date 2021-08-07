<?php
require_once "loxberry_io.php";

LOGDEB("Response parameter: viname=\"".@$_GET['viname']."\" viseparator=\"".@$_GET['viseparator']."\" viparam=\"".@$_GET['viparam']."\"");
$contents = explode("<br>", ob_get_contents());

// Check parameter
if(!isset($_GET['viseparator'])) {
	LOGDEB("Response: \"viseparator\" empty. Set to \"-\"");
	$_GET['viseparator'] = "-";
}

if(isset($_GET['viparam'])) {
	// only send to certain parameters
	$viparams = explode(";", $_GET['viparam']);
}

if(!isset($_GET['viname'])) {
	print "Parameter \"viname\" not set!<br>";
	LOGWARN("Parameter \"viname\" not set!");
} else {
	$start = date_create('NOW');
	$start_mic = microtime(true);

	foreach($contents AS $content) {
		$values = explode("@", $content);
		if(count($values) != 3 or empty($values[0])) {
			continue;
		}
		
		if(empty($viparams) OR in_array($values[1], $viparams) OR in_array($values[0].$values[1], $viparams)) {
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
	}
} 
?>
