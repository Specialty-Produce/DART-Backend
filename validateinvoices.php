<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<validateinvoices status="failed" code="0" retry="false" errmsg="XXX">
</validateinvoices>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON);

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No jsondata supplied', $badXML);
	echo $badXML;
	exit ();
}

$jd = json_decode ( $appJSON );
// userid
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No userid', $badXML);
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
$numSaleIDs = count ( $saleIDs );
if ($numSaleIDs == 0) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No saleids', $badXML );
	echo $badXML;
	exit ();
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	// Get the driver route
	if ($userid == DEBUG_USERID) {
		// Do nothing
	} else {
		// Prep for the XML version of invoice list
		$invXML = "<ROOT>\n";
		foreach ( $saleIDs as $id )
			$invXML .= '<Rec rID = "' . $id . '"/>' . "\n";
		$invXML .= "</ROOT>\n";
		// Validate the invoices
		$result = $dbh->exec ( "uspDartValidateInvoices '" . $invXML . "'" );
	}
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<validateinvoices status="success">
</validateinvoices>
EOT;
echo $resultStr;
exit ();
?>