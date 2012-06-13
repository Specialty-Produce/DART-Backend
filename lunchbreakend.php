<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<lunchend status="failed" code="0" retry="true" errmsg="XXX">
</lunchend>
EOT;

$postData = '';
foreach ($_POST as $key => $val) {
	$postData .= $key . "=>" . $val . ", ";
}
dartLogging ( $currentScript, "postdata=" . $postData );

// User ID
$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
if ($userid == FALSE || is_null ( $userid )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
	echo $badXML;
	exit ();
}

// End Time
$endtime = filter_input ( INPUT_POST, 'endtime', FILTER_SANITIZE_STRING );
if ($endtime == FALSE || is_null ( $endtime )) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid End Time', $badXML);
	echo $badXML;
	exit ();
}

// Comment - can be empty
$comment = filter_input ( INPUT_POST, 'comment', FILTER_SANITIZE_STRING );

$sql = '';
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$sql = "uspDARTBreakTime $userid, '" . $endtime . ".000', 2, '" . $comment . "', 0";
	$result = $dbh->exec($sql);
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}

if ($result === false) {
	$errMsg = "$sql returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<lunchend status="success">
</lunchend>
EOT;
echo $resultStr;
exit();
?>