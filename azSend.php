<?php
require_once 'global_CDC.php';
require_once 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
error_log("$currentScript : START : " . php_uname("n"));

$invXML = '--';
for($i = 1; $i < count ( $argv ); $i ++)
	$invXML .= $argv [$i] . '--';
$invXML .= '--';
/*
ob_start();
phpinfo();
$data = ob_get_contents();
ob_clean();
//error_log("$currentScript : phpinfo() : $data");
$invNum = $_GET['i'];
$invNum = 'shell_exec';
error_log("$currentScript : $invNum");
SP_ErrorLogging ( "$currentScript : Test SP_ErrorLogging()", true, DART_ERROR_LOG, 'DART Azure Send Test' );
*/
sleep(10);
error_log("$currentScript : $invXML");
error_log("$currentScript : END");