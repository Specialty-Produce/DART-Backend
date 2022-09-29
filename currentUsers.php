<?php
include_once 'global_CDC.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

try {
	$dbh = new PDO ( 'spdb', '', '' );
	$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	
	$sql = "select distinct d.sDARTVersion, d.siPadName, u.txtFirstName, u.txtLastName, t.sDescription
			from tblDARTDeliverySession d
			left join tblUser u
			on d.iUserID=u.iUserID
			left join tblDARTTruckData dtd on dtd.iSessionID = d.iDartRouteID
			left join tblTruck t on t.iTruckID = dtd.iTruckID
			where
			d.dtStart >= convert(date, getdate())
			order by d.sDARTVersion, u.txtFirstName, u.txtLastName";
	
	$stmt = $dbh->query ( $sql );
	$sessions = $stmt->fetchAll ( PDO::FETCH_BOTH );
	$stmt->closeCursor ();
	
	$dbh = null;
} catch ( PDOException $e ) {
	$errMsg = $e->getFile () . ' (' . $e->getLine () . ')' . $e->getMessage ();
	echo $errMsg;
	exit ();
}
?>
<!DOCType html>
<html>
<head>
<title>DART Users</title>
</head>
<body>
<h3>Today's DART Users</h3>
<?php echo date('m/d/y H:i'); ?><br/><br/>
<table>
	<thead>
		<tr>
			<th>DART Version</th>
			<th>User Name</th>
			<th>iPad Name</th>
			<th>Vehicle</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($sessions as $entry) { ?>
		<tr>
			<td><?php echo $entry['sDARTVersion']; ?></td>
			<td><?php echo mb_convert_encoding($entry['txtFirstName'] . ' ' . $entry['txtLastName'], 'UTF-8', 'Windows-1252'); ?></td>
			<td><?php echo $entry['siPadName']; ?></td>
			<td><?php echo $entry['sDescription']; ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
</body>
</html>