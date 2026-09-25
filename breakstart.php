<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_ADPWFN_SP.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

$postData = '';
foreach ($_POST as $key => $val) {
	$postData .= $key . "=>" . $val . ", ";
}
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Start Time
$starttime = filter_input(INPUT_POST, 'starttime');
if ($starttime == FALSE || is_null($starttime)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No starttime supplied', 'No starttime supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Break Type
$breaktype = filter_input(INPUT_POST, 'breaktype', FILTER_VALIDATE_INT);
if ($breaktype == FALSE || is_null($breaktype) || ($breaktype != 1 && $breaktype != 2)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No breaktype supplied', 'No breaktype supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Mileage
$mileage = 0;
if ($breaktype == 2) {
	$mileage = filter_input(INPUT_POST, 'mileage', FILTER_VALIDATE_INT);
	if ($mileage == FALSE || is_null($mileage)) {
		sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No mileage supplied', 'No mileage supplied in POST request');
		dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
		exit();
	}
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
$result = false;
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		switch ($breaktype) {
			case 1:
				$sql = "uspDARTBreakTime ?, ?, 1, '', 0";
				$stmt = $dbh->prepare($sql);
				$result = $stmt->execute([$userid, $starttime . '.000']);
				break;
			case 2:
				$sql = "uspDARTBreakTime $userid, ?, 1, '', ?";
				$stmt = $dbh->prepare($sql);
				$result = $stmt->execute([$userid, $starttime . '.000', $mileage]);
				break;
			case 3:
				if (strlen($starttime) > 0) {
					SP_DebugLogging("$currentScript: Would be recording the entered clock punch IN time and sending to ADP.", 'dart_debug');
				} else {
					SP_DebugLogging("$currentScript: Would be recording the driver acknowledging they already punched in.", 'dart_debug');
				}
				$sql = "select top 10 * from tblSale";
				break;
			default:
				break;
		}

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

// Submit to ADP WFN
// We do not worry about letting DART know if this fails or not. Failed punches emailed to HR.
$personNumber = '';
$adpWFNError = '';
try {
	$personNumber = ADPWFN_SP::getPersonNumberByUserID($userid);
	if ($personNumber != FALSE) {
		$punchResult = ADPWFN_SP::submitPunch($personNumber, 'out', $starttime, 'Dart Lunch Start');
		SP_DebugLogging("$currentScript: ADPWFN_SP::submitPunch : $personNumber, 'out', $starttime, 'dart' : $punchResult", 'cdc_adpwfn');
	}
} catch (SP_Exception $e) {
	$errMsg = "ADP WFN Error : " . $e->getMessage();
	$errMsg .= "\nUserID : $userid -- Person Number : $personNumber -- Start Time : $starttime";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'ADP WFN Submit Punch Error');
	// Email HR
	$mail = new PHPMailerSP();
	$mail->setApiKey('hr');
	$mail->isHTML(false);
	$mail->FromName = "SP System";
	$mail->From = "itadmin@specialtyproduce.com";
	$mail->Subject = "ADP WFN Punch Submit Error";
	$mail->Body = "There was an error submitting a DART Lunch START to ADP WFN.\n\nUserID : $userid -- Person Number : $personNumber -- Start Time : $starttime";
	$mail->AddAddress("adppuncherrors@specialtyproduce.com");
	$mail->Send();
	$adpWFNError = ": ADP WFN Error : $personNumber";
}

sendResult();
dartLogging($currentScript, "  Success $adpWFNError", $codeStr);
