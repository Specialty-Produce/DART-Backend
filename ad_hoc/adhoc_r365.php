<?php
exit();
require_once 'global_CDC.php';
require_once '../dart_init.php';
require_once 'classes_SP/class_InvoiceR365.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// @formatter:off
$r365Locations = array (7685,7577,7621);
// @formatter:on

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	foreach ( $r365Locations as $locID ) {
		echo "<b>LocID</b> : $locID";
		// Get the FTP Info
		$stmt = $dbh->query ( "SELECT sR365ID, sFTPUsername, sFTPPassword, sFTPFolder FROM tblDartInvoiceSendR365 WHERE iLocationID=$locID" );
		$r365Data = $stmt->fetchAll ( PDO::FETCH_ASSOC );
		$stmt->closeCursor ();
		echo "<h2>R365 Info</h2><pre>" . print_r ( $r365Data, true ) . "</pre>";
		
		// Get the SaleIDs
		$stmt = $dbh->query ( "select iSaleID from vwSaleAll where dtShip >= '3/10/2022' and iDartStatusID=5 and iLocationDestinationID=$locID" );
		$saleIDs = array ();
		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
			$saleIDs [] = $row ['iSaleID'];
		}
		$stmt->closeCursor ();
		echo "<h2>Sale IDs</h2><pre>" . print_r ( $saleIDs, true ) . "</pre><hr/>";
		
		/*
		$r365InvoiceList = array ();
		$invR365 = new InvoiceR365 ( $r365Data [0] );
		foreach ( $saleIDs as $saleID ) {
			// Retrieve and add to CSV each invoice
			$invR365->retrieveInvoice ( $saleID );
			$r365InvoiceList [] = $saleID;
			$invR365->generateCSVLineItems ();
			$invR365->detail = array ();
		}
		echo "<h2>CSV</h2><pre>" . $invR365->csvStr . "</pre>";
		try {
			$invR365->ftpCSV ();
		} catch ( SP_Exception $spe ) {
			$errMsg = "R365 : FTP CSV error : " . $spe->getMessage ();
			echo "<br/>    R365 FTP Failed : " . implode ( ',', $r365InvoiceList );
		}
		echo "<br/>    R365 FTP Sent : " . implode ( ',', $r365InvoiceList ) . "<hr/>";
		*/
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	echo "<b>Error :</b> $errMsg";
}