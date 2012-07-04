<?php
include_once 'global_CDC.php';

/**
 * Generate the 810 for a particular invoice
 */

define ( "EDI_DIR", "\\\\vServices\\EDI\$\\" );
define ( "FTP_ROOT", 'C:\inetpub\filezillaroot\\' );

$returnVals = array ();
$returnVals ['status'] = 'Fail';

$invNum = filter_input ( INPUT_POST, 'i', FILTER_VALIDATE_INT );
if ($invNum == FALSE || is_null ( $invNum )) {
	$returnVals ['errmsg'] = "Invalid invoice number";
	echo json_encode ( $returnVals );
	exit ();
}

$ediID = false;
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// The XML to get the invoice info
	$invXML = "<ROOT>\n";
	$invXML .= '<Rec rID="' . $invNum . '"/>' . "\n";
	$invXML .= "</ROOT>\n";
	
	// Get the info
	$stmt = $dbh->query ( "uspDARTSendInvoiceInfo '" . $invXML . "'" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$ediID = trim ( $row ['sInterchangeID'] );
	}
	unset ( $stmt );
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
	$returnVals ['errmsg'] = "Database error : " . $errorTxt;
	echo json_encode ( $returnVals );
	exit ();
}

if ($ediID == false || strlen ( $ediID ) == 0) {
	$returnVals ['errmsg'] = "Invalid EDI ID";
	echo json_encode ( $returnVals );
	exit ();
}

// Ok, we have a valid EDI ID, so just call the 810 functions
$ftpOutgoing = FTP_ROOT . $ediID . '\outgoing';
include_once 'EDI_SP\Receivers\\' . $ediID . '\ts810.php';
$tsFunction = $ediID . '_810';
list ( $success, $msg ) = $tsFunction ( $invNum );
if (! $success) {
	$errMsg = "$tsFunction returned false : $msg";
	SP_errorLogging ( $errMsg, true, 'error_edi' );
	$returnVals ['errmsg'] = "810 generation error : " . $errMsg;
	echo json_encode ( $returnVals );
	exit ();
}
$outFileName = 'O_SP_' . date ( 'md_His' ) . '.810';
$outPath = $ftpOutgoing . '\\' . $outFileName;
if (! file_put_contents ( $outPath, $msg )) {
	$errMsg = "Error writing outgoing 810 : $outPath";
	SP_errorLogging ( $errMsg, true, 'error_edi' );
	$returnVals ['errmsg'] = "Filesystem error : " . $errMsg;
	echo json_encode ( $returnVals );
	exit ();
}

// Success
$returnVals ['status'] = 'Success';
echo json_encode ( $returnVals );
exit ();
?>