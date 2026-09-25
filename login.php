<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_ADPWFN_SP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['jsondata'])) {
	dartLogging($currentScript, "jsondata=" . $_POST['jsondata'] . " : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
} else {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Decode the app data
$jd = json_decode($_POST['jsondata']);

// username
$username = filter_var($jd->username);
if ($username == FALSE || is_null($username)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No username supplied', 'No username supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// password
$password = filter_var($jd->password);
if ($password == FALSE || is_null($password)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No password supplied', 'No password supplied in jsondata');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}
// Device ID
$udid = filter_var($jd->udid);
if ($udid == FALSE || is_null($udid)) {
	$udid = 'none';
}
// Device Name
$deviceName = filter_var($jd->devicename);
if ($deviceName == FALSE || is_null($deviceName)) {
	$deviceName = 'none';
} else {
	$deviceName = preg_replace('/\'/', "''", $deviceName);
}
// APN Token
$token = filter_var($jd->apntoken);
if ($token == FALSE || is_null($token)) {
	$token = 'none';
}
// Version
$version = filter_var($jd->version);
if ($version == FALSE || is_null($version)) {
	$version = 'none';
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "uspDARTLogin '$username', '$password', '$udid', '$version', '$deviceName', '$token'";
		$stmt = $dbh->query($sql);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		// Returns -1 on invalid username or password
		$userID = $result['iUserID'];
		$stmt->closeCursor();

		if ($userID == -1) {
			sendError(401, ERROR_CODES::ERROR_INVALID_USER, 'Invalid username or password', 'Invalid username or password supplied in POST request');
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			exit();
		}

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

// Get ADP punches.  We don't worry if this faile.
$punchTS = '';
try {
	$nowDT = new DateTime();
	$punches = ADPWFN_SP::getPunchesByUserIDs(array($userID), $nowDT->format('Y-m-d'));
	if (array_key_exists($userID, $punches)) {
		if ($punches[$userID][0]['type'] == 1) {
			$pTimeParts = explode(':', $punches[$userID][0]['time']);
			$punchIn = new DateTime();
			$punchIn->setTime($pTimeParts[0], $pTimeParts[1], $pTimeParts[2]);
			$punchTS = $punchIn->format('Y-m-d H:i:s');
		}
	} else if ($userID == 158904) {
		$punchIn = new DateTime();
		$punchTS = $punchIn->format('Y-m-d H:i:s');
	}
} catch (SP_Exception $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
}

$sendObj->data->userid = $result['iUserID'];
$sendObj->data->sessionid = $result['iDartSessionID'];
$sendObj->data->usertype = ($result['iUserID'] == DEBUG_USERID) ? 'salesperson' : (($result['UserType'] == 1) ? 'driver' : 'salesperson');
$sendObj->data->userfname = mb_convert_encoding($result['txtFirstName'], "UTF-8", "Windows-1252");
$sendObj->data->userlname = mb_convert_encoding($result['txtLastName'], "UTF-8", "Windows-1252");
$sendObj->data->punchintime = $punchTS;
$sendObj->data->gpsdatafrequency = ($result['iUserID'] == DEBUG_USERID) ? 1 : $result['GPSFrequency']; // Minutes
$sendObj->data->gpsaccuracy = ($result['iUserID'] == DEBUG_USERID) ? 50 : $result['GPSAccuracy']; // Meters
$sendObj->data->gpsreportinterval = ($result['iUserID'] == DEBUG_USERID) ? 5 : $result['GPSReporting']; // # of points to accumulate before reporting
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
