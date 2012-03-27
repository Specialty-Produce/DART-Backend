<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
require ('classes_SP/class_invoicePDF.php');
require ('class.phpmailer.php');

// Get the information on the location associated with these invoices
$locInfo = array ();
$sendEmails = array ();
$sendFaxes = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// The XML to get the invoice info
	$invXML = "<ROOT>\n";
	for($i = 1; $i < count ( $argv ); $i ++)
		$invXML .= '<Rec rID="' . $argv [$i] . '"/>' . "\n";
	$invXML .= "</ROOT>\n";
	
	// Get the info
	$stmt = $dbh->query ( "uspDARTSendInvoiceInfo '" . $invXML . "'" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$shipDate = date ( 'n/j/Y', strtotime ( $row ['dtShip'] ) );
		$deliveryDate = date ( 'n/j/Y g:i:s A', strtotime ( $row ['dtDartDelivered'] ) );
		$isDarkStop = ($row ['iSigner'] == DARK_STOP_ID) ? true : false;
		$locInfo [$row ['iSaleID']] = array ('id' => $row ['iLocationDestinationID'], 'saleID' => $row ['iSaleID'], 'name' => $row ['sDescription'], 'address' => $row ['sAddress1'], 'city' => $row ['sCity'], 'state' => $row ['sState'], 'zip' => $row ['sPostalCode'], 'phone' => $row ['sPhone'], 'salesperson' => $row ['txtSalesPerson'], 'salesphone' => $row ['txtCellPhone'], 'salesemail' => $row ['txtSalesEmail'], 'terms' => $row ['sTerms'], 'po' => $row ['sPO'], 'darkstop' => $isDarkStop, 'signer' => $row ['txtSigner'], 'shipdate' => $shipDate, 'deldate' => $deliveryDate, 'greenYTD' => $row ['mYTD'] );
	}
	$stmt->closeCursor ();
	
	// Get the emails
	$stmt = $dbh->query ( "SELECT sEmail, sDescription FROM tblDartInvoiceSendEmails WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row )
		$sendEmails [] = array ('name' => $row ['sDescription'], 'email' => $row ['sEmail'] );
	$stmt->closeCursor ();
	
	// Get the faxes
	$stmt = $dbh->query ( "SELECT sFax, sDescription FROM tblDartInvoiceSendFaxes WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row )
		$sendFaxes [] = array ('name' => $row ['sDescription'], 'fax' => $faxNumber = preg_replace ( '/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $row ['sFax'] ) );
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
	exit ();
}

// Work through each invoice and save the PDF
for($i = 1; $i < count ( $argv ); $i ++) {
	$invNum = $argv [$i];
	
	$lineItems = array ();
	$invTotal = 0.0;
	$trackInvoiceEdits = array ();
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		// set the error reporting attribute.
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		// Get the contents of this catalog, and update the prodList, if needed.
		$sql = "uspWebXFInvoiceDetail " . $invNum;
		$stmt = $dbh->query ( $sql );
		$greenDiscount = 0.0;
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$itemTotal = preg_replace ( '/^\$/', '', $row ['Total'] );
			$invTotal += $itemTotal;
			if ($row ['iProductID'] == 9997)
				$greenDiscount = $itemTotal;
			else
				$lineItems [] = array ('description' => $row ['Description'], 'ordered' => round ( $row ['fOrderQuantity'], 2 ), 'shipped' => round ( $row ['fShipQuantity'], 2 ), 'unitPrice' => sprintf ( "%0.2f", $row ['mUnitPrice'] ), 'itemTotal' => $itemTotal, 'status' => $row ['Status'] );
		}
		$stmt->closeCursor ();
		if ($greenDiscount != 0.0)
			$lineItems [] = array ('description' => 'Green Discount ...', 'ordered' => 1, 'shipped' => 1, 'unitPrice' => sprintf ( "%0.2f", $greenDiscount ), 'itemTotal' => $greenDiscount, 'status' => '' );
		
	// Get the tracking info for initial entry
		$sql = "uspWebXFInvoiceTrackingInfo " . $invNum;
		$stmt = $dbh->query ( $sql );
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$ts = preg_replace ( '/^(.*) (\d+):(\d+):\d+:\d+(.)$/', '$1 $2:$3 $4M', $row ['TimePlace'] );
			$trackInvoiceEntry = array ('source' => $row ['OrderSource'], 'timeStamp' => date ( 'M j, Y g:i A', strtotime ( $ts ) ), 'driver' => $row ['Driver'], 'packer' => $row ['Packer'], 'orderTaker' => $row ['OrderedTaker'], 'ooUser' => $row ['UserNameOrdered'] );
		}
		$stmt->closeCursor ();
		
		// Get the tracking info for edits
		$sql = "uspWebXFInvoiceTrackingEdits " . $invNum;
		$stmt = $dbh->query ( $sql );
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$trackInvoiceEdits [] = array ('modifiedBy' => $row ['ModifiedBy'], 'timeStamp' => $row ['TimeModified'] );
		}
		$stmt->closeCursor ();
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
		SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
		exit ();
	}
	
	$pdf = new invoicePDF ();
	$pdf->setLocation ( $locInfo [$invNum] ['name'], $locInfo [$invNum] ['address'], $locInfo [$invNum] ['city'], $locInfo [$invNum] ['state'], $locInfo [$invNum] ['zip'], formatPhone ( $locInfo [$invNum] ['phone'] ) );
	$pdf->setInvoiceHeader ( $invNum, $locInfo [$invNum] ['shipdate'], $locInfo [$invNum] ['salesperson'], formatPhone ( $locInfo [$invNum] ['salesphone'] ), $locInfo [$invNum] ['po'], $locInfo [$invNum] ['terms'] );
	$pdf->startInvoice ();
	// Item List
	foreach ( $lineItems as $line ) {
		$pdf->addLineItem ( $line ['description'], $line ['ordered'], $line ['shipped'], $line ['unitPrice'], $line ['itemTotal'], $line ['status'] );
	}
	// Invoice Total
	if ($pdf->checkNoSpaceLeft ( 0.2 ))
		$pdf->markContinued ();
	$pdf->addTotal ( $invTotal );
	// Add the signature and signer info
	if ($pdf->checkNoSpaceLeft ( 1.61 ))
		$pdf->markContinued ();
	if ($locInfo [$invNum] ['darkstop']) {
		$sigImage = DART_SIG_DIR . 'darkstop.png';
	} else {
		$sigImage = DART_SIG_DIR . $locInfo [$invNum] ['id'] . '/' . $invNum . '.png';
	}
	$pdf->addSignatureImage ( $sigImage );
	// Add signer info
	$pdf->addSigner ( $locInfo [$invNum] ['signer'], $locInfo [$invNum] ['deldate'] );
	
	// Add the tracking information
	if (isset ( $trackInvoiceEntry )) {
		if ($pdf->checkNoSpaceLeft ( 0.25 ))
			$pdf->markContinued ();
		$pdf->addTrackingInfo ( $trackInvoiceEntry ['source'], (preg_match ( '/Online/', $trackInvoiceEntry ['source'] ) > 0) ? $trackInvoiceEntry ['ooUser'] : $trackInvoiceEntry ['orderTaker'], $trackInvoiceEntry ['timeStamp'], $trackInvoiceEntry ['packer'], $trackInvoiceEntry ['driver'] );
		if (count ( $trackInvoiceEdits ) > 0) {
			if ($pdf->checkNoSpaceLeft ( 0.25 * count ( $trackInvoiceEdits ) ))
				$pdf->markContinued ();
			$pdf->addTrackingEdits ();
			foreach ( $trackInvoiceEdits as $row ) {
				$pdf->addTrackingEditLine ( $row ['modifiedBy'], $row ['timeStamp'] );
			}
		}
	}
	// Output the PDF
	$outFile = DART_PDF_DIR . $invNum . ".pdf";
	$pdf->Output ( $outFile, 'F' );
	$pdf = null;
}

