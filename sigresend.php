<?php
include_once 'global_CDC.php';
include 'dart_init.php';
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

// Signature data
$sigdata = filter_input ( INPUT_POST, 'sigdata', FILTER_SANITIZE_STRING );
if ($sigdata == FALSE || is_null ( $sigdata )) {
	$badXML = preg_replace ( '/XXX/', $currentScript . ' : Invalid crash log name', $badXML );
	echo $badXML;
	exit ();
}

echo $successXML;
exit ();
?>