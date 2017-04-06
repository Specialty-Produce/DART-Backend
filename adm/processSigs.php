<?php
include_once 'global_CDC.php';
include '../dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

exit();

$invList = array ();
$imgList = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// Get the list of missing invoice information
	$sql = "select s.iSaleID, s.iLocationDestinationID as iLocationID, l.sDescription from tblSaleArchive s
			join tblLocation l
			on l.iLocationID = s.iLocationDestinationID
			where dtShip >= '7/7/2016' and dtShip <= '7/8/2016' and iSBF=0 and iDartStatusID in (3,4) order by dtShip asc, iLocationDestinationID, iSaleID";
	$stmt = $dbh->query ( $sql );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$invList [] = array ('saleID' => $row ['iSaleID'], 'locID' => $row ['iLocationID'], 'locDesc' => mb_convert_encoding ( $row ['sDescription'], "UTF-8", "Windows-1252" ), 'total' => 0.0);
	}
	$stmt->closeCursor ();
	
	// Get the calculated totals for each invoice
	foreach ( $invList as &$entry ) {
		$sql = "uspWebXFInvoiceDetail " . $entry ['saleID'];
		$stmt = $dbh->query ( $sql );
		$greenDiscount = 0.0;
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$itemTotal = preg_replace ( '/^\$/', '', $row ['Total'] );
			if ($row ['iProductID'] == 9997)
				$entry ['total'] += $itemTotal;
			else
				$entry ['total'] += $itemTotal;
		}
		$stmt->closeCursor ();
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = "SQL = $sql\n";
	$errMsg .= $e->getFile () . ' (' . $e->getLine () . ') : ' . $e->getMessage ();
	echo "ERROR!!!\n$errMsg\n";
}
echo "<h3>" . count ( $invList ) . " Total Invoices</h3>";

foreach ( $invList as $inv ) {
	$sigFile = 'Sigs/invoice_' . $inv ['saleID'] . '_signature.png';
	if (file_exists ( $sigFile )) {
		echo '<img src="' . $sigFile . '"/><br/>';
		$imgList [] = $inv ['saleID'];
	} else {
		echo "<b>NO signature image</b><br/>";
	}
	echo '<div style="padding-left: 150px;">' . $inv ['saleID'] . " : " . sprintf ( "%5d", $inv ['locID'] ) . " : $ <b>" . sprintf ( "%0.2f", $inv ['total'] ) . "</b><br/>" . $inv ['locDesc'] . "</div>";
}
?>
<br />
<br />
SaleIDs with images checked on this page (<?php echo count($imgList); ?>) :
<br />
<?php echo implode(',', $imgList); ?>
<br />
<br />