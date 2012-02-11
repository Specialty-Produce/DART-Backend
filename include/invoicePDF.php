<?php
require('fpdf16/fpdf.php');

class PDF extends FPDF {
	// Variables
	public $locIdx;
	public $hdrText = array('Product Description','Order','Ship','Price',' Extension');
	public $hdrAlign = array('L','C','C','C','C');
	public $colWs = array();
	public $colWspacing = array();
	public $invHeaderText = array('Invoice #', 'Invoice Date', 'Sales Person', 'Sales Person Phone', 'Customer P.O.');
	public $invColWs = array(1, 1, 1.5, 1.5, 1.5);
	public $invColSpacing = 0.375;
	public $invHeaderValues = array();

	//Page header
	function Header() {
		// Left Side Text
		$this->SetFont('Arial','B',12);
		$this->SetXY(0.25, 0.15);
		$this->Cell(0, 0.2, $_SESSION['userLocs'][$this->locIdx]['locationDesc'], 0, 1);
		$this->SetFont('Arial', '', 10);
		$txt = $_SESSION['userLocs'][$this->locIdx]['firstName'] . " " . $_SESSION['userLocs'][$this->locIdx]['lastName'] . "\n";
		$txt .= $_SESSION['userLocs'][$this->locIdx]['address1'] . "\n";
		$txt .= $_SESSION['userLocs'][$this->locIdx]['city'] . ", " . $_SESSION['userLocs'][$this->locIdx]['state'];
		$this->MultiCell(3.14, 0.15, $txt, 0, 'L');
		// Right Side Text
		$this->SetFont('Arial','B',12);
		$widthRt = $this->GetStringWidth("Specialty Produce") + 0.1;
		$this->SetLeftMargin((8.5 - 0.15 - $widthRt));
		$this->SetY(0.15);
		$this->Cell($widthRt, 0.2, "Specialty Produce", 0, 1, 'R');
		$this->SetFont('Arial', '', 10);
		$txt = "P.O. Box 82951\n";
		$txt .= "San Diego, CA  92138\n";
		$txt .= "Tel 619.295.3172\n";
		$txt .= "Fax 619.295.9541\n";
		$this->MultiCell($widthRt, 0.15, $txt, 0, 'R');
		$this->SetLeftMargin(0.25);
		//Logo - 2.25 x 0.95 in
		$this->Image('images/sp_logo_lg.jpg',3.125, 0.1, 2.25);

		// Put the invoice information in the header if this is the first page
		if ($this->PageNo() == 1) {
			$this->SetXY(0.25, $this->GetY() + 0.2);
			$this->SetFont('Arial','B',10);
			for ($i = 0 ; $i < 5 ; $i++) {
				$this->Cell($this->invColWs[$i], 0.15, $this->invHeaderText[$i], 0, 0, 'C', false);
				if ($i < 4) $this->Cell($this->invColSpacing, 0.15, ' ', 0, 0, 'C', false);
			}
			$this->SetXY(0.25, $this->GetY() + 0.15);
			$this->SetFont('Arial','',10);
			for ($i = 0 ; $i < 5 ; $i++) {
				$this->Cell($this->invColWs[$i], 0.15, $this->invHeaderValues[$i], 0, 0, 'C', false);
				if ($i < 4) $this->Cell($this->invColSpacing, 0.15, ' ', 0, 0, 'C', false);
			}
		}
		
		// Put the item headers in place
		$this->SetXY(0.25, $this->GetY() + 0.2);
		$this->SetFont('Arial','B',10);
		for ($i = 0 ; $i < 5 ; $i++) {
			$this->Cell($this->colWs[$i], 0.15, $this->hdrText[$i], 'TB', 0, $this->hdrAlign[$i], false);
			if ($i < 4) $this->Cell($this->colWspacing[$i], 0.15, ' ', 'TB', 0, 'C', false);
		}
		$this->SetXY(0.25, $this->GetY() + 0.2);
	}

	//Page footer
	function Footer() {
		//Arial italic 8
		$this->SetFont('Arial','I',8);
		// Disclaimer
		$this->SetXY(0.25, -0.4);
		$this->Cell(7.125, 0.2, 'This reprint may not reflect all credits, adjustments or returns. Questions? Please contact our accounting department at 619.876.4070.', 0, 0, 'C');
		//Page number
		$this->SetXY(0.25, -0.4);
		$this->Cell(8.0, 0.2, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'R');
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