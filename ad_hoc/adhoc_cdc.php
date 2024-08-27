<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

error_log('currentScript: ' . $currentScript);

echo "Done";
exit();

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<adhoc_cdc status="failed" code="0" retry="false" errmsg="XXX">
</adhoc_cdc>
EOT;

/* Test append image to mail */
$note = 'foobar';
$appendNote = $note;
require_once 'classes_SP/class_PHPMailerSP.php';
$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$mail->FromName = "DART System";
$mail->From = "itadmin@specialtyproduce.com";
$mail->Subject = "Location GPS/Note Update";
$mail->AddAddress('christopher@specialtyproduce.com', 'Christopher Cilley');

$mail->Body = 'Christopher Cilley' . ",\n\n";
$mail->Body .= "A request to update a location's GPS information and/or Note has been submitted.\n\n";
$mail->Body .= "The system automatically updated the GPS information in the database.\n\nPlease take a moment to review this update.\n\n";
$mail->Body .= "Note : \n$note\n";
if (strlen($appendNote) > 0) {
    $mail->Body .= "***** Driver's note has been appended to the location's notes *****\n";
    $mail->Body .= "Please be sure to review the updated note using the Access Editors (see attached screenshots).\n";
    $mail->addAttachment("../images/NoteEditor.png", "NoteEditor.png");
    $mail->addAttachment("../images/NoteInvoice.png", "NoteInvoice.png");
}

if (!$mail->Send()) {
    echo "send error";
} else
    echo "sent";

exit();

/* Testing appending location note */
include_once 'classes_SP/class_LocationSP.php';
// 'Drop Off after 11am - not later than noon.  NO DARK DROPS' : 3503
$locationID = 3503;
$appendNote = 'foobar';
try {
    LocationSP::appendLocationNote($locationID, $appendNote);
} catch (SP_Exception $e) {
    $errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
    echo $errMsg;
}

exit();

/* Testing GPS pin updating */
include_once 'classes_SP/class_LocationSP.php';
// 32.709961, -117.159058
$locationID = 7149;
$lat = 32.709961;
$lon = -117.1590580999;

$foo = sprintf("%0.6f", $lat);

echo $lat . '<br>';
echo (is_float($lat) ? 'lat is float' : 'lat is not float') . '<br>';
echo $foo . '<br>';
echo (is_float($foo) ? 'foo is float' : 'foo is not float') . '<br>';

try {
    LocationSP::updateGPS($locationID, $lat, $lon);
} catch (SP_Exception $e) {
    $errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
    echo $errMsg;
}


exit();

/* Testint punches */
include_once 'classes_SP/class_ADP_SP.php';
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
