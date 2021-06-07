<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>DART Index Page</title>
</head>
<body>
<h3>DART Index Page</h3>
<?php echo date('m/d/y H:i:s'); ?>
<p>Your IP : <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
PHP Version : <?php echo phpversion(); ?>
<p>SQL Version : <?php
	try {
	$dbh = new PDO ( 'spdb', '', '' );
	// set the error reporting attribute.
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// $sql = "uspDARTLogin '$username', '$password', '$udid', '$version', '$deviceName', '$token'";
	
	$sql = "select @@VERSION as VersionInfo";
	$stmt = $dbh->query ( $sql );
	$result = $stmt->fetch ( PDO::FETCH_ASSOC );
	// Returns -1 on invalid username or password
	$versionInfo = $result ['VersionInfo'];
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$versionInfo = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
}
echo $versionInfo;
?></p>
<?php
$sigImageFile = '/home/site/dartsigs/darkstop.png';
$sigData = file_get_contents($sigImageFile);
$base64 = 'data:image/png;base64,' . base64_encode($sigData);
?>
<img src="<?php echo $base64 ?>" alt="Dark Stop" />
</body>
</html>