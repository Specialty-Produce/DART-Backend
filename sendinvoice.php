<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
require ('fpdf16/fpdf.php');
require ("class.phpmailer.php");

// Static data
$outPath = 'C:/Temp/invoicePDFs/';

// TODO : Add "item notes" on line items with "misc code"


class PDF extends FPDF {
	// Variables
	public $hdrText = array ('Product Description', 'Order', 'Ship', 'Price', ' Extension' );
	public $hdrAlign = array ('L', 'C', 'C', 'C', 'C' );
	public $colWs = array ();
	public $colWspacing = array ();
	public $invHeaderText = array ('Invoice #', 'Invoice Date', 'Sales Person', 'Sales Person Phone', 'Customer P.O.', 'Terms' );
	public $invColWs = array (0.625, 0.875, 1.25, 1.25, 1, 0.75 );
	public $invColSpacing = 0.45;
	public $invHeaderValues = array ();
	public $sigPath = DART_SIG_DIR;
	
	//Page header
	function Header() {
		global $locInfo, $invNum;
		// Left Side Text
		$this->SetFont ( 'Arial', 'B', 12 );
		$this->SetXY ( 0.25, 0.15 );
		//$this->Cell ( 0, 0.2, $foobar ['userLocs'] [$this->locIdx] ['locationDesc'], 0, 1 );
		$this->Cell ( 0, 0.2, $locInfo [$invNum] ['name'], 0, 1 );
		$this->SetFont ( 'Arial', '', 10 );
		$txt = $locInfo [$invNum] ['address'] . "\n";
		$txt .= $locInfo [$invNum] ['city'] . ", " . $locInfo [$invNum] ['state'] . " " . $locInfo [$invNum] ['zip'] . "\n";
		$txt .= formatPhone ( $locInfo [$invNum] ['phone'] );
		$this->MultiCell ( 3.14, 0.15, $txt, 0, 'L' );
		// Right Side Text
		$this->SetFont ( 'Arial', 'B', 12 );
		$widthRt = $this->GetStringWidth ( "Specialty Produce" ) + 0.1;
		$this->SetLeftMargin ( (8.5 - 0.15 - $widthRt) );
		$this->SetY ( 0.15 );
		$this->Cell ( $widthRt, 0.2, "Specialty Produce", 0, 1, 'R' );
		$this->SetFont ( 'Arial', '', 10 );
		$txt = "P.O. Box 82951\n";
		$txt .= "San Diego, CA  92138\n";
		$txt .= "Tel 619.295.3172\n";
		$txt .= "Fax 619.295.9541\n";
		$this->MultiCell ( $widthRt, 0.15, $txt, 0, 'R' );
		$this->SetLeftMargin ( 0.25 );
		//Logo - 2.25 x 0.95 in
		$this->Image ( 'images/sp_logo_lg.jpg', 3.125, 0.1, 2.25 );
		
		// Put the invoice information in the header if this is the first page
		$this->SetXY ( 0.25, $this->GetY () + 0.2 );
		$this->SetFont ( 'Arial', 'B', 10 );
		for($i = 0; $i < 6; $i ++) {
			$this->Cell ( $this->invColWs [$i], 0.15, $this->invHeaderText [$i], 0, 0, 'C', false );
			if ($i < 5)
				$this->Cell ( $this->invColSpacing, 0.15, ' ', 0, 0, 'C', false );
		}
		$this->SetXY ( 0.25, $this->GetY () + 0.2 );
		$this->SetFont ( 'Arial', '', 10 );
		for($i = 0; $i < 6; $i ++) {
			$this->Cell ( $this->invColWs [$i], 0.15, $this->invHeaderValues [$i], 0, 0, 'C', false );
			if ($i < 5)
				$this->Cell ( $this->invColSpacing, 0.15, ' ', 0, 0, 'C', false );
		}
		
		// Put the item headers in place
		$this->SetXY ( 0.25, $this->GetY () + 0.25 );
		$this->SetFont ( 'Arial', 'B', 10 );
		for($i = 0; $i < 5; $i ++) {
			$this->Cell ( $this->colWs [$i], 0.15, $this->hdrText [$i], 'TB', 0, $this->hdrAlign [$i], false );
			if ($i < 4)
				$this->Cell ( $this->colWspacing [$i], 0.15, ' ', 'TB', 0, 'C', false );
		}
		$this->SetXY ( 0.25, $this->GetY () + 0.2 );
	}
	
