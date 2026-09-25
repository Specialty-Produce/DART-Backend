<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;
global $pullColors;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Log the data
$postData = (isset($_POST)) ? serialize($_POST) : 'none';
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if ($userid == DEBUG_USERID) {
		$sql = "select * from tblDartDataPullReport";
	} else {
		$sql = "uspDARTGetPullReport " . $userid;
	}
	$stmt = $dbh->query($sql);
	$pullReport = $stmt->fetchAll(PDO::FETCH_BOTH);
	$dbh = null;
} catch (PDOException $e) {
	$errMsg = "SQL = $sql\n";
	$eMessage = $e->getMessage();
	$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
	$errMsg .= "\n\ncodeStr = $codeStr\n";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

$sendObj->data->pullReport = array();
foreach ($pullReport as $entry) {
	$colorCode = ($entry['iDARTColorCode'] == null) ? 0 : $entry['iDARTColorCode'];
	$sendObj->data->pullReport[] = array(
		'rowcolor' => $pullColors[$colorCode],
		'description' => mb_convert_encoding($entry['sDescription'], "UTF-8", "Windows-1252"),
		'productid' => intval($entry['iProductID']),
		'quantity' => floatval(sprintf('%0.2f', $entry['Qty'])),
		'master' => $entry['MasterLocation'],
		'location' => $entry['Location']
	);
}
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
