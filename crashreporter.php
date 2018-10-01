<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$tempDir = "C:/PHP/temp/";

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<crashreporter status="failed" errmsg="XXX">
</crashreporter>
EOT;

$successXML = <<< EOT
<?xml version="1.0"?>
<crashreporter status="success">
</crashreporter>
EOT;

dartLogging ( $currentScript, "post=" . print_r($_POST, true), $codeStr );

// Get the POST data
// iPad Name
$ipadname = filter_input ( INPUT_POST, 'ipadname', FILTER_SANITIZE_STRING );
if ($ipadname == FALSE || is_null ( $ipadname )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid iPad name', $badXML );
	echo $badXML;
	exit ();
}

// Timestamp
$crashtimestamp = filter_input ( INPUT_POST, 'crashtimestamp', FILTER_SANITIZE_STRING );
if ($crashtimestamp == FALSE || is_null ( $crashtimestamp )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid crash timestamp', $badXML );
	echo $badXML;
	exit ();
}

// Crash Log Name
$crashlogname = filter_input ( INPUT_POST, 'crashlogname', FILTER_SANITIZE_STRING );
if ($crashlogname == FALSE || is_null ( $crashlogname )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid crash log name', $badXML );
	echo $badXML;
	exit ();
}

// Encoded Crash Log
if (isset ( $_POST ['crashlog'] )) {
	$encodedCrashLog = $_POST ['crashlog'];
} else {
	$encodedCrashLog = false;
}
if ($encodedCrashLog == FALSE || is_null ( $encodedCrashLog )) {
	dartLogging ( $currentScript, "    \$encodedCrashLog is FALSE or NULL : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No encoded crash log supplied', $badXML );
	echo $badXML;
	exit ();
}

// Setup email
// Mailer
require_once 'PHPMailer5.2/PHPMailerAutoload.php';
$mail = new PHPMailer ();
$mail->IsSMTP ();
$mail->SMTPOptions = array ('ssl' => array ('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
$mail->Host = SPConsts::PHPMailerHostIP;
$mail->Helo = "vDart-PHP";
$mail->SMTPAuth = false;
$mail->FromName = "Specialty Produce DART";
$mail->From = "itadmin@specialtyproduce.com";
$mail->AddAddress ( "terry.ace.sp2@gmail.com", "Terry Grossman" );
$mail->AddCC ( "christopher@specialtyproduce.com", "Christopher Cilley" );
$mail->Body = "Crash report attached.";

// Work through each crash log
$filename = $tempDir . $crashlogname;
if (file_put_contents ( $filename, $encodedCrashLog ) === false) {
	dartLogging ( $currentScript, "Error writing out binary crash log to $tempDir", $codeStr );
	continue;
}
$mail->Subject = "Crash : $ipadname  : $crashtimestamp";
$mail->AddAttachment ( $filename, $crashlogname );
if (! $mail->Send ()) {
	dartLogging ( $currentScript, "Error sending crash report", $codeStr );
}
$mail->ClearAttachments ();
unlink ( $filename );

echo $successXML;
dartLogging ( $currentScript, "  Success : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
exit ();
?>