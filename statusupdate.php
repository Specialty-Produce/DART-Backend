<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<statusupdate status="failed" code="0" retry="true" errmsg="XXX">
</statusupdate>
EOT;

$userid = filter_input ( INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT );
$dartSession = filter_input ( INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT );
$identifier = filter_input ( INPUT_POST, 'identifier', FILTER_SANITIZE_STRING );
$update = filter_input ( INPUT_POST, 'update', FILTER_SANITIZE_STRING );
$notes = filter_input ( INPUT_POST, 'notes', FILTER_SANITIZE_STRING );


$errMsg = "statusupdate : $identifier - $userid / $dartSession\n$update\n$notes";
dartLogging ( $currentScript, $errMsg );
// Write to PHP logs folder
$timeStamp = date ( '[d-M-Y H:i:s]' );
$filename = SPConsts::ErrorLogRoot . DART_STATUS . ".txt";
$errFile = fopen ( $filename, "a" );
fwrite ( $errFile, $timeStamp . " : " . $errMsg . "\n" );
fclose ( $errFile );

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<statusupdate status="success">
</statusupdate>
EOT;
echo $resultStr;
exit();
?>