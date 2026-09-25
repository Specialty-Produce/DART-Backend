<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

$checklistHTML = file_get_contents('accidentreport.htm');
$sendObj->data->accidentreportHTML = $checklistHTML;
sendResult();
// $myData = json_decode(json_encode($sendObj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
// echo $myData->data->accidentreportHTML;
