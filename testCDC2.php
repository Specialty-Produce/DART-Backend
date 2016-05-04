<?php
include_once 'global_CDC.php';

SP_errorLogging ( "testCDC2 started", false );

// Get the addresses
$emailList = array("christopher@specialtyproduce.com", "xtophersd@yahoo.com");

// Set up the mailer with correct default values...
require_once 'PHPMailer5.2/PHPMailerAutoload.php';
$mail = new PHPMailer ();
$mail->IsSMTP ();
$mail->SMTPOptions = array ('ssl' => array ('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
$mail->Host = SPConsts::PHPMailerHostIP;
$mail->Helo = "vDart-PHP";
$mail->SMTPAuth = false;

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