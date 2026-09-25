<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Log the data
$postData = (isset($_POST)) ? serialize($_POST) : 'none';
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $dbh->query("select top 1 dtVersion, siPad from tblDartVersion where siPad is not null and len(siPad) > 0 order by dtCreated desc");
	$versioniPad = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	$stmt = $dbh->query("select top 1 dtVersion, siPhone from tblDartVersion where siPhone is not null and len(sIphone) > 0 order by dtCreated desc");
	$versioniPhone = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	$stmt = $dbh->query("select top 1 dtVersion, siPhoneDB from tblDartVersion where siPhoneDB is not null and len(siPhoneDB) > 0 order by dtCreated desc");
	$versioniPhoneDB = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

$sendObj->data->ipad->versiondate = date('Y-m-d H:i:s', strtotime($versioniPad['dtVersion']));
$sendObj->data->ipad->version = $versioniPad['siPad'];
$sendObj->data->iphone->versiondate = date('Y-m-d H:i:s', strtotime($versioniPhone['dtVersion']));
$sendObj->data->iphone->version = $versioniPhone['siPhone'];
$sendObj->data->iphonedb->versiondate = date('Y-m-d H:i:s', strtotime($versioniPhoneDB['dtVersion']));
$sendObj->data->iphonedb->version = $versioniPhoneDB['siPhoneDB'];
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
