<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putnewsigner status="failed" errmsg="XXX">
</putnewsigner>
EOT;

// Get the POST data
$appJSON = $_POST ['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON);

$resultXML = <<< EOT
<?xml version="1.0"?>
<putnewsigner status="success">
</putnewsigner>
EOT;
echo $resultXML;
exit();
?>