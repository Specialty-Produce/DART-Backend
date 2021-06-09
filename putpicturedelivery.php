<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_AzureBlobSP.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<putpicturedelivery status="failed" code="0" retry="true" errmsg="XXX">
</putpicturedelivery>
EOT;

$successXML = <<< EOT
<?xml version="1.0"?>
<putpicturedelivery status="success">
</putpicturedelivery>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// TODO : CRC check
// jsonCRC32

// Get the POST data
if (isset ( $_POST ['jsondata'] )) {
	$appJSON = $_POST ['jsondata'];
	// dartLogging ( $currentScript, "jsondata=" . (preg_replace ( '/(,"signatureimage":")[^"]+(","status")/', '$1 --- $2', $appJSON )), $codeStr );
	dartLogging ( $currentScript, "jsondata=" . $appJSON, $codeStr );
	// dartLogging ( $currentScript, "POST=" . print_r($_POST, true), $codeStr );
} else {
	$appJSON = false;
}

if (MAINTENANCE_MODE) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Maintenace Mode', $badXML );
	// $badXML = preg_replace ( '/retry="true"/', 'retry="false"', $badXML );
	echo $badXML;
	dartLogging ( $currentScript, " in Maintenace Mode", $codeStr );
	exit ();
}

// appJSON
if ($appJSON == FALSE || is_null ( $appJSON )) {
	dartLogging ( $currentScript, "    jsondata is FALSE or NULL : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : No jsondata supplied', $badXML );
	echo $badXML;
	exit ();
}

// Good to go...
$jd = json_decode ( $appJSON );
if ($jd == FALSE || is_null ( $jd )) {
	dartLogging ( $currentScript, "    decoded jsondata is FALSE or NULL : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid jsondata supplied', $badXML );
	echo $badXML;
	SP_ErrorLogging ( "Decoded JSON data is invalid for codeStr = $codeStr. Hand fix and adhoc enter data", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data" );
	exit ();
}

// Get the DART Delivery Pic ID
$saleIDs = array ();
$invXML = "<ROOT>\n";
foreach ( $jd->invoice_list as $invoice ) {
	$invXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
	$saleIDs [] = $invoice->saleid;
}
$invXML .= "</ROOT>";
$result = false;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
$iPicTrackID = 0;
while ( $sqlFailed ) {
	$sqlFailed = false;
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		$sql = "uspDARTDeliveryPicture '" . $invXML . "'";
		$stmt = $dbh->query ( $sql );
		$returnSet = $stmt->fetch ( PDO::FETCH_BOTH );
		$iPicTrackID = $returnSet ['iPicTrackID'];
		$stmt->closeCursor ();
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage ();
		$errMsg .= $e->getFile () . ' (' . $e->getLine () . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		$errMsg .= "\ninvXML = " . $invXML;
		SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
		if (preg_match ( '/Timeout expired/', $eMessage ) || preg_match ( '/SQL Server does not exist or access denied/', $eMessage ) || preg_match ( '/deadlock victim/', $eMessage )) {
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount ++;
				$sqlFailed = true;
				sleep ( DART_SQL_TIMEOUT_SLEEP );
			} else {
				$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML );
				$badXML = preg_replace ( '/code="0"/', 'code="1"', $badXML );
				echo $badXML;
				exit ();
			}
		} else {
			dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
			$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
			echo $badXML;
			exit ();
		}
	}
}
$fileName = 'delivery_pic_' . $iPicTrackID . ".png";
$filePath = SPConsts::TempDir . $fileName;
// Create from the encoded string
if (! $imgSrc = imagecreatefromstring ( base64_decode ( $jd->deliveryimage ) )) {
	$errMsg = "Could not create image from deliveryimage data, saleIDs = " . implode ( ',', $saleIDs ) . ", code = " . $codeStr;
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Could not create image from productimage data", $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not create image from deliveryimage data', $badXML );
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

// Write to Azure
try {
	$azb = new AzureBlobSP ( 'specprodstorage' );
	imagepng ( $imgDest, $filePath );
	if ($jd->pictype == 'poorquality')
		$azb->putBlockBlobFile ( AzureBlobSP::AZURE_STORAGE_DART_DELIVERY_PICS_DIR, '', $fileName, $filePath );
	else {
		$errMsg = "Invalid pictype : " . $jd->pictype . ", code = " . $codeStr;
		SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
		dartLogging ( $currentScript, "    Invalid pic type", $codeStr );
		$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid pic type', $badXML );
		echo $badXML;
		exit ();
	}
	unlink ( $filePath );
} catch ( SP_Exception $e ) {
	$errMsg = $e->getMessage ();
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG, 'DART Sig Error' );
}

echo $successXML;
dartLogging ( $currentScript, "  Success : " . $_SERVER ['REMOTE_ADDR'] . " : " . $_SERVER ['HTTP_USER_AGENT'], $codeStr );
exit ();
?>