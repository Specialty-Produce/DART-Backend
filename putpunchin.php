<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_ADPWFN_SP.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

$postData = '';
foreach ($_POST as $key => $val) {
    $postData .= $key . "=>" . $val . ", ";
}
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

// Punch Time
$punchtime = filter_input(INPUT_POST, 'punchtime');
if ($punchtime == FALSE || is_null($punchtime)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No punchtime supplied', 'No punchtime supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

// Response : 1 = "I punched in at the wall clock", 0 = "Submitting punch here."
$response = filter_input(INPUT_POST, 'response', FILTER_VALIDATE_INT);
if ($response == FALSE || is_null($response) || ($response != 1 && $response != 0)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No response supplied', 'No response supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}
// $userid = 635;
// $punchtime = '2023-10-06  08:30:48';
// $response = 0;

$adpResult = 1;
if ($response == 0) {
    // Submit to ADP
    $adpFailed = true;
    $adpAttemptCount = 1;
    while ($adpFailed) {
        $adpFailed = false;
        try {
            $personNumber = ADPWFN_SP::getPersonNumberByUserID($userid);
            if ($personNumber != FALSE) {
                $punchResult = ADPWFN_SP::submitPunch($personNumber, 'in', $punchtime, 'Dart In');
                SP_DebugLogging("$currentScript: ADPWFN_SP::submitPunch : $personNumber, 'clockin', $starttime, 'dart' : $punchResult", 'cdc_adpwfn');
            }
        } catch (SP_Exception $e) {
            if ($adpAttemptCount < 3) {
                $adpAttemptCount++;
                $adpFailed = true;
                sleep(3);
            } else {
                $errMsg = "ADP Error : " . $e->getMessage();
                $errMsg .= "\nUserID : $userid -- BadgeID : $badgeID -- Punch Time : $punchtime";
                SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'ADP Submit Punch Error');
                // Email HR
                $mail = new PHPMailerSP();
                $mail->setApiKey('hr');
                $mail->isHTML(false);
                $mail->FromName = "SP System";
                $mail->From = "itadmin@specialtyproduce.com";
                $mail->Subject = "ADP Punch In Submit Error";
                $mail->Body = "There was an error submitting a DART Timeclock Punch In to ADP.\n\nUserID : $userid -- BadgeID : $badgeID -- End Time : $endtime";
                $mail->addAddress("adppuncherrors@specialtyproduce.com");
                $mail->addBCC("christopher@specialtyproduce.com");
                $mail->Send();
                $adpResult = 0;
            }
        }
    }

    // Submit to ADP WFN
    $personNumber = '';
    try {
        $personNumber = ADPWFN_SP::getPersonNumberByUserID($userid);
        if ($personNumber != FALSE) {
            $punchResult = ADPWFN_SP::submitPunch($personNumber, 'in', $endtime, 'dart');
            // SP_DebugLogging("$currentScript: ADPWFN_SP::submitPunch : $personNumber, 'in', $endtime, 'dart' : $punchResult", 'cdc_adpwfn');
        }
    } catch (SP_Exception $e) {
        $errMsg = "ADP WFN Error : " . $e->getMessage();
        $errMsg .= "\nUserID : $userid -- Person Number : $personNumber -- END Time : $endtime";
        SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'ADP WFN Submit Punch Error');
        // Email HR
        $mail = new PHPMailerSP();
        $mail->setApiKey('hr');
        $mail->isHTML(false);
        $mail->FromName = "SP System";
        $mail->From = "itadmin@specialtyproduce.com";
        $mail->Subject = "ADP WFN Punch Submit Error";
        $mail->Body = "There was an error submitting a DART Timeclock Punch In to ADP WFN.\n\nUserID : $userid -- Person Number : $personNumber -- End Time : $endtime";
        $mail->AddAddress("adppuncherrors@specialtyproduce.com");
        $mail->Send();
    }
} else {
    $punchtime = null;
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
    $sqlFailed = false;
    try {
        $dbh = new PDO('spdb', '', '');
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "uspDartPunchIn $userid, $response, ?, $adpResult";
        $stmt = $dbh->prepare($sql);
        $result = $stmt->execute(array($punchtime));

        $dbh = null;
    } catch (PDOException $e) {
        $errMsg = "SQL = $sql\n";
        $eMessage = $e->getMessage();
        $errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
        $errMsg .= "\n\ncodeStr = $codeStr\n";
        if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
            $sqlParts = explode(' ', $sql);
            if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
                $sqlAttemptCount++;
                $sqlFailed = true;
                sleep(DART_SQL_TIMEOUT_SLEEP);
            } else {
                sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], false);
                dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
                exit();
            }
        } else {
            SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
            sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
            dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
            exit();
        }
    }
}

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
