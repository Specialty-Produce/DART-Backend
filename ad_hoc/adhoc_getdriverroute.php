<?php
include_once 'global_CDC.php';
include '../dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<driverroute_location_list status="failed" code="0" retry="true" errmsg="XXX">
</driverroute_location_list>
EOT;

echo "No active...";
exit();

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );



// User ID
$userid = 82135;
dartLogging ( $currentScript, "userID=" . $userid, $codeStr );

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	// Get the driver route
	if ($userid == DEBUG_USERID) {
		// Get a sessionID
		$stmt = $dbh->query ( "uspDARTInvoicesAssignDebug" );
		$result = $stmt->fetch ( PDO::FETCH_ASSOC );
		$dartSessionID = $result ['iDartSessionID'];
		$stmt->closeCursor ();
		// Determine the Sync state
		$sf = fopen ( "driverstate.txt", "r" );
		$state = fread ( $sf, 1 );
		fclose ( $sf );
		// Get the route data
		$debugTable = ($state == "0") ? "tblDartDataDriverRoute" : "tblDartDataDriverRouteRsync";
		$stmt = $dbh->query ( "select * from $debugTable order by iLocationDestinationID" );
		$routeInfo = $stmt->fetchAll ( PDO::FETCH_BOTH );
		$stmt->closeCursor ();
		// Get the signers
		$stmt = $dbh->query ( "select * from tblDartDataSigners" );
		$signerInfo = $stmt->fetchAll ( PDO::FETCH_BOTH );
		$stmt->closeCursor ();
	} else {
		// Complete the assignment of invoices to the driver and get the sessionid
		$assignResult = $dbh->exec ( "uspDARTInvoicesAssign $userid" );
		
		// Get the invoices assigned to the driver
		$stmt = $dbh->query ( "uspDARTGetDriverRoute $userid" );
		$routeInfo = $stmt->fetchAll ( PDO::FETCH_BOTH );
		$stmt->closeCursor ();
		
		// Get the signers
		$stmt = $dbh->query ( "uspDARTGetDriverRouteSigners $userid" );
		$signerInfo = $stmt->fetchAll ( PDO::FETCH_BOTH );
		$stmt->closeCursor ();
	}
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Parse the route data to generate the arrays for the different parts of the XML
// mb_convert_encoding ( xxx, "UTF-8", "Windows-1252" )
$location = array ();
$locationList = array ();
$currentLocation = - 1;
foreach ( $routeInfo as $entry ) {
	$locID = $entry ['iLocationDestinationID'];
	$lastUpdate = preg_replace ( '/(.*)\.\d{3}$/', '$1', $entry ['dtDartLastUpdated'] );
	if (! in_array ( $locID, $locationList )) {
		$locationList [] = $locID;
		// It is a new location to enter into the array
		// mb_convert_encoding ( $result['tSeasonal'], "UTF-8", "Windows-1252" )
		$location [$locID] = array ('id' => $locID, 'sort' => 1, 'lastupdate' => $lastUpdate, 'name' => mb_convert_encoding ( $entry ['sDescription'], "UTF-8", "Windows-1252" ), 'street' => mb_convert_encoding ( $entry ['sAddress1'], "UTF-8", "Windows-1252" ),
				'city' => mb_convert_encoding ( $entry ['sCity'], "UTF-8", "Windows-1252" ), 'state' => mb_convert_encoding ( $entry ['sState'], "UTF-8", "Windows-1252" ), 'zip' => mb_convert_encoding ( $entry ['sPostalCode'], "UTF-8", "Windows-1252" ), 'gpslat' => trim($entry ['sGpsLat']),
				'gpslon' => trim($entry ['sGpsLon']), 'phone' => mb_convert_encoding ( $entry ['sPhone'], "UTF-8", "Windows-1252" ), 'deliverytime' => $entry ['sTime'], 'requirespaper' => $entry ['iInvoiceException'], 'allowdarkdrop' => ($entry ['iNDS'] == 0) ? 'true' : 'false',
				'locnotes' => mb_convert_encoding ( $entry ['txtLocationNotes'], "UTF-8", "Windows-1252" ), 'salesname' => mb_convert_encoding ( $entry ['txtSalesPerson'], "UTF-8", "Windows-1252" ), 'salesemail' => mb_convert_encoding ( $entry ['txtEmail'], "UTF-8", "Windows-1252" ),
				'salesphone' => mb_convert_encoding ( $entry ['txtCellPhone'], "UTF-8", "Windows-1252" ), 'acctnote' => mb_convert_encoding ( $entry ['sAccountingNote'], "UTF-8", "Windows-1252" ), 'terms' => mb_convert_encoding ( $entry ['sTerms'], "UTF-8", "Windows-1252" ), 'invoices' => array (),
				'signers' => array ());
		// Add the invoice and set the update time
		$location [$locID] ['invoices'] [] = array ('lastupdate' => $lastUpdate, 'number' => $entry ['iSaleID'], 'currentstate' => (is_null ( $entry ['iDartStatusID'] )) ? 0 : $entry ['iDartStatusID'], 'invnotes' => mb_convert_encoding ( $entry ['txtInvoiceNotes'], "UTF-8", "Windows-1252" ));
	} else {
		// We have already seen this location, so add the invoice and change the lastupdate time if needed
		$location [$locID] ['invoices'] [] = array ('lastupdate' => $lastUpdate, 'number' => $entry ['iSaleID'], 'currentstate' => (is_null ( $entry ['iDartStatusID'] )) ? 0 : $entry ['iDartStatusID'], 'invnotes' => mb_convert_encoding ( $entry ['txtInvoiceNotes'], "UTF-8", "Windows-1252" ));
		if (strtotime ( $lastUpdate ) > strtotime ( $location [$locID] ['lastupdate'] ))
			$location [$locID] ['lastupdate'] = $lastUpdate;
	}
}

