<?php
include_once 'global_CDC.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include '../dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

error_log ( "$currentScript : START" );

// Email the log file to DART developer
$logFile = "errorreport";
$outFile = DART_LOG_DIR . $logFile . ".php.logxxx";
if (file_exists ( $outFile )) {
	$mail = new PHPMailerSP ();
	$mail->setApiKey ( 'acct' );
	$mail->FromName = "Specialty Produce DART";
	$mail->From = "itadmin@specialtyproduce.com";
	$mail->Subject = "azDART Log Files : " . date ( 'ymd' );
	$mail->AddAddress ( "terry.ace.sp2@gmail.com", "Terry Grossman" );
	$mail->Body = "Attached are the daily log(s) for DART:\n\n";
	$mail->AddAttachment ( $outFile, $logFile . "_" . date ( 'ymd' ) . ".txt" );
	$mail->Body .= $logFile . "\n";
	if (! $mail->Send ()) {
		SP_ErrorLogging ( "Could not send DART log files", true, "", "DART Log File email" );
	}
}

// Archive the log files
$archiveDir = DART_LOG_ARCHIVE_DIR . date ( 'Y_m_d' );
if (mkdir ( $archiveDir )) {
	$handle = opendir ( DART_LOG_DIR );
	if ($handle != false) {
		while ( false !== ($file = readdir ( $handle )) ) {
			if (is_file ( DART_LOG_DIR . $file )) {
				rename ( DART_LOG_DIR . $file, $archiveDir . '/' . $file );
			}
		}
		closedir ( $handle );
	} else {
		SP_ErrorLogging ( "Could not open " . DART_LOG_DIR, true, "", "$currentScript : Error" );
	}
} else {
	SP_ErrorLogging ( "Could not create $archiveDir", true, "", "$currentScript : Error" );
}

// Remove old log folders
$daysBack = 30;
$mTimeValue = time () - ($daysBack * 24 * 60 * 60);
$dirList = scandir ( DART_LOG_ARCHIVE_DIR );
foreach ( $dirList as $dirName ) {
	// Is it a dir
	$dirPath = DART_LOG_ARCHIVE_DIR . $dirName;
	if (is_dir ( $dirPath )) {
		if (filemtime ( $dirPath ) < $mTimeValue) {
			$files = new RecursiveIteratorIterator ( new RecursiveDirectoryIterator ( $dirPath ), RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $fileinfo ) {
				$fInfo = $fileinfo->getRealPath ();
				if (! is_dir ( $fInfo ))
					unlink ( $fInfo );
			}
			error_log ( "$currentScript : Removed $dirPath" );
			rmdir ( $dirPath );
		}
	}
}

// Temp file cleanup
$cleanupLocations = array (
		array (
				'folder' => DART_PDF_DIR,
				'maxAge' => 30 
		),
		array (
				'folder' => DART_PTF_LOG_DIR,
				'maxAge' => 60 
		) 
);
$anHour = 60 * 60;
$aDay = $anHour * 24;
$unlinkErrors = array ();
$theTime = time ();
foreach ( $cleanupLocations as $loc ) {
	$fileList = array ();
	$fileWaitTime = $aDay * $loc ['maxAge'];
	$handle = opendir ( $loc ['folder'] );
	if ($handle != false) {
		while ( false !== ($file = readdir ( $handle )) ) {
			if ($file != "." && $file != "..") {
				$filename = $loc ['folder'] . $file;
				$fileRealPath = realpath ( $filename );
				if ($fileRealPath != false) {
					$fileModTime = filemtime ( $fileRealPath );
					if (($theTime - $fileModTime) > $fileWaitTime) {
						$fileList [] = $fileRealPath;
					}
				} else {
					$unlinkErrors .= "Error realpath : $filename\n";
				}
			}
		}
		closedir ( $handle );
	} else {
		SP_ErrorLogging ( "Error opening pdfPath : {$loc ['folder']}", true, '', "$currentScript : Error" );
	}
	foreach ( $fileList as $fname ) {
		error_log ( "$currentScript : removing $fname" );
		if (! unlink ( $fname )) {
			$unlinkErrors [] = "Error unlinking : $fname";
		}
	}
}
if (count ( $unlinkErrors ) > 0)
	SP_ErrorLogging ( "Unlink errors :\n" . implode ( '\n', $unlinkErrors ), true, '', "$currentScript : Error" );

error_log ( "$currentScript : END" );
?>