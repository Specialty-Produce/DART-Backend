<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
$appJSON = $_POST['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$jd = json_decode($appJSON);

// iPad ID
$ipadid = filter_var($jd->ipadid);
if ($ipadid == FALSE || is_null($ipadid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No ipadid supplied', 'No ipadid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// UserID, it is ok for it to be "0"
$userid = filter_var($jd->userid, FILTER_SANITIZE_NUMBER_INT);
if (($userid == FALSE || is_null($userid)) && ! $userid == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// Dart Session ID, it is ok for it to be "0"
$dartSession = filter_var($jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT);
if (($dartSession == FALSE || is_null($dartSession)) && ! $dartSession == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dartsessionid supplied', 'No dartsessionid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// GPS Latitude and Longitude JSON
$gpsJSON = $jd->gpsjson;
if ($gpsJSON == FALSE || is_null($gpsJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No gpsjson supplied', 'No gpsjson supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$gpsInfo = json_decode($gpsJSON);

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// Initialize the variables
	$coordDateTime = "";
	$lat = 0.0;
	$lon = 0.0;
	$theTime = date('Y-m-d H:i:s') . '.000';
	$acc = -1.0;

	$dbh->beginTransaction();
	$stmt = $dbh->prepare("INSERT INTO tblGPSData
		(gpsTime, sIpadID, iUserID, iDartSessionID, dtCoordTime, fLatitude, fLongitude, fAccuracy)
		VALUES
		(:gpstime, :ipadid, :userid, :sessionid, :coordtime, :lat, :lon, :acc)");
	$stmt->bindParam(':gpstime', $theTime);
	$stmt->bindParam(':ipadid', $ipadid);
	$stmt->bindParam(':userid', $userid);
	$stmt->bindParam(':sessionid', $dartSession);
	$stmt->bindParam(':coordtime', $coordDateTime);
	$stmt->bindParam(':lat', $lat);
	$stmt->bindParam(':lon', $lon);
	$stmt->bindParam(':acc', $acc);

	foreach ($gpsInfo as $entry) {

		if (! isset($entry->timestamp)) {
			dartLogging($currentScript, " : timestamp NOT SET : " . $gpsJSON);
			dartLogging("errorreport.php", " : timestamp NOT SET : " . $gpsJSON);
			continue;
		}
		$coordDateTime = date('Y-m-d H:i:s', strtotime($entry->timestamp)) . '.000';
		$lat = $entry->lat;
		$lon = $entry->lon;
		$acc = (isset($entry->acc)) ? $entry->acc : -1.0;
		$stmt->execute();
	}
	$result = $dbh->commit();

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

if ($result == true) {
	sendResult();
} else {
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', 'Database error, see ' . DART_ERROR_LOG . ' log');
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
}
