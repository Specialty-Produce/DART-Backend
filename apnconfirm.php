<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<apnconfirm status="failed" retry="true" errmsg="XXX">
</apnconfirm>
EOT;

$goodXML = <<< EOT
<?xml version="1.0"?>
<apnconfirm status="success">
</apnconfirm>
EOT;

// Log the POST data
$postData = (isset ( $_POST )) ? serialize ( $_POST ) : 'none';
dartLogging ( $currentScript, "POST data=" . $postData );

// Content ID
$contentID = filter_input ( INPUT_POST, 'content-id', FILTER_SANITIZE_NUMBER_INT );
if ($contentID == FALSE || is_null ( $contentID )) {
	$contentID = 0;
}

// Invoice ID
$invoiceID = filter_input ( INPUT_POST, 'iid', FILTER_SANITIZE_NUMBER_INT );
if ($invoiceID == FALSE || is_null ( $invoiceID )) {
	$invoiceID = 0;
}

$debugInvoices = array (
		4514248,
		4514195 
);
if (in_array ( $invoiceID, $debugInvoices )) {
	echo $goodXML;
	exit ();
}

if ($contentID == 0 && $invoiceID == 0) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : ContentID and IID cannot both be 0', $badXML );
	$badXML = preg_replace ( '/true/', $currentScript . 'false', $badXML );
	echo $badXML;
	exit ();
}

// Update the status
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$sql = "UPDATE tblDARTAPNASAP SET dtConfirmed=:dateSent WHERE iAutoID=$contentID";
	$stmt = $dbh->prepare ( $sql );
	$thisDate = date ( "Y-m-d H:i:s", time () ) . ".000";
	$stmt->bindParam ( ':dateSent', $thisDate );
	$stmt->execute ();
	unset ( $stmt );
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, '', 'Dart GPS ASAP' );
	exit ();
}

echo $goodXML;
exit ();
?>