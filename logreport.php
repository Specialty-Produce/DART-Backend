<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<logreport status="failed" code="0" retry="true" errmsg="XXX">
</logreport>
EOT;

$logfile = filter_input ( INPUT_POST, 'logfile', FILTER_SANITIZE_STRING );
$logdata = filter_input ( INPUT_POST, 'logdata' );

$logFileFile = DART_PTF_LOG_DIR . $logfile;

dartLogging ( $currentScript, $logfile );

if (file_exists ( $logFileFile )) {
	file_put_contents ( $logFileFile, "\n*** APPEND ***\n", FILE_APPEND );
}

// Write to PHP logs folder
file_put_contents ( $logFileFile, $logdata, FILE_APPEND );

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<logreport status="success">
</logreport>
EOT;
echo $resultStr;
exit ();
?>