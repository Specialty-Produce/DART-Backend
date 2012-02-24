<?php
include_once 'global_CDC.php';
include 'dart_init.php';

$invNum = filter_input ( INPUT_POST, 'i', FILTER_VALIDATE_INT );
$locID = filter_input ( INPUT_POST, 'l', FILTER_VALIDATE_INT );

if ($invNum == -1)
	$sigImage = DART_SIG_DIR . 'darkstop.png';
else
	$sigImage = DART_SIG_DIR . $locID . '/' . $invNum . '.png';
readfile($sigImage);  
exit();
?>