<?php
/*
 * Cloudflare Health Check endpoint
 */
include_once 'global_CDC.php';
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// SQL Version
	$sql = "select @@VERSION as VersionInfo";
	$stmt = $dbh->query ( $sql );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	// Returns -1 on invalid username or password
	$versionInfo = $result ['VersionInfo'];
	$stmt->closeCursor ();
	$dbh = null;
} catch ( PDOException $e ) {
	http_response_code(500);
	echo "Database Error";
	exit();
}
http_response_code(200);
?>
Looks Good