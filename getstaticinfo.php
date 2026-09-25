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

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if (isset($_GET['ah'])) {
	$userid = 635;
	$adhoc = true;
}
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// There is no difference DEBUG_USER and live driver.
	$stmt = $dbh->query("uspDARTProductStatus");
	$productStatus = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$stmt = $dbh->query("uspDARTSentBackDescription");
	$sentBacks = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$stmt = $dbh->query("uspDARTOrderStatus");
	$orderStatus = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$stmt = $dbh->query("uspDARTDriverList");
	$driverList = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$sql = "uspDARTVehicleList";
	$stmt = $dbh->query($sql);
	$vehicleList = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$sql = "select iProductID, mUnitPrice from vwCRV order by iProductID, mUnitPrice";
	$stmt = $dbh->query($sql);
	$crvList = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$sql = "uspDartMenu";
	$stmt = $dbh->query($sql);
	$menuList = $stmt->fetchAll(PDO::FETCH_BOTH);
	$stmt->closeCursor();

	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Constants
$sendObj->data->webservicefilesavedays = 5; // Number of days to save web service files

// Product Status
$sendObj->data->productstatus = array();
foreach ($productStatus as $entry) {
	$sendObj->data->productstatus[] = array(
		'id' => intval($entry['iShortID']),
		'description' => mb_convert_encoding($entry['sDescription'], "UTF-8", "Windows-1252")
	);
}
// Sent Backs
$sendObj->data->sentbackstatus = array();
foreach ($sentBacks as $entry) {
	$sendObj->data->sentbackstatus[] = array(
		'dsbid' => intval($entry['iDSBID']),
		'typeid' => intval($entry['iTypeID']),
		'category' => $entry['sCategory'],
		'description' => mb_convert_encoding($entry['sDescription'], "UTF-8", "Windows-1252")
	);
}
// Order Status
$sendObj->data->orderstatus = array();
foreach ($orderStatus as $entry) {
	$sendObj->data->orderstatus[] = array(
		'id' => intval($entry['iAutoID']),
		'description' => mb_convert_encoding($entry['sDescription'], "UTF-8", "Windows-1252")
	);
}
// Message Addresses
$sendObj->data->messageaddresses = array(
	array('id' => 9215, 'name' => 'Christopher Cilley'),
	array('id' => 2835, 'name' => 'Erick Chavez'),
	array('id' => 2, 'name' => 'Management'),
	array('id' => 635, 'name' => 'Roger Harrington')
);
// Drivers
$sendObj->data->drivers = array();
foreach ($driverList as $entry) {
	$sendObj->data->drivers[] = array(
		'id' => intval($entry['iUserID']),
		'name' => mb_convert_encoding($entry['txtFirstName'], "UTF-8", "Windows-1252") . " " . mb_convert_encoding($entry['txtLastName'], "UTF-8", "Windows-1252")
	);
}
// Vehicles
$sendObj->data->vehicles = array();
foreach ($vehicleList as $vehicle) {
	$sendObj->data->vehicles[] = array(
		'id' => intval($vehicle['iTruckID']),
		'description' => $vehicle['sDescription'],
		'refrigerated' => ($vehicle['iRefrigerated'] == 0) ? false : true,
		'odometer' => intval($vehicle['iOdometer']),
		'samsaraid' => $vehicle['iSamsaraID']
	);
}
// CRV
$sendObj->data->crv = array();
foreach ($crvList as $entry) {
	$sendObj->data->crv[] = array(
		'id' => intval($entry['iProductID']),
		'unitprice' => floatval(sprintf("%0.2f", $entry['mUnitPrice']))
	);
}
// Menu Red X
$sendObj->data->menu_red_x = array();
foreach ($menuList as $entry) {
	if ($entry['iTypeID'] != 1) {
		continue;
	}
	$jsActions = json_decode($entry['sAction'], true);
	$sendObj->data->menu_red_x[] = array(
		'dsbid' => intval($entry['iDSBID']),
		'actions' => implode(',', $jsActions),
		'notes' => $entry['sNotes'],
		'notesspanish' => $entry['sNoteSpanish'],
		'description' => $entry['sDescription']
	);
}
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
