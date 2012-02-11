<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putgpsdata status="failed" errmsg="XXX">
</putgpsdata>
EOT;

$goodXML = <<< EOT
<?xml version="1.0"?>
<putgpsdata status="success">
</putgpsdata>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging ( $currentScript, "jsondata=" . $appJSON );

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

$jd = json_decode ( $appJSON );

// iPad ID
$ipadid = filter_var ( $jd->ipadid, FILTER_SANITIZE_STRING );
if ($ipadid == FALSE || is_null ( $ipadid )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No iPad ID', $badXML );
	echo $badXML;
	exit ();
}

// UserID, it is ok for it to be "0"
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if (($userid == FALSE || is_null ( $userid )) && ! $userid == 0) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No userid', $badXML );
	echo $badXML;
	exit ();
}
// Dart Session ID, it is ok for it to be "0"
$dartSession = filter_var ( $jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT );
if (($dartSession == FALSE || is_null ( $dartSession )) && ! $dartSession == 0) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No Dart session ID', $badXML );
	echo $badXML;
	exit ();
}

// GPS Latitude and Longitude JSON
$gpsJSON = $jd->gpsjson;
if ($gpsJSON == FALSE || is_null ( $gpsJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No gps data', $badXML );
	echo $badXML;
	exit ();
}
$gpsInfo = json_decode ( $gpsJSON );

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// Initialize the variables
	$coordDateTime = "";
	$lat = 0.0;
	$lon = 0.0;
	$theTime = date ( 'Y-m-d H:i:s' ) . '.000';
	$acc = -1.0;
	
	$dbh->beginTransaction ();
	$stmt = $dbh->prepare ( "INSERT INTO tblGPSData
		(gpsTime, sIpadID, iUserID, iDartSessionID, dtCoordTime, fLatitude, fLongitude, fAccuracy)
		VALUES
		(:gpstime, :ipadid, :userid, :sessionid, :coordtime, :lat, :lon, :acc)" );
	$stmt->bindParam ( ':gpstime', $theTime );
	$stmt->bindParam ( ':ipadid', $ipadid );
	$stmt->bindParam ( ':userid', $userid );
	$stmt->bindParam ( ':sessionid', $dartSession );
	$stmt->bindParam ( ':coordtime', $coordDateTime );
	$stmt->bindParam ( ':lat', $lat );
	$stmt->bindParam ( ':lon', $lon );
	$stmt->bindParam ( ':acc', $acc );
	
	foreach ( $gpsInfo as $entry ) {
		$coordDateTime = date ( 'Y-m-d H:i:s', strtotime ( $entry->timestamp ) ) . '.000';
		$lat = $entry->lat;
		$lon = $entry->lon;
		$acc = (isset($entry->acc)) ? $entry->acc : -1.0;
		$stmt->execute ();
	}
	$result = $dbh->commit ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($result == true) {
	echo $goodXML;
} else {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database transaction commit error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
}
exit ();
?>