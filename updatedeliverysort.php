<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_DART.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
$appJSON = $_POST['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON);

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$jd = json_decode($appJSON);

// UserID
$userid = filter_var($jd->userid, FILTER_SANITIZE_NUMBER_INT);
if (($userid == FALSE || is_null($userid))) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// If Debug, just send success
if ($userid == DEBUG_USERID) {
	sendResult();
	exit();
}

// Dart Session ID
$dartSession = filter_var($jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT);
if (($dartSession == FALSE || is_null($dartSession))) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dartsessionid supplied', 'No dartsessionid supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// Deliveries list
if ($jd->deliveryjson == FALSE || is_null($jd->deliveryjson)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No deliveryjson supplied', 'No deliveryjson supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sortVal = 1;
		$locXML = "<ROOT>\n";
		foreach ($jd->deliveryjson as $entry) {
			if ($entry->status <= 5) {
				$locXML .= '<Rec LID="' . $entry->locationid . '" iSortID="' . $sortVal . '"/>' . "\n";
				$sortVal++;
			}
		}
		$locXML .= "</ROOT>";
		$result = $dbh->exec("uspDARTDelivery " . $userid . ", '" . $locXML . "'");

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		$errMsg .= "\nlocXML = " . $locXML;
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

// Click record
if (isset($jd->optimizedroute) && $jd->optimizedroute)
	DART::click(3, $dartSession, 0, $userid, 0, 'o', '', '', '', '', 0);
