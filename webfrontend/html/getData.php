<?php
require_once "loxberry_system.php";
require_once "loxberry_log.php";

# Initialisieren
$error = -1;
$empty_lines = 0;
$trashs = array();

# Datum darf leer sein
if(empty($_GET['jahr'])) {
	$_GET['jahr'] = date('Y');
}

# Datum das von der Abfrage nicht erreicht wird setzen
$init_jahr = max($_GET['jahr']+1, date('Y', strtotime("+1 year")));
define('date_init', "31.12.$init_jahr");

# Übergaben prüfen
if(empty($_GET['ort']) or empty($_GET['str']) or empty($_GET['nr'])) {
	die("Parameter is missing!");
}
# Umlaute in ISO-8859-1 wandeln
$in_ort = conv_iso($_GET['ort']);
$in_str = conv_iso($_GET['str']);
$in_nr  = conv_iso($_GET['nr']);	

# Log erzeugen
$params = [
    "name" => "Daemon",
    "filename" => "$lbplogdir/awv-ot.log",
    "append" => 1
];
$log = LBLog::newLog ($params);

ob_start();
LOGSTART("AWV-OT HTTP getData.php started");

# Config laden
include_once 'config.php';

# Anfrage aufbauen
$post_fields = "JAHR=".$_GET['jahr']."&Ort=$in_ort&Strasse=$in_str&HSN=$in_nr";
LOGDEB("Postfields:$post_fields");

# Anfrage an AWV-OT stellen
LOGINF("Getting data from www.awv-ot.de");
$curl = post_curl($post_fields);
LOGDEB("Request received with code: ".curl_getinfo($ch, CURLINFO_HTTP_CODE));

if($curl['http_code'] != "200") {
	# Fehlermeldung erzeugen
	print "Failed to get data!<br><br>";
	LOGERR("Failed to get data!");
	# Daten nicht ausgeben, damit Loxonewerte nicht überschrieben werden
	$error = 1;
} else {
	# Inhalt komprimieren
	$content = substr($curl['result'], strpos($curl['result'], "<div id=\"Daten\""));

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
			# Hier wird konvertiert
			$converted = convert_table($tds, $i);
			# Daten hinzufügen
			array_push($trashs, array("desc" => $nodeValue, "dates" => $converted['dates']));
			# Fehler zählen
			$empty_lines = $empty_lines + $converted['error'];
		}
	}

	# Inhalte vorhanden?
	if(count($trashs) == $empty_lines) {
		# Fehlermeldung erzeugen
		print "No data! Please check parameters.<br><br>";
		LOGERR("No data! Please check parameters.");
		# Daten Trotzdem ausgeben um die Loxonewerte zu initialisieren
		$error = 2;
	}
}

# Erstellunginfos ausgeben
print "System@DateTime@".date('d.m.Y H:i:s')."<br>";
print "System@DateTimeLox@".epoch2lox(time())."<br>";
print "System@Error@$error<br><br>";

if($error != 1) {
	# Trash initialisieren
	$next_trash = array("id" => 0, "desc" => "", "date" => strtotime(date_init), "date_12" => strtotime(date_init));

	# Daten ausgeben
	foreach($trashs as $i=>$trash) {
		# ID erhöhen damit 0 als Fehler erkannt wird
		$i++;
		# Nächste Datum ermitteln 
		$trash_date = next_date($trash['dates'], "23:59:59");

		# Ist das der nächste?
		# Ermittlung auf 12 Uhr, damit die Erinnerung um 16 Uhr klappt
		$next_trash_date = next_date($trash['dates'], "12:00:00");
		if($next_trash['date_12'] > $next_trash_date) {			
			$next_trash['id']   = $i;
			$next_trash['desc'] = $trash['desc'];			
			$next_trash['date'] = $trash_date;
			$next_trash['date_12'] = $next_trash_date;
			LOGDEB("Now is next trash...");
		}
		
		# Werte ausgeben
		
		print "$i@Name@".$trash['desc']."<br>";
		
		print "$i@Day@".weekday($trash_date, false)."<br>";
		print "$i@DayShort@".weekday($trash_date, true)."<br>";
		print "$i@Date@".date('d.m.Y', $trash_date)."<br>";
		print "$i@DateLox@".epoch2lox($trash_date)."<br>";
		print "$i@Left@".time_left($trash_date)."<br><br>";
		
		LOGINF($trash['desc'].":".date('d.m.Y', $trash_date));		
	}

	print "Next@Id@".$next_trash['id']."<br>";
	print "Next@Name@".$next_trash['desc']."<br>";
	print "Next@Day@".weekday($next_trash['date'], false)."<br>";
	print "Next@DayShort@".weekday($next_trash['date'], true)."<br>";	
	print "Next@Date@".date('d.m.Y', $next_trash['date'])."<br>";
	print "Next@DateLox@".epoch2lox($next_trash['date'])."<br>";
	print "Next@Left@".time_left($next_trash['date'])."<br><br>";	
	LOGINF("Next:".$next_trash['desc']);
}

// Responce to virutal input?
if($config_http_send == 1) {
	LOGDEB("Starting Response to miniserver...");
	include_once 'sendResponces.php';
} 
// print data
ob_end_flush();

LOGEND("AWV-OT HTTP getData.php stopped");	

###################### Funktionen ######################

function conv_iso($input) {
	return urlencode(utf8_decode($input));
}

function post_curl($post_fields) {
	$ch = curl_init("https://www.awv-ot.de/tourenauskunft/auskunftbatix.php");
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);

	$curl_array = array("result" => "", "http_code" => 0);	
	$curl_array['result'] = curl_exec($ch);
	$curl_array['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $curl_array;
}

# Konvertierungsfunktion
function convert_table($table, $id) {
	# Vier Elemente weiter sind die Daten	
	$dates_str = trim($table[$id+4]->nodeValue);
	$dates = array_filter(explode(" ", $dates_str));
	
	if(empty($dates)) {
		# Kein Datum hinterlegt
		$error = 1;
		$dates = array(date_init);
	}	
	return array("error" => $error, "dates" => $dates);
}

# Kleinstes Datum ermitteln
function next_date($values, $time) {
	foreach($values as $value) {
		$value = strtotime(substr($value,0,10)." ".$time);
		if($value != false && $value >= time()) {
			# Das ist das nächste Datum in der Zukunft
			return $next_date = $value;
		}
	}
}

# Restzeit in Stunden ermitteln
function time_left($next_date) {
	if(date('d.m.Y', $next_date) == date_init) {
		# Initialdatum -> Festwert setzen
		return 9999;
	}
	# Ohne Nachkommastellen -> jeweils zur halben Stunde wird gerundet
	return round(($next_date - time()) / 3600, 0);
}

# Wochentag konvertieren
function weekday($date, $format_short) {
	$trans = array(
		'Monday'    => 'Montag',
		'Tuesday'   => 'Dienstag',
		'Wednesday' => 'Mittwoch',
		'Thursday'  => 'Donnerstag',
		'Friday'    => 'Freitag',
		'Saturday'  => 'Samstag',
		'Sunday'    => 'Sonntag',
		'Mon'       => 'Mo',
		'Tue'       => 'Di',
		'Wed'       => 'Mi',
		'Thu'       => 'Do',
		'Fri'       => 'Fr',
		'Sat'       => 'Sa',
		'Sun'       => 'So',
	);	
	
	if($format_short) {
		return strtr(date('D', $date), $trans);
	} else {
		return strtr(date('l', $date), $trans);
	}
}

?>
