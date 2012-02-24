<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$fileWaitTime = 5 * 60;
$pdfPath = 'C:\Temp\invoicePDFs\\';
$theTime = time();
if ($handle = opendir ( $pdfPath )) {
	while ( false !== ($file = readdir ( $handle )) ) {
		if ($file != "." && $file != "..") {
			$filename = $pdfPath . $file;
			$fileModTime = filemtime($filename);
			if (($theTime - $fileModTime) > $fileWaitTime) {
				//exec("acrowrap /t $filename");
				sleep(3);
				unlink($filename);
			}
		}
	}
	closedir ( $handle );
} else {
	SP_ErrorLogging ( "Error opening pdfPath : $pdfPath", true, DART_ERROR_LOG );
}
exit(0);
?>