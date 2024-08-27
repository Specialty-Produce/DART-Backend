<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<redx status="failed" code="0" retry="false" errmsg="XXX">
</redx>
EOT;

dartLogging($currentScript, " : postdata = " . print_r($_POST, true));
error_log($currentScript . " : START : postdata = " . print_r($_POST, true));

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_VALIDATE_INT);
if ($userid === FALSE || is_null($userid)) {
    $badXML = preg_replace('/XXX/', $currentScript . ' : Invalid User ID', $badXML);
    echo $badXML;
    exit();
}

// Sale ID
$saleid = filter_input(INPUT_POST, 'saleid', FILTER_VALIDATE_INT);
if ($saleid === FALSE || is_null($saleid)) {
    $badXML = preg_replace('/XXX/', $currentScript . ' : Invalid Sale ID', $badXML);
    echo $badXML;
    exit();
}

// DBS ID
$dsbid = filter_input(INPUT_POST, 'dsbid', FILTER_VALIDATE_INT);
if ($dsbid === FALSE || is_null($dsbid)) {
    $badXML = preg_replace('/XXX/', $currentScript . ' : DBS ID', $badXML);
    echo $badXML;
    exit();
}

// DBS ID
$dsbNote = filter_input(INPUT_POST, 'dsbNote', FILTER_SANITIZE_STRING);
if ($dsbNote === FALSE || is_null($dsbNote)) {
    $badXML = preg_replace('/XXX/', $currentScript . ' : DBS ID', $badXML);
    echo $badXML;
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
        $errMsg .= "\n\n\$sqlAttemptCount = $sqlAttemptCount";
        if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage) || preg_match('/Schema changed/', $eMessage)) {
            SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : CancelledBySP Retry');
            if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
                $sqlAttemptCount++;
                $sqlFailed = true;
                sleep(DART_SQL_TIMEOUT_SLEEP);
            } else {
                $badXML = preg_replace('/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML);
                $badXML = preg_replace('/code="0"/', 'code="1"', $badXML);
                echo $badXML;
                exit();
            }
        } else {
            SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : CancelledBySP Serious');
            $badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
            echo $badXML;
            exit();
        }
    }
}

if ($result === false) {
    $errMsg = "uspDartMenuProcess " . json_encode($dsbData) . " : returned FALSE";
    SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
    $badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
    echo $badXML;
    exit();
}

error_log($currentScript . " : END");

// Generate the XML
$resultStr = <<< EOT
<?xml version="1.0"?>
<redx status="success">
</redx>
EOT;
echo $resultStr;
exit();
