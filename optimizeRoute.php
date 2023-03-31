<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_RoutingGTM.php';
include_once 'classes_SP/class_DART.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<optimize_route_list status="failed" code="0" retry="false" errmsg="XXX">
</optimize_route_list>
EOT;

$debug = false;
// if (isset($_GET['a'])) {
// 	$debug = true;
// 	$userid = 12054;
// 	$optJSON = '{"origin":{"lat":32.74364827570099,"lon":-117.1869407149535},"locations":[{"asap":0,"id":8261,"lat":32.87977600097656,"name":"Zanzibar at the Loft","lon":-117.2355575561523},{"asap":0,"id":1688,"lat":32.88981628417969,"name":"Cliffhanger Cafe Menu","lon":-117.2508087158203},{"asap":0,"id":564,"lat":32.90445709228516,"name":"Lodge at Torrey Pines Main","lon":-117.2445373535156},{"asap":0,"id":6746,"lat":32.8717155456543,"name":"Raised By Wolves","lon":-117.2136383056641},{"asap":0,"id":8324,"lat":32.87705612182617,"name":"Uncle Italian","lon":-117.2396240234375},{"asap":1,"id":5323,"lat":32.87334442138672,"name":"Regents Pizzeria","lon":-117.2179107666016},{"asap":0,"id":8358,"lat":32.89577484130859,"name":"Park Commons - ARE","lon":-117.2011108398438},{"asap":0,"id":7029,"lat":32.90058898925781,"name":"Gravity Heights Restaurant and Brewery","lon":-117.1909408569336}]}';
// 	// $optJSON = '{"origin":{"lat":32.744026,"lon":-117.187627},"locations":[{"name":"Handlery Hotel & Resort","id":973,"lat":32.760414,"lon":-117.172455,"asap":true},{"name":"Kensington Cafe","id":3118,"lat":32.763236,"lon":-117.106311,"asap":false},{"name":"Carnitas Snack Shack North Park","id":3235,"lat":32.748516,"lon":-117.135489,"asap":false},{"name":"Soda & Swine Liberty Station (Bar)","id":5160,"lat":32.737547,"lon":-117.211503,"asap":false},{"name":"Brigantine Imperial Beach","id":6510,"lat":32.5795631,"lon":-117.1317297,"asap":false}]}';
// }

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
	echo $badXML;
	error_log("$currentScript : exit...");
	exit();
}

// Dart Session ID
$dartsessionid = filter_input(INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT);
if ($dartsessionid == FALSE || is_null($dartsessionid)) {
	$dartsessionid = 1;
}

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['optData'])) {
	$optJSON = $_POST['optData'];
	dartLogging($currentScript, "userid = $userid : dartsessionid = $dartsessionid : jsondata=" . $optJSON, $codeStr);
} else {
	if (!$debug)
		$optJSON = false;
}

// appJSON
if ($optJSON == FALSE || is_null($optJSON)) {
	dartLogging($currentScript, "    optData is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : No optData supplied', $badXML);
	echo $badXML;
	exit();
}

// Good to go...
$jd = json_decode($optJSON);
if ($jd == FALSE || is_null($jd)) {
	dartLogging($currentScript, "    decoded JSON optData is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid JSON optData supplied', $badXML);
	echo $badXML;
	SP_ErrorLogging("Decoded JSON optData is invalid for codeStr = $codeStr.", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON optData");
	exit();
}

if ($jd->origin->lat == 0 || $jd->origin->lon == 0) {
	dartLogging($currentScript, "    Lat || Lon == 0 : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
	$badXML = preg_replace('/XXX/', 'Call to optimizeRoute failed, reason: GPS disabled on iPad.  Please enable location services for DART.  If problem persists bring iPad to Alan or Christopher in IT.', $badXML);
	echo $badXML;
	SP_ErrorLogging("Lat || Lon == 0 for codeStr = $codeStr.", true, DART_ERROR_LOG, "DART - $currentScript - Lat || Lon == 0");
	exit();
}

if (count($jd->locations) > 23) {
	dartLogging($currentScript, "    Too many waypoints : " . count($jd->locations) . " : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
	$badXML = preg_replace('/XXX/', 'Call to optimizeRoute failed, reason: Too many delivery stops in the list, 23 is max.', $badXML);
	echo $badXML;
	SP_ErrorLogging("Too many waypoints : " . count($jd->locations) . " : for codeStr = $codeStr.", true, DART_ERROR_LOG, "DART - $currentScript - Too many waypoints");
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

if ($debug) {
	error_log("$currentScript : array_keys(\$currentLocationOrder) : " . print_r(array_keys($currentLocationOrder), true));
	$foo = $routeGTM->getGoogleRouteTime(array_keys($currentLocationOrder), true, true, false);
	error_log("$currentScript : \$routeGTM->getGoogleRouteTime(array_keys(\$currentLocationOrder), true, true, false) : foo : " . print_r($foo, true));
}
try {
	list($currentTravelTime, $currentTravelSummary, $optRouteError) = $routeGTM->getGoogleRouteTime(array_keys($currentLocationOrder), true, true, false);
	list($currentRouteText, $curentRouteURL) = $routeGTM->getRouteTextURL(array_keys($currentLocationOrder));
} catch (SP_Exception $spe) {
	$errMsg = $spe->getMessage();
	dartLogging($currentScript, "SP_Exception : $errMsg", $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . " : $errMsg", $badXML);
	echo $badXML;
	exit();
}

if ($debug) {
	echo "current Travel = " . convertToHoursMins($currentTravelTime / 60) . "<br/>";
	echo "current Summary = $currentTravelSummary<br/>";
	echo "<pre>" . $currentRouteText . "</pre>";
	echo $curentRouteURL;
	echo "<br/><br/>";
}

// Optimize
list($optTravelTime, $optSummary, $optRouteText, $optRouteURL, $optRouteOrder, $optRouteError) = $routeGTM->getGoogleOptimizedRoute();

if ($debug) {
	echo "opt Travel = " . convertToHoursMins($optTravelTime / 60) . "<br/>";
	echo "opt Summary = $optSummary<br/>";
	echo "<pre>" . $optRouteText . "</pre>";
	echo $optRouteURL;
	echo "<br/><br/>Routes are ";
	if (array_keys($currentLocationOrder) === $optRouteOrder)
		echo "EQUAL";
	else
		echo "NOT equal";
	echo "<br/><br/>\n\n";
}
// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<optimize_route_list status="success"';
$resultStr .= ' orderChanged="' . ((array_keys($currentLocationOrder) === $optRouteOrder) ? 'false' : 'true') . '"';
$resultStr .= ' currentTime="' . convertToHoursMins($currentTravelTime / 60) . '"';
$resultStr .= ' optimizedTime="' . convertToHoursMins($optTravelTime / 60) . '"';
$resultStr .= ' unloadEstimate="' . convertToHoursMins(count($currentLocationOrder) * 10) . '"';
$resultStr .= ' summary="' . $optSummary . '">' . "\n";
foreach ($optRouteOrder as $loc) {
	$resultStr .= '<entry id="' . $currentLocationOrder[$loc] . '">' . $loc . '</entry>' . "\n";
}
$resultStr .= '</optimize_route_list>';
if ($debug) {
	echo "<hr/><h3>\$resultStr</h3><pre>" . htmlentities($resultStr) . '</pre>';
} else {
	echo $resultStr;
}

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

exit();
