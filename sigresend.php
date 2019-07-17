<?php
include_once 'global_CDC.php';
include 'dart_init.php';
include_once 'classes_SP/class_InvoiceSP.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<sigresend status="failed" errmsg="XXX">
</sigresend>
EOT;

$successXML = <<< EOT
<?xml version="1.0"?>
<sigresend status="success">
</sigresend>
EOT;

dartLogging ( $currentScript, "post=" . print_r($_POST, true), $codeStr );

// Get the POST data
// iPad Name
$ipadname = filter_input ( INPUT_POST, 'ipadname', FILTER_SANITIZE_STRING );
if ($ipadname == FALSE || is_null ( $ipadname )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid iPad name', $badXML );
	echo $badXML;
	exit ();
}

// Signature filename
$sigfilename = filter_input ( INPUT_POST, 'sigfilename', FILTER_SANITIZE_STRING );
if ($sigfilename == FALSE || is_null ( $sigfilename )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid crash timestamp', $badXML );
	echo $badXML;
	exit ();
}
preg_match('/[^\d]+_(\d+)_.+/', $sigfilename, $matches);
$saleID = $matches[1];

// Signature data
$sigdata = filter_input ( INPUT_POST, 'sigdata', FILTER_SANITIZE_STRING );
if ($sigdata == FALSE || is_null ( $sigdata )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid crash log name', $badXML );
	echo $badXML;
	exit ();
}

// Get the invoice information
try {
	$inv = new InvoiceSP();
	$inv->retrieveInvoice($saleID);
	$locationID = $inv->getLocationID();
} catch (SP_Exception $e) {
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML );
	echo $badXML;
	exit ();
}

dartLogging ( $currentScript, "     sale ID = $saleID, location ID = $locationID", $codeStr );

// SIGNATURE IMAGE
$filedir = DART_SIG_DIR . $locationID;
if (! is_dir ( $filedir )) {
	if (! mkdir ( $filedir )) {
		$errMsg = "Could not create folder for locationID = " . $locationID;
		dartLogging ( $currentScript, "    Could not create folder for locationID = " . $locationID, $codeStr );
		SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
		$badXML = preg_replace ( '/XXX/', $currentScript . ' : Could not create folder for locationID = ' . $locationID, $badXML );
		echo $badXML;
		exit ();
	}
}
// Convert the image to 24-bit and save
$file = $filedir . '/' . $saleID . ".png";

// Create from the encoded string
if (! $imgSrc = imagecreatefromstring ( base64_decode ( $sigdata ) )) {
	$errMsg = "Could not create image from signatureimage data, saleID = " . $saleID . ", code = " . $codeStr;
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
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

echo $successXML;
exit ();
?>