<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<updatedeliverysort status="failed" code="0" retry="true" errmsg="XXX">
</updatedeliverysort>
EOT;

// Success
$successXML = <<< EOT
<?xml version="1.0"?>
<updatedeliverysort status="success">
</updatedeliverysort>
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

// UserID
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if (($userid == FALSE || is_null ( $userid ))) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No userid', $badXML );
	echo $badXML;
	exit ();
}

// If Debug, just send success
if ($userid == DEBUG_USERID) {
	echo $successXML;
	exit ();
}

// Dart Session ID
$dartSession = filter_var ( $jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT );
if (($dartSession == FALSE || is_null ( $dartSession ))) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No Dart session ID', $badXML );
	echo $badXML;
	exit ();
}
// Deliveries list
if ($jd->deliveryjson == FALSE || is_null ( $jd->deliveryjson )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No location list data', $badXML );
	echo $badXML;
	exit ();
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$sortVal = 1;
	$locXML = "<ROOT>\n";
	foreach ( $jd->deliveryjson as $entry ) {
		if ($entry->status <= 5) {
			$locXML .= '<Rec LID="' . $entry->locationid . '" iSortID="' . $sortVal . '"/>' . "\n";
			$sortVal ++;
		}
	}
	$locXML .= "</ROOT>";
	$result = $dbh->exec ( "uspDARTDelivery " . $userid . ", '" . $locXML . "'" );
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

echo $successXML;
exit ();
?>