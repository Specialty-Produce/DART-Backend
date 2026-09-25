<?php
include_once 'global_CDC.php';
include 'dart_init.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include_once 'classes_SP/class_LocationSP.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

$postData = '';
foreach ($_POST as $key => $val) {
	$postData .= $key . "=>" . $val . ", ";
}
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

// Session ID
$sessionID = filter_input(INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT);
if ($sessionID == FALSE || is_null($sessionID)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No dartsessionid supplied', 'No dartsessionid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// iPadID
$ipadID = filter_input(INPUT_POST, 'ipadid');
if ($ipadID == FALSE || is_null($ipadID)) {
	$ipadID = "<not set>";
}

// Location ID
$locationID = filter_input(INPUT_POST, 'locationid', FILTER_SANITIZE_NUMBER_INT);
if ($locationID == FALSE || is_null($locationID)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No locationid supplied', 'No locationid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// username
$username = filter_input(INPUT_POST, 'username');
if ($username == FALSE || is_null($username)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No username supplied', 'No username supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Location Name
$locationName = filter_input(INPUT_POST, 'locationname');
if ($locationName == FALSE || is_null($locationName)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No locationname supplied', 'No locationname supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// latitude
$gpslat = filter_input(INPUT_POST, 'lat', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
if ($gpslat == FALSE || is_null($gpslat)) {
	$gpslat = "<not set>";
}

// longitude
$gpslon = filter_input(INPUT_POST, 'lon', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
if ($gpslon == FALSE || is_null($gpslon)) {
	$gpslon = "<not set>";
}

// Note
$locNote = '';
$note = filter_input(INPUT_POST, 'note');
if ($note === FALSE || is_null($note)) {
	$note = false;
	dartLogging($currentScript, "note is FALSE or NULL");
} else {
	$locNote = preg_replace('/\s+/', ' ', preg_replace('/[<>]/', '', trim($note)));
	$note = true;
	dartLogging($currentScript, "note ->$locNote<-");
}

// Update the GPS information
try {
	if (abs($gpslat) > 0 && abs($gpslon) > 0) {
		LocationSP::updateGPS($locationID, $gpslat, $gpslon);
	}
	if ($note) {
		// Archive old note
		LocationSP::archiveLocationNote($locationID, $username);
		LocationSP::updateLocationNote($locationID, $locNote);
	}
} catch (SP_Exception $e) {
	$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : Update GPS Error!');
}

// Instantiate the mail stuff
$contactInfo = LocationSP::getContactInfo($locationID);
$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$mail->FromName = "DART System";
$mail->From = "itadmin@specialtyproduce.com";
$mail->Subject = "Location GPS/Note Update";
$mail->AddAddress($contactInfo['DefaultEmail'], $contactInfo['DefaultName']);
// $mail->AddBCC("christopher@specialtyproduce.com", "Christopher Cilley");

$mail->Body = $contactInfo['DefaultName'] . ",\n\n";
$mail->Body .= "A request to update a location's GPS information and/or Note has been submitted.\n\n";
$mail->Body .= "The system automatically updated the GPS information in the database.\n\nPlease take a moment to review this update.\n\n";
$mail->Body .= "Driver : $username\n";
$mail->Body .= "Location : $locationName\n";
$mail->Body .= "Location ID : $locationID\n";
$mail->Body .= "GPS Latitude : $gpslat\n";
$mail->Body .= "GPS Longitude : $gpslon\n";
$mail->Body .= "Note : \n" . ((strlen($locNote) == 0) ? '<empty note - deleted>' : $locNote) . "\n";
$mail->Body .= "\nPlease be sure to review the updated info using the Access Editors (see attached screenshots).\n";
$mail->addAttachment("images/NoteEditor.png", "NoteEditor.png");
$mail->addAttachment("images/NoteInvoice.png", "NoteInvoice.png");
$mail->Body .= "\nSession ID : $sessionID\n";
$mail->Body .= "iPad ID : $ipadID\n";
$mail->Body .= "\n\n----------\nDebug information : POST DATA : \n" . print_r($_POST, true) . "\n\n";

if (!$mail->Send()) {
	SP_ErrorLogging("Error sending email for $currentScript : " . $mail->ErrorInfo, true, DART_ERROR_LOG);
} else
	echo $goodXML;
$mail->ClearAddresses();

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
