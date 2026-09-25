<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

$sendObj->data->endchecklistHTML = file_get_contents('endchecklist.htm');
sendResult();
// $myData = json_decode(json_encode($sendObj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
// echo $myData->data->endchecklistHTML;
