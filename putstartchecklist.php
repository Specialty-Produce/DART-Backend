<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putstartchecklist status="failed" code="0" retry="true" errmsg="XXX">
</putstartchecklist>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging ( $currentScript, "jsondata=" . $appJSON );

//$appJSON='{"userid":"637","dartsessionid":"1194","cljson":"{\"truck\":\"22:0:1\",\"odometer\":\"1\",\"test_items\":[\"gauges_fuel\"],\"comments\":\"Need%20oil%20chance.\"}"}';


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
// Dart Session ID
$dartSession = filter_var ( $jd->dartsessionid, FILTER_SANITIZE_NUMBER_INT );
if ($dartSession == FALSE || is_null ( $dartSession )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No Dart session ID', $badXML );
	echo $badXML;
	exit ();
}

// checklistJSON
$checklistJSON = $jd->cljson;
if ($checklistJSON == FALSE || is_null ( $checklistJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No checklist data', $badXML );
	echo $badXML;
	exit ();
}
$clInfo = json_decode ( $checklistJSON );

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$truckinfo = preg_split ( '/:/', $clInfo->truck );
	$sqlds = "uspDARTTruckDataStart $dartSession, $userid, " . $truckinfo [0] . ", " . $clInfo->odometer;
	$stmt = $dbh->query ( $sqlds );
	$dataresult = $stmt->fetch ( PDO::FETCH_ASSOC );
	$stmt->closeCursor ();
	
	$itemCount = 0;
	$clXML = "<ROOT>";
	foreach ( $clInfo->test_items as $item ) {
		$clXML .= "\n" . '<Rec sDes = "' . $item . '"/>';
		$itemCount ++;
	}
	if ($clInfo->comments != '') {
		$clXML .= "\n" . '<Rec sDes = "Comment : ' . rawurldecode ( $clInfo->comments ) . '"/>';
		$itemCount ++;
	}
	$clXML .= "\n</ROOT>";
	$clresult = 0;
	$sqlcl = '';
	if ($itemCount > 0) {
		$sqlcl = "uspDARTCheckListTruck '" . $clXML . "', $dartSession";
		$stmt = $dbh->query ( $sqlcl );
		$result = $stmt->fetchAll ( PDO::FETCH_ASSOC );
		$stmt->closeCursor ();
		$clresult = count ( $result );
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_errorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if (! isset ( $dataresult ['Identity'] ) || $clresult != $itemCount) {
	$errMsg = "$currentScript failed on : (! isset ( \$dataresult ['Identity'] ) || clresult ($clresult) !=  itemCount ($itemCount)\n";
	$errMsg .= "sqlds = $sqlds\nsqlcl = $sqlcl";
	SP_errorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

$resultXML = <<< EOT
<?xml version="1.0"?>
<putstartchecklist status="success">
EOT;
$resultXML .= "<truckid>" . $clInfo->truck . "</truckid>\n";
$resultXML .= "</putstartchecklist>\n";
echo $resultXML;
exit ();
?>