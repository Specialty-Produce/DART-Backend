<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<updateinvoices status="failed" errmsg="XXX">
</updateinvoices>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging ( $currentScript, "jsondata=" . $appJSON );

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

$jd = json_decode ( $appJSON );
// userid
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No userid', $badXML );
	echo $badXML;
	exit ();
}

// invJSON
$invJSON = $jd->invoicesjson;
if ($invJSON == FALSE || is_null ( $invJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No saleids JSON', $badXML );
	echo $badXML;
	exit ();
}
$saleIDs = json_decode ( $invJSON );

// Check for invoices
if (count ( $saleIDs ) == 0) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No saleids', $badXML );
	echo $badXML;
	dartLogging ( $currentScript, "no sale ids" );
	exit ();
}

// Get the invoices marked as "being delivered", which is code 1 for this stored procedure
$updateCode = 1;
$signerID = 0;  // Only matters for when we are running deliverycomplete.php
$invXML = '';
$result = false;
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// Prep for the XML version of invoice list for the stored procedure
	$invXML = "<ROOT>\n";
	foreach ( $saleIDs as $id )
		$invXML .= '<Rec rID = "' . $id . '"/>' . "\n";
	$invXML .= "</ROOT>\n";
	// dartLogging ( $currentScript, "invXML=" . $invXML );
	$result = $dbh->exec ( "uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'" );
	
	// Get the invoice data
	$stmt = $dbh->query ( "uspDARTCheckLastUpdate '" . $invXML . "'" );
	$invInfo = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($result === false) {
	$errMsg = "uspDARTDelivered $updateCode, $invXML returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<updateinvoices status="success">' . "\n";
$resultStr .= '<invoices_invoice_list>' . "\n";
foreach ( $invInfo as $item ) {
	$lastUpdate = preg_replace ( '/(.*)\.\d{3}$/', '$1', $item ['dtDartLastUpdated'] );
	$resultStr .= '<invoice saleid="' . $item['iSaleID'] . '" locid="' . $item['iLocationDestinationID'] . '" lastupdate="' . $lastUpdate . '" />' . "\n";
}
$resultStr .= '</invoices_invoice_list>' . "\n";
$resultStr .= '</updateinvoices>' . "\n";
echo $resultStr;
exit ();
?>