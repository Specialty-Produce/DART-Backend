<?php
include_once 'global_CDC.php';
include 'dart_init.php';

$userID = filter_input ( INPUT_GET, 'u' );
if (is_null ( $userID ) || $userID == false)
	$userID = 635;

$filename = DART_LOG_DIR . 'putgpsdata.php' . ".log";

$entries = array ();

$re = '/^\[([^\]]+)] :  : jsondata=({"userid":"' . $userID . '".+)$/';

echo "<pre>";
$file = fopen ( $filename, "r" ) or die ( "Cannot open file!\n" );
while ( $line = fgets ( $file ) ) {
	if (preg_match ( $re, $line, $matches )) {
		$entries [] = array (
				'ts' => $matches [1],
				'json' => $matches [2],
				'line' => $line
		);
	}
}
fclose ( $file );
$entries = array_reverse ( $entries );

if (count ( $entries ) == 0)
	echo "No entries for $userID";
else {
	foreach ( $entries as $data ) {
		$jd = json_decode ( $data ['json'] );
		$gpsInfo = json_decode ( $jd->gpsjson );
		echo $data ['ts'] . " : " . $jd->dartsessionid . " - " . count ( $gpsInfo ) . " - " . $jd->gpsjson . "\n";
	}
}

echo "</pre>";