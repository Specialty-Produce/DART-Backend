<?php
include_once 'global_CDC.php';

SP_errorLogging ( "testCDC2 started", false );

// Get the addresses
$emailList = array("christopher@specialtyproduce.com", "xtophersd@yahoo.com");

// Set up the mailer with correct default values...
require 'classes_SP/class_PHPMailerSP.php';
$mail = new PHPMailerSP ();
$mail->setApiKey ( 'acct' );

// Send out the emails
$mailCount = 0;
$badEmails = array ();
$mail->FromName = "ITAdmin";
$mail->From = "itadmin@specialtyproduce.com";
$mail->Subject = "TestCDC" . " - " . date('n/j/Y H:i:s') . " - " . $argv[1];
$mail->AddReplyTo ( "itadmin@specialtyproduce.com" );
$mail->Body = "This is the body...";
foreach ( $emailList as $email ) {
	$email = trim($email);
	if (strlen ( $email ) > 0) {
		$mail->AddAddress ( $email );
		if (! $mail->Send ()) {
			$badEmails [] = $email;
		} else {
			$mailCount++;
		}
		$mail->ClearAddresses ();
	}
}
if (count ( $badEmails ) > 0) {
	$errMsg = "Bad emails when sending out : " . "TestCDC" . ":\n";
	foreach ( $badEmails as $email ) {
		$errMsg .= " $email ";
	}
	SP_errorLogging ( $errMsg, true );
}

sleep(10);

SP_errorLogging ( "testCDC2 ended", false );
exit();
?>