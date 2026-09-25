<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_DART.php';
include_once 'classes_SP/class_AzureBlobSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	dartLogging($currentScript, "Starting...\n" . $_POST['jsondata'], $codeStr);
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

// Process the image
$fileName = $jd->lineitemid . ".png";
dartLogging($currentScript, "Image: $fileName", $codeStr);
$filePath = SPConsts::TempDir . $fileName;
// Create from the encoded string
if (!$imgSrc = imagecreatefromstring(base64_decode($jd->productimage))) {
	$errMsg = "Could not create image from productimage data, lineitemid = " . $jd->lineitemid . ", code = " . $codeStr;
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving item image.', $errMsg, true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}
$width = imagesx($imgSrc);
$height = imagesy($imgSrc);

// Make the new image
if (!$imgDest = imagecreatetruecolor($width, $height)) {
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving item image.', "Could not create new true color image.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

// Copy sent into new
if (!imagecopy($imgDest, $imgSrc, 0, 0, 0, 0, $width, $height)) {
	sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving item image.', "Could not copy source image to new image.", true);
	dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
	exit();
}

// Write to Azure
try {
	$azb = new AzureBlobSP('specprodstorage');
	imagepng($imgDest, $filePath);
	if ($jd->pictype == 'poorquality') {
		$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_POOR_QUALITY_PICS_DIR, '', $fileName, $filePath, 'image/png');
		DART::alertPoorQuality($jd->lineitemid);
	} else {
		$errMsg = "Invalid pictype : " . $jd->pictype . ", code = " . $codeStr;
		SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
		sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Invalid picture type.', "$errMsg", true);
		dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
		exit();
	}
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
