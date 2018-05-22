<?php
// Hard-coded UserIDs
define ( "DEBUG_USERID", 12658 );
define ( "PRINTED_INVOICE_ID", 13358 );
define ( "DARK_STOP_ID", 17862 );

// Logging
define ( "DART_ERROR_LOG", "dart_errors" );
define ( "DART_REPORTING", "dart_reporting" );
define ( "DART_LOG_DIR", "C:/inetpub/wwwroot/Dart/logs/" );

// Invoice-related
define ( "DART_PDF_DIR", "C:/Temp/invoicePDFs/" );
define ( "DART_RSI_DIR", "C:/Temp/invoiceRSIs/" );
define ( "DART_PPP_DIR", "C:/Temp/invoicePPPs/" );
define ( "DART_PLATEIQ_DIR", "C:/Temp/invoicePlateIQs/" );
define ( "DART_CT_DIR", "C:/Temp/invoiceCTs/" );
define ( "DART_HULA_DIR", "C:/inetpub/filezillaroot/RestaurantMatrix/outgoing/" );
define ( "DART_BEVAGER_DIR", "C:/inetpub/filezillaroot/Bevager/" );
define ( "DART_SIG_DIR", "\\\\vServices\\dartsigs\$\\" );
define ( "DART_FAX_DIR", "\\\\Server3\\DartFaxes\$\\" );

// Silent Fax
define ( "SILENT_FAX_DIR", "\\\\SilentFax\\AIX_WOutbox\\" );

// For use by the XFresh invoice retrieval
define ( "SCAN_INVOICE_DIR", "\\\\vServices\\invoices\$\\" );

// Kludge to have users never get invoices
define ( "DONT_SEND_INVOICE_EMAIL", "dontsendinvoices@specialtyproduce.com" );

// SQL error-trapping
define ( "DART_SQL_TIMEOUT_SLEEP", 3 );
define ( "DART_SQL_TIMEOUT_MAX_TRIES", 3 );

// Error Codes
define ( "DART_ERR_NONE", 0 );
define ( "DART_ERR_SQL_DB_TIMEOUT", 1 );
function dartLogging($webservice, $data, $code = '') {
	$filename = DART_LOG_DIR . $webservice . ".log";
	$confirmFile = fopen ( $filename, "a+" );
	$timeStamp = date ( '[d-M-Y H:i:s]' );
	fwrite ( $confirmFile, $timeStamp . " : " . $code . " : " . $data . "\n" );
	fclose ( $confirmFile );
}
function DART_escapeXmlString($str) {
	// must do ampersand first
	$search = array (
			'&',
			'>',
			'<',
			"'",
			'"' 
	);
	$repl = array (
			'&amp;',
			'&gt;',
			'&lt;',
			'&apos;',
			'&quot;' 
	);
	return str_replace ( $search, $repl, $str );
}

$pullColors = array (
		"#FFFFFF",
		"#66FFFF",
		"#E62E00" 
);

$invoiceStatus = array (
		"Received",
		"Staged",
		"Loading Truck",
		"Out for Delivery",
		"Currently Being Delivered",
		"Delivery Complete" 
);
?>