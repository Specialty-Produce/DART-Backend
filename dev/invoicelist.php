<?php
include_once 'global_CDC.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$pageMsg = '';
$saleID = 0;

$dartStatus = array('0', '1', 'Loading', 'En Route', 'Being Delivered', 'Delivered');
// Get the current list of invoices
try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	// Get the current list of invoices
	$sql = "select s.*, l.sDescription, u.txtFirstName from tblSale s
			join tblLocation l
			on s.iLocationDestinationID = l.iLocationID
			left join tblUser u
			on s.iDriverID = u.iUserID
			where s.iLocationDestinationID in (3291, 3292, 3293, 3294)
			and s.dtShip >= '" . date('n/j/Y') . "'
			and s.iLocationSourceID=1
			order by l.sDescription, s.iSaleID";
	$stmt = $dbh->query ( $sql );
	$invList = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor();
	
	$dbh = null;
} catch ( PDOException $e ) {
	echo $e->getMessage ();
	exit ();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>DART Dev - List Invoices</title>
<link rel="stylesheet" type="text/css" href="dartdev.css" />
</head>
<body>
<h3>List Invoices</h3>
<form method="post" action="invoicelist.php">
<table cellpadding="5" cellspacing="5" border="0">
	<tr>
		<th>Sale ID</th>
		<th>Location</th>
		<th>Driver</th>
		<th>Status</th>
		<th>Route ID</th>
		<th>Last Update</th>
		<th>Delivered</th>
	</tr>
	<?php
		foreach ($invList as $entry) {
			$status = (is_null($entry['iDartStatusID'])) ? 'null' : $dartStatus[$entry['iDartStatusID']];
			$route = (is_null($entry['iDartRouteID'])) ? 'null' : $entry['iDartRouteID'];
			$update = (is_null($entry['dtDartLastUpdated'])) ? 'null' : $entry['dtDartLastUpdated'];
			$delivered = (is_null($entry['dtDartDelivered'])) ? 'null' : $entry['dtDartDelivered'];
	?>
	<tr>
		<td align="center"><?php echo $entry['iSaleID']; ?></td>
		<td align="center"><?php echo $entry['sDescription']; ?></td>
		<td align="center"><?php echo $entry['txtFirstName']; ?></td>
		<td align="center"><?php echo $status; ?></td>
		<td align="center"><?php echo $route; ?></td>
		<td align="center"><?php echo $update; ?></td>
		<td align="center"><?php echo $delivered; ?></td>
	</tr>
	<?php } ?>
</table>
</form>
</body>
</html>