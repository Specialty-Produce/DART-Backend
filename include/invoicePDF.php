<?php
require('fpdf16/fpdf.php');

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
		$txt = "P.O. Box 82066\n";
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

// Variables coming from including files
global $locIdx;
global $headerAlign;
global $invNum;
global $invDate;
global $poNum;
global $invTotal;
global $trackInvoiceEntry;
global $trackInvoiceEdits;
global $lineItems;

//Column widths
$colWs = array(4.2, 0.6, 0.6, 0.8, 0.8);
$colWspacing = array(0.25, .25, .25, 0.25);
$colAlign = array('L', 'C', 'C', 'R', 'R');

$pdf=new PDF('P', 'in', 'letter');
$pdf->SetFillColor(200, 200, 200);
$pdf->locIdx = $locIdx;
$pdf->colWs = $colWs;
$pdf->headerAlign = $headerAlign;
$pdf->colWspacing = $colWspacing;
$pdf->invHeaderValues[0] = $invNum;
$pdf->invHeaderValues[1] = $invDate;
$pdf->invHeaderValues[2] = $_SESSION['userLocs'][$locIdx]['salesRepFName'] . " " . $_SESSION['userLocs'][$locIdx]['salesRepLName'];
$pdf->invHeaderValues[3] = $_SESSION['userLocs'][$locIdx]['salesRepCellNumber'];
$pdf->invHeaderValues[4] = $poNum;
$pdf->AliasNbPages();
$pdf->SetMargins(0.25, 0.15, 0.25);
$pdf->SetAutoPageBreak(true, 0.35);
$pdf->AddPage();
$pdf->SetFont('Courier','',10);

// Item List
$lineCount = 0;
foreach ($lineItems as $line) {
	$pdf->Cell($colWs[0], 0.185, $line['description'], 0, 0, $colAlign[0], false);
	$pdf->Cell($colWspacing[0], 0.15, ' ', 0, 0, 'C', false);
	$pdf->Cell($colWs[1], 0.185, $line['ordered'], 0, 0, $colAlign[1], false);
	$pdf->Cell($colWspacing[1], 0.15, ' ', 0, 0, 'C', false);
	$pdf->Cell($colWs[2], 0.185, $line['shipped'], 0, 0, $colAlign[2], false);
	$pdf->Cell($colWspacing[2], 0.15, ' ', 0, 0, 'C', false);
	$pdf->Cell($colWs[3], 0.185, sprintf("$%8s", $line['unitPrice']), 0, 0, $colAlign[3], false);
	$pdf->Cell($colWspacing[3], 0.15, ' ', 0, 0, 'C', false);
	$pdf->Cell($colWs[4], 0.185, sprintf("$%8s", $line['itemTotal']), 0, 0, $colAlign[4], false);
	$lineCount++;
	$pageEnd = ($pdf->PageNo() == 1) ? 48 : 50;
	if ($lineCount == $pageEnd) {
		$pdf->SetLeftMargin(0.25);
		$pdf->AddPage();
		$lineCount = 0;
	} else {
		$pdf->Ln(0.185);
	}
}
// Print the total, make sure we don't have to go to the next page...
$pageEnd = ($pdf->PageNo() == 1) ? 48 : 50;
if ($lineCount == $pageEnd) {
	$pdf->SetLeftMargin(0.25);
	$pdf->AddPage();
	$lineCount = 0;
}
$pdf->SetFont('Courier','B',10);
$pdf->Cell(8, 0.15, sprintf("Total  :  $%7s", $invTotal), 0, 0, 'R', false);

// Add the tracking information
$pdf->Ln(0.185);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 0.2, "Tracking Information:", 'T', 1);
$pdf->SetFont('Arial','B',9);
$invTrackHeader = array('Order Type', 'Entered By', 'Timestamp', 'Packer', 'Driver');
$invTrackColWs = array(1, 1, 1.5, 1.5, 1.5);
$invTrackColSpacing = 0.375;
for ($i = 0 ; $i < 5 ; $i++) {
	$pdf->Cell($invTrackColWs[$i], 0.15, $invTrackHeader[$i], 0, 0, 'C', false);
	if ($i < 4) $pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
}
$pdf->SetXY(0.25, $pdf->GetY() + 0.15);
$pdf->SetFont('Arial','',9);
$pdf->Cell($invTrackColWs[0], 0.15, $trackInvoiceEntry['source'], 0, 0, 'C', false);
$pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
$pdf->Cell($invTrackColWs[1], 0.15, (preg_match('/Online/', $trackInvoiceEntry['source']) > 0) ? $trackInvoiceEntry['ooUser'] : $trackInvoiceEntry['orderTaker'], 0, 0, 'C', false);
$pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
$pdf->Cell($invTrackColWs[2], 0.15, $trackInvoiceEntry['timeStamp'], 0, 0, 'C', false);
$pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
$pdf->Cell($invTrackColWs[3], 0.15, $trackInvoiceEntry['packer'], 0, 0, 'C', false);
$pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
$pdf->Cell($invTrackColWs[4], 0.15, $trackInvoiceEntry['driver'], 0, 0, 'C', false);
if (count($trackInvoiceEdits) > 0) {
	$pdf->Ln(0.2);
	$pdf->SetFont('Arial','B',10);
	$pdf->Cell(0, 0.2, "Invoice edits made online:", 0, 1);
	$pdf->SetFont('Arial','',9);
	foreach ($trackInvoiceEdits as $row) {
		$pdf->Cell(1, 0.15, $row['modifiedBy'], 0, 0, 'C', false);
		$pdf->Cell($invTrackColSpacing, 0.15, ' ', 0, 0, 'C', false);
		$pdf->Cell(1.5, 0.15, $row['timeStamp'], 0, 0, 'C', false);
		$pdf->Ln(0.185);
	}
}
// Output the PDF
$pdf->Output();
?>