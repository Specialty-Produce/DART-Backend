<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_ADP_SP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<loginresponse status="failed" code="0" retry="false" errmsg="XXX">
</loginresponse>
EOT;


$userID = 16204;
$punchTS = '';
try {
    $nowDT = new DateTime();
    $punches = ADP_SP::getPunchesByUserIDs(array($userID), $nowDT->format('Y-m-d'));
    if (array_key_exists($userID, $punches)) {
        if ($punches[$userID][0]['type'] == 1) {
            $pTimeParts = explode(':', $punches[$userID][0]['time']);
            $punchIn = new DateTime();
            $punchIn->setTime($pTimeParts[0], $pTimeParts[1], $pTimeParts[2]);
            $punchTS = $punchIn->format('Y-m-d H:i:s');
        }
    } else {
        $punchTS = '';
    }
} catch (SP_Exception $e) {
    $errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
    SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
}

$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<loginresponse status="success">' . "\n";
$resultStr .= "<userid>" . $userID . "</userid>\n";
$resultStr .= "<sessionid>" . 1000 . "</sessionid>\n";
$resultStr .= "<punchintime>" . $punchTS . "</punchintime>\n";
$resultStr .= "</loginresponse>";
echo $resultStr;
exit();
