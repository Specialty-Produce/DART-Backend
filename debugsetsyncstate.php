<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<debugsetsync status="failed" errmsg="XXX">
</debugsetsync>
EOT;

// Sync State, 0 = use "original" tables, 1 = use "rsync" tables
$sync = filter_input ( INPUT_POST, 'sync', FILTER_SANITIZE_NUMBER_INT );
if ($sync == FALSE || is_null ( $sync )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Missing sync value', $badXML );
	echo $badXML;
	exit ();
}
if ($sync < 0 || $sync > 1) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid sync value, must be 0 or 1', $badXML );
	echo $badXML;
	exit ();
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	// Complete the assignment of invoices to the driver and get the sessionid
	$result = $dbh->exec ( "UPDATE tblDARTDebugSync SET iRsyncState=$sync WHERE iAutoID=1" );
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

$resultXML = <<< EOT
<?xml version="1.0"?>
<debugsetsync status="success">
</debugsetsync>
EOT;
echo $resultXML;
exit ();
?>