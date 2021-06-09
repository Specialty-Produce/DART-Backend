<!DOCTYPE html>
<html lang="en">
<head>
<title>iPad Login Info</title>
</head>
<body>
	<h3>iPad Login Info</h3>
<?php echo date('n/j/y'); ?><br />
	<pre>
<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
function sortEntries($a, $b) {
	$cmpUDID = strnatcmp ( $a ['udid'], $b ['udid'] );
	if ($cmpUDID == 0) {
		$cmpDevice = strnatcmp ( $a ['device'], $b ['device'] );
		if ($cmpDevice == 0) {
			$cmpUser = strnatcmp ( $a ['user'], $b ['user'] );
			if ($cmpUser == 0) {
				return strnatcmp ( $a ['token'], $b ['token'] );
			} else {
				return $cmpUser;
			}
		} else {
			return $cmpDevice;
		}
	} else {
		return $cmpUDID;
	}
}

$filename = DART_LOG_DIR . "login.php.log";
$fh = fopen ( $filename, "r" );

$entries = array ();
while ( ($line = fgets ( $fh )) !== false ) {
	if (preg_match ( '/.+ jsondata=(.+) : \d+.+/', $line, $matches )) {
		$jd = json_decode ( $matches [1] );
		$entries [] = array (
				'udid' => $jd->udid,
				'token' => $jd->apntoken,
				'device' => $jd->devicename,
				'user' => $jd->username 
		);
	}
}
fclose ( $fh );
usort ( $entries, 'sortEntries' );

$udids = array ();
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$stmt = $dbh->query ( "select distinct ltrim(rtrim(sUDIDDART)) as udid, iAutoID as devid
from tblIpadInfo
where LEN(sUDIDDART) > 1
order by udid" );
	foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row )
		$udids [$row ['udid']] = $row ['devid'];
	
	$dbh = null;
} catch ( PDOException $e ) {
	echo "Database error...";
	exit ();
}

$lastI = $entries [0];
$devID = (array_key_exists ( $lastI ['udid'], $udids )) ? $udids [$lastI ['udid']] : 0;
echo $lastI ['udid'] . ',' . $lastI ['device'] . ',' . $lastI ['user'] . ',' . $lastI ['token'] . ',' . $devID . "\n";
foreach ( $entries as $i ) {
	if (sortEntries ( $lastI, $i ) == 0)
		continue;
	else {
		$devID = (array_key_exists ( $i ['udid'], $udids )) ? $udids [$i ['udid']] : 0;
		echo $i ['udid'] . ',' . $i ['device'] . ',' . $i ['user'] . ',' . $i ['token'] . ',' . $devID . "\n";
		$lastI = $i;
	}
}
?>
</pre>
</body>
</html>