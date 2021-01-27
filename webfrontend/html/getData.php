<?php
require_once "loxberry_system.php";
require_once "loxberry_log.php";

# Initialisieren
$error = 0;
$trashs = array();

# Übergaben prüfen
if(empty($_GET['ort']) or empty($_GET['str']) or empty($_GET['nr'])) {
	die("Parameter is missing!");
}
# Datum darf leer sein
if(empty($_GET['jahr'])) {
	$_GET['jahr'] = date("Y");
}

# Umlaute in ISO-8859-1 wandeln
$in_ort = urlencode(utf8_decode($_GET['ort']));
$in_str = urlencode(utf8_decode($_GET['str']));
$in_nr  = urlencode(utf8_decode($_GET['nr']));

# Log erzeugen
$params = [
    "name" => "Daemon",
    "filename" => "$lbplogdir/nut.log",
    "append" => 1
];
$log = LBLog::newLog ($params);

LOGSTART("AWV-OT HTTP getData.php started");

# Anfrage aufbauen
$post_fields = "JAHR=".$_GET['jahr']."&Ort=$in_ort&Strasse=$in_str&HSN=$in_nr";
LOGDEB("Postfields:$post_fields");

# Anfrage an AWV-OT stellen
LOGINF("Getting data from www.awv-ot.de");
$ch = curl_init("https://www.awv-ot.de/tourenauskunft/auskunftbatix.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$result = curl_exec($ch) or die("Curl Failed");
LOGDEB("Request received with code: ".curl_getinfo($ch, CURLINFO_HTTP_CODE));

if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != "200") {
	LOGERR("Failed to get data!");
	$error = 1;
}

# Inhalt komprimieren
$content = substr($result, strpos($result, "<div id=\"Daten\""));

# Neues DOMDocument-Objekt instanzieren
$doc = new DOMDocument; 
$doc->loadHTML($content);
$xpath = new DOMXPath($doc); 
$tds = $xpath->query('//td');

# Daten konvertieren
LOGDEB("Converting data...");
foreach($tds as $i=>$td) {
	$nodeValue = trim($td->nodeValue);
	if(!empty($nodeValue) && strtotime(substr($nodeValue,0,10)) == false) {
		$dates = convert_table($tds, $i);
		if(!empty($dates)) {
			array_push($trashs, array($nodeValue, $dates));		
		}	
	}
}

# Inhalte vorhanden?
if(empty($trashs)) {
	LOGERR("No data! Please check parameters.");
	$error = 2;
}
	
# Erstellunginfos ausgeben
print "System@DateTime@".date('d.m.Y H:i:s')."<br>";
print "System@DateTimeLox@".epoch2lox(time())."<br>";
print "System@Error@$error<br><br>";

if($error == 0) {
	# Inifitydate setzen
	$next_trash_date = Date(8640000000000000);

	# Daten ausgeben
	foreach($trashs as $i=>$trash) {
		# Ist das der nächste?
		$trash_date_12 = next_date($trash[1], "12:00:00");
		if($trash_date_12 < $next_trash_date) {
			$next_trash = $i;
			$next_trash_name = $trash[0];
			$next_trash_date = $trash_date_12;
		}
		
		# Werte ausgeben
		print $trash[0]."@Id@".$i."<br>";
		$trash_date_24 = next_date($trash[1], "23:59:59");	
		print $trash[0]."@Date@".date('d.m.Y H:i:s', $trash_date_24)."<br>";
		print $trash[0]."@DateLox@".epoch2lox($trash_date_24)."<br>";
		print $trash[0]."@Left@".min_left($trash_date_24)."<br><br>";
		
		LOGINF($trash[0].":".date('d.m.Y H:i:s', $trash_date_24));
		LOGDEB("Next:".date('d.m.Y H:i:s', $trash_date_12));
	}

	print "NextTrash@Id@$next_trash<br>";
	print "NextTrash@Name@$next_trash_name<br>";
	LOGINF("NextTrash:$next_trash_name");
}

LOGEND("AWV-OT HTTP getData.php stopped");	

###################### Funktionen ######################

# Konvertierungsfunktion
function convert_table($table, $id) {
  $dates_str = trim($table[$id+4]->nodeValue);
  $dates = explode(" ", $dates_str);
  return array_filter($dates);
}

# Kleinstes Datum ermitteln
function next_date($values, $time) {
	foreach($values as $value) {
		$value = strtotime(substr($value,0,10)." ".$time);
		if($value != false && $value >= time()) {
			return $next_date = $value;
			break;
		}
	}
}

# Restminuten ermitteln
function min_left($next_date) {
	return round(($next_date - time()) / 3600, 3);
}

?>
