<?php
include_once 'global_CDC.php';
include '../dart_init.php';
include_once 'classes_SP/class_SendGridSP.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

$jsonStr = file_get_contents("php://input");

try {
    SendGridSP::processWebhookCallback($jsonStr);
} catch (SP_Exception $spe) {
    $errMsg = "SG WebhookCallback Error: " . $spe->getMessage() . "\n" . $jsonStr;
    SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'SG WebhookCallback Error');
}

http_response_code(200);
exit();
