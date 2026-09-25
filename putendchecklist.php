<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_DART.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);
} else {
	$appJSON = null;
}

if (MAINTENANCE_MODE) {
	sendError(503, ERROR_CODES::ERROR_MAINTENANCE_MODE, 'Site undergoing maintenance. Please try again.', 'Maintenance Mode is ON', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$jd = json_decode($appJSON);
// User ID
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
	// echo $resultXML;
	// dartLogging($currentScript, "No checklist data - faked success");
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No checklist json supplied', 'No cljson supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$clInfo = json_decode($checklistJSON);

// templogJSON
$templogJSON = json_decode($jd->templogjson);

// Submit the end checklist information and close out the session
try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$odoValue = ($clInfo->odometer > 0) ? $clInfo->odometer : 0;
	$sqlds = "uspDARTTruckDataEnd $dartSession, " . $odoValue;
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
		$clXML .= "\n" . '<Rec sDes = "Comment : ' . htmlspecialchars(rawurldecode($clInfo->comments), ENT_QUOTES) . '"/>';
		$itemCount++;
	}
	$clXML .= "\n</ROOT>";
	$clresult = 0;
	if ($itemCount > 0) {
		$sqlcl = "uspDARTCheckListTruck '" . $clXML . "', $dartSession";
		$stmt = $dbh->query($sqlcl);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		$clresult = count($result);
	}

	// Close out the session
	$result = $dbh->exec("uspDARTInvoicesAssignEnd $dartSession");

	// Temperature logs
	if (count($templogJSON) > 0) {
		$startTemp = $templogJSON[0]->temperature;
		array_shift($templogJSON);
		$tlXML = "<ROOT>";
		foreach ($templogJSON as $stop) {
			foreach ($stop->stopinvoices as $value) {
				$tlXML .= "\n" . '<Rec rID = "' . $value . '" iTemp = "' . $stop->temperature . '" />';
			}
		}
		$tlXML .= "\n</ROOT>";
		$sqltl = "uspDARTTemperatureLog $dartSession, $startTemp, '" . $tlXML . "'";
		$result = $dbh->exec($sqltl);
	}

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	$errMsg .= "sqltl = " . $sqltl;
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

sendResult();
$odoReading = (isset($clInfo->odometer)) ? intval($clInfo->odometer) : 0;
DART::click(4, $dartSession, 0, $userid, 0, '', '', '', '', '', $odoReading);
