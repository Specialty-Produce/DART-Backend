<?php
require_once 'global_CDC.php';
require_once '../dart_init.php';
require_once 'classes_SP/class_InvoicePlateIQ.php';
require_once 'classes_SP/class_AzureBlobSP.php';

$sIDs = array ();

exit();

// Sale ID
$sid = filter_input ( INPUT_GET, 's', FILTER_VALIDATE_INT );
if ($sid == FALSE || is_null ( $sid )) {
	$useArray = filter_input ( INPUT_GET, 'a', FILTER_VALIDATE_INT );
	if ($useArray == FALSE || is_null ( $useArray )) {
		echo "No Sale ID provided...";
		exit ();
	} else {
		$sIDs = array ();
	}
} else {
	$sIDs = array (
			$sid 
	);
}

$errors = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$azb = new AzureBlobSP ( 'specprodstorage', false );
	
	foreach ( $sIDs as $saleID ) {
		$invPlateIQ = new InvoicePlateIQ ();
		$invPlateIQ->retrieveInvoice ( $saleID );
		$locID = $invPlateIQ->getLocationID ();
		
		if (! $invPlateIQ->validatePlateIQLocation ()) {
			$errors [] = "$saleID is associated with {$invPlateIQ->location} ($locID), which is not a PlateIQ FTP location.";
			continue;
		}
		
		// Get PO Default
		$stmt = $dbh->query ( "SELECT iLocationID, sEmail, sPODefault FROM tblDartInvoiceSendPlateIQ WHERE iLocationID=" . $locID );
		$result = $stmt->fetch ( PDO::FETCH_ASSOC );
		$plateIQEmailAddress = ($result ['iLocationID'] > 0) ? $result ['sEmail'] : '';
		$plateIQPODefault = ($result ['iLocationID'] > 0) ? $result ['sPODefault'] : '';
		$stmt->closeCursor ();
		$invPlateIQ->PODefault = $plateIQPODefault;
		
		$piqString = $invPlateIQ->getPIQHeader ();
		$piqString .= $invPlateIQ->generatePIQOutput ();
		$piqFilename = $locID . '_' . $saleID . '_' . date ( 'ymd_His' ) . '.csv';
		$errors[] = "$saleID : $locID : $plateIQPODefault : $piqFilename";
		$piqFile = DART_PLATEIQ_DIR . $piqFilename;
		$piqFH = fopen ( $piqFile, "w" );
		fwrite ( $piqFH, $piqString );
		fclose ( $piqFH );
		$azb->putBlockBlobFile ( AzureBlobSP::AZURE_STORAGE_FTP_DIR, $invPlateIQ::FILEMAGE_FTP_DIR, $piqFilename, $piqFile );
		sleep ( 1 );
		unlink ( $piqFile );
	}
} catch ( Exception $e ) {
	echo "There was a generic error... " . $e->getMessage ();
} catch ( SP_Exception $spe ) {
	echo "There was a SP Exception error... " . $spe->getMessage ();
} catch ( PDOException $spe ) {
	echo "There was a PDO Exception error... " . $spe->getMessage ();
}
if (count ( $errors ) > 0) {
	echo "<h3>Errors</h3>" . implode ( '<br/>', $errors );
}
echo "Sent";