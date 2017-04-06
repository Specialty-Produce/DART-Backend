<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<dartversions status="failed" code="0" retry="true" errmsg="XXX">
</dartversions>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// Log the data
$postData = (isset ( $_POST )) ? serialize ( $_POST ) : 'none';
dartLogging ( $currentScript, "postdata=" . $postData, $codeStr );

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$stmt = $dbh->query ( "select top 1 dtVersion, siPad from tblDartVersion where siPad is not null order by dtCreated desc" );
	$versioniPad = $stmt->fetch ( PDO::FETCH_ASSOC );
	$stmt->closeCursor ();
	
	$stmt = $dbh->query ( "select top 1 dtVersion, siPhone from tblDartVersion where siPhone is not null order by dtCreated desc" );
	$versioniPhone = $stmt->fetch ( PDO::FETCH_ASSOC );
	$stmt->closeCursor ();
	
	$stmt = $dbh->query ( "select top 1 dtVersion, siPhoneDB from tblDartVersion where siPhoneDB is not null order by dtCreated desc" );
	$versioniPhoneDB = $stmt->fetch ( PDO::FETCH_ASSOC );
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<dartversions status="success">' . "\n";
$resultStr .= '<dart_versions_list>' . "\n";
$resultStr .= '<ipad versiondate="' . date ( 'Y-m-d H:i:s', strtotime ( $versioniPad ['dtVersion'] ) ) . '">' . $versioniPad ['siPad'] . '</ipad>' . "\n";
$resultStr .= '<iphone versiondate="' . date ( 'Y-m-d H:i:s', strtotime ( $versioniPhone ['dtVersion'] ) ) . '">' . $versioniPhone ['siPhone'] . '</iphone>' . "\n";
$resultStr .= '<iphonedb versiondate="' . date ( 'Y-m-d H:i:s', strtotime ( $versioniPhoneDB ['dtVersion'] ) ) . '">' . $versioniPhoneDB ['siPhoneDB'] . '</iphonedb>' . "\n";
$resultStr .= '</dart_versions_list>' . "\n";
$resultStr .= "</dartversions>";
echo $resultStr;
dartLogging ( $currentScript, "  Success", $codeStr );
exit ();
?>