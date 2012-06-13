<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putaccidentreport status="failed" code="0" retry="true" errmsg="XXX">
</putaccidentreport>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON);

//$appJSON ='{"userid":"12658","dartsessionid":"1315","lat":32.74318,"lon":-117.1874,"timestamp":"2011-08-05 14:55","cljson":"{\"numvehicles\":\"1\",\"location\":\"Herr\",\"privateprop\":\"yes\",\"time\":\"1\",\"time12h\":\"pm\",\"activity\":\"other\",\"drivername\":\"T\",\"driverlicense\":\"C\",\"driveraddress\":\"V\",\"drivercity\":\"V\",\"driverstate\":\"C\",\"driverzip\":\"true\",\"phoneareawork\":\"true\",\"phonework\":\"true\",\"phoneareahome\":\"true\",\"phonehome\":\"true\",\"vehiclemake\":\"C\",\"vehicleplate\":\"C\",\"insuranceco\":\"C\",\"policynum\":\"C\",\"comments\":\"Test\"}"}';

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No jsondata supplied', $badXML);
	echo $badXML;
	exit ();
}

$jd = json_decode ( $appJSON );
// userid
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No userid', $badXML);
	echo $badXML;
	exit ();
}
// Dart Session ID
$dartSession = filter_var ( $jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT );
if ($dartSession == FALSE || is_null ( $dartSession )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Dart session ID', $badXML);
	echo $badXML;
	exit ();
}
// GPS Latitude and Longitude
$gpsLat = $jd->lat;
if ($gpsLat == FALSE || is_null ( $gpsLat )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Latitude', $badXML);
	echo $badXML;
	exit ();
}
$gpsLon = $jd->lon;
if ($gpsLon == FALSE || is_null ( $gpsLon )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Longitude', $badXML);
	echo $badXML;
	exit ();
}
// Timestamp
$timestamp = filter_var($jd->timestamp, FILTER_SANITIZE_STRING);
if ($timestamp == FALSE || is_null ( $timestamp )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Timestamp', $badXML);
	echo $badXML;
	exit ();
}
$timestamp .= ".000";

// checklistJSON
$checklistJSON = $jd->cljson;
if ($checklistJSON == FALSE || is_null ( $checklistJSON )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No checklist data', $badXML);
	echo $badXML;
	exit ();
}
$clInfo = json_decode($checklistJSON);

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$clXML = "<ROOT>";
	$clXML .= "\n" . '<Rec rID = "timestamp", rVal = "' . $timestamp . '" />';
	$clXML .= "\n" . '<Rec rID = "gpslat", rVal = "' . $gpsLat . '" />';
	$clXML .= "\n" . '<Rec rID = "gpslon", rVal = "' . $gpsLon . '" />';
	foreach ($clInfo as $key => $val)
		$clXML .= "\n" . '<Rec rID = "' . $key . '", rVal = "' . $val . '" />';
	$clXML .= "\n</ROOT>";

	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_errorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}

$resultXML = <<< EOT
<?xml version="1.0"?>
<putaccidentreport status="success">
</putaccidentreport>
EOT;
echo $resultXML;
exit();
?>