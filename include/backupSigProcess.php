<?php
include_once 'global_CDC.php';
include '../dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// Make sure path mapping is working
$testImageFile = DART_SIG_DIR . 'darkstop.png';
if (! is_file ( $testImageFile )) {
	SP_ErrorLogging ( "$currentScript : DartSig path mapping not working! !is_file($testImageFile)", true, '', "$currentScript : Path Mapping Broken" );
	exit ();
}

$root = scandir ( DART_SIG_BACKUP_DIR );
$locDirs = array ();

foreach ( $root as $entry ) {
	if ($entry == "." || $entry == "..")
		continue;
	if (is_dir ( DART_SIG_BACKUP_DIR . $entry ))
		$locDirs [] = $entry;
}

$copiedFiles = array ();
foreach ( $locDirs as $locID ) {
	$sigFiles = array ();
	$locDir = DART_SIG_BACKUP_DIR . $locID;
	$locRoot = scandir ( $locDir );
	foreach ( $locRoot as $entry ) {
		if ($entry == "." || $entry == "..")
			continue;
		if (is_file ( $locDir . '/' . $entry )) {
			$sourceSig = $locDir . '/' . $entry;
			$destDir = DART_SIG_DIR . $locID;
			$destSig = $destDir . '/' . $entry;
			if (! is_dir ( $destDir ))
				if (! mkdir ( $destDir )) {
					SP_ErrorLogging ( "$currentScript : Could not create folder for locationID=$locID : $destDir", true, '', "DART : Could not create folder : $currentScript" );
					$destSig = false;
				}
			if ($destSig !== false) {
				if (! copy ( $sourceSig, $destSig ))
					SP_ErrorLogging ( "$currentScript : Error copying $sourceSig to $destSig", true, '', "$currentScript : Sig file copy error" );
				else {
					$copiedFiles [] = $sourceSig;
					unlink ( $sourceSig );
				}
			}
		}
	}
	if (! rmdir ( $locDir ))
		SP_ErrorLogging ( "$currentScript : Error removing $locDir", true, '', "$currentScript : Remove directory error" );
}
if (count ( $copiedFiles ) > 0) {
	SP_ErrorLogging ( "$currentScript : Copied backup signature file(s):\n" . implode ( "\n", $copiedFiles ), true, '', "$currentScript : Copied Files" );
}