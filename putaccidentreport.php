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
// userid
$userid = filter_var($jd->userid, FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// Dart Session ID
$dartSession = filter_var($jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT);
if ($dartSession == FALSE || is_null($dartSession)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dartsessionid supplied', 'No dartsessionid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// GPS Latitude and Longitude
$gpsLat = $jd->lat;
if ($gpsLat == FALSE || is_null($gpsLat)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No $gpsLat supplied', 'No $gpsLat supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$gpsLon = $jd->lon;
if ($gpsLon == FALSE || is_null($gpsLon)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No $gpsLon supplied', 'No $gpsLon supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// Timestamp
$timestamp = filter_var($jd->timestamp);
if ($timestamp == FALSE || is_null($timestamp)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No timestamp supplied', 'No timestamp supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$timestamp .= ".000";

// checklistJSON
$checklistJSON = $jd->cljson;
if ($checklistJSON == FALSE || is_null($checklistJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No cljson supplied', 'No cljson supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$clInfo = json_decode($checklistJSON);

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$clXML = "<ROOT>";
		$clXML .= "\n" . '<Rec rID = "timestamp", rVal = "' . $timestamp . '" />';
		$clXML .= "\n" . '<Rec rID = "gpslat", rVal = "' . $gpsLat . '" />';
		$clXML .= "\n" . '<Rec rID = "gpslon", rVal = "' . $gpsLon . '" />';
		foreach ($clInfo as $key => $val)
			$clXML .= "\n" . '<Rec rID = "' . $key . '", rVal = "' . $val . '" />';
		$clXML .= "\n</ROOT>";

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		$errMsg .= "\n\nclXML = $clXML\n";
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
			$sqlParts = explode(' ', $sql);
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount++;
				$sqlFailed = true;
				sleep(DART_SQL_TIMEOUT_SLEEP);
			} else {
				sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		} else {
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
			sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			exit();
		}
	}
}

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
