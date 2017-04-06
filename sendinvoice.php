<?php
// exit ();
require_once 'global_CDC.php';
require_once 'dart_init.php';
require_once 'classes_SP/class_LocationSP.php';
require_once 'classes_SP/class_invoicePDF.php';
require_once 'classes_SP/class_InvoiceRSI.php';
// Hula Software became Restuarant Matrix - I changed the FTP folder but otherwise left the Hula naming scheme
require_once 'classes_SP/class_InvoiceHula.php';
require_once 'classes_SP/class_InvoiceProfitProPlus.php';
require_once 'classes_SP/class_InvoiceR365.php';
require_once 'EDI_SP.php';
require_once 'classes_SP/class_SP_FTP.php';
require_once 'PHPMailer5.2/PHPMailerAutoload.php';

function sortLineItems ($a, $b) {
	global $useCOG;
	if ($useCOG) {
		$cmpCOG = strnatcmp ( $a ['cogAccount'], $b ['cogAccount'] );
		if ($cmpCOG == 0)
			return strnatcmp ( $a ['description'], $b ['description'] );
		else
			return $cmpCOG;
	} else
		return strnatcmp ( $a ['description'], $b ['description'] );
}

$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
if (preg_match ( '/adhoc/', $currentScript ))
	$adhoc = true;
else
	$adhoc = false;

$debug = false;
/* XXX */
$debugMail = 'christopher@specialtyproduce.com';
$debugName = 'Christopher Cilley';
$debugFax = '';
$pdfMail = true;
$pdfFax = true;
$processEDIs = true;
$rsiMail = true;
$hulaMail = true;
$pppMail = true;
$r365FTP = true;

// $resendArray = array (3123888,3123662,3123908,3123956,3123321);

// foreach ( $resendArray as $resendSaleID ) {

if ($adhoc) {
	// $argv = array('adhoc_sendinvoice.php', 2047355, 2047430, 2047550, 2047868, 2048719, 2048824, 2048999, 2049593, 2050683, 2050957, 2051078);
	/* XXX */
	$resendSaleID = 3126265;
	$argv = array ('adhoc_sendinvoice.php', $resendSaleID);
	echo "<pre>\n";
	echo "Starting...\n\n";
	echo "count = " . count ( $argv ) . "\n";
	
	$debug = true;
	$pdfMail = true;
	$pdfFax = false;
	$processEDIs = false;
	$rsiMail = false;
	$hulaMail = false;
	$pppMail = false;
}

$useCOG = false;

