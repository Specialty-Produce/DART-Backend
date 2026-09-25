<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Log the POST data
$postData = (isset($_POST)) ? serialize($_POST) : 'none';
dartLogging($currentScript, "POST data=" . $postData, $codeStr);

// Content ID
$contentID = filter_input(INPUT_POST, 'content-id', FILTER_SANITIZE_NUMBER_INT);
if ($contentID == FALSE || is_null($contentID)) {
	$contentID = 0;
}

// Invoice ID
$invoiceID = filter_input(INPUT_POST, 'iid', FILTER_SANITIZE_NUMBER_INT);
if ($invoiceID == FALSE || is_null($invoiceID)) {
	$invoiceID = 0;
}

$debugInvoices = array(
	4514248,
	4514195
);
if (in_array($invoiceID, $debugInvoices)) {
	sendResult();
	exit();
}

if ($contentID == 0 && $invoiceID == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'ContentID and IID cannot both be 0', 'Invalid ContentID and IID in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Update the status
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		// set the error reporting attribute.
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "UPDATE tblDARTAPNASAP SET dtConfirmed=:dateSent WHERE iAutoID=$contentID";
		$stmt = $dbh->prepare($sql);
		$thisDate = date("Y-m-d H:i:s", time()) . ".000";
		$stmt->bindParam(':dateSent', $thisDate);
		$stmt->execute();
		unset($stmt);

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

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
