<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<transferdriver status="failed" code="0" retry="true" errmsg="XXX">
</transferdriver>
EOT;

// Log the data
$postData = (isset ( $_POST )) ? serialize ( $_POST ) : 'none';
dartLogging ( $currentScript, "postdata=" . $postData );

// User ID
$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
	echo $badXML;
	exit ();
}

// SaleID
$saleid = filter_input ( INPUT_POST, 'saleid', FILTER_SANITIZE_NUMBER_INT );
if ($saleid == FALSE || is_null ( $saleid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid Sale ID', $badXML);
	echo $badXML;
	exit ();
}

$successXML = <<< EOT
<?xml version="1.0"?>
<transferdriver status="success">
</transferdriver>
EOT;

// Done if the DEBUG user
if ($userid == DEBUG_USERID) {
	echo $successXML;
	exit ();
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

	// There is no difference DEBUG_USER and live driver.
	$result = $dbh->exec ( "uspDARTTransferDriver $saleid, $userid" );

	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}

if ($result === false) {
	$errMsg = "uspDartCancelBySP $userid, $saleid returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Generate the XML
echo $successXML;
exit ();
?>