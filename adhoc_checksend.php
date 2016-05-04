<?php
exit();
include_once 'global_CDC.php';
try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	SP_ErrorLogging ( "adhoc_checksend SUCCESS", false, DART_ERROR_LOG );
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errorTxt = $e->getFile () . " (" . $e->getLine () . ") : " . $e->getMessage ();
	SP_ErrorLogging ( "adhoc_checksend : " . $errorTxt, false, DART_ERROR_LOG );
}
exit();
?>