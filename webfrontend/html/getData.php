<?php
require_once "loxberry_system.php";
require_once "loxberry_log.php";

# Initialisieren
$error = -1;
$empty_lines = 0;
$trashs = array();

# Datum darf leer sein
if(!isset($_GET['jahr'])) {
	$_GET['jahr'] = date('Y');
}

# Datum das von der Abfrage nicht erreicht wird setzen
$init_jahr = max($_GET['jahr']+2, date('Y', strtotime("+2 year")));
define('date_init', "31.12.$init_jahr");
define('format', 'd.m.Y');

# Übergaben prüfen
if(!isset($_GET['ort']) or !isset($_GET['str']) or !isset($_GET['nr'])) {
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

# Zwei mal ausführen, mit gewähltem Jahr und dem nächsten
for ($year_add = 0; $year_add <= 1; $year_add++) {
	# Jahr ermitteln
	$year_current = $_GET['jahr']+$year_add;
	
	# Anfrage aufbauen
	$post_fields = "JAHR=$year_current&Ort=$in_ort&Strasse=$in_str&HSN=$in_nr";
	LOGDEB("Postfields:$post_fields");

	# Anfrage an AWV-OT stellen
	LOGINF("Getting data from www.awv-ot.de");
	$curl = post_curl($post_fields);
	LOGDEB("Request received with code: ".$curl['http_code']);

	if($curl['http_code'] != "200") {
		# Fehlermeldung erzeugen
		print "Failed to get data!<br><br>";
		LOGERR("Failed to get data!");
		# Daten nicht ausgeben, damit Loxonewerte nicht überschrieben werden
		$error = 1;
	} else {
		# Inhalt komprimieren
		$content = substr($curl['result'], strpos($curl['result'], "<div id=\"Daten\""));
		$content = substr($content, 0, strpos($content, "</div>"));

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
				if (array_key_exists($nodeValue, $trashs)) {
					# Daten an vorhande Müllart anfügen
					$trashs[$nodeValue] = array_merge($trashs[$nodeValue], $converted['dates']);
				} else {
					# Neue Müllart hinterlegen
					$trashs += [ $nodeValue => $converted['dates'] ];
				}
				# Daten sortieren
				usort($trashs[$nodeValue], 'compareByTimeStamp');
				#asort($trashs[$nodeValue]);
				# Fehler zählen
				$empty_lines = $empty_lines + $converted['error'];
			}
		}
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

# Erstellunginfos ausgeben
print "System@DateTime@".date('d.m.Y H:i:s')."<br>";
print "System@DateTimeLox@".epoch2lox(time())."<br>";
print "System@Error@$error<br><br>";

if($error != 1) {
	# Trash initialisieren
	$next_trash = array("id" => 0, "desc" => "", "date" => strtotime(date_init), "date_12" => strtotime(date_init));

	# Daten ausgeben
	$index = 0;
	foreach($trashs as $trash_desc => $trash_dates) {
		# ID erhöhen damit 0 als Fehler erkannt wird
		$index++;
		# Nächste Datum ermitteln 
		$trash_date = next_date($trash_dates, "23:59:59");

		# Ist das der nächste?
		# Ermittlung auf 12 Uhr, damit die Erinnerung um 16 Uhr klappt
		$next_trash_date = next_date($trash_dates, "12:00:00");
		if($next_trash['date_12'] > $next_trash_date) {			
			$next_trash['id']   = $index;
			$next_trash['desc'] = $trash_desc;
			$next_trash['date'] = $trash_date;
			$next_trash['date_12'] = $next_trash_date;
			LOGDEB("Now is next trash...");
		}
		
		# Werte ausgeben		
		print "$index@Name@".$trash_desc."<br>";
		
		print "$index@Day@".weekday($trash_date, false)."<br>";
		print "$index@DayShort@".weekday($trash_date, true)."<br>";
		print "$index@Date@".date(format, $trash_date)."<br>";
		print "$index@DateLox@".epoch2lox($trash_date)."<br>";
		print "$index@Left@".time_left($trash_date)."<br><br>";
		
		LOGINF($trash_desc.":".date('d.m.Y', $trash_date));
	}

	print "Next@Id@".$next_trash['id']."<br>";
	print "Next@Name@".$next_trash['desc']."<br>";
	print "Next@Day@".weekday($next_trash['date'], false)."<br>";
	print "Next@DayShort@".weekday($next_trash['date'], true)."<br>";	
	print "Next@Date@".date(format, $next_trash['date'])."<br>";
	print "Next@DateLox@".epoch2lox($next_trash['date'])."<br>";
	print "Next@Left@".time_left($next_trash['date'])."<br><br>";	
	LOGINF("Next:".$next_trash['desc']);
}

// Responce to virutal input?
if($config_send) {
	LOGDEB("Starting Response to miniserver or MQTT...");
	include_once 'sendResponces.php';
} 
// print data
ob_end_flush();

LOGEND("AWV-OT HTTP getData.php stopped");	

###################### Funktionen ######################

function compareByTimeStamp($time1, $time2) {
        return strtotime($time1) - strtotime($time2);
}

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
	$date_str = trim($table[$id+4]->nodeValue);
	$dates_str = array_filter(explode(" ", $date_str));
	
	if(empty($dates_str)) {
		# Kein Datum hinterlegt
		return array("error" => 1, "dates" => array(date_init));
	} else {
		# Unnötige Leerzeichen entfernen
		$dates = array();
		foreach($dates_str as $date_str) {
			array_push($dates, substr($date_str, 0, 10));
		}		
		return array("error" => 0, "dates" => $dates);
	}
}

# Kleinstes Datum ermitteln
function next_date($values, $time) {
	foreach($values as $value) {
		$value = strtotime("$value $time");
		if($value != false && $value >= time()) {
			# Das ist das nächste Datum in der Zukunft
			return $next_date = $value;
		}
	}
	# Wenn nichts gefunden wurde das Initaldatum zurückgeben
	return strtotime(date_init." ".$time);;
}

# Restzeit in Stunden ermitteln
function time_left($next_date) {
	if(date(format, $next_date) == date(date_init)) {
		# Initialdatum -> Festwert setzen
		return 9999;
	}
	# Abrunden auf die nächst kleine Stunde
	return floor(($next_date - time()) / 3600);
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
