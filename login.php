<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<loginresponse status="failed" code="0" retry="false" errmsg="XXX">
</loginresponse>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// Get the POST data
if (isset ( $_POST ['jsondata'] )) {
	dartLogging ( $currentScript, "jsondata=" . $_POST ['jsondata'] . " : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
} else {
	dartLogging ( $currentScript, "    jsondata is FALSE or NULL : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

// Decode the app data
$jd = json_decode ( $_POST ['jsondata'] );

// username
$username = filter_var ( $jd->username, FILTER_SANITIZE_STRING );
if ($username == FALSE || is_null ( $username )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No username supplied', $badXML );
	echo $badXML;
	exit ();
}
// password
$password = filter_var ( $jd->password, FILTER_SANITIZE_STRING );
if ($password == FALSE || is_null ( $password )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No password supplied', $badXML );
	echo $badXML;
	exit ();
}
// Device ID
$udid = filter_var ( $jd->udid, FILTER_SANITIZE_STRING );
if ($udid == FALSE || is_null ( $udid )) {
	$udid = 'none';
}
// Device Name
$deviceName = filter_var ( $jd->devicename );
if ($deviceName == FALSE || is_null ( $deviceName )) {
	$deviceName = 'none';
} else {
	$deviceName = preg_replace ( '/\'/', "''", $deviceName );
}
// APN Token
$token = filter_var ( $jd->apntoken, FILTER_SANITIZE_STRING );
if ($token == FALSE || is_null ( $token )) {
	$token = 'none';
}
// Version
$version = filter_var ( $jd->version, FILTER_SANITIZE_STRING );
if ($version == FALSE || is_null ( $version )) {
	$version = 'none';
}

try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

	$sql = "uspDARTLogin '$username', '$password', '$udid', '$version', '$deviceName', '$token'";
	$stmt = $dbh->query ( $sql );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	// Returns -1 on invalid username or password
	$userID = $result ['iUserID'];
	$stmt->closeCursor ();

	if ($userID == - 1) {
		$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid login', $badXML );
		echo $badXML;
		exit ();
	}

	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage () . " : username / password = " . $username . "/" . $password;
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<loginresponse status="success">' . "\n";
$resultStr .= "<userid>" . $result ['iUserID'] . "</userid>\n";
$resultStr .= "<sessionid>" . $result ['iDartSessionID'] . "</sessionid>\n";
// UserTypes : 1=driver, 2=salesperson
$resultStr .= "<usertype>";
if ($result ['iUserID'] == DEBUG_USERID) {
	$resultStr .= "salesperson";
} else {
	$resultStr .= ($result ['UserType'] == 1) ? "driver" : "salesperson";
}
$resultStr .= "</usertype>\n";

$resultStr .= "<userfname>" . mb_convert_encoding ( $result ['txtFirstName'], "UTF-8", "Windows-1252" ) . "</userfname>\n";
$resultStr .= "<userlname>" . mb_convert_encoding ( $result ['txtLastName'], "UTF-8", "Windows-1252" ) . "</userlname>\n";
if ($result ['iUserID'] == DEBUG_USERID) {
	$resultStr .= "<gpsdatafrequency>1</gpsdatafrequency>\n"; // Minutes
	$resultStr .= "<gpsaccuracy>50</gpsaccuracy>\n"; // Meters
	$resultStr .= "<gpsreportinterval>5</gpsreportinterval>\n"; // Minutes
} else {
	$resultStr .= "<gpsdatafrequency>" . $result ['GPSFrequency'] . "</gpsdatafrequency>\n"; // Minutes
	$resultStr .= "<gpsaccuracy>" . $result ['GPSAccuracy'] . "</gpsaccuracy>\n"; // Meters
	$resultStr .= "<gpsreportinterval>" . $result ['GPSReporting'] . "</gpsreportinterval>\n"; // Minutes
}
$resultStr .= "</loginresponse>";
echo $resultStr;
dartLogging ( $currentScript, "  Success", $codeStr );
exit ();
?>