// Keep track of how many places this is sent to successfully.  If we are at "0" at the end, print for sales person
$sendCount = 0;

// Instantiate the mail stuff
$mail = new PHPMailer ();
$mail->IsSMTP ();
$mail->Host = "localhost";
$mail->SMTPAuth = false;

// Send the emails
$emailLogFile = SPConsts::ErrorLogRoot . "dart_emails.txt";
if (count ( $sendEmails ) > 0) {
	$fp = fopen ( $emailLogFile, "a" );
	
	// Subject line
	$subjectStr = (count ( $argv ) == 2) ? 'SP Invoice : ' : 'SP Invoices : ';
	$subjectStr .= $argv [1];
	for($i = 2; $i < count ( $argv ); $i ++)
		$subjectStr .= ', ' . $argv [$i];
	
	fwrite ( $fp, date ( '[d-M-Y H:i:s]' ) . " : " . $subjectStr . " -" );
	
	$mail->FromName = "Specialty Produce Accounting";
	$mail->From = "ar@specialtyproduce.com";
	$mail->Subject = $subjectStr;
	$mail->AddReplyTo ( "ar@specialtyproduce.com", "Specialty Produce Accounting" );
	// Add the PDFs
	foreach ( array_keys ( $locInfo ) as $key ) {
		$outFile = DART_PDF_DIR . $key . ".pdf";
		$mail->AddAttachment ( $outFile, "$key.pdf" );
	}
	// Add the body
	$mail->Body = <<< EOT
Dear Customer, 

Remember, you can always view your invoice history and proof of delivery by logging into your account at www.specialtyproduce.com.

Your invoice is attached. Please make check payable to Specialty Produce and mail it to:
P.O. Box 82951, San Diego, CA 92138

Refer to attached invoice for your payment terms. Payment is due in our office by your payment term.
Should you have any questions, please contact accounting department at AR@SPECIALTYPRODUCE.COM or (619) 876-4070.

Please disregard this email if you have already remit the payment.

We appreciate your business.

Sincerely,
Specialty Produce

EOT;
	
	// Send the emails
	$badEmails = array ();
	foreach ( $sendEmails as $entry ) {
		if (strlen ( $entry ['email'] ) > 0) {
			fwrite ( $fp, " " . $entry ['email'] );
			if ($entry ['email'] == DONT_SEND_INVOICE_EMAIL) {
				$sendCount ++;
			} else {
				$mail->AddAddress ( $entry ['email'], $entry ['name'] );
				if (! $mail->Send ()) {
					$badEmails [] = $entry ['email'];
				} else {
					$sendCount ++;
				}
				$mail->ClearAddresses ();
			}
		}
	}
	$mail->ClearAttachments ();
	fwrite ( $fp, "(" . $sendCount . ")\n" );
	fclose ( $fp );
}

