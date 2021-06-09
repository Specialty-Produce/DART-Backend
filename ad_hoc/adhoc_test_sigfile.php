<?php
require_once 'global_CDC.php';
require_once '../dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$sigImageFile = DART_SIG_DIR . 872 . '\\' . 5476386 . '.png';
if (! file_exists ( $sigImageFile )) {
	error_log("$currentScript : A");
} else {
	error_log("$currentScript : B");
}

$sigImageFile = '//vServices/dartsigs$/872/5476386.png';
if (! file_exists ( $sigImageFile )) {
	error_log("$currentScript : C");
} else {
	error_log("$currentScript : D");
}