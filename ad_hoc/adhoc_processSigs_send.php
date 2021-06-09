<pre>
<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

exit ();

$invArray = array (3093445,3090641,3090639,3090640,3090637,3090643,3090642);
$invList = array ();
$errorList = array ();

$sigImgDir = 'adm/Sigs/';

// Get the location information and setup the list
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$sql = "select iSaleID, iLocationDestinationID, dtShip from tblSaleArchive where iSaleID in (" . implode ( ',', $invArray ) . ") order by iLocationDestinationID, iSaleID";
	$stmt = $dbh->query ( $sql );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
		$invList [] = array ('saleID' => $row ['iSaleID'], 'locID' => $row ['iLocationDestinationID'], 'ship' => strtotime ( $row ['dtShip'] ), 'sigCopied' => false, 'readyToSend' => false);
	}
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = "SQL = $sql\n";
	$errMsg .= $e->getFile () . ' (' . $e->getLine () . ') : ' . $e->getMessage ();
	echo "ERROR!!!\n$errMsg\n";
	exit ();
}

// Copy all of the images
foreach ( $invList as &$entry ) {
	$sigSourceFile = $sigImgDir . 'invoice_' . $entry ['saleID'] . '_signature.png';
	$sigDestDir = DART_SIG_DIR . $entry ['locID'];
	if (! is_dir ( $sigDestDir )) {
		if (! mkdir ( $sigDestDir )) {
			echo "Could not create folder for locationID = " . $entry ['locID'];
			exit ();
		}
	}
	$sigDestFile = DART_SIG_DIR . $entry ['locID'] . '\\' . $entry ['saleID'] . '.png';
	if (file_exists ( $sigSourceFile )) {
		// Copy the file
		if (copy ( $sigSourceFile, $sigDestFile )) {
			$entry ['sigCopied'] = true;
		} else {
			$errorList [] = $entry ['saleID'] . " : Copy error : $sigSourceFile -> $sigDestFile\n";
		}
	} else {
		$errorList [] = $entry ['saleID'] . " : No sigSourceFile : $sigSourceFile\n";
	}
}

// Update the database
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$dbh->beginTransaction ();
	$sql = "update tblSaleArchive set iDartStatusID=5, dtDartDelivered=:delDate, iSigner=69343 where iSaleID=:saleID";
	$stmt = $dbh->prepare ( $sql );
	$stmt->bindParam ( ':delDate', $delDate );
	$stmt->bindParam ( ':saleID', $saleID );
	foreach ( $invList as &$entry ) {
		if ($entry ['sigCopied']) {
			$delDate = date ( "Y-m-d H:i:s", $entry ['ship'] ) . ".000";
			$saleID = $entry ['saleID'];
			$stmt->execute ();
		}
	}
	$result = $dbh->commit ();
	if ($result) {
		foreach ( $invList as &$entry ) {
			if ($entry ['sigCopied'])
				$entry ['readyToSend'] = true;
		}
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = "SQL = $sql\n";
	$errMsg .= $e->getFile () . ' (' . $e->getLine () . ') : ' . $e->getMessage ();
	echo "ERROR!!!\n$errMsg\n";
	exit ();
}

// Send the invoices grouped by LocationID
$lastLocID = $invList [0] ['locID'];
$sendList = array ();
$sendCount = 0;
foreach ( $invList as $inv ) {
	if ($inv ['readyToSend'] == false)
		continue;
		// Are we working on a new locID?
	$sendCount ++;
	if ($inv ['locID'] != $lastLocID) {
		// Send it
		$invoiceStr = ' ' . implode ( ' ', $sendList );
		if (phpversion () == "5.6.20") {
			pclose ( popen ( "start /B C:\PROGRA~2\PHP\V56~1.20\php.exe sendinvoice.php$invoiceStr", "r" ) );
			echo 'Call : pclose ( popen ( "start /B C:\PROGRA~2\PHP\V56~1.20\php.exe sendinvoice.php' . $invoiceStr . '", "r" ) );' . "\n";
		} else {
			pclose ( popen ( "start /B php sendinvoice.php$invoiceStr", "r" ) );
			echo 'Call : pclose ( popen ( "start /B php sendinvoice.php' . $invoiceStr . '", "r" ) );' . "\n";
		}
		// Start the new set
		$sendList = array ();
		$sendList [] = $inv ['saleID'];
		$lastLocID = $inv ['locID'];
	} else {
		// Add it
		$sendList [] = $inv ['saleID'];
	}
}
// Send the remaining
echo "Remaining...\n";
if (count ( $sendList ) > 0) {
	$invoiceStr = ' ' . implode ( ' ', $sendList );
	if (phpversion () == "5.6.20") {
		pclose ( popen ( "start /B C:\PROGRA~2\PHP\V56~1.20\php.exe sendinvoice.php$invoiceStr", "r" ) );
		echo 'Call : pclose ( popen ( "start /B C:\PROGRA~2\PHP\V56~1.20\php.exe sendinvoice.php' . $invoiceStr . '", "r" ) );' . "\n";
	} else {
		pclose ( popen ( "start /B php sendinvoice.php$invoiceStr", "r" ) );
		echo 'Call : pclose ( popen ( "start /B php sendinvoice.php' . $invoiceStr . '", "r" ) );' . "\n";
	}
}
echo "Full list = " . count ( $invArray ) . "\n";
echo "Send count = $sendCount\n";
echo "\n\n";
if (count ( $errorList ) > 0) {
	echo "Errors :\n" . print_r ( $errorList );
} else {
	echo "Errors : None\n";
}
exit ();
?>
</pre>