// Add the signer info for each location
foreach ( $signerInfo as $entry ) {
	$location [$entry ['iLocationDestinationID']] ['signers'] [] = array ('userID' => $entry ['iUserID'], 'fname' => mb_convert_encoding ( $entry ['txtFirstName'], "UTF-8", "Windows-1252" ), 'lname' => mb_convert_encoding ( $entry ['txtLastName'], "UTF-8", "Windows-1252" ),
			'cell' => preg_replace ( '/[^0-9]/', '', $entry ['txtCellPhone'] ), 'email' => $entry ['txtEmail']);
}

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<driverroute_location_list status="success">' . "\n";
$sortCount = 1;
foreach ( $locationList as $locID ) {
	$requiresPaper = ($location [$locID] ['requirespaper'] == - 1) ? "true" : "false";
	$resultStr .= '<location id="' . $locID . '" sort="' . $sortCount . '" lastupdate="' . $location [$locID] ['lastupdate'] . '" requirespaper="' . $requiresPaper . '" allowdarkdrop="' . $location [$locID] ['allowdarkdrop'] . '">' . "\n";
	$sortCount ++;
	$resultStr .= "<name>" . $location [$locID] ['name'] . "</name>\n";
	$resultStr .= "<street>" . $location [$locID] ['street'] . "</street>\n";
	$resultStr .= "<city>" . $location [$locID] ['city'] . "</city>\n";
	$resultStr .= "<state>" . $location [$locID] ['state'] . "</state>\n";
	$resultStr .= "<zip>" . $location [$locID] ['zip'] . "</zip>\n";
	if (($location [$locID] ['gpslat'] == FALSE || is_null ( $location [$locID] ['gpslat'] ) || $location [$locID] ['gpslat'] == 0) || ($location [$locID] ['gpslon'] == FALSE || is_null ( $location [$locID] ['gpslon'] ) || $location [$locID] ['gpslon'] == 0)) {
		// (32.77515136946222, -117.29759216308594)
		$resultStr .= "<gpslat>32.7751513</gpslat>\n";
		$resultStr .= "<gpslon>-117.2975921</gpslon>\n";
	} else {
		$resultStr .= "<gpslat>" . $location [$locID] ['gpslat'] . "</gpslat>\n";
		$resultStr .= "<gpslon>" . $location [$locID] ['gpslon'] . "</gpslon>\n";
	}
	$resultStr .= "<phone>" . $location [$locID] ['phone'] . "</phone>\n";
	$resultStr .= "<deliverytime>" . $location [$locID] ['deliverytime'] . "</deliverytime>\n";
	$resultStr .= "<notes>" . $location [$locID] ['locnotes'] . "</notes>\n";
	$resultStr .= "<salesname>" . $location [$locID] ['salesname'] . "</salesname>\n";
	$resultStr .= "<salesemail>" . $location [$locID] ['salesemail'] . "</salesemail>\n";
	$resultStr .= "<salesphone>" . $location [$locID] ['salesphone'] . "</salesphone>\n";
	$resultStr .= "<accountingnote>" . $location [$locID] ['acctnote'] . "</accountingnote>\n";
	$resultStr .= "<accountingterms>" . $location [$locID] ['terms'] . "</accountingterms>\n";
	$resultStr .= "<invoices_invoice_list>\n";
	foreach ( $location [$locID] ['invoices'] as $entry ) {
		$resultStr .= '<invoice lastupdate="' . $entry ['lastupdate'] . '" currentstate="' . $entry ['currentstate'] . '">' . $entry ['number'] . "</invoice>\n";
	}
	$resultStr .= "</invoices_invoice_list>\n";
	$resultStr .= "<signers_entry_list>\n";
	if ($requiresPaper == "false") {
		foreach ( $location [$locID] ['signers'] as $entry ) {
			$resultStr .= '<entry id="' . $entry ['userID'] . '" cell="' . $entry ['cell'] . '" email="' . $entry ['email'] . '" first="' . $entry ['fname'] . '" last="' . $entry ['lname'] . '">' . $entry ['fname'] . " " . $entry ['lname'] . "</entry>\n";
		}
	}
	// The signer to use if there is a paper invoice present.
	// With version 1.0 build 37 this is now hard-coded in the app.
	// $resultStr .= '<entry id="13358">' . "Printed Invoice</entry>\n";
	$resultStr .= "</signers_entry_list>\n";
	$resultStr .= "</location>\n";
}
$resultStr .= "</driverroute_location_list>";
echo $resultStr;
dartLogging ( $currentScript, "  Success", $codeStr );
exit ();
?>
