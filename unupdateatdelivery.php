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
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in jsondata POST variable');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// invJSON
$invJSON = $jd->invoicesjson;
if ($invJSON == FALSE || is_null($invJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No sale ids supplied', 'No invoicesjson supplied in jsondata POST variable');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
$saleIDs = json_decode($invJSON);

// Check for invoices
if (count($saleIDs) == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No sale ids supplied', 'Empty invoicesjson array in jsondata POST variable');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$updateCode = 3;
$signerID = 0; // Only matters for when we are running deliverycomplete.php
$invXML = '';
$result = false;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Prep for the XML version of invoice list for the stored procedure
		$invXML = "<ROOT>\n";
		foreach ($saleIDs as $id)
			$invXML .= '<Rec rID = "' . $id . '"/>' . "\n";
		$invXML .= "</ROOT>\n";
		dartLogging($currentScript, "invXML=" . $invXML);
		$result = $dbh->exec("uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'");

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
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

if ($result === false) {
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', 'Database error, see ' . DART_ERROR_LOG . ' log');
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
