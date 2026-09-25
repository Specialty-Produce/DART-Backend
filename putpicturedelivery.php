<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_AzureBlobSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

if (isset($_SERVER['CONTENT_LENGTH'])) {
	$size = (int) $_SERVER['CONTENT_LENGTH'];
} else {
	$size = 0;
}

// error_log("putpicturedelivery.php : $currentScript : $codeStr : POST Size : " . $size);

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	dartLogging($currentScript, "Starting...", $codeStr);
} else {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

if (MAINTENANCE_MODE) {
	sendError(503, ERROR_CODES::ERROR_MAINTENANCE_MODE, 'Site undergoing maintenance. Please try again.', 'Maintenance Mode is ON', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Good to go...
$jd = json_decode($appJSON);
if ($jd == FALSE || is_null($jd)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Bad JSON data', 'Decoded jsondata is FALSE or NULL');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. Hand fix and adhoc enter data", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
	exit();
}

// Get the DART Delivery Pic ID
$saleIDs = array();
$invXML = "<ROOT>\n";
foreach ($jd->invoice_list as $invoice) {
	$invXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
	$saleIDs[] = $invoice->saleid;
}
$invXML .= "</ROOT>";
dartLogging($currentScript, "invXML : $invXML", $codeStr);
$result = false;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
$iPicTrackID = 0;
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "uspDARTDeliveryPicture '" . $invXML . "'"; // tblDARTDeliveryPicture
		$stmt = $dbh->query($sql);
		$returnSet = $stmt->fetch(PDO::FETCH_BOTH);
		$iPicTrackID = $returnSet['iPicTrackID'];
		$stmt->closeCursor();

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
			$sqlParts = explode(' ', $sql);
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount++;
				$sqlFailed = true;
				sleep(DART_SQL_TIMEOUT_SLEEP);
			} else {
				sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		} else {
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
			sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			exit();
		}
	}
}
$fileName = 'delivery_pic_' . $iPicTrackID . ".png";
$filePath = SPConsts::TempDir . $fileName;
// Create from the encoded string
if (!$imgSrc = imagecreatefromstring(base64_decode($jd->deliveryimage))) {
	$errMsg = "Could not create image from signatureimage data, saleID = " . $invoice->saleid . ", code = " . $codeStr . " : file = $file";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', $errMsg, true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}
$width = imagesx($imgSrc);
$height = imagesy($imgSrc);

// Make the new image
if (!$imgDest = imagecreatetruecolor($width, $height)) {
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving delivery image.', "Could not create new true color image.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

// Copy sent into new
if (!imagecopy($imgDest, $imgSrc, 0, 0, 0, 0, $width, $height)) {
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving delivery image.', "Could not copy source image to new image.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

// Write it out
if (!imagepng($imgDest, $filePath)) {
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving delivery image.', "Could not save png image.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

// Write to Azure
try {
	$azb = new AzureBlobSP('specprodstorage');
	$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_DART_DELIVERY_PICS_DIR, '', $fileName, $filePath, 'image/png');
	unlink($filePath);
} catch (SP_Exception $e) {
	$errMsg = $e->getMessage();
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'Delivery Picture Upload Error');
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving delivery image to Azure.', "Could not save delivery image to Azure.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

sendResult();
dartLogging($currentScript, "  Success", $codeStr);
