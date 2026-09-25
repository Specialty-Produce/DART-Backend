<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_Samsara_SP.php';
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

// checklistJSON
$checklistJSON = $jd->cljson;
if ($checklistJSON == FALSE || is_null($checklistJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No checklist json supplied', 'No cljson supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$clInfo = json_decode($checklistJSON);
$spTruckID = 0;

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$truckinfo = preg_split('/:/', $clInfo->truck);
	$odoReading = ($clInfo->odometer > 999999) ? 999999 : $clInfo->odometer;
	$spTruckID = $truckinfo[0];
	$sqlds = "uspDARTTruckDataStart $dartSession, $userid, " . $spTruckID . ", " . $odoReading;
	$stmt = $dbh->query($sqlds);
	$dataresult = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	$itemCount = 0;
	$clXML = "<ROOT>";
	foreach ($clInfo->test_items as $item) {
		$clXML .= "\n" . '<Rec sDes = "' . $item . '"/>';
		$itemCount++;
	}
	if ($clInfo->comments != '') {
		$clXML .= "\n" . '<Rec sDes = "Comment : ' . rawurldecode($clInfo->comments) . '"/>';
		$itemCount++;
	}
	$clXML .= "\n</ROOT>";
	$clresult = 0;
	$sqlcl = '';
	if ($itemCount > 0) {
		$sqlcl = "uspDARTCheckListTruck '" . $clXML . "', $dartSession";
		$stmt = $dbh->query($sqlcl);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		$clresult = count($result);
	}

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	$errMsg .= "sqlcl = " . $sqlcl;
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Samsara assignment
try {
	$samDriverID = Samsara_SP::getEmployeeDriverID($userid);
	$samVehicleID = Samsara_SP::getVehicleID($spTruckID);
	if ($samDriverID > 0 && $samVehicleID > 0) {
		$samAssignResult = Samsara_SP::assignDriverToVehicle($samDriverID, $samVehicleID);
		// SP_errorLogging("Samsara Assign Result : $userid / $spTruckID -> $samAssignResult", false, DART_ERROR_LOG);
	}
} catch (SP_Exception $e) {
	SP_errorLogging($e, true, DART_ERROR_LOG, 'DART : Samsara Assign Error');
}

if (!isset($dataresult['Identity']) || $clresult != $itemCount) {
	$errMsg = "$currentScript failed on : (! isset ( \$dataresult ['Identity'] ) || clresult ($clresult) !=  itemCount ($itemCount)\n";
	$errMsg .= "sqlds = $sqlds\nsqlcl = $sqlcl";
	SP_errorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log');
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

$sendObj->data->truckid = $clInfo->truck;
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
