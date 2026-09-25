<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_RoutingGTM.php';
include_once 'classes_SP/class_DART.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Dart Session ID
$dartsessionid = filter_input(INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT);
if ($dartsessionid == FALSE || is_null($dartsessionid)) {
	$dartsessionid = 1;
}

// Get the POST data
if (isset($_POST['optData'])) {
	$optJSON = $_POST['optData'];
	dartLogging($currentScript, "userid = $userid : dartsessionid = $dartsessionid : jsondata=" . $optJSON, $codeStr);
} else {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No optData supplied', 'No optData supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Good to go...
$jd = json_decode($optJSON);
if ($jd == FALSE || is_null($jd)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Bad JSON data', 'Decoded jsondata is FALSE or NULL');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. Hand fix and adhoc enter data", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
	exit();
}

if ($jd->origin->lat == 0 || $jd->origin->lon == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Call to optimizeRoute failed, reason: GPS disabled on iPad.  Please enable location services for DART.  If problem persists bring iPad to Alan or Christopher in IT.', 'Lat || Lon == 0 ');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

if (count($jd->locations) > 23) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Call to optimizeRoute failed, reason: Too many delivery stops in the list, 23 is max.', 'Too many waypoints : ' . count($jd->locations));
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$startMS = microtime(true) * 1000.0;

// Setup the router
$routeGTM = new RoutingGTM(1, 's', false, false, true, 'Current Location', $jd->origin->lat, $jd->origin->lon);
$currentLocationOrder = array();
foreach ($jd->locations as $entry) {
	$routeGTM->addLocation($entry->name, $entry->lat, $entry->lon, $entry->asap);
	$currentLocationOrder[$entry->name] = $entry->id;
}

try {
	list($currentTravelTime, $currentTravelSummary, $optRouteError) = $routeGTM->getGoogleRouteTime(array_keys($currentLocationOrder), true, true, false);
	list($currentRouteText, $curentRouteURL) = $routeGTM->getRouteTextURL(array_keys($currentLocationOrder));
} catch (SP_Exception $spe) {
	$errMsg = $spe->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
	sendError(500, ERROR_CODES::ERROR_UNKNOWN, 'Google Mapping services are down.', 'Google error, see ' . DART_ERROR_LOG . ' log', false);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Optimize
list($optTravelTime, $optSummary, $optRouteText, $optRouteURL, $optRouteOrder, $optRouteError) = $routeGTM->getGoogleOptimizedRoute();

// Generate the response
$sendObj->data->orderChanged = (array_keys($currentLocationOrder) === $optRouteOrder) ? false : true;
$sendObj->data->currentTime = convertToHoursMins($currentTravelTime / 60);
$sendObj->data->optimizedTime = convertToHoursMins($optTravelTime / 60);
$sendObj->data->unloadEstimate = convertToHoursMins(count($currentLocationOrder) * 10);
$sendObj->data->summary = $optSummary;
$sendObj->data->routeOrder = array();
foreach ($optRouteOrder as $loc) {
	$sendObj->data->routeOrder[] = array('id' => $currentLocationOrder[$loc], 'name' => $loc);
}

// Click recording
$endMS = microtime(true) * 1000.0;
if ((array_keys($currentLocationOrder) === $optRouteOrder)) {
	$vm1 = 's';
	$vm2 = '';
} else {
	if ($currentTravelTime > $optTravelTime) {
		$vm1 = 'o';
		$vm2 = convertToHoursMins(($currentTravelTime - $optTravelTime) / 60);
	} else {
		$vm1 = 'w';
		$vm2 = '-' . convertToHoursMins(($optTravelTime - $currentTravelTime) / 60);
	}
}
$vm3 = convertToHoursMins($currentTravelTime / 60);
$optStr = (array_keys($currentLocationOrder) === $optRouteOrder) ? '0' : '-1';
DART::click(2, $dartsessionid, 0, $userid, 0, $vm1, $vm2, $vm3, '', '', ($endMS - $startMS));

sendResult();
