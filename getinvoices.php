<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// User id
if (isset($_GET['uid']) && isset($_GET['sids'])) {
	$userid = trim(filter_input(INPUT_GET, 'uid', FILTER_VALIDATE_INT));
	$sIDs = trim(filter_input(INPUT_GET, 'sids'));
	$saleIDs = explode(',', $sIDs);
} else {
	// Get the POST data
	$appJSON = $_POST['jsondata'];
	dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);

	// appJSON
	if ($appJSON == FALSE || is_null($appJSON)) {
		sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
		dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
		exit();
	}

	$jd = json_decode($appJSON);
	// userid
	$userid = filter_var($jd->userid, FILTER_SANITIZE_NUMBER_INT);
	if ($userid == FALSE || is_null($userid)) {
		sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in jsondata POST variable');
		dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
		exit();
	}

	// invJSON
	$invJSON = $jd->invoicesjson;
	if ($invJSON == FALSE || is_null($invJSON)) {
		sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No sale ids supplied', 'No invoicesjson supplied in jsondata POST variable');
		dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
		exit();
	}
	$saleIDs = json_decode($invJSON);
}

// Check for invoices
$numSaleIDs = count($saleIDs);
if ($numSaleIDs == 0) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No sale ids supplied', 'Empty invoicesjson array in jsondata POST variable');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if ($userid == DEBUG_USERID) {
			// Determine the Sync state
			$sf = fopen("driverstate.txt", "r");
			$state = fread($sf, 1);
			fclose($sf);
			// Prep for the IN clause of the SQL
			$saleStr = $saleIDs[0];
			foreach ($saleIDs as $id)
				$saleStr .= ",$id";
			// Get the route data
			$debugRouteTable = ($state == "0") ? "tblDartDataDriverRoute" : "tblDartDataDriverRouteRsync";
			$sql = "select * from $debugRouteTable where iSaleID in ($saleStr) order by iLocationDestinationID";
			$stmt = $dbh->query($sql);
			$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
			// Get the invoice data
			$debugInvoiceTable = ($state == "0") ? "tblDartDataInvoices" : "tblDartDataInvoicesRsync";
			$sql = "select * from $debugInvoiceTable where iSaleID in ($saleStr) order by iLocationDestinationID, iSaleID, sDescription, iProductID";
			$stmt = $dbh->query($sql);
			$invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
		} else {
			// Get the route data
			$sql = "uspDARTGetDriverRoute $userid";
			$stmt = $dbh->query($sql);
			$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
			// Prep for the XML version of invoice list for uspDARTGetInvoices
			$invXML = "<ROOT>\n";
			foreach ($saleIDs as $id)
				$invXML .= '<Rec rID = "' . $id . '"/>' . "\n";
			$invXML .= "</ROOT>\n";
			// Get the invoice data
			$sql = "uspDARTGetInvoicesDev2 '" . $invXML . "'";
			$stmt = $dbh->query($sql);
			$invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
		}
		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
			$sqlParts = explode(' ', $sql);
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount++;
				$sqlFailed = true;
				sleep(DART_SQL_TIMEOUT_SLEEP);
			} else {
				sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		} else {
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
			sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			exit();
		}
	}
}

// Parse the route data to generate the arrays for the different parts of the XML
$invoiceList = array();
foreach ($routeInfo as $entry) {
	if (in_array($entry['iSaleID'], $saleIDs)) {
		$lastUpdate = preg_replace('/(.*)\.\d{3}$/', '$1', $entry['dtDartLastUpdated']);
		$packerLocation = (is_null($entry['sPackerLocation'])) ? '' : mb_convert_encoding($entry['sPackerLocation'], "UTF-8", "Windows-1252");
		$invoiceList[$entry['iSaleID']] = array(
			'saleID' => intval($entry['iSaleID']),
			'locID' => intval($entry['iLocationDestinationID']),
			'date' => date('m/d/Y'),
			'lastupdate' => $lastUpdate,
			'notes' => mb_convert_encoding($entry['txtInvoiceNotes'], "UTF-8", "Windows-1252"),
			'po' => mb_convert_encoding($entry['sPo'], "UTF-8", "Windows-1252"),
			'terms' => mb_convert_encoding($entry['sTerms'], "UTF-8", "Windows-1252"),
			'packerlocation' => $packerLocation
		);
		$invoiceList[$entry['iSaleID']]['items'] = array();
	}
}

