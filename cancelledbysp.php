<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<cancelledbysp status="failed" code="0" retry="true" errmsg="XXX">
</cancelledbysp>
EOT;

// Log the data
$postData = (isset ( $_POST )) ? serialize ( $_POST ) : 'none';
dartLogging ( $currentScript, "postdata=" . $postData );

// User ID
$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid User ID', $badXML );
	echo $badXML;
	exit ();
}

// SaleID
$saleid = filter_input ( INPUT_POST, 'saleid', FILTER_SANITIZE_NUMBER_INT );
if ($saleid == FALSE || is_null ( $saleid )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid Sale ID', $badXML );
	echo $badXML;
	exit ();
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ( $sqlFailed ) {
	$sqlFailed = false;
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		// There is no difference DEBUG_USER and live driver.
		$result = $dbh->exec ( "uspDARTCancelBySP $saleid" );
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage ();
		$errMsg .= $e->getFile () . ' (' . $e->getLine () . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\n\$sqlAttemptCount = $sqlAttemptCount";
		if (preg_match ( '/Timeout expired/', $eMessage ) || preg_match ( '/SQL Server does not exist or access denied/', $eMessage ) || preg_match ( '/deadlock victim/', $eMessage ) || preg_match ( '/Schema changed/', $eMessage )) {
			SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, 'DART : CancelledBySP Retry' );
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount ++;
				$sqlFailed = true;
				sleep ( DART_SQL_TIMEOUT_SLEEP );
			} else {
				$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML );
				$badXML = preg_replace ( '/code="0"/', 'code="1"', $badXML );
				echo $badXML;
				exit ();
			}
		} else {
			SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, 'DART : CancelledBySP Serious' );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
			echo $badXML;
			exit ();
		}
	}
}

if ($result === false) {
	$errMsg = "uspDartCancelBySP $userid, $saleid returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<cancelledbysp status="success">
</cancelledbysp>
EOT;
echo $resultStr;
exit ();
?>