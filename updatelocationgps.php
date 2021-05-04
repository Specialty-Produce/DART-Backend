<?php
include_once 'global_CDC.php';
include 'dart_init.php';
require_once 'classes_SP/class_PHPMailerSP.php';

$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<updatelocationgps status="failed" code="0" retry="false" errmsg="XXX">
</updatelocationgps>
EOT;

$goodXML = <<< EOT
<?xml version="1.0"?>
<updatelocationgps status="success">
</updatelocationgps>
EOT;

$postData = '';
foreach ( $_POST as $key => $val ) {
	$postData .= $key . "=>" . $val . ", ";
}
dartLogging ( $currentScript, "postdata=" . $postData );

// Session ID
$sessionID = filter_input ( INPUT_POST, 'dartsessionid', FILTER_SANITIZE_NUMBER_INT );
if ($sessionID == FALSE || is_null ( $sessionID )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid Session ID', $badXML );
	echo $badXML;
	exit ();
}

// iPadID
$ipadID = filter_input ( INPUT_POST, 'ipadid', FILTER_SANITIZE_STRING );
if ($ipadID == FALSE || is_null ( $ipadID )) {
	$ipadID = "<not set>";
}

// Location ID
$locationID = filter_input ( INPUT_POST, 'locationid', FILTER_SANITIZE_NUMBER_INT );
if ($locationID == FALSE || is_null ( $locationID )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid Location ID', $badXML );
	echo $badXML;
	exit ();
}

// username
$username = filter_input ( INPUT_POST, 'username', FILTER_SANITIZE_STRING );
if ($username == FALSE || is_null ( $username )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid Username', $badXML );
	echo $badXML;
	exit ();
}

// Location Name
$locationName = filter_input ( INPUT_POST, 'locationname', FILTER_SANITIZE_STRING );
if ($locationName == FALSE || is_null ( $locationName )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid Location Name', $badXML );
	echo $badXML;
	exit ();
}

// latitude
$gpslat = filter_input ( INPUT_POST, 'lat', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
if ($gpslat == FALSE || is_null ( $gpslat )) {
	$gpslat = "<not set>";
}

// longitude
$gpslon = filter_input ( INPUT_POST, 'lon', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
if ($gpslon == FALSE || is_null ( $gpslon )) {
	$gpslon = "<not set>";
}

// Note
$note = filter_input ( INPUT_POST, 'note', FILTER_SANITIZE_STRING );
if ($note == FALSE || is_null ( $note )) {
	$note = "<not set>";
}

// Instantiate the mail stuff
$mail = new PHPMailerSP ();
$mail->setApiKey ( 'acct' );
$mail->FromName = "DART System";
$mail->From = "itadmin@specialtyproduce.com";
$mail->Subject = "Location GPS/Note Update";
$mail->AddCC ( "roger@specialtyproduce.com", "Roger Harrington" );
$mail->AddAddress ( "damaris@specialtyproduce.com", "Damaris Jacobo" );

$mail->Body = "A request to update a location's GPS information and/or Note has been submitted.\n\n";
$mail->Body .= "Driver : $username\n";
$mail->Body .= "Location : $locationName\n";
$mail->Body .= "Location ID : $locationID\n";
$mail->Body .= "GPS Latitude : $gpslat\n";
$mail->Body .= "GPS Longitude : $gpslon\n";
$mail->Body .= "Note : \n$note\n";
$mail->Body .= "\nSession ID : $sessionID\n";
$mail->Body .= "iPad ID : $ipadID\n";

if (! $mail->Send ()) {
	SP_ErrorLogging ( "Error sending email for $currentScript", true, DART_ERROR_LOG );
} else
	echo $goodXML;

$mail->ClearAddresses ();
exit ();
?>