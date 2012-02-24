<?php
include_once 'global_CDC.php';

$faxErrorLog = "dart_fax";
$faxLogFile = SPConsts::ErrorLogRoot . "dart_fax.txt";
$tiffDir = "D:\\DartFaxes\\";

// Make multiple attempts if a Fax->Submit fails...
$faxTimeoutSleep = 10;
$faxMaxAttempts = 3;

$faxList = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// Get any pending faxes
	$stmt = $dbh->query ( "uspDARTFaxList " );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$faxNumber = preg_replace ( '/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $row ['sFaxNumber'] );
		$faxList [] = array ('faxID' => $row ['iFaxID'], 'saleID' => $row ['iSaleID'], 'description' => $row ['sDescription'], 'fax' => $faxNumber );
	}
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( $errorTxt, true, $faxErrorLog );
	exit ();
}

$sentFaxes = array ();
foreach ( $faxList as $entry ) {
	$fp = fopen ( $faxLogFile, "a" );
	fwrite ( $fp, date ( '[d-M-Y H:i:s]' ) . " : " . $entry ['saleID'] . " : " . $entry ['description'] . " : " . $entry ['fax'] . " - " );
	
	$tiffFileName = $tiffDir . $entry ['saleID'] . ".tiff";
	
	try {
		$doc = new COM ( "FaxComEx.FaxDocument" );
		$doc->Body = $tiffFileName;
		$doc->DocumentName = "Invoice " . $entry ['saleID'];
		$doc->Recipients->Add ( $entry ['fax'], $entry ['description'] );
		// FAX_PRIORITY_TYPE_ENUM->fptHIGH = 2 for High Priority
		$doc->Priority = 2;
		$faxFailed = true;
		$faxAttemptCount = 1;
		while ( $faxFailed ) {
			$faxFailed = false;
			try {
				$doc->Submit ( "" );
			} catch ( Exception $e ) {
				$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . " faxAttemptCount=$faxAttemptCount : " . $e->getMessage ();
				SP_ErrorLogging ( $errMsg, true, $faxErrorLog );
				if ($faxAttemptCount < $faxMaxAttempts) {
					$faxAttemptCount ++;
					$faxFailed = true;
					sleep ( $faxTimeoutSleep );
				} else {
					exit ();
				}
			}
		}
		sleep ( 3 );
		$doc = null;
		// unlink ( $outputTiffFileName );
		fwrite ( $fp, "success\n" );
		fclose ( $fp );
		$sentFaxes [] = $entry ['faxID'];
	} catch ( com_exception $e ) {
		if ($doc)
			$doc = null;
		$errorTxt = "Invoice " . $entry ['saleID'] . " : " . $entry ['fax'] . " : " . $entry ['description'] . "\n";
		$errorTxt .= $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
		SP_ErrorLogging ( $errorTxt, true, $faxErrorLog );
		fwrite ( $fp, "FAIL!!!\n" );
		fclose ( $fp );
	}
}

if (count ( $sentFaxes ) > 0) {
	$faxXML = "<ROOT>\n";
	foreach ( $sentFaxes as $faxID )
		$faxXML .= '<Rec rID="' . $faxID . '" />' . "\n";
	$faxXML .= "</ROOT>";
	
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		// Update the fax list
		$updateResult = $dbh->exec ( "uspDARTFaxListUpdate  '" . $faxXML . "'" );
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
		SP_ErrorLogging ( $errorTxt, true, $faxErrorLog );
		exit ();
	}
	
	if ($updateResult === false) {
		$errorTxt = "uspDARTFaxListUpdate FAILED!  faxXML = " . $faxXML;
		SP_ErrorLogging ( $errorTxt, true, $faxErrorLog );
		exit ();
	}
}
exit ();
?>