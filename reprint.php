<?php

// Calls on our local network get access to the pages, otherwise you have to have validated as an SP employee
$dotted_ip_address = $_SERVER ['REMOTE_ADDR'];
$ip_number = (ip2long ( $dotted_ip_address )) ? sprintf ( "%u", ip2long ( $dotted_ip_address ) ) : 0;
if ($ip_number < 1185397282 || $ip_number > 1185397309) {
	echo "Access denied...";
	exit ();
}

/* WORKS */
$invType = filter_input ( INPUT_GET, 't', FILTER_SANITIZE_NUMBER_INT );
$invNum = filter_input ( INPUT_GET, 'i', FILTER_SANITIZE_NUMBER_INT );
$returnTxt = "Access granted...\n\n";
$returnTxt .="Type = " . $invType . ", Num = " . $invNum . "\n\n";
$returnTxt .= var_export ( $_SERVER, true );
echo $returnTxt;

/* WORKS!!!
$filename = "abc.pdf";
$handle = fopen($filename, "rb");
$contents = fread($handle, filesize($filename));
fclose($handle);
echo $contents;
exit();
*/
?>