// Get the information on the location associated with these invoices
$locInfo = array ();
$sendEmails = array ();
$sendFaxes = array ();
$offLinePOs = array ();
$offLinePOcount = 1;
$rsiID = 0;
$hulaID = 0;
$r365ID = '';
$pppEmails = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// The XML to get the invoice info
	$invXML = "<ROOT>\n";
	for($i = 1; $i < count ( $argv ); $i ++)
		$invXML .= '<Rec rID="' . $argv [$i] . '"/>' . "\n";
	$invXML .= "</ROOT>\n";
	if ($adhoc) {
		echo "\$invXML = " . htmlentities ( $invXML ) . "\n";
	}
	// Get the info
	$stmt = $dbh->query ( "uspDARTSendInvoiceInfo '" . $invXML . "'" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$shipDate = date ( 'n/j/Y', strtotime ( $row ['dtShip'] ) );
		$deliveryDate = date ( 'n/j/Y g:i:s A', strtotime ( $row ['dtDartDelivered'] ) );
		$isDarkStop = ($row ['iSigner'] == DARK_STOP_ID) ? true : false;
		// Determine if this is an offline PO for an EDI
		$ediID = trim ( $row ['sInterchangeID'] );
		$POnumber = trim ( $row ['sPO'] );
		if (strlen ( $ediID ) > 0 && strlen ( $POnumber ) == 0) {
			$requireEDIPO = constant ( 'EDISPConsts::' . $ediID . "_REQUIREPO" );
			if ($requireEDIPO != null) {
				$POnumber = 'SP-' . date ( 'ymdHi' ) . '-' . sprintf ( "%02d", $offLinePOcount );
				$offLinePOs [$row ['iSaleID']] = $POnumber;
				$offLinePOcount ++;
			}
		}
		$showProdID = ($row ['iShowProductID'] == - 1) ? true : false;
		$locInfo [$row ['iSaleID']] = array ('id' => $row ['iLocationDestinationID'], 'saleID' => $row ['iSaleID'], 'name' => $row ['sDescription'], 'address' => $row ['sAddress1'], 'city' => $row ['sCity'], 'state' => $row ['sState'], 'zip' => $row ['sPostalCode'], 'phone' => $row ['sPhone'], 
				'salesperson' => $row ['txtSalesPerson'], 'salesphone' => $row ['txtCellPhone'], 'salesemail' => $row ['txtSalesEmail'], 'terms' => $row ['sTerms'], 'po' => $POnumber, 'darkstop' => $isDarkStop, 'signer' => $row ['txtSigner'], 'shipdate' => $shipDate, 'deldate' => $deliveryDate, 
				'greenYTD' => $row ['mYTD'], 'ediID' => $ediID, 'showProdID' => $showProdID);
	}
	$stmt->closeCursor ();
	
	// Get the emails
	$stmt = $dbh->query ( "SELECT sEmail, sDescription FROM tblDartInvoiceSendEmails WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row )
		$sendEmails [] = array ('name' => $row ['sDescription'], 'email' => $row ['sEmail']);
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$sendEmails = array ();
			if ($pdfMail)
				$sendEmails [] = array ('name' => $debugName, 'email' => $debugMail);
		}
	} else {
		if ($debug)
			$sendEmails [] = array ('name' => $debugName, 'email' => $debugMail);
	}
	
	// Get the faxes
	$stmt = $dbh->query ( "SELECT sFax, sDescription FROM tblDartInvoiceSendFaxes WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row )
		$sendFaxes [] = array ('name' => $row ['sDescription'], 'fax' => $faxNumber = preg_replace ( '/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $row ['sFax'] ));
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$sendFaxes = array ();
			if ($pdfFax)
				$sendFaxes [] = array ('name' => $debugName, 'fax' => $faxNumber = preg_replace ( '/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $debugFax ));
		}
	} else {
		if ($debug && strlen ( $debugFax ) == 10)
			$sendFaxes [] = array ('name' => $debugName, 'fax' => $faxNumber = preg_replace ( '/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $debugFax ));
	}
	
	// RSI ID
	$stmt = $dbh->query ( "SELECT iRSIID FROM tblDartInvoiceSendRSI WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	$rsiID = ($result ['iRSIID'] > 0) ? $result ['iRSIID'] : 0;
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$rsiID = 0;
		}
	}
	
	// Hula ID
	$stmt = $dbh->query ( "SELECT iLocationID FROM tblDartInvoiceSendHula WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	$hulaID = ($result ['iLocationID'] > 0) ? $result ['iLocationID'] : 0;
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$hulaID = 0;
		}
	}
	
	// Profit Pro Plus
	$stmt = $dbh->query ( "SELECT iLocationID, tEmails FROM tblDartInvoiceSendProfitProPlus WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	$pppEmails = ($result ['iLocationID'] > 0) ? explode ( ',', $result ['tEmails'] ) : array ();
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$pppEmails = array ();
			if ($pppMail)
				$pppEmails [] = array ('name' => $debugName, 'email' => $debugMail);
		}
	}
	
	// Restaurant 365
	$stmt = $dbh->query ( "SELECT iLocationID, sR365ID FROM tblDartInvoiceSendR365 WHERE iLocationID=" . $locInfo [$argv [1]] ['id'] );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	$r365ID = ($result ['iLocationID'] > 0) ? $result ['sR365ID'] : '';
	$stmt->closeCursor ();
	if ($adhoc) {
		if ($debug) {
			$r365ID = '';
		}
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
	exit ();
}

// Get the COG Accounts information
try {
	list ( $useCOG, $cogShowZeroTotal, $cogMaster, $cogLocation ) = LocationSP::getCOGSetup ( $locInfo [$argv [1]] ['id'] );
} catch ( SP_Exception $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
	exit ();
}

// Work through each invoice and save the PDF
if ($pdfMail || $pdfFax) {
	for($i = 1; $i < count ( $argv ); $i ++) {
		$invNum = $argv [$i];
		
		$lineItems = array ();
		$invTotal = 0.0;
		$trackInvoiceEdits = array ();
		try {
			$dbh = new PDO ( 'spdb', '', '' );
			// set the error reporting attribute.
			$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			
			// Get the line items of the invoice.
			$sql = "uspWebXFInvoiceDetail " . $invNum;
			$stmt = $dbh->query ( $sql );
			foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
				$itemTotal = preg_replace ( '/^\$/', '', $row ['Total'] );
				$invTotal += $itemTotal;
				if ($useCOG) {
					$cogAccount = ($cogMaster [$row ['iCOGMasterID']] != null && $cogMaster [$row ['iCOGMasterID']] != 0) ? $cogMaster [$row ['iCOGMasterID']] : 0;
					$cogLocation [$cogAccount] ['count'] += 1;
					$cogLocation [$cogAccount] ['total'] += $itemTotal;
				} else {
					$cogAccount = '';
				}
				if ($row ['iProductID'] == 9997)
					$lineItems [] = array ('prodID' => 9997, 'unitID' => 'ea', 'description' => 'Green Discount ...', 'ordered' => 1, 'shipped' => 1, 'unitPrice' => sprintf ( "%0.2f", $itemTotal ), 'itemTotal' => $itemTotal, 'status' => '', 'cogAccount' => $cogAccount);
				else
					$lineItems [] = array ('description' => $row ['Description'], 'ordered' => round ( $row ['fOrderQuantity'], 2 ), 'shipped' => round ( $row ['fShipQuantity'], 2 ), 'unitPrice' => sprintf ( "%0.2f", $row ['mUnitPrice'] ), 'itemTotal' => $itemTotal, 'status' => $row ['Status'], 
							'prodID' => $row ['iProductID'], 'cogAccount' => $cogAccount);
			}
			$stmt->closeCursor ();
			
			// Get the tracking info for initial entry
			$sql = "uspWebXFInvoiceTrackingInfo " . $invNum;
			$stmt = $dbh->query ( $sql );
			foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
				$ts = preg_replace ( '/^(.*) (\d+):(\d+):\d+:\d+(.)$/', '$1 $2:$3 $4M', $row ['TimePlace'] );
				$trackInvoiceEntry = array ('source' => $row ['OrderSource'], 'timeStamp' => date ( 'M j, Y g:i A', strtotime ( $ts ) ), 'driver' => $row ['Driver'], 'packer' => $row ['Packer'], 'orderTaker' => $row ['OrderedTaker'], 'ooUser' => $row ['UserNameOrdered']);
			}
			$stmt->closeCursor ();
			
			// Get the tracking info for edits
			$sql = "uspWebXFInvoiceTrackingEdits " . $invNum;
			$stmt = $dbh->query ( $sql );
			foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
				$trackInvoiceEdits [] = array ('modifiedBy' => $row ['ModifiedBy'], 'timeStamp' => $row ['TimeModified']);
			}
			$stmt->closeCursor ();
			
			$dbh = null;
		} catch ( PDOException $e ) {
			$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
			SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
			exit ();
		}
		
		if ($useCOG)
			usort ( $lineItems, 'sortLineItems' );
		
		$pdf = new invoicePDF ();
		$pdf->setLocation ( $locInfo [$invNum] ['name'], $locInfo [$invNum] ['address'], $locInfo [$invNum] ['city'], $locInfo [$invNum] ['state'], $locInfo [$invNum] ['zip'], formatPhone ( $locInfo [$invNum] ['phone'] ) );
		$pdf->setInvoiceHeader ( $invNum, $locInfo [$invNum] ['shipdate'], $locInfo [$invNum] ['salesperson'], formatPhone ( $locInfo [$invNum] ['salesphone'] ), $locInfo [$invNum] ['po'], $locInfo [$invNum] ['terms'] );
		if ($locInfo [$invNum] ['showProdID'])
			$pdf->showProdID ();
		$pdf->startInvoice ();
		// Item List
		$cogLastAccount = '';
		foreach ( $lineItems as $line ) {
			if ($useCOG && $line ['cogAccount'] !== $cogLastAccount) {
				$cogLastAccount = $line ['cogAccount'];
				$pdf->addCOGAccountLine ( $cogLastAccount . ' - ' . $cogLocation [$cogLastAccount] ['description'] );
			}
			$pdf->addLineItem ( $line ['description'], $line ['ordered'], $line ['shipped'], $line ['unitPrice'], $line ['itemTotal'], $line ['status'], $line ['prodID'] );
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
		// Add COG info
		if ($useCOG) {
			// Determine how many lines of space we need
			if ($cogShowZeroTotal)
				$numCOGLines = count ( $cogLocation );
			else {
				$numCOGLines = 0;
				foreach ( $cogLocation as $entry ) {
					$numCOGLines += ($entry ['count'] > 0) ? 1 : 0;
				}
			}
			if ($pdf->checkNoSpaceLeft ( 0.2 * ($numCOGLines + 2) ))
				$pdf->markContinued ();
				// Calc max Account length
			$pdf->SetFont ( 'Arial', 'B', 12 );
			$maxLengthAccount = $pdf->GetStringWidth ( 'Account' );
			$maxLengthDesc = $pdf->GetStringWidth ( 'Description' );
			$maxLengthTotal = $pdf->GetStringWidth ( 'Amount' );
			foreach ( $cogLocation as $accKey => $entry ) {
				if ($cogShowZeroTotal == false && $cogLocation [$accKey] ['count'] == 0)
					continue;
				$tmpLen = $pdf->GetStringWidth ( $accKey );
				$maxLengthAccount = ($tmpLen > $maxLengthAccount) ? $tmpLen : $maxLengthAccount;
				$tmpLen = $pdf->GetStringWidth ( $entry ['description'] );
				$maxLengthDesc = ($tmpLen > $maxLengthDesc) ? $tmpLen : $maxLengthDesc;
				$tmpLen = $pdf->GetStringWidth ( sprintf ( '%0.2f', $entry ['total'] ) );
				$maxLengthTotal = ($tmpLen > $maxLengthTotal) ? $tmpLen : $maxLengthTotal;
			}
			$pdf->addCOGHeader ( $maxLengthAccount, $maxLengthDesc, $maxLengthTotal );
			foreach ( $cogLocation as $accKey => $entry ) {
				if ($accKey == '0' && $entry ['count'] == 0)
					continue;
				if ($cogShowZeroTotal == false && $cogLocation [$accKey] ['count'] == 0)
					continue;
				$pdf->addCOGLine ( $accKey, $entry ['description'], sprintf ( '%0.2f', $entry ['total'] ) );
			}
			$pdf->addCOGTotal ( sprintf ( "%0.2f", $invTotal ) );
		}
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
}

// Keep track of how many places this is sent to successfully. If we are at "0" at the end, print for sales person
$sendCount = 0;

// Instantiate the mail stuff
$mail = new PHPMailer ();
$mail->IsSMTP ();
$mail->SMTPOptions = array ('ssl' => array ('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
$mail->Host = SPConsts::PHPMailerHostIP;
$mail->Helo = "vDart-PHP";
// $mail->Host = "localhost";
$mail->SMTPAuth = false;

// Send the emails
if ($pdfMail) {
	if ($adhoc)
		echo "Sending PDFs via email...\n";
	$emailLogFile = SPConsts::ErrorLogRoot . "dart_emails.txt";
	if (count ( $sendEmails ) > 0) {
		$fp = fopen ( $emailLogFile, "a" );
		
		// Subject line
		$subjectStr = (count ( $argv ) == 2) ? 'SP Invoice : ' : 'SP Invoices : ';
		$subjectStr .= $argv [1];
		$subjectStr .= (strlen ( $locInfo [$argv [1]] ['po'] ) > 0) ? ' (' . $locInfo [$argv [1]] ['po'] . ')' : '';
		for($i = 2; $i < count ( $argv ); $i ++) {
			$subjectStr .= ', ' . $argv [$i];
			$subjectStr .= (strlen ( $locInfo [$argv [$i]] ['po'] ) > 0) ? ' (' . $locInfo [$argv [$i]] ['po'] . ')' : '';
		}
		
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
		
		// Send the emails
		$badEmails = array ();
		if ($debug)
			$mail->AddBCC ( $debugMail, $debugName );
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
					$mail->ClearBCCs ();
				}
			}
		}
		$mail->ClearAttachments ();
		fwrite ( $fp, "(" . $sendCount . ")\n" );
		fclose ( $fp );
	}
	$mail->ClearAllRecipients ();
} else {
	if ($adhoc)
		echo "NOT sending PDFs via email...\n";
}