	//Page footer
	function Footer() {
		// Add the company info
		$this->SetXY ( 0.25, - 1.35 );
		$this->SetFont ( 'Arial', 'B', 8 );
		$infoText = "Comment Hotline 619.876.4067 or comments@specialtyproduce.com\nWe are certified to handle Organics! Our CA Organic Registration Number is 37-1293";
		$this->MultiCell ( 8.0, 0.14, $infoText, 'T', 'C' );
		// Add the disclaimer
		$this->SetFont ( 'Times', 'I', 9 );
		$disclaimer = 'The perishable commodities listed on this invoice are sold subject to the statutory trust authorized by section 5C of The Perishable Agricultural Commodities Act, 1930 (7 U.S.C. 499[E][C]).  The seller of these commodities retains a trust claim over these commodities, all inventories of food or other products derived from these commodities, and any receivables or proceeds from the sale of these commodities until full payment is received.  We reserve the right to protect our P.A.C.A. Trust Fund Benefits on past due accounts over 30 days.  This shipment is in compliance with the public law 98-273 of the P.A.C.A. Trust Provision.  PAYMENT TERMS: NET 21 DAYS.  If no signed letter of terms on file, P.A.C.A. 10 day prompt pay terms apply.  Past due invoices are subject to a FINANCE CHARGE of 1 1/2% (which is an ANNUAL PERCENTAGE RATE OF 18%).  If legal action is taken to collect a past due account, buyer agrees to pay all collection costs and/or all reasonable attorney fees.  ALL CLAIMS MUST BE REPORTED WITHIN 24 HOURS.';
		$this->MultiCell ( 8.0, 0.12, $disclaimer, 'T', 'C' );
		//Page number
		$this->SetFont ( 'Arial', 'B', 9 );
		$this->Cell ( 0, 0.2, 'Page ' . $this->PageNo () . '/{nb}', 'T', 0, 'C' );
	}
}

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
		$deliveryDate = date ( 'n/j/Y g:i:s A', strtotime ( $row ['dtDartDelivered'] ) );
		$locInfo [$row ['iSaleID']] = array ('id' => $row ['iLocationDestinationID'], 'saleID' => $row ['iSaleID'], 'name' => $row ['sDescription'], 'address' => $row ['sAddress1'], 'city' => $row ['sCity'], 'state' => $row ['sState'], 'zip' => $row ['sPostalCode'], 'phone' => $row ['sPhone'], 'salesperson' => $row ['txtSalesPerson'], 'salesphone' => $row ['txtCellPhone'], 'salesemail' => $row ['txtSalesEmail'], 'terms' => $row ['sTerms'], 'po' => $row ['sPO'], 'signer' => $row ['txtSigner'], 'deldate' => $deliveryDate, 'greenYTD' => $row ['mYTD'] );
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
	
	//Column widths
	$colWs = array (4.2, 0.6, 0.6, 0.8, 0.8 );
	$colWspacing = array (0.25, .25, .25, 0.25 );
	$colAlign = array ('L', 'C', 'C', 'R', 'R' );
	
	$pdf = new PDF ( 'P', 'in', 'letter' );
	$pdf->SetFillColor ( 200, 200, 200 );
	$pdf->colWs = $colWs;
	$pdf->colWspacing = $colWspacing;
	$pdf->invHeaderValues [0] = $invNum;
	$pdf->invHeaderValues [1] = $locInfo [$invNum] ['deldate'];
	$pdf->invHeaderValues [2] = $locInfo [$invNum] ['salesperson'];
	$pdf->invHeaderValues [3] = formatPhone ( $locInfo [$invNum] ['salesphone'] );
	$pdf->invHeaderValues [4] = $locInfo [$invNum] ['po'];
	$pdf->invHeaderValues [5] = $locInfo [$invNum] ['terms'];
	$pdf->AliasNbPages ();
	$pdf->SetMargins ( 0.25, 0.15, 0.25 );
	$pdf->SetAutoPageBreak ( true, 0.35 );
	$pdf->AddPage ();
	$pdf->SetFont ( 'Courier', '', 10 );
	
	// Item List
	$lineCount = 0;
	foreach ( $lineItems as $line ) {
		$pdf->Cell ( $colWs [0], 0.185, $line ['description'], 0, 0, $colAlign [0], false );
		$pdf->Cell ( $colWspacing [0], 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $colWs [1], 0.185, $line ['ordered'], 0, 0, $colAlign [1], false );
		$pdf->Cell ( $colWspacing [1], 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $colWs [2], 0.185, $line ['shipped'], 0, 0, $colAlign [2], false );
		$pdf->Cell ( $colWspacing [2], 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $colWs [3], 0.185, sprintf ( "$%8s", $line ['unitPrice'] ), 0, 0, $colAlign [3], false );
		$pdf->Cell ( $colWspacing [3], 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $colWs [4], 0.185, sprintf ( "$%8s", $line ['itemTotal'] ), 0, 0, $colAlign [4], false );
		if ($line ['status'] != '') {
			$pdf->Ln ( 0.185 );
			$pdf->SetFont ( 'Courier', 'I', 9 );
			$pdf->Cell ( 0, 0.185, "    " . $line ['status'], 0, 0, 'L', false );
			$pdf->SetFont ( 'Courier', '', 10 );
		}
		if ($pdf->GetY () > 9.0) {
			$pdf->Ln ( 0.185 );
			$pdf->SetFont ( 'Courier', 'B', 10 );
			$pdf->Cell ( 0, 0.185, 'Continued...', 0, 0, 'R', false );
			$pdf->SetFont ( 'Courier', '', 10 );
			$pdf->SetLeftMargin ( 0.25 );
			$pdf->AddPage ();
		} else {
			$pdf->Ln ( 0.185 );
		}
	}
	// Print the total, make sure we don't have to go to the next page...
	if ($pdf->GetY () > 9.0) {
		$pdf->SetLeftMargin ( 0.25 );
		$pdf->AddPage ();
		$lineCount = 0;
	}
	$pdf->SetFont ( 'Courier', 'B', 12 );
	$pdf->Cell ( 8, 0.2, sprintf ( "Total  :  $%7.2f", $invTotal ), 0, 0, 'R', false );
	
	// Add the signature
	$pdf->Ln ( 0.2 );
	$sigImage = DART_SIG_DIR . $locInfo [$invNum] ['id'] . '/' . $invNum . '.png';
	$pdf->Image ( $sigImage, 1.0, null, 6.5, 1.26 );
	
	// Add signer info
	$pdf->SetXY ( 1, $pdf->GetY () );
	$pdf->SetFont ( 'Arial', 'B', 10 );
	$pdf->Cell ( 1, 0.15, "Received By :", 0, 0, 'L', false );
	$pdf->SetFont ( 'Arial', '', 10 );
	$pdf->Cell ( 3, 0.15, $locInfo [$invNum] ['signer'], 0, 0, 'L', false );
	$pdf->Cell ( 2.5, 0.15, $locInfo [$invNum] ['deldate'], 0, 0, 'R', false );
	
	// Add the tracking information
	$pdf->SetXY ( 0.25, $pdf->GetY () + 0.375 );
	$pdf->Line ( 0.25, $pdf->GetY (), 8.25, $pdf->GetY () );
	//$pdf->SetXY ( 0.25, $pdf->GetY () + 0.05 );
	$pdf->SetFont ( 'Arial', 'B', 9 );
	$invTrackHeader = array ('Order Type', 'Entered By', 'Timestamp', 'Packer', 'Driver' );
	$invTrackColWs = array (1, 1, 1.5, 1.5, 1.5 );
	$invTrackColSpacing = 0.375;
	for($i = 0; $i < 5; $i ++) {
		$pdf->Cell ( $invTrackColWs [$i], 0.15, $invTrackHeader [$i], 0, 0, 'C', false );
		if ($i < 4)
			$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
	}
	if (isset ( $trackInvoiceEntry )) {
		$pdf->SetXY ( 0.25, $pdf->GetY () + 0.15 );
		$pdf->SetFont ( 'Arial', '', 9 );
		$pdf->Cell ( $invTrackColWs [0], 0.15, $trackInvoiceEntry ['source'], 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColWs [1], 0.15, (preg_match ( '/Online/', $trackInvoiceEntry ['source'] ) > 0) ? $trackInvoiceEntry ['ooUser'] : $trackInvoiceEntry ['orderTaker'], 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColWs [2], 0.15, $trackInvoiceEntry ['timeStamp'], 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColWs [3], 0.15, $trackInvoiceEntry ['packer'], 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
		$pdf->Cell ( $invTrackColWs [4], 0.15, $trackInvoiceEntry ['driver'], 0, 0, 'C', false );
		if (count ( $trackInvoiceEdits ) > 0) {
			$pdf->Ln ( 0.2 );
			$pdf->SetFont ( 'Arial', 'B', 10 );
			$pdf->Cell ( 0, 0.2, "Invoice edits made online:", 0, 1 );
			$pdf->SetFont ( 'Arial', '', 9 );
			foreach ( $trackInvoiceEdits as $row ) {
				$pdf->Cell ( 1, 0.15, $row ['modifiedBy'], 0, 0, 'C', false );
				$pdf->Cell ( $invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false );
				$pdf->Cell ( 1.5, 0.15, $row ['timeStamp'], 0, 0, 'C', false );
				$pdf->Ln ( 0.185 );
			}
		}
	}
	
	// Output the PDF
	$outFile = $outPath . $invNum . ".pdf";
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
		$outFile = $outPath . $key . ".pdf";
		$mail->AddAttachment ( $outFile, "$key.pdf" );
	}
	// Add the body
	$mail->Body = <<< EOT
