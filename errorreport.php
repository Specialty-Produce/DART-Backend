<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<errorreport status="failed" code="0" retry="true" errmsg="XXX">
</errorreport>
EOT;

$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
$dartSession = filter_input ( INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT );
$udid = filter_input ( INPUT_POST, 'udid', FILTER_SANITIZE_STRING );
$webservice = filter_input ( INPUT_POST, 'webservice', FILTER_SANITIZE_STRING );
$returnedxml = filter_input ( INPUT_POST, 'returnedxml', FILTER_SANITIZE_STRING );

$errMsg = "errorreport ($udid): $webservice - $userid / $dartSession\n$returnedxml";
dartLogging ( $currentScript, $errMsg );
// Write to PHP logs folder
$timeStamp = date ( '[d-M-Y H:i:s]' );
$filename = SPConsts::ErrorLogRoot . DART_REPORTING . ".txt";
$errFile = fopen ( $filename, "a" );
fwrite ( $errFile, $timeStamp . " : " . $errMsg . "\n" );
fclose ( $errFile );

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<errorreport status="success">
</errorreport>
EOT;
echo $resultStr;
exit();
?>