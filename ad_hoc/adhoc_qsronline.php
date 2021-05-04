<pre>
<?php
exit ();
require_once 'global_CDC.php';
require_once '../dart_init.php';
require_once 'classes_SP/class_InvoiceSP_QSROnline.php';
require_once 'classes_SP/class_AzureBlobSP.php';

$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$invList = array(4964749,4965500,4965612,4965708,4966910,4967996,4968144,4968486,4970091,4970766,4971198,4971202,4971473,4973056,4973570,4974270,4974913);
//$invList = array(4963872);
$locID = 7305;

try {
	$azb = new AzureBlobSP ( 'specprodstorage', false );
	foreach ( $invList as $saleID ) {
		echo "Working on $saleID ... ";
		$qsrFilename = $locID . '_' . $saleID . '_' . date ( 'ymd_His' ) . '.csv';
		$invQSR = new InvoiceSP_QSROnline ();
		$invQSR->retrieveInvoice ( $saleID );
		$qsrString = $invQSR->generateInvoiceCSV ();
		$qsrFile = SPConsts::TempDir . $qsrFilename;
		file_put_contents($qsrFile, $qsrString);
		$azb->putBlockBlobFile ( AzureBlobSP::AZURE_STORAGE_FTP_DIR, $invQSR::FILEMAGE_FTP_DIR, $qsrFilename, $qsrFile );
		// Disabled writing to DART FTP folder when QSR updated to Azure FTP
		// Email 2/12/20 : Re:[## 27812 ##] Specialty Produce FTP server moving
		// $qsrFile = DART_QSR_DIR . $qsrFilename;
		// $qsrFH = fopen ( $qsrFile, "w" );
		// fwrite ( $qsrFH, $qsrString );
		// fclose ( $qsrFH );
		sleep(3);
		unlink($qsrFile);
		echo "Done\n";
	}
} catch ( SP_Exception $spe ) {
	$errMsg = "QSROnline :  error : " . $spe->getMessage ();
	SP_errorLogging ( $errMsg, true, '', $currentScript . " - QSROnline error" );
}
?>
</pre>