<?php

$tmpPath = 'D:/vDart/delcomplete';

$handle = opendir ( $tmpPath );
if ($handle != false) {
	while ( false !== ($file = readdir ( $handle )) ) {
		if ($file != "." && $file != "..") {
			$filename = $tmpPath . '/' . $file;
			$fh = fopen($filename, 'r');
			if ($fh) {
				while (($line = fgets($fh)) !== false) {
					$pregReturn = preg_match('/^.{34}jsondata=(.+)$/', $line, $matches);
					if ($pregReturn) {
						$jd = json_decode ( $matches[1]  );
						$locationID = $jd->deliveryjson->delivery->locationid;
						$signerID = $jd->deliveryjson->delivery->signerid;
						//echo "$locationID,$signerID";
						foreach ( $jd->deliveryjson->invoice_list as $invoice ) {
							//echo "," . $invoice->saleid;
							echo $invoice->saleid . "\n";
						}
						//echo "\n";
					}
				}
				fclose($fh);
			} else {
				echo "Failed to open file : $filename\n";
			}
		}
	}
	closedir ( $handle );
} else {
	echo "Failed to open directory : $tmpPath\n";
}