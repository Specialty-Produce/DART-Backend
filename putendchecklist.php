<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putendchecklist status="failed" code="0" retry="true" errmsg="XXX">
</putendchecklist>
EOT;

// Get the POST data
if (isset($_POST ['jsondata'])) {
	$appJSON = $_POST ['jsondata'];
	dartLogging ( $currentScript, "jsondata=" . $appJSON );
} else {
	$appJSON = null;
}

//$appJSON = '{"userid":"4360","dartsessionid":"12842","templogjson":"[{\"stopinvoices\":[],\"temperature\":55,\"timestamp\":\"5/3/12 6:18:47 AM PDT\",\"routeStopID\":0},{\"stopinvoices\":[\"1521623\"],\"temperature\":40,\"timestamp\":\"5/3/12 6:54:54 AM PDT\",\"routeStopID\":2602},{\"stopinvoices\":[\"1521632\"],\"temperature\":43,\"timestamp\":\"5/3/12 7:10:52 AM PDT\",\"routeStopID\":2549},{\"stopinvoices\":[\"1521304\",\"1521723\"],\"temperature\":42,\"timestamp\":\"5/3/12 7:20:39 AM PDT\",\"routeStopID\":266},{\"stopinvoices\":[\"1521380\"],\"temperature\":43,\"timestamp\":\"5/3/12 7:36:53 AM PDT\",\"routeStopID\":3057},{\"stopinvoices\":[\"1521663\"],\"temperature\":41,\"timestamp\":\"5/3/12 7:47:42 AM PDT\",\"routeStopID\":439},{\"stopinvoices\":[\"1521430\"],\"temperature\":39,\"timestamp\":\"5/3/12 8:04:51 AM PDT\",\"routeStopID\":4118},{\"stopinvoices\":[\"1521433\"],\"temperature\":39,\"timestamp\":\"5/3/12 8:30:06 AM PDT\",\"routeStopID\":3129},{\"stopinvoices\":[\"1521317\"],\"temperature\":39,\"timestamp\":\"5/3/12 8:30:20 AM PDT\",\"routeStopID\":3130},{\"stopinvoices\":[\"1521708\"],\"temperature\":39,\"timestamp\":\"5/3/12 8:30:36 AM PDT\",\"routeStopID\":3131},{\"stopinvoices\":[\"1521368\"],\"temperature\":38,\"timestamp\":\"5/3/12 8:54:52 AM PDT\",\"routeStopID\":9866},{\"stopinvoices\":[\"1521724\"],\"temperature\":41,\"timestamp\":\"5/3/12 9:07:51 AM PDT\",\"routeStopID\":2629},{\"stopinvoices\":[\"1521536\"],\"temperature\":39,\"timestamp\":\"5/3/12 9:19:50 AM PDT\",\"routeStopID\":494},{\"stopinvoices\":[\"1521559\"],\"temperature\":38,\"timestamp\":\"5/3/12 9:35:06 AM PDT\",\"routeStopID\":2329},{\"stopinvoices\":[\"1521285\"],\"temperature\":43,\"timestamp\":\"5/3/12 9:41:41 AM PDT\",\"routeStopID\":1560},{\"stopinvoices\":[\"1521379\",\"1521435\"],\"temperature\":43,\"timestamp\":\"5/3/12 10:06:23 AM PDT\",\"routeStopID\":779},{\"stopinvoices\":[\"1521634\"],\"temperature\":45,\"timestamp\":\"5/3/12 10:26:54 AM PDT\",\"routeStopID\":3356},{\"stopinvoices\":[\"1521720\"],\"temperature\":39,\"timestamp\":\"5/3/12 10:49:10 AM PDT\",\"routeStopID\":837},{\"stopinvoices\":[\"1521486\"],\"temperature\":46,\"timestamp\":\"5/3/12 10:53:18 AM PDT\",\"routeStopID\":3176},{\"stopinvoices\":[\"1521686\"],\"temperature\":38,\"timestamp\":\"5/3/12 11:54:29 AM PDT\",\"routeStopID\":1627}]","cljson":"{\"odometer\":\"493588\",\"test_items\":[],\"comments\":\"\"}"}';

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

$jd = json_decode ( $appJSON );
// User ID
$userid = filter_var ( $jd->userid, FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid User ID', $badXML );
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

// templogJSON
$templogJSON = json_decode ( $jd->templogjson );

// Submit the end checklist information and close out the session
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$odoValue = ($clInfo->odometer > 0) ? $clInfo->odometer : 0;
	$sqlds = "uspDARTTruckDataEnd $dartSession, " . $odoValue;
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
	if ($itemCount > 0) {
		$sqlcl = "uspDARTCheckListTruck '" . $clXML . "', $dartSession";
		$stmt = $dbh->query ( $sqlcl );
		$result = $stmt->fetchAll ( PDO::FETCH_ASSOC );
		$stmt->closeCursor ();
		$clresult = count ( $result );
	}
	
	// Close out the session
	$result = $dbh->exec ( "uspDARTInvoicesAssignEnd $dartSession" );
	
	// Temperature logs
	if (count ( $templogJSON ) > 0) {
		$startTemp = $templogJSON [0]->temperature;
		array_shift ( $templogJSON );
		$tlXML = "<ROOT>";
		foreach ( $templogJSON as $stop ) {
			foreach ( $stop->stopinvoices as $value ) {
				$tlXML .= "\n" . '<Rec rID = "' . $value . '" iTemp = "' . $stop->temperature . '" />';
			}
		}
		$tlXML .= "\n</ROOT>";
		$sqltl = "uspDARTTemperatureLog $dartSession, $startTemp, '" . $tlXML . "'";
		$result = $dbh->exec ( $sqltl );
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	$errMsg .= "sqltl = " . $sqltl;
	SP_errorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

/*
if ($dataresult['Identity'] === false || $clresult != $itemCount) {
	$errMsg = "clresult = $clresult, itemCount = $itemCount \n";
	$errMsg .= "sqlcl = $sqlcl";
	SP_errorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}
*/

$resultXML = <<< EOT
<?xml version="1.0"?>
<putendchecklist status="success">
</putendchecklist>
EOT;
echo $resultXML;
exit ();
?>