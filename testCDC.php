<?php
echo "exit...";
exit();
require_once 'global_CDC.php';
require_once 'dart_init.php';
require_once 'classes_SP/class_PHPMailerSP.php';
// Instantiate the mail stuff
$mail = new PHPMailerSP();
$mail->setApiKey('acct');

$mailBody = <<< EOT
This is a test email to see if the mailer is working.

This email sent exactly the same way as invoice emails.  Except return email is christopher instead of ar.

EOT;

$mail->FromName = "Christopher Cilley";
$mail->From = "christopher@specialtyproduce.com";
$mail->Subject = 'Test Email - ' . date('YmdHi');
$mail->AddReplyTo("christopher@specialtyproduce.com", "Christopher Cilley");
// Add the PDFs
$mail->AddAttachment('images/TestInvoice.pdf', "TestInvoice.pdf");
$mail->Body = $mailBody;
// Addresses
$mail->AddAddress('christopher@specialtyproduce.com', 'Christopher Cilley');
$mail->AddAddress('Mark.Kropczynski@luxurycollection.com', 'Mark Kropczynski');
// Send
if (!$mail->Send()) {
	echo "Failed to send email.  Error: " . $mail->ErrorInfo . "\n";
} else {
	echo "Email sent successfully.\n";
}