// Fax it
if ($pdfFax) {
	if ($adhoc)
		echo "Sending PDFs via fax...\n";
		// Can't fax a pdf from within this program. Need to convert to tiff first.
		// Source for the tiff conversion : http://phpdave.wordpress.com/tag/php-pdf-to-tiff/
	if (count ( $sendFaxes ) > 0) {
		$invXML = "<ROOT>\n";
		foreach ( $locInfo as $invoice ) {
			$invXML .= '<Rec rID="' . $invoice ['saleID'] . '"/>' . "\n";
			// Convert PDF to TIFF
			$inputPDFFileName = DART_PDF_DIR . $invoice ['saleID'] . ".pdf";
			$outputTiffFileName = DART_PDF_DIR . $invoice ['saleID'] . ".tiff";
			// ghost script command to run
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
			// dartLogging ( $currentScript, " Dart Fax add, sql = " . $invXML );
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
} else {
	if ($adhoc)
		echo "NOT sending PDFs via fax...\n";
}

// Process the EDI invoices
if ($processEDIs) {
	if ($adhoc)
		echo "Sending via EDI...\n";
	$ftpConnector = new SP_FTP ();
	foreach ( $locInfo as $loc ) {
		if (strlen ( $loc ['ediID'] ) > 0) {
			$saleID = $loc ['saleID'];
			if (array_key_exists ( $saleID, $offLinePOs )) {
				// Newly generated Offline PO
				try {
					$dbh = new PDO ( 'spdb', '', '' );
					$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
					$stmt = $dbh->query ( "uspEDIOfflinePORegister " . $saleID . ", '" . $offLinePOs [$saleID] . "'" );
					$opoEmails = $stmt->fetchAll ( PDO::FETCH_BOTH );
					$dbh = null;
				} catch ( PDOException $e ) {
					$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
					SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
					exit ();
				}
				if (count ( $opoEmails ) > 0) {
					$fp = fopen ( $emailLogFile, "a" );
					$subjectStr = "SP Offline PO : " . $offLinePOs [$saleID];
					fwrite ( $fp, date ( '[d-M-Y H:i:s]' ) . " : " . $subjectStr . " -" );
					
					$mail->FromName = "Specialty Produce Accounting";
					$mail->From = "ar@specialtyproduce.com";
					$mail->Subject = $subjectStr;
					$mail->AddReplyTo ( "ar@specialtyproduce.com", "Specialty Produce Accounting" );
					if ($debug)
						$mail->AddBCC ( $debugMail, $debugName );
						// Add the PDFs
					$mail->AddAttachment ( DART_PDF_DIR . $saleID . ".pdf", "$saleID.pdf" );
					// Add the body
					$mail->Body = <<< EOT
Dear Customer,

The attached invoice did not have a PO number.
Since you use a buying group that requires us to transfer POs and invoices with them electronically, we have generated an offline PO number for this invoice.

Please make sure you take the appropriate steps in your buying group's online system to accept this PO:

EOT;
					$mail->Body .= "\tInvoice # " . $saleID . ", PO # " . $offLinePOs [$saleID] . "\n";
					$mail->Body .= <<< EOT

We appreciate your business.

Sincerely,
Specialty Produce

EOT;
					$sendCount = 0;
					foreach ( $opoEmails as $entry ) {
						fwrite ( $fp, " " . $entry ['txtEmail'] );
						$mail->AddAddress ( $entry ['txtEmail'], $entry ['txtName'] );
						if ($mail->Send ()) {
							$sendCount ++;
						}
						$mail->ClearAddresses ();
					}
					$mail->ClearAttachments ();
					$mail->ClearBCCs ();
					fwrite ( $fp, "(" . $sendCount . ")\n" );
					fclose ( $fp );
				}
			}
			// Send the PO
			include_once 'EDI_SP\Receivers\\' . $loc ['ediID'] . '\ts810.php';
			$tsFunction = $loc ['ediID'] . '_810';
			list ( $success, $msg ) = $tsFunction ( $loc ['saleID'] );
			if (! $success) {
				$errMsg = "$tsFunction returned false : $msg";
				$errMsg .= "\nsaleID = " . $loc ['saleID'] . ", ediID = " . $loc ['ediID'];
				SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
				continue;
			}
			if (strlen ( $msg ) == 0) {
				$errMsg = "$tsFunction returned empty EDI string";
				$errMsg .= "\nsaleID = " . $loc ['saleID'] . ", ediID = " . $loc ['ediID'];
				SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
				continue;
			}
			$outFileName = 'O_SP_' . date ( 'ymd_His' ) . '.810';
			$outPath = EDISPConsts::FTP_ROOT . $loc ['ediID'] . '\\outgoing\\';
			$outFile = $outPath . $outFileName;
			if (! file_put_contents ( $outFile, $msg )) {
				$errMsg = "Error writing outgoing 810 : $outFile" . "\nfor invoice # " . $loc ['saleID'];
				SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
				continue;
			}
			// Set up the ftp connection, if needed
			$sentSuccessfully = true;
			if (constant ( 'EDISPConsts::' . $loc ['ediID'] . "_SENDFTP" )) {
				$ftpConnector->server = constant ( 'EDISPConsts::' . $loc ['ediID'] . "_FTP" );
				$ftpConnector->username = constant ( 'EDISPConsts::' . $loc ['ediID'] . "_USERNAME" );
				$ftpConnector->password = constant ( 'EDISPConsts::' . $loc ['ediID'] . "_PASSWORD" );
				try {
					$ftpConnector->sendFile ( $outPath, $outFileName );
				} catch ( SP_Exception $spe ) {
					SP_ErrorLogging ( $spe, true, DART_ERROR_LOG, "DART Error : cURL send" );
					$sentSuccessfully = false;
				}
				if ($sentSuccessfully) {
					dartLogging ( $currentScript, "    Successfully FTP'd " . $outFile );
				} else {
					$errMsg = "File not sent successfully, moved to flagged folder on vDart:\n$outFile\nfor invoice # " . $loc ['saleID'];
					SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, $currentScript . " - EDI error" );
					flagFTPFile ( $loc ['ediID'], $outFile );
				}
			}
			// Save the outgoing file to the EDI dir
			if ($sentSuccessfully) {
				$savePath = EDISPConsts::EDI_SAVE_DIR . $loc ['ediID'] . '\\outgoing\\' . $outFileName;
				if (! copy ( $outFile, $savePath )) {
					$errMsg = "Error saving $outFile to $savePath\nfor invoice # " . $loc ['saleID'];
					SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
					continue;
				}
				if ($adhoc)
					echo "Sent $outFileName for saleID = $saleID\n";
					// Unlink the file if we sent it, otherwise it will sit waiting to be picked up and subsequently deleted.
				if (constant ( 'EDISPConsts::' . $loc ['ediID'] . "_SENDFTP" )) {
					if (! unlink ( $outFile )) {
						$errMsg = "Error unlinking $outFile\nfor invoice # " . $loc ['saleID'];
						SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
						continue;
					}
				}
			}
		}
		// Need to sleep 2 seconds so we don't overwrite a file
		sleep ( 2 );
	}
	$mail->ClearAllRecipients ();
} else {
	if ($adhoc)
		echo "NOT sending via EDI...\n";
}

// Process RSI Invoices
if ($rsiMail) {
	if ($adhoc)
		echo "Sending via RSI...\n";
	if ($rsiID > 0) {
		$rsiLocationName = $locInfo [$argv [1]] ['name'];
		// $rsiMailtoAddress = "xtophersd@yahoo.com";
		$rsiMailtoAddress = $rsiID . "@restacct.com";
		$rsiFilename = preg_replace ( '/[^a-zA-Z0-9]/', '', $rsiLocationName ) . '_' . date ( 'Ymd_Hi' ) . '.txt';
		$rsiFile = DART_RSI_DIR . $rsiFilename;
		$rsiFH = fopen ( $rsiFile, "w" );
		foreach ( $locInfo as $loc ) {
			$invRSI = new InvoiceRSI ();
			try {
				$invRSI->retrieveInvoice ( $loc ['saleID'] );
				fwrite ( $rsiFH, $invRSI->generateRSIOutput () );
			} catch ( SP_Exception $spe ) {
				$errMsg = "RSI : Retrieve invoice error : " . $spe->getMessage ();
				SP_errorLogging ( $errMsg, true, '', $currentScript . " - RSI error" );
				continue;
			}
		}
		fclose ( $rsiFH );
		$mail->FromName = "Specialty Produce Accounting";
		$mail->From = "ar@specialtyproduce.com";
		$mail->AddAddress ( $rsiMailtoAddress );
		if ($debug)
			$mail->AddBCC ( $debugMail, $debugName );
		$mail->Subject = "Specialty Produce Imported Invoice";
		$mail->AddReplyTo ( "ar@specialtyproduce.com", "Specialty Produce Accounting" );
		$mail->AddAttachment ( $rsiFile, $rsiFilename );
		// Add the body
		$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $rsiLocationName . "\n - Specialty Produce System";
		// Send the email
		if (! $mail->Send ()) {
			$errMsg = "RSI : Send mail error : " . $rsiMailtoAddress;
			SP_errorLogging ( $errMsg, true, '', $currentScript . " - RSI error" );
		}
		$mail->ClearAttachments ();
		$mail->ClearAllRecipients ();
		sleep ( 3 );
		unlink ( $rsiFile );
	}
	$mail->ClearAllRecipients ();
} else {
	if ($adhoc)
		echo "NOT sending via RSI...\n";
}

// Process Hula Invoices
if ($hulaMail) {
	if ($adhoc)
		echo "Sending via HULA...\n";
	if ($hulaID > 0) {
		$hulaFilenamePrefix = preg_replace ( '/[^a-zA-Z0-9_-]/', '', preg_replace ( '/\s/', '_', $locInfo [$argv [1]] ['name'] ) );
		foreach ( $locInfo as $loc ) {
			$invHula = new InvoiceHula ();
			try {
				$invHula->retrieveInvoice ( $loc ['saleID'] );
				$hulaFile = DART_HULA_DIR . $hulaFilenamePrefix . "_" . $loc ['saleID'] . ".txt";
				$hulaFH = fopen ( $hulaFile, "w" );
				fwrite ( $hulaFH, $invHula->generateHulaOutput () );
				fclose ( $hulaFH );
			} catch ( SP_Exception $spe ) {
				$errMsg = "Hula : Retrieve invoice error : " . $spe->getMessage ();
				SP_errorLogging ( $errMsg, true, '', $currentScript . " - Hula error" );
				continue;
			}
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending via HULA...\n";
}

// Process Profit Pro Plus Invoices
if ($pppMail) {
	if ($adhoc)
		echo "Sending via PPM...\n";
	if (count ( $pppEmails ) > 0) {
		// Start
		$pppLocationName = $locInfo [$argv [1]] ['name'];
		$mail->FromName = "Specialty Produce Accounting";
		$mail->From = "ar@specialtyproduce.com";
		$mail->Subject = "$pppLocationName : Specialty Produce Imported Invoice";
		$mail->AddReplyTo ( "ar@specialtyproduce.com", "Specialty Produce Accounting" );
		// Add the body
		$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $pppLocationName . "\n - Specialty Produce System";
		
		foreach ( $locInfo as $loc ) {
			// Add the Addresses
			foreach ( $pppEmails as $pppMailToAddress )
				$mail->AddAddress ( $pppMailToAddress );
			if ($debug)
				$mail->AddBCC ( $debugMail, $debugName );
				// Generate the file
			$pppFileName = $loc ['saleID'] . '.txt';
			$pppFile = DART_PPP_DIR . $pppFileName;
			$pppFH = fopen ( $pppFile, "w" );
			$invPPP = new InvoiceProfitProPlus ();
			try {
				$invPPP->retrieveInvoice ( $loc ['saleID'] );
				fwrite ( $pppFH, $invPPP->generateProfitProPlusOutput () );
			} catch ( SP_Exception $spe ) {
				$errMsg = "PPP : Retrieve invoice error : " . $spe->getMessage ();
				SP_errorLogging ( $errMsg, true, '', $currentScript . " - PPP error" );
				continue;
			}
			fclose ( $pppFH );
			$mail->AddAttachment ( $pppFile, $pppFileName );
			// Send the email
			if (! $mail->Send ()) {
				$errMsg = "PPP : Send mail error : " . implode ( ',', $pppEmails );
				SP_errorLogging ( $errMsg, true, '', $currentScript . " - PPP error" );
			}
			$mail->ClearAllRecipients ();
			$mail->ClearAttachments ();
		}
		sleep ( 3 );
		foreach ( $locInfo as $loc ) {
			$pppFileName = $loc ['saleID'] . '.txt';
			$pppFile = DART_PPP_DIR . $pppFileName;
			unlink ( $pppFile );
		}
	}
	$mail->ClearAllRecipients ();
	$mail->ClearAttachments ();
} else {
	if ($adhoc)
		echo "NOT sending via PPM...\n";
}

if ($r365FTP) {
	if ($adhoc)
		echo "Sending via FTP to R365...\n";
	if (strlen ( $r365ID ) > 0) {
		$invR365 = new InvoiceR365 ( $r365ID );
		foreach ( $locInfo as $loc ) {
			// Retrieve and add to CSV each invoice
			$invR365->retrieveInvoice ( $loc ['saleID'] );
			$invR365->generateCSVLineItems ();
			$invR365->detail = array ();
		}
		try {
			$invR365->ftpCSV ();
		} catch ( SP_Exception $spe ) {
			$errMsg = "R365 : FTP CSV error : " . $spe->getMessage ();
			SP_errorLogging ( $errMsg, true, '', $currentScript . " - R365 error" );
			continue;
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending via FTP to R365...\n";
}

// Remove the PDFs
// Do not remove the PDF files, we're going to let them stay for 90 days and delete them with a Scheduled Task
if ($adhoc) {
	echo "Done...";
	echo "</pre>\n";
}
// }
exit ( 0 );
?>