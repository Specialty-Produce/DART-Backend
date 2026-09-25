<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

dartLogging($currentScript, " : postdata = " . print_r($_POST, true), $codeStr);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_VALIDATE_INT);
if ($userid === FALSE || is_null($userid)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

// Sale ID
$saleid = filter_input(INPUT_POST, 'saleid', FILTER_VALIDATE_INT);
if ($saleid === FALSE || is_null($saleid)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No saleid supplied', 'No saleid supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

// DBS ID
$dsbid = filter_input(INPUT_POST, 'dsbid', FILTER_VALIDATE_INT);
if ($dsbid === FALSE || is_null($dsbid)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dsbid supplied', 'No dsbid supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

// DBS ID
$dsbNote = filter_input(INPUT_POST, 'dsbNote');
if ($dsbNote === FALSE || is_null($dsbNote)) {
    sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dsbNote supplied', 'No dsbNote supplied in POST request');
    dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
    exit();
}

$dsbData =  array(
    'iDSBID' => $dsbid,
    'iSaleID' => $saleid,
    'iSaleDetailID' => 0,
    'dtDate' => date('Y-m-d'),
    'sNote' => $dsbNote,
    'fQty' => 0.0
);

$result = true;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
    $sqlFailed = false;
    try {
        $dbh = new PDO('spdb', '', '');
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        error_log($currentScript . " : dsbData JSON String =" . json_encode($dsbData));
        $sql = "uspDartMenuProcess ?";
        $stmt = $dbh->prepare($sql);
        $result = $stmt->execute(array(json_encode($dsbData)));
        error_log($currentScript . " : uspDartMenuProcess result = " . (($result === false) ? 'FALSE' : 'TRUE'));

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
                sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
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

if ($result === false) {
    $errMsg = "uspDartMenuProcess " . json_encode($dsbData) . " : returned FALSE";
    SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
    sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', 'Database error, see ' . DART_ERROR_LOG . ' log');
    dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
    exit();
}

error_log($currentScript . " : END");

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
