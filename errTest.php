<?php
require_once 'global_CDC.php';
require_once 'dart_init.php';
$errMsg .= "Test error : " . generateRandomCode(8);
SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, 'DART : Test Error' );