<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<driverinvoices_invoice_list status="failed" code="0" retry="false" errmsg="XXX">
</driverinvoices_invoice_list>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
	echo $badXML;
	exit();
}

dartLogging($currentScript, "userid=" . $userid, $codeStr);

try {
	$dbh = new PDO('spdb', '', '');
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// Get the driver route
	if ($userid == DEBUG_USERID) {
		// Determine the Sync state
		$sf = fopen("driverstate.txt", "r");
		$state = fread($sf, 1);
		fclose($sf);
		// Get the route data
		$debugRouteTable = ($state == "0") ? "tblDartDataDriverRoute" : "tblDartDataDriverRouteRsync";
		$stmt = $dbh->query("select * from $debugRouteTable order by iLocationDestinationID");
		$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
		$stmt->closeCursor();
		// Get the invoice data
		$debugInvoiceTable = ($state == "0") ? "tblDartDataInvoices" : "tblDartDataInvoicesRsync";
		$stmt = $dbh->query("select * from $debugInvoiceTable order by iLocationDestinationID, iSaleID, sDescription, iProductID");
		$invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
		$stmt->closeCursor();
	} else {
		// Get the route data
		$stmt = $dbh->query("uspDARTGetDriverRoute $userid");
		$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
		$stmt->closeCursor();
		// Get the invoice data
		$stmt = $dbh->query("uspDARTGetDriverInvoices $userid");
		$invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
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

// Parse the route data to generate the arrays for the different parts of the XML
$invoiceList = array();
foreach ($routeInfo as $entry) {
	$lastUpdate = preg_replace('/(.*)\.\d{3}$/', '$1', $entry['dtDartLastUpdated']);
	$packerLocation = (is_null($entry['sPackerLocation'])) ? '' : mb_convert_encoding($entry['sPackerLocation'], "UTF-8", "Windows-1252");
	$invoiceList[$entry['iSaleID']] = array(
		'saleID' => $entry['iSaleID'],
		'locID' => $entry['iLocationDestinationID'],
		'date' => strftime("%m/%d/%Y"),
		'lastupdate' => $lastUpdate,
		'notes' => DART_escapeXmlString(mb_convert_encoding($entry['txtInvoiceNotes'], "UTF-8", "Windows-1252")),
		'po' => mb_convert_encoding($entry['sPO'], "UTF-8", "Windows-1252"),
		'terms' => mb_convert_encoding($entry['sTerms'], "UTF-8", "Windows-1252"),
		'packerlocation' => $packerLocation
	);
	$invoiceList[$entry['iSaleID']]['items'] = array();
}

// Parse the invoice data to generate the arrays for the different parts of the XML
$priceList = array();
foreach ($invInfo as $item) {
	// Add the line pricing info
	if (!isset($priceList[$item['iSaleDetailID']]))
		$priceList[$item['iSaleDetailID']] = array();
	$priceList[$item['iSaleDetailID']][$item['iUnitID']] = array(
		'desc' => mb_convert_encoding($item['UnitDescription'], "UTF-8", "Windows-1252"),
		'cost' => $item['mUnitPrice'],
		'crvID' => $item['iCRVProductID'],
		'crvPrice' => $item['mCRVPrice']
	);
	// Only add to the invoice the actual unitID set items
	if ($item['iInvoiceDefault'] == 1)
		$invoiceList[$item['iSaleID']]['items'][] = array(
			'lineid' => $item['iSaleDetailID'],
			'prodid' => $item['iProductID'],
			'proddesc' => mb_convert_encoding($item['sDescription'], "UTF-8", "Windows-1252"),
			'unitid' => $item['iUnitID'],
			'qorder' => $item['fOrderQuantity'],
			'qship' => $item['fShipQuantity'],
			'status' => $item['iShort'],
			'itemspec' => DART_escapeXmlString(mb_convert_encoding($item['sItemNotes'], "UTF-8", "Windows-1252")),
			'greendiscount' => sprintf("%0.2f", 100.0 * ($item['fDiscountOnline'] + $item['fDiscountOnTime']))
		);
}
// add greendiscount from getinvoices.php
/*
 * echo "<pre>\n"; echo "ROUTEINFO\n"; print_r($routeInfo); echo "\n-----------------------------------------------------------------\n"; echo "INVINFO\n"; print_r($invInfo); echo "\n-----------------------------------------------------------------\n"; echo "PRICELIST\n"; print_r($priceList); echo "\n-----------------------------------------------------------------\n"; echo "INVOICELIST\n"; print_r($invoiceList); echo "</pre>\n";
 */

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<driverinvoices_invoice_list status="success">' . "\n";
include 'include/invoiceXML.php';
$resultStr .= "</driverinvoices_invoice_list>";
echo $resultStr;
dartLogging($currentScript, "  Success", $codeStr);
exit();
