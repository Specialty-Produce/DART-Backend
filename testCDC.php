<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<deliverycomplete status="failed" code="0" retry="true" errmsg="XXX">
</deliverycomplete>
EOT;

$successXML = <<< EOT
<?xml version="1.0"?>
<deliverycomplete status="success">
</deliverycomplete>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// Get the POST data
/*
if (isset ( $_POST ['jsondata'] )) {
	$appJSON = $_POST ['jsondata'];
	//dartLogging ( $currentScript, "jsondata=" . (preg_replace ( '/(,"signatureimage":")[^"]+(","status")/', '$1 --- $2', $appJSON )), $codeStr );
	dartLogging ( $currentScript, "jsondata=" . $appJSON, $codeStr );
} else {
	$appJSON = false;
}
*/

$appJSON = '';

if (isset ( $_POST ['debuginfo'] )) {
	dartLogging ( $currentScript, "debuginfo=" . $_POST ['debuginfo'], $codeStr );
}

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	dartLogging ( $currentScript, "    jsondata is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

// Truncated appJSON - implies the connection was lost in midtransmission
if (! preg_match ( '/,"userid":"\d+"}$/', $appJSON )) {
	dartLogging ( $currentScript, "    jsondata is TRUNCATED", $codeStr );
	exit ();
}

// TODO CRC check

// Good to go...
$jd = json_decode ( $appJSON );

// Pull these out for easier reference
$locationID = $jd->deliveryjson->delivery->locationid;
$signerID = $jd->deliveryjson->delivery->signerid;
if ($signerID < 0) {
	$isDarkDrop = true;
	$signerID = DARK_STOP_ID;
} else {
	$isDarkDrop = false;
}

// Ignore printed invoice signers
if ($signerID != PRINTED_INVOICE_ID && $isDarkDrop == false) {
	// SIGNATURE IMAGE
	$filedir = DART_SIG_DIR . $jd->deliveryjson->delivery->locationid;
	if (! is_dir ( $filedir )) {
		if (! mkdir ( $filedir )) {
			dartLogging ( $currentScript, "    Could not create folder for locationID = " . $jd->deliveryjson->delivery->locationid, $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not create folder for locationID = ' . $jd->deliveryjson->delivery->locationid, $badXML );
			echo $badXML;
			exit ();
		}
	}
	// Convert the image to 24-bit and save
	foreach ( $jd->deliveryjson->invoice_list as $invoice ) {
		$file = $filedir . '/' . $invoice->saleid . ".png";
		
		// Create from the encoded string
		if (! $imgSrc = imagecreatefromstring ( base64_decode ( $invoice->signatureimage ) )) {
			dartLogging ( $currentScript, "    Could not create image from signatureimage data", $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not create image from signatureimage data', $badXML );
			echo $badXML;
			exit ();
		}
		$width = imagesx ( $imgSrc );
		$height = imagesy ( $imgSrc );
		
		// Make the new image
		if (! $imgDest = imagecreatetruecolor ( $width, $height )) {
			dartLogging ( $currentScript, "    Could not create new true color image", $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not create new true color image', $badXML );
			echo $badXML;
			exit ();
		}
		
		// Copy sent into new
		if (! imagecopy ( $imgDest, $imgSrc, 0, 0, 0, 0, $width, $height )) {
			dartLogging ( $currentScript, "    Could not copy source image to new image", $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not copy source image to new image', $badXML );
			echo $badXML;
			exit ();
		}
		
		// Write it out
		if (! imagepng ( $imgDest, $file )) {
			dartLogging ( $currentScript, "    Could not save png image", $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not save png image', $badXML );
			echo $badXML;
			exit ();
		}
	}
}

if ($jd->userid == DEBUG_USERID) {
	echo $successXML;
	exit ();
}

// Get the invoices marked as "delivered", which is code 2 for this stored procedure
$updateCode = 2;
$invXML = '';
$result = false;
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

	// Prep for the XML version of invoice list for the stored procedure
	$invXML = "<ROOT>\n";
	foreach ( $jd->deliveryjson->invoice_list as $invoice )
		$invXML .= '<Rec rID="' . $invoice->saleid . '" dtDelTime="' . $invoice->signtimestamp . '.000"/>' . "\n";
	$invXML .= "</ROOT>";

		// Check if this is a DART driver for later use by "sendinvoice"
	$stmt = $dbh->query ( "uspDARTCheckDriverIsDart " . $jd->userid );
	$driverCheck = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor ();
	$dartDriver = ($driverCheck[0]['iStatus'] == 1) ? true : false;
	echo ($dartDriver) ? "true..." : "false...";
	
	
	// Check if this is a repeat call to deliverycomplete.php
	$repeatCall = false;
	$stmt = $dbh->query ( "uspDARTCheckDeliveryDate '" . $invXML . "'" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_BOTH ) as $row ) {
		if ($row['dtDartDelivered'] != '') {
			$repeatCall = true;
		}
	}
	$stmt->closeCursor ();
	if ($repeatCall) {
		dartLogging ( $currentScript, "  Repeat Call : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
		$dbh = null;
		echo $successXML;
		exit();
	}
	
	
	// First check to see if there is a signer
	if ($signerID == 0) {
		// Add the signer
		$sql = "uspDARTAddSigner 0, $locationID, '" . preg_replace('/\'+/', '\'\'', trim ( $jd->deliveryjson->delivery->signerinfo->fname )) . "', ";
		$sql .= "'" . preg_replace('/\'+/', '\'\'', trim ( $jd->deliveryjson->delivery->signerinfo->lname )) . "', ";
		$sigEmail = trim ( $jd->deliveryjson->delivery->signerinfo->email );
		$sql .= ($sigEmail == '') ? 'null' : "'" . $sigEmail . "'";
		$sql .= ", 1, ";
		$sigPhone = formatPhone ( trim ( $jd->deliveryjson->delivery->signerinfo->phone ) );
		$sql .= ($sigPhone == '') ? 'null' : "'" . $sigPhone . "'";
		dartLogging ( $currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr );
		$stmt = $dbh->query ( $sql );
		$result = $stmt->fetch ( PDO::FETCH_ASSOC );
		$signerID = $result ['iUserID'];
		$stmt->closeCursor ();
	}
	
	// Get the last update time according to the database
	$invTimesDB = array ();
	$stmt = $dbh->query ( "uspDARTCheckLastUpdate '" . $invXML . "'" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_BOTH ) as $row ) {
		$lastUpdate = preg_replace ( '/(.*):\d{2}\.\d{3}$/', '$1', $row ['dtDartLastUpdated'] );
		$invTimesDB [$row ['iSaleID']] = $lastUpdate;
	}
	$stmt->closeCursor ();
	
	// Now mark the invoice as "delivered"
	$resultDelivered = $dbh->exec ( "uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'" );
	
	// We need to build the list of invoices that have lastupdatetime values different between database and ipad
	$updateAtDeliveryFailXML = '';
	$saleDetailXML = '';
	foreach ( $jd->deliveryjson->invoice_list as $invoice ) {
		$getAllLines = false;
		if ($invoice->lastupdatetime != $invTimesDB [$invoice->saleid]) {
			$getAllLines = true;
			$updateAtDeliveryFailXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
		}
		foreach ( $invoice->invoice_item_list as $line ) {
			if ($line->edited == "true" || $getAllLines == true) {
				$saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . '' . '"/>' . "\n";
			}
		}
	}
	
	// Now call the stored procedures, as needed, if my XML strings are not empty
	$resultUpdateChanges = true;
	if ($saleDetailXML != '') {
		$saleDetailXML = "<ROOT>\n" . $saleDetailXML . "</ROOT>";
		dartLogging ( $currentScript, "    saleDetailXML=" . $saleDetailXML, $codeStr );
		$resultUpdateChanges = $dbh->exec ( "uspDARTDeliveryCompleteUpdates '" . $saleDetailXML . "'" );
	}
	$resultDeliveryFail = true;
	if ($updateAtDeliveryFailXML != '') {
		$updateAtDeliveryFailXML = "<ROOT>\n" . $updateAtDeliveryFailXML . "</ROOT>";
		dartLogging ( $currentScript, "    updateAtDeliveryFailXML=" . $updateAtDeliveryFailXML, $codeStr );
		$resultDeliveryFail = $dbh->exec ( "uspDARTUpdateAtDeliveryFail '" . $updateAtDeliveryFailXML . "'" );
	}
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	$errMsg .= "\ninvXML = " . $invXML;
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($resultDelivered === false) {
	$errMsg = "uspDARTDelivered $updateCode, $signerID, $invXML returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($resultUpdateChanges === false) {
	$errMsg = "uspDARTDeliveryCompleteUpdates $saleDetailXML returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

if ($resultDeliveryFail === false) {
	$errMsg = "uspDARTUpdateAtDeliveryFail $updateAtDeliveryFailXML returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

// Call sendinvoices.php with the invoice list to generate the PDFs and send them out for non-PRINTED INVOICE
if ($signerID != PRINTED_INVOICE_ID && $dartDriver) {
	$invoiceStr = '';
	foreach ( $jd->deliveryjson->invoice_list as $invoice )
		$invoiceStr .= " " . $invoice->saleid;
	pclose ( popen ( "start /B php sendinvoice.php$invoiceStr", "r" ) );
}



echo $successXML;
dartLogging ( $currentScript, "  Success : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
exit ();
?>