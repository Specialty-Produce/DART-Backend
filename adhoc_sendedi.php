<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
require_once 'EDI_SP.php';
require ('classes_SP/class_SP_cURLFTP.php');

// Browers on our local network get access to the page
$dotted_ip_address = $_SERVER ['REMOTE_ADDR'];
$ip_number = (ip2long ( $dotted_ip_address )) ? sprintf ( "%u", ip2long ( $dotted_ip_address ) ) : 0;
if ($ip_number < 1185397282 || $ip_number > 1185397309) {
	echo "not allowed...";
	exit ();
}

// edi ID
$ediID = filter_input ( INPUT_GET, 'edi', FILTER_SANITIZE_STRING );
if ($ediID == FALSE || is_null ( $ediID )) {
	echo "missing edi ID...";
	exit ();
}

// sale ID
$saleID = filter_input ( INPUT_GET, 'sale', FILTER_SANITIZE_NUMBER_INT );
if ($saleID == FALSE || is_null ( $saleID )) {
	echo "missing sale ID...";
	exit ();
}

// Send the PO
echo "<h2>Progress...</h2>\n";
include_once 'EDI_SP\Receivers\\' . $ediID . '\ts810.php';
$tsFunction = $ediID . '_810';
list ( $success, $msg ) = $tsFunction ( $saleID );
if (! $success) {
	$errMsg = "$tsFunction returned false : $msg";
	$errMsg .= "\nsaleID = " . $saleID . ", ediID = " . $ediID;
	echo $errMsg;
	exit ();
}
echo "EDI output : $msg\n";

$outFileName = 'O_SP_' . date ( 'md_His' ) . '.810';
$outPath = EDISPConsts::FTP_ROOT . $ediID . '\outgoing\\';
$outFile = $outPath . $outFileName;
if (! file_put_contents ( $outFile, $msg )) {
	$errMsg = "Error writing outgoing 810 : $outFile";
	echo $errMsg;
	exit ();
}
echo "\n\noutFile = $outFile<br/>\n";
if (true) {
// Set up the ftp connection
echo "Send via FTP...\n";
$ftpConnector = new SP_cURLFTP ();
if ($ftpConnector->numOutFiles () == 0) {
	$ftpConnector->cURL = constant ( 'EDISPConsts::' . $ediID . "_FTP" );
	$ftpConnector->cUSERPWD = constant ( 'EDISPConsts::' . $ediID . "_USERNAME" ) . ":" . constant ( 'EDISPConsts::' . $ediID . "_PASSWORD" );
	$ftpConnector->setOutPath ( $outPath );
}
$sentSuccessfully = true;
$ftpConnector->addOutFile ( $outFileName );
try {
	$ftpConnector->sendOutFiles ();
} catch ( SP_Exception $spe ) {
	SP_ErrorLogging ( $spe, true, DART_ERROR_LOG, "DART Error : cURL send" );
	dartLogging ( $currentScript, "    EDI cURL error, see " . DART_ERROR_LOG );
	$sentSuccessfully = false;
}
if ($sentSuccessfully) {
	// Save the outgoing file to the EDI dir
	$savePath = EDISPConsts::EDI_SAVE_DIR . $ediID . '\outgoing\\' . $outFileName;
	if (! copy ( $outFile, $savePath )) {
		flagFTPFile ( $ediID, $outFile );
		$errMsg = "Error saving $outFile to $savePath";
		echo $errMsg;
		exit ();
	}
	if (! unlink ( $outFile )) {
		flagFTPFile ( $ediID, $outFile );
		$errMsg = "Error unlinking $outFile";
		echo $errMsg;
		exit ();
	}
} else {
	$errMsg = "File not sent successfully, moved to flagged folder on vDart:\n$outFile";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, "DART Error : file flagged" );
	flagFTPFile ( $ediID, $outFile );
}
}
echo "Done...";
?>