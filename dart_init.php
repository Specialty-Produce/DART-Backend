<?php
define ( "DEBUG_USERID", 12658 );
define ( "PRINTED_INVOICE_ID", 13358 );
define ( "DARK_STOP_ID", 17862 );
define ( "DART_ERROR_LOG", "dart_errors" );
define ( "DART_REPORTING", "dart_reporting" );
define ( "DART_LOG_DIR", "C:/inetpub/wwwroot/Dart/logs/" );
define ( "DART_PDF_DIR", "C:/Temp/invoicePDFs/" );
define ( "DART_SIG_DIR", "\\\\vServices\\dartsigs\$\\" );
define ( "DART_FAX_DIR", "\\\\Server3\\DartFaxes\$\\" );
// For use by the XFresh invoice retrieval
define ( "SCAN_INVOICE_DIR", "\\\\vServices\\invoices\$\\" );

function dartLogging($webservice, $data, $code = '') {
	$filename = DART_LOG_DIR . $webservice . ".log";
	$confirmFile = fopen ( $filename, "a+" );
	$timeStamp = date ( '[d-M-Y H:i:s]' );
	fwrite ( $confirmFile, $timeStamp . " : " . $code . " : " . $data . "\n" );
	fclose ( $confirmFile );
}

$pullColors = array ("#FFFFFF", "#66FFFF", "#E62E00" );

$invoiceStatus = array ("Received", "Staged", "Loading Truck", "Out for Delivery", "Currently Being Delivered", "Delivery Complete" );
?>