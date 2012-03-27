<?php
include_once 'global_CDC.php';
include 'dart_init.php';
require ('classes_SP/class_invoicePDF.php');

// Calls on our local network get access to the pages, otherwise fail
$dotted_ip_address = $_SERVER ['REMOTE_ADDR'];
$ip_number = (ip2long ( $dotted_ip_address )) ? sprintf ( "%u", ip2long ( $dotted_ip_address ) ) : 0;
if ($ip_number < 1185397282 || $ip_number > 1185397309) {
	echo "Access denied...";
	exit ();
}

$invType = filter_input ( INPUT_GET, 't', FILTER_SANITIZE_STRING );
$invNum = filter_input ( INPUT_GET, 'i', FILTER_SANITIZE_NUMBER_INT );

if ($invType == 'd') {
	// DART invoice
	$lineItems = array ();
	$invTotal = 0.0;
	$trackInvoiceEdits = array ();
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		// set the error reporting attribute.
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		// Get the location info
		// The XML to get the invoice info
		$invXML = "<ROOT>\n";
			$invXML .= '<Rec rID="' . $invNum . '"/>' . "\n";
		$invXML .= "</ROOT>\n";
		$stmt = $dbh->query ( "uspDARTSendInvoiceInfo '" . $invXML . "'" );
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$shipDate = date('n/j/Y', strtotime($row['dtShip']));
			$deliveryDate = date ( 'n/j/Y g:i:s A', strtotime ( $row ['dtDartDelivered'] ) );
			$locInfo [$row ['iSaleID']] = array ('id' => $row ['iLocationDestinationID'], 'saleID' => $row ['iSaleID'], 'name' => $row ['sDescription'], 'address' => $row ['sAddress1'], 'city' => $row ['sCity'], 'state' => $row ['sState'], 'zip' => $row ['sPostalCode'], 'phone' => $row ['sPhone'], 'salesperson' => $row ['txtSalesPerson'], 'salesphone' => $row ['txtCellPhone'], 'salesemail' => $row ['txtSalesEmail'], 'terms' => $row ['sTerms'], 'po' => $row ['sPO'], 'signer' => $row ['txtSigner'], 'shipdate'=>$shipDate, 'deldate' => $deliveryDate, 'greenYTD' => $row ['mYTD'] );
		}
		$stmt->closeCursor ();
		
		// Get the line items on this invoice
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
		include ("includes/errorPage.php");
		exit ();
	}
	
	// Generate the PDF
	$pdf = new invoicePDF ();
	$pdf->setLocation ( $locInfo [$invNum] ['name'], $locInfo [$invNum] ['address'], $locInfo [$invNum] ['city'], $locInfo [$invNum] ['state'], $locInfo [$invNum] ['zip'], formatPhone ( $locInfo [$invNum] ['phone'] ) );
	$pdf->setInvoiceHeader ( $invNum, $locInfo [$invNum] ['shipdate'], $locInfo [$invNum] ['salesperson'], formatPhone ( $locInfo [$invNum] ['salesphone'] ), $locInfo [$invNum] ['po'], $locInfo [$invNum] ['terms'] );
	$pdf->startInvoice ();
	// Item List
	foreach ( $lineItems as $line ) {
		$pdf->addLineItem ( $line ['description'], $line ['ordered'], $line ['shipped'], $line ['unitPrice'], $line ['itemTotal'], $line ['status'] );
	}
	// Invoice Total
	$pdf->addTotal ( $invTotal );
	// Add the signature
	$sigImage = DART_SIG_DIR . $locInfo [$invNum] ['id'] . '/' . $invNum . '.png';
	$pdf->addSignatureImage ( $sigImage );
	// Add signer info
	$pdf->addSigner ( $locInfo [$invNum] ['signer'], $locInfo [$invNum] ['deldate'] );
	// Add the tracking information
	if (isset ( $trackInvoiceEntry )) {
		$pdf->addTrackingInfo ( $trackInvoiceEntry ['source'], (preg_match ( '/Online/', $trackInvoiceEntry ['source'] ) > 0) ? $trackInvoiceEntry ['ooUser'] : $trackInvoiceEntry ['orderTaker'], $trackInvoiceEntry ['timeStamp'], $trackInvoiceEntry ['packer'], $trackInvoiceEntry ['driver'] );
		if (count ( $trackInvoiceEdits ) > 0) {
			$pdf->addTrackingEdits ();
			foreach ( $trackInvoiceEdits as $row ) {
				$pdf->addTrackingEditLine ( $row ['modifiedBy'], $row ['timeStamp'] );
			}
		}
	}
	$pdf->Output ( $invNum . ".pdf", 'I' );

} elseif ($invType == 's') {
	// Scanned invoice


} else {
	// Invalid type
	echo "Invalid type...";
	exit ();
}
?>