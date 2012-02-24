<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<staticinfo status="failed" errmsg="XXX">
</staticinfo>
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

	// There is no difference DEBUG_USER and live driver.
	
	$stmt = $dbh->query ( "uspDARTProductStatus" );
	$productStatus = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor();
	
	$stmt = $dbh->query ( "uspDARTOrderStatus" );
	$orderStatus = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor();
	
	$stmt = $dbh->query ( "uspDARTDriverList" );
	$driverList = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor();
	
	// TODO : Get message addresses
	
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
$resultStr .= '<staticinfo status="success">' . "\n";
$resultStr .= '<productstatus_entry_list infoname="productstatus">' . "\n";
foreach ( $productStatus as $entry ) {
	$resultStr .= '<entry id="' . $entry['iShortID'] . '">' . mb_convert_encoding ( $entry['sDescription'], "UTF-8", "Windows-1252" ) . "</entry>\n";
}
$resultStr .= "</productstatus_entry_list>\n";
$resultStr .= '<orderstatus_entry_list infoname="orderstatus">' . "\n";
foreach ( $orderStatus as $entry ) {
	$resultStr .= '<entry id="' . $entry['iAutoID'] . '">' . mb_convert_encoding ( $entry['sDescription'], "UTF-8", "Windows-1252" ) . "</entry>\n";
}
$resultStr .= "</orderstatus_entry_list>\n";
$resultStr .= '<messageaddresses_entry_list infoname="addresses">' . "\n";
$resultStr .= <<< EOT
<entry id="9215">Christopher Cilley</entry>
<entry id="2835">Erick Chavez</entry>
<entry id="2">Management</entry>
<entry id="635">Roger Harrington</entry>

EOT;
$resultStr .= "</messageaddresses_entry_list>\n";
$resultStr .= '<driver_entry_list infoname="drivers">' . "\n";
foreach ( $driverList as $entry ) {
	$resultStr .= '<entry id="' . $entry['iUserID'] . '">' . mb_convert_encoding ( $entry['txtFirstName'], "UTF-8", "Windows-1252" ) . " " . mb_convert_encoding ( $entry['txtLastName'], "UTF-8", "Windows-1252" ) . "</entry>\n";
}
$resultStr .= "</driver_entry_list>\n";
$resultStr .= "</staticinfo>";
echo $resultStr;
exit();
?>