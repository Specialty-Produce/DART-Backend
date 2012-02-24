<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

global $pullColors;

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<pullreport_item_list status="failed" errmsg="XXX">
</pullreport_item_list>
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

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	// Get the driver route
	if ($userid == DEBUG_USERID) {
		$sql = "select * from tblDartDataPullReport";
	} else {
		$sql = "uspDARTGetPullReport " . $userid;
	}
	$stmt = $dbh->query ( $sql );
	$pullReport = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit ();
}

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<pullreport_item_list status="success">' . "\n";
foreach ( $pullReport as $entry ) {
	$resultStr .= '<item rowcolor="' . $pullColors[$entry['iDARTColorCode']] . '">' . "\n";
	$resultStr .= "<description>" . mb_convert_encoding ( $entry['sDescription'], "UTF-8", "Windows-1252" ) . "</description>\n";
	$resultStr .= "<productid>" . $entry['iProductID'] . "</productid>\n";
	$resultStr .= "<quantity>" . $entry['Qty'] . "</quantity>\n";
	$resultStr .= "<master>" . $entry['MasterLocation'] . "</master>\n";
	$resultStr .= "<location>" . $entry['Location'] . "</location>\n";
	$resultStr .= "</item>\n";
}
$resultStr .= "</pullreport_item_list>";
echo $resultStr;
exit();
?>