<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

dartLogging($currentScript, "post=" . print_r($_POST, true), $codeStr);

// iPad Name
$ipadname = filter_input(INPUT_POST, 'ipadname');
if ($ipadname == FALSE || is_null($ipadname)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No ipadname supplied', 'No ipadname supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Timestamp
$crashtimestamp = filter_input(INPUT_POST, 'crashtimestamp');
if ($crashtimestamp == FALSE || is_null($crashtimestamp)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No crashtimestamp supplied', 'No crashtimestamp supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Crash Log Name
$crashlogname = filter_input(INPUT_POST, 'crashlogname');
if ($crashlogname == FALSE || is_null($crashlogname)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No crashlogname supplied', 'No crashlogname supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Encoded Crash Log
if (isset($_POST['crashlog'])) {
	$encodedCrashLog = $_POST['crashlog'];
} else {
	$encodedCrashLog = false;
}
if ($encodedCrashLog == FALSE || is_null($encodedCrashLog)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No crashlog supplied', 'No crashlog supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Mail
require_once 'classes_SP/class_PHPMailerSP.php';
$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$mail->FromName = "Specialty Produce DART";
$mail->From = "itadmin@specialtyproduce.com";
$mail->AddAddress("terry.ace.sp2@gmail.com", "Terry Grossman");
$mail->AddCC("christopher@specialtyproduce.com", "Christopher Cilley");
$mail->Body = "Crash report attached.";

// Work through each crash log
$filename = SPConsts::TempDir . $crashlogname;
if (file_put_contents($filename, $encodedCrashLog) === false) {
	dartLogging($currentScript, "Error writing out binary crash log to " . SPConsts::TempDir, $codeStr);
	continue;
}
$mail->Subject = "Crash : $ipadname  : $crashtimestamp";
$mail->AddAttachment($filename, $crashlogname);
if (! $mail->Send()) {
	dartLogging($currentScript, "Error sending crash report", $codeStr);
}
$mail->ClearAttachments();
unlink($filename);

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
