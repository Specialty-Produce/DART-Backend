<?php
// Maintenace Mode
define("MAINTENANCE_MODE", false);

// Hard-coded UserIDs
define("DEBUG_USERID", 12658);
define("PRINTED_INVOICE_ID", 13358);
define("DARK_STOP_ID", 17862);
define("DELIVERY_NO_SIGNATURE_ID", 96107);

/* Hard-coded Product IDs */
define("GREEN_DISCOUNT_ID", 9997);
define("SALES_TAX_ID", 8625);

// Logging
define("DART_ERROR_LOG", "dart_errors");
define("DART_REPORTING", "dart_reporting");
define("DART_STATUS", "dart_status");
define("DART_LOG_DIR", "/home/site/wwwroot/logs/");
define("DART_LOG_ARCHIVE_DIR", "/home/site/wwwroot/logs/archive/");

// Invoice-related
define("DART_SIG_DIR", "/home/site/dartsigs/");
define("DART_SIG_BACKUP_DIR", "/home/site/Temp/dartsigBackup/");
define("DART_PDF_DIR", "/home/site/Temp/invoicePDFs/");
define("DART_RSI_DIR", "/home/site/Temp/invoiceRSIs/");
define("DART_PPP_DIR", "/home/site/Temp/invoicePPPs/");
define("DART_PLATEIQ_DIR", "/home/site/Temp/invoicePlateIQs/");
define("DART_CT_DIR", "/home/site/Temp/invoiceCTs/");
define("DART_PTF_LOG_DIR", "/home/site/Temp/PTFLogs/");

// Kludge to have users never get invoices
define("DONT_SEND_INVOICE_EMAIL", "dontsendinvoices@specialtyproduce.com");

// SQL error-trapping
define("DART_SQL_TIMEOUT_SLEEP", 3);
define("DART_SQL_TIMEOUT_MAX_TRIES", 3);

// Error Codes
define("DART_ERR_NONE", 0);
define("DART_ERR_SQL_DB_TIMEOUT", 1);

// Sendinvoice API
define("DART_SENDINVOICE_API_KEY", 'abcd1234*');

// Functions
function dartLogging($webservice, $data, $code = '') {
	$filename = DART_LOG_DIR . $webservice . ".log";
	$confirmFile = fopen($filename, "a+");
	$timeStamp = date('[d-M-Y H:i:s]');
	fwrite($confirmFile, $timeStamp . " : " . $code . " : " . $data . "\n");
	fclose($confirmFile);
}
function dartLoggingHour($webservice, $data, $code = '') {
	$filename = DART_LOG_DIR . $webservice . "_" . date('H')  . ".log";
	$confirmFile = fopen($filename, "a+");
	$timeStamp = date('[d-M-Y H:i:s]');
	fwrite($confirmFile, $timeStamp . " : " . $code . " : " . $data . "\n");
	fclose($confirmFile);
}
// 250527 : CDC : This function is no longer used by the new JSON webservices, but is left here for reference.
/*
function DART_escapeXmlString($str) {
	// must do ampersand first
	$search = array(
		'&',
		'>',
		'<',
		"'",
		'"'
	);
	$repl = array(
		'&amp;',
		'&gt;',
		'&lt;',
		'&apos;',
		'&quot;'
	);
	return str_replace($search, $repl, $str);
}
	*/

$pullColors = array(
	"#FFFFFF",
	"#66FFFF",
	"#E62E00"
);

$invoiceStatus = array(
	"Received",
	"Staged",
	"Loading Truck",
	"Out for Delivery",
	"Currently Being Delivered",
	"Delivery Complete"
);

/*
Sends a correctly formatted message using the REST API standard.
200-level (Success) – request was successful
400-level (Client error) – client sent an invalid request
500-level (Server error) – server failed to fulfill a valid request due to an error with server

Details:
$sendObj = new stdClass();
$sendObj->webservice = 'response.php';
$sendObj->status = 'success';
$sendObj->statusCode = 200;
$sendObj->retry = false;
$sendObj->error = new stdClass();
$sendObj->error->code = "This could be numeric or a CONSTANT or not exist.";
$sendObj->error->userMessage = "This is what would be displayed to the user.";
$sendObj->error->systemMessage = "This would have debugging information for developers.";
$sendObj->error->timestamp = date('Y-m-d H:i:s');
$sendObj->data = new stdClass();
*/

$sendObj = new stdClass();
$sendObj->webservice = '';
$sendObj->status = 'success';
$sendObj->statusCode = 200;
// $sendObj->error = new stdClass();
// $sendObj->error->code = 0;
// $sendObj->error->userMessage = '';
// $sendObj->error->systemMessage = '';
// $sendObj->error->retry = false;
// $sendObj->error->timestamp = '';
$sendObj->data = new stdClass();

function sendError($statusCode, $code, $userMessage, $systemMessage = '', $retry = false) {
	global $sendObj;
	$sendObj->status = 'error';
	$sendObj->statusCode = $statusCode;
	$sendObj->error = new stdClass();
	$sendObj->error->code = $code;
	$sendObj->error->retry = $retry;
	$sendObj->error->userMessage = $userMessage;
	$sendObj->error->systemMessage = $systemMessage;
	$sendObj->error->timestamp = date('Y-m-d H:i:s');
	sendResult();
}

function sendResult() {
	global $sendObj;
	http_response_code($sendObj->statusCode);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($sendObj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

interface ERROR_CODES {
	const ERROR_UNKNOWN = 1;
	const ERROR_NETWORK = 2;
	const ERROR_DATABASE = 3;
	const ERROR_DATABASE_TIMEOUT = 4;
	const ERROR_NO_RESULT = 5; // When a result is expected
	const ERROR_INVALID_DATA = 6; // As supplied by the user
	const ERROR_INVALID_USER = 7;
	const ERROR_MAINTENANCE_MODE = 8;
}
