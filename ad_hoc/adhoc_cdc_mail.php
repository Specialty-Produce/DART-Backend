<?php
include_once 'global_CDC.php';
require_once 'classes_SP/class_PHPMailerSP.php';

$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$mail->FromName = "Specialty Produce Accounting";
$mail->From = "ar@specialtyproduce.com";
$mail->Subject = $subjectStr;
$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
$mail->Subject = "Test " . generateRandomCode(6) . " - Invoice Attached - Specialty Produce";
$mailBody = <<< EOT
Dear Customer,

Your latest signed invoice is attached.  Remember, you can always view your invoice history and proof of delivery by logging into your account at www.specialtyproduce.com.

Refer to attached invoice for your payment terms. Payment is due in our office by your payment terms date.

You can remit payment two ways...
+ Online : Simply log into your account at www.specialtyproduce.com and click the green bar for "Accounting - Online Bill Pay".  Please note, you will need to first contact our accounting department at ar@specialtyproduce.com or (619) 876-4070 to activate your account for online bill pay.
+ Mail : Make checks payable to Specialty Produce and mail it to: P.O. Box 82066, San Diego, CA 92138

Should you have any questions, please contact accounting department at ar@specialtyproduce.com or (619) 876-4070.

We appreciate your business.

Sincerely,
Specialty Produce
EOT;
$mail->Body = $mailBody;
$outFile = "/home/site/Temp/6990194.pdf";
$mail->AddAttachment($outFile, date('YmdHi') . "_inv.pdf");
$mail->AddAddress('cdcilley@gmail.com', 'Christopher Cilley');
$mail->addAddress('xtophersd@yahoo.com', 'Xtopher Cilley');
$mail->addBCC('christopher@specialtyproduce.com');
if (!$mail->Send()) {
    echo "Mailer Error: " . $mail->ErrorInfo;
} else {
    echo "Sent OK...";
}
