<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// Get the current list of vehicles
	$sql = "uspDARTVehicleList";
	$stmt = $dbh->query($sql);
	$vehicleList = $stmt->fetchAll(PDO::FETCH_BOTH);
	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

$checklistHTML = file_get_contents('startchecklist.htm');
// Work up vehicle list options
$vlOptions = '';
foreach ($vehicleList as $vehicle) {
	$vlOptions .= '<option value="' . $vehicle['iTruckID'] . ':' . $vehicle['iRefrigerated'] . ':' . $vehicle['iOdometer'] . '">' . $vehicle['sDescription'] . "</option>\n";
}

$sendObj->data->startchecklistHTML = preg_replace('/XXXVEHICLEOPTIONSXXX/', $vlOptions, $checklistHTML);
sendResult();
// $myData = json_decode(json_encode($sendObj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
// echo $myData->data->startchecklistHTML;