Dear Customer, 

Your invoice is attached. Please make check payable to Specialty Produce and mail it to:
P.O. Box 82951, San Diego, CA 92138

Refer to attached invoice for your payment terms. Payment is due in our office by your payment term.
Should you have any questions, please contact accounting department at AR@SPECIALTYPRODUCE.COM or (619) 876-4070.

We appreciate your business.

Sincerely,
Specialty Produce

EOT;
	
	// Send the emails
	$badEmails = array ();
	foreach ( $sendEmails as $entry ) {
		if (strlen ( $entry ['email'] ) > 0) {
			$mail->AddAddress ( $entry ['email'], $entry ['name'] );
			fwrite ( $fp, " " . $entry ['email'] );
			/*
			if (! $mail->Send ()) {
				$badEmails [] = $entry ['email'];
			} else {
				$sendCount ++;
			}
			*/
			$mail->ClearAddresses ();
		}
	}
	$mail->ClearAttachments ();
	fwrite ( $fp, "(" . $sendCount . ")\n" );
	fclose ( $fp );
}

// Fax it
// Can't fax a pdf from within this program.  Need to convert to tiff first.
// Source for the tiff conversion : http://phpdave.wordpress.com/tag/php-pdf-to-tiff/
$faxLogFile = SPConsts::ErrorLogRoot . "dart_fax.txt";
// Make multiple attempts if a Fax->Submit fails...
$faxTimeoutSleep = 10;
$faxMaxAttempts = 3;
if (count ( $sendFaxes ) > 0) {
	$debugEmailString = "";
	$salesEmail = "";
	$salesName = "";
	foreach ( $sendFaxes as $entry ) {
		foreach ( $locInfo as $invoice ) {
			
			// Convert PDF to TIFF
			$inputPDFFileName = $outPath . $invoice ['saleID'] . ".pdf";
			$outputTiffFileName = $outPath . $invoice ['saleID'] . ".tiff";
			//ghost script command to run
			$cmd = "C:\PROGRA~1\gs\gs9.04\bin\gswin32.exe -q -SDEVICE=tiffg4 -r600x600 -sPAPERSIZE=letter -sOutputFile=$outputTiffFileName -dNOPAUSE -dBATCH  $inputPDFFileName 2>&1";
			$response = shell_exec ( $cmd );
			
			$fp = fopen ( $faxLogFile, "a" );
			fwrite ( $fp, date ( '[d-M-Y H:i:s]' ) . " : " . $invoice ['saleID'] . " : " . $invoice ['name'] . " : " . $entry ['fax'] . " - " );
			// Send the fax
			try {
				$doc = new COM ( "FaxComEx.FaxDocument" );
				$doc->Body = $outputTiffFileName;
				$doc->DocumentName = "Invoice " . $invoice ['saleID'];
				$doc->Recipients->Add ( $entry ['fax'], $invoice ['name'] . $entry ['name'] );
				// FAX_PRIORITY_TYPE_ENUM->fptHIGH = 2 for High Priority
				$doc->Priority = 2;
				$faxFailed = true;
				$faxAttemptCount = 1;
				/*
				while ( $faxFailed ) {
					$faxFailed = false;
					try {
						$doc->Submit ( "" );
					} catch (Exception $e) {
						$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . " faxAttemptCount=$faxAttemptCount : " . $e->getMessage ();
						SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
						if ($faxAttemptCount < $faxMaxAttempts) {
							$faxAttemptCount ++;
							$faxFailed = true;
							sleep($faxTimeoutSleep);
						} else {
							exit();
						}
					}
				}
				*/
				sleep ( 3 );
				$doc = null;
				unlink ( $outputTiffFileName );
				$sendCount ++;
				$debugEmailString .= "\n" . $invoice ['saleID'] . " : " . $invoice ['name'] . " : " . $entry ['fax'];
				$salesEmail = $invoice ['salesemail'];
				$salesName = $invoice ['salesperson'];
				fwrite ( $fp, "success\n" );
				fclose ( $fp );
			} catch ( com_exception $e ) {
				if ($doc)
					$doc = null;
				$errorTxt = "Invoice " . $invoice ['saleID'] . " : " . $entry ['fax'] . " : " . $invoice ['name'] . " : " . $entry ['name'] . "\n";
				$errorTxt .= $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
				SP_ErrorLogging ( $errorTxt, true, DART_ERROR_LOG );
				fwrite ( $fp, "FAIL!!!\n" );
				fclose ( $fp );
			}
		}
	}
	// Send the debugging email
	if ($debugEmailString != "") {
		$mail->FromName = "Specialty Produce IT";
		$mail->From = "itadmin@specialtyproduce.com";
		$mail->Subject = "Fax double check";
		$mail->AddReplyTo ( "itadmin@specialtyproduce.com", "Specialty Produce IT" );
		$mail->Body = <<< EOT
Dear Salesperson,
The following invoices were submitted to our fax server as part of a "Delivery Complete" call in DART.
As part of our testing and debugging process, if you get a chance, can you confirm receipt of one of these:

EOT;
		$mail->Body .= $debugEmailString;
		$mail->Body .= "\n\nYou will only get these emails while we are testing...";
		$mail->AddAddress ( $salesEmail, $salesName );
		$mail->AddAddress ( "bob@specialtyproduce.com", "Bob" );
		if (! $mail->Send ()) {
			SP_ErrorLogging ( "Problem sending debug fax email" . $debugEmailString, true, DART_ERROR_LOG );
		}
		$mail->ClearAddresses ();
	}
}

// If we sent the invoices out at least once, delete the PDFs
if ($sendCount > 0) {
	foreach ( array_keys ( $locInfo ) as $invoice ) {
		$outFile = $outPath . $invNum . ".pdf";
		unlink ( $outFile );
	}
}
exit ( 0 );
?>