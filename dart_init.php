<?php
define ( "DEBUG_USERID", 12658 );
define ( "DART_ERROR_LOG", "dart_errors" );
define ( "DART_REPORTING", "dart_reporting" );
define ( "DART_LOG_DIR", "C:/wwwroot/DART/logs/" );
define ( "DART_SIG_DIR", "D:/DARTSigs/" );

function dartLogging($webservice, $data, $code = '') {
	$filename = DART_LOG_DIR . $webservice . ".log";
	$confirmFile = fopen ( $filename, "a+" );
	$timeStamp = date ( '[d-M-Y H:i:s]' );
	fwrite ( $confirmFile, $timeStamp . " : " . $code . " : " . $data . "\n" );
	fclose ( $confirmFile );
}

$pullColors = array("#FFFFFF", "#66FFFF", "#E62E00");

$invoiceStatus = array("Received", "Staged", "Loading Truck", "Out for Delivery", "Currently Being Delivered", "Delivery Complete");
?>