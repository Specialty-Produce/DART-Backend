<?php
include_once 'global_CDC.php';
include '../dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// User ID
$userid = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);
$userid = DEBUG_USERID;
if ($userid == FALSE || is_null($userid)) {
	echo "Invalid username.";
	exit();
}

//exit();

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// Get the driver route
	if ($userid == DEBUG_USERID) {
		$stmt = $dbh->query("select * from tblDartDataDriverRoute order by iLocationDestinationID");
		$routeInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo "<pre>" . print_r($routeInfo, true) . "</pre>";
		$dartSessionID = 10;
	} else {
		// Complete the assignment of invoices to the driver and get the sessionid
		$stmt = $dbh->query("uspDARTInvoicesAssign $userid");
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$dartSessionID = $result['iDartSessionID'];
		$stmt->closeCursor();

		// Get the invoices assigned to the driver
		$stmt = $dbh->query("uspDARTGetDriverRoute $userid");
		$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
		$stmt->closeCursor();
	}
	$dbh = null;
} catch (PDOException $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

echo "Valid username.";
exit();