// Parse the invoice data to generate the arrays for the different parts of the XML
$priceList = array();
foreach ($invInfo as $item) {
	// Add the line pricing info
	if (!isset($priceList[$item['iSaleDetailID']]))
		$priceList[$item['iSaleDetailID']] = array();
	$priceList[$item['iSaleDetailID']][$item['iUnitID']] = array(
		'desc' => mb_convert_encoding($item['UnitDescription'], "UTF-8", "Windows-1252"),
		'cost' => floatval($item['mUnitPrice']),
		'crvID' => floatval($item['iCRVProductID']),
		'crvPrice' => floatval($item['mCRVPrice'])
	);
	$salesTaxRate = floatval(sprintf("%0.4f", 100 * $item['fTaxRate']));
	$salestaxAmount = floatval(sprintf("%0.2f", $item['mTax']));
	// Only add to the invoice the actual unitID set items
	if ($item['iInvoiceDefault'] == 1)
		$invoiceList[$item['iSaleID']]['items'][] = array(
			'lineid' => intval($item['iSaleDetailID']),
			'prodid' => intval($item['iProductID']),
			'proddesc' => trim(mb_convert_encoding($item['sDescription'], "UTF-8", "Windows-1252")),
			'unitid' => intval($item['iUnitID']),
			'qorder' => floatval($item['fOrderQuantity']),
			'qship' => floatval($item['fShipQuantity']),
			'status' => intval($item['iShort']),
			'itemspec' => mb_convert_encoding($item['sItemNotes'], "UTF-8", "Windows-1252"),
			'salestaxrate' => floatval($salesTaxRate),
			'salestaxamount' => floatval($salestaxAmount),
			'greendiscount' => floatval(sprintf("%0.2f", 100.0 * ($item['fDiscountOnline'] + $item['fDiscountOnTime'])))
		);
}

$sendObj->data = array();
foreach ($invoiceList as $inv) {
	// Generate the line items
	$lineItems = array();
	$sortCount = 1;
	foreach ($inv['items'] as $item) {
		// Line item
		$lineItems[] = array(
			'lineid' => intval($item['lineid']),
			'sort' => $sortCount,
			'prodid' => intval($item['prodid']),
			'proddesc' => $item['proddesc'],
			'unitid' => intval($item['unitid']),
			'qorder' => floatval($item['qorder']),
			'qship' => floatval($item['qship']),
			'status' => $item['status'],
			'itemspec' => $item['itemspec'],
			'salestaxrate' => floatval($item['salestaxrate']),
			'salestaxamount' => floatval($item['salestaxamount']),
			'greendiscount' => floatval($item['greendiscount']),
			'pricing' => array()
		);
		// Pricing
		if (isset($priceList[$item['lineid']])) {
			foreach ($priceList[$item['lineid']] as $unitID => $entry) {
				$lineItems[count($lineItems) - 1]['pricing'][] = [
					'id' => intval($unitID),
					'desc' => $entry['desc'],
					'cost' => floatval(sprintf('%0.2f', $entry['cost'])),
					'crvID' => intval($entry['crvID']),
					'crvPrice' => floatval(sprintf('%0.2f', $entry['crvPrice']))
				];
			}
		}
		$sortCount++;
	}
	// Packer Location
	if (!isset($inv['packerlocation'])) {
		SP_ErrorLogging('invoiceXML : packerlocation NOT set : ' . $inv['saleID'], false, DART_ERROR_LOG);
	}
	$packerLocation = $inv['packerlocation'] ?? '';
	// Build send object
	$sendObj->data[] = array(
		'saleid' => intval($inv['saleID']),
		'locid' => intval($inv['locID']),
		'lastupdate' => $inv['lastupdate'],
		'date' => $inv['date'],
		'notes' => $inv['notes'],
		'ponumber' => $inv['po'],
		'terms' => $inv['terms'],
		'packerlocation' => $packerLocation,
		'items' => $lineItems
	);
}
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