// Fax it
// Can't fax a pdf from within this program.  Need to convert to tiff first.
// Source for the tiff conversion : http://phpdave.wordpress.com/tag/php-pdf-to-tiff/
if (count ( $sendFaxes ) > 0) {
	$invXML = "<ROOT>\n";
	foreach ( $locInfo as $invoice ) {
		$invXML .= '<Rec rID="' . $invoice ['saleID'] . '"/>' . "\n";
		// Convert PDF to TIFF
		$inputPDFFileName = DART_PDF_DIR . $invoice ['saleID'] . ".pdf";
		$outputTiffFileName = DART_PDF_DIR . $invoice ['saleID'] . ".tiff";
		//ghost script command to run
		$cmd = "C:\PROGRA~2\gs\gs9.04\bin\gswin32.exe -q -SDEVICE=tiffg4 -r600x600 -sPAPERSIZE=letter -sOutputFile=$outputTiffFileName -dNOPAUSE -dBATCH  $inputPDFFileName 2>&1";
		$response = shell_exec ( $cmd );
		copy ( $outputTiffFileName, DART_FAX_DIR . $invoice ['saleID'] . ".tiff" );
		sleep ( 3 );
		unlink ( $outputTiffFileName );
	}
	$invXML .= "</ROOT>";
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$sql = "uspDARTFaxListAdd '$invXML'";
		dartLogging ( $currentScript, "    Dart Fax add, sql = " . $invXML );
		$resultFaxAdded = $dbh->exec ( $sql );
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
		SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
		exit ();
	}
	
	if ($resultFaxAdded === false) {
		$errMsg = "uspDARTFaxListAdd $invXML returned FALSE";
		SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
		dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG );
		exit ();
	}
}

// Remove the PDFs
sleep ( 5 );
foreach ( array_keys ( $locInfo ) as $invNum ) {
	$outFile = DART_PDF_DIR . $invNum . ".pdf";
	unlink ( $outFile );
}

exit ( 0 );
?>