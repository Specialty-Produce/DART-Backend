<?php
// Maintenace Mode
define ( "MAINTENANCE_MODE", false );

// Hard-coded UserIDs
define ( "DEBUG_USERID", 12658 );
define ( "PRINTED_INVOICE_ID", 13358 );
define ( "DARK_STOP_ID", 17862 );
define ( "DELIVERY_NO_SIGNATURE_ID", 96107 );

// Logging
define ( "DART_ERROR_LOG", "dart_errors" );
define ( "DART_REPORTING", "dart_reporting" );
define ( "DART_LOG_DIR", "/home/site/wwwroot/logs/" );

// Invoice-related
define ( "DART_SIG_DIR", "/home/site/dartsigs/" );
define ( "DART_PDF_DIR", "/home/site/Temp/invoicePDFs/" );
define ( "DART_RSI_DIR", "/home/site/Temp/invoiceRSIs/" );
define ( "DART_PPP_DIR", "/home/site/Temp/invoicePPPs/" );
define ( "DART_PLATEIQ_DIR", "/home/site/Temp/invoicePlateIQs/" );
define ( "DART_CT_DIR", "/home/site/Temp/invoiceCTs/" );
define ( "DART_HULA_DIR", "C:/inetpub/filezillaroot/RestaurantMatrix/outgoing/" );
// define ( "DART_BEVAGER_DIR", "C:/inetpub/filezillaroot/Bevager/" );
// define ( "DART_QSR_DIR", "C:/inetpub/filezillaroot/QSROnline/" );
define ( "DART_SIMPLE123_DIR", "C:/SFTP_Root/Invoice/" );

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