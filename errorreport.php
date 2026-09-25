<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
$dartSession = filter_input(INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT);
$udid = filter_input(INPUT_POST, 'udid');
$webservice = filter_input(INPUT_POST, 'webservice');
$returnedxml = filter_input(INPUT_POST, 'returnedxml');
$misc = filter_input(INPUT_POST, 'misc');

$errMsg = "errorreport ($udid): $webservice - $userid / $dartSession\n$returnedxml\n$misc";
dartLogging($currentScript, $errMsg);
// Write to PHP logs folder
$timeStamp = date('[d-M-Y H:i:s]');
$filename = SPConsts::ErrorLogRoot . DART_REPORTING . ".txt";
$errFile = fopen($filename, "a");
fwrite($errFile, $timeStamp . " : " . $errMsg . "\n");
fclose($errFile);

sendResult();
exit();

// To pull delivery information back out...
$errdata = 'PASTE ERROR DATA HERE';
eval(html_entity_decode($errdata));
$jd = json_decode($jsondata);
print_r($jd);
