<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<removefromdart status="failed" code="0" retry="true" errmsg="XXX">
</removefromdart>
EOT;

// userid
$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
$dartSession = filter_input ( INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT );
$saleid = filter_input ( INPUT_POST, 'saleid', FILTER_SANITIZE_NUMBER_INT );

// Logging
dartLogging($currentScript, "postvars : userid=$userid, dartsessionid=$dartSession, saleid=$saleid");

// UserID
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No userid', $badXML);
	echo $badXML;
	exit ();
}
// Dart Session ID
if ($dartSession == FALSE || is_null ( $dartSession )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Dart session ID', $badXML);
	echo $badXML;
	exit ();
}
// Invoice #
if ($dartSession == FALSE || is_null ( $dartSession )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No Sale ID', $badXML);
	echo $badXML;
	exit ();
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	if ($userid == DEBUG_USERID) {
		$resultMark = true;
	} else {
		$resultMark = $dbh->exec ( "uspDARTInvoiceMarkedDelivered $saleid" );
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($resultMark === false) {
	$errMsg = "uspDARTInvoiceMarkedDelivered $saleid returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	$badXML = preg_replace ( '/retry="true"/', 'retry="false"', $badXML );
	echo $badXML;
	exit ();
}

$resultXML = <<< EOT
<?xml version="1.0"?>
<removefromdart status="success">
</removefromdart>
EOT;
echo $resultXML;
exit();
?>