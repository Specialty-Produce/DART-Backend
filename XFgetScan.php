<?php
include_once 'global_CDC.php';
include 'dart_init.php';

$invNum = filter_input ( INPUT_POST, 'i', FILTER_VALIDATE_INT );
$locID = filter_input ( INPUT_POST, 'l', FILTER_VALIDATE_INT );

$scanImage = SCAN_INVOICE_DIR . $locID . '/' . $invNum . '.pdf';

if (file_exists($scanImage)) {
	readfile($scanImage);  
} else {
	echo '';
}
exit();
?>