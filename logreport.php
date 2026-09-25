<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

$logfile = filter_input(INPUT_POST, 'logfile');
$logdata = filter_input(INPUT_POST, 'logdata');

$logFileName = DART_PTF_LOG_DIR . $logfile;

dartLogging($currentScript, $logfile, $codeStr);

if (file_exists($logFileName)) {
	file_put_contents($logFileName, "\n*** APPEND ***\n", FILE_APPEND);
}

// Write to PHP logs folder
file_put_contents($logFileName, $logdata, FILE_APPEND);

sendResult();
