<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_ADPWFN_SP.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<breakend status="failed" code="0" retry="false" errmsg="XXX">
</breakend>
EOT;

$postData = '';
foreach ($_POST as $key => $val) {
	$postData .= $key . "=>" . $val . ", ";
}
dartLogging($currentScript, "postdata=" . $postData);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
	echo $badXML;
	exit();
}

// End Time
$endtime = filter_input(INPUT_POST, 'endtime', FILTER_SANITIZE_STRING);
if ($endtime == FALSE || is_null($endtime)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid End Time', $badXML);
	echo $badXML;
	exit();
}

// Break Type
$breaktype = filter_input(INPUT_POST, 'breaktype', FILTER_VALIDATE_INT);
if ($breaktype == FALSE || is_null($breaktype) || ($breaktype != 1 && $breaktype != 2)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid Break Type', $badXML);
	echo $badXML;
	exit();
}

// Mileage
$mileage = 0;
if ($breaktype == 2) {
	$mileage = filter_input(INPUT_POST, 'mileage', FILTER_VALIDATE_INT);
	if ($mileage == FALSE || is_null($mileage)) {
		$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid Mileage', $badXML);
		echo $badXML;
		exit();
	}
}

// Comment - can be empty
$comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);

$sql = '';
try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($breaktype == 1) {
		$sql = "uspDARTBreakTime $userid, '" . $endtime . ".000', 2, '" . $comment . "', 0";
	} else {
		$sql = "uspDARTBreakTime $userid, '" . $endtime . ".000', 2, '" . $comment . "', " . $mileage;
	}
	$result = $dbh->exec($sql);

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

if ($result === false) {
	$errMsg = "$sql returned FALSE";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

// Submit to ADP WFN
$personNumber = '';
try {
	$personNumber = ADPWFN_SP::getPersonNumberByUserID($userid);
	if ($personNumber != FALSE) {
		$punchResult = ADPWFN_SP::submitPunch($personNumber, 'in', $endtime, 'Dart Lunch End');
		SP_DebugLogging("$currentScript: ADPWFN_SP::submitPunch : $personNumber, 'in', $endtime, 'dart' : $punchResult", 'cdc_adpwfn');
	}
} catch (SP_Exception $e) {
	$errMsg = "ADP WFN Error : " . $e->getMessage();
	$errMsg .= "\nUserID : $userid -- Person Number : $personNumber -- END Time : $endtime";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'ADP WFN Submit Punch Error');
	// Email HR
	$mail = new PHPMailerSP();
	$mail->setApiKey('hr');
	$mail->isHTML(false);
	$mail->FromName = "SP System";
	$mail->From = "itadmin@specialtyproduce.com";
	$mail->Subject = "ADP WFN Punch Submit Error";
	$mail->Body = "There was an error submitting a DART Lunch END to ADP WFN.\n\nUserID : $userid -- Person Number : $personNumber -- End Time : $endtime";
	$mail->AddAddress("adppuncherrors@specialtyproduce.com");
	$mail->Send();
}

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<breakend status="success">
</breakend>
EOT;
echo $resultStr;
exit();
