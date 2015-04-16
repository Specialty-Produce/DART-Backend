<?php
include_once 'global_CDC.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$pageMsg = '';
$numItems = 0;
$saleID = 0;
$currentCatalog = 1088;
if (isset($_POST['ciSubmit'])) {
	if ($_POST['locationSel'] == 0) {
		$pageMsg .= "<br/>No location chosen...";
	}
	if ($_POST['driverSel'] == 0) {
		$pageMsg .= "<br/>No driver chosen...";
	}
	if ($_POST['numItems'] == 0) {
		$numItems = rand(1, 10);
	} else {
		$numItems = $_POST['numItems'];
	}
	if ($pageMsg == '') {
		// Create and submit the invoice
		$prodList = array();
		try {
			$dbh = new PDO('spdb', '', '');
    		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    		// Get the prodList
    		$stmt = $dbh->query("uspWebOOOrderForm " . $_POST['locationSel'] . ", " . $currentCatalog . ", 0");
    		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
    			$prodID = $row['iProductID'];
    			$prodList[$prodID] = array('description'=>$row['sCustFieldDesc'], 'unavailable'=>($row['iUnavailable'] > 0 ? true : false), 'reason'=>$row['sReason'], 'firstRead'=>true, 'units'=>array());
    			$prodList[$prodID]['defaultUnitSet'] = false;
    		}
    		$stmt->closeCursor();

    		// Get the units
    		$stmt = $dbh->query("uspWebOOProfilePrice " . $_POST['locationSel'] . ", " . $currentCatalog);
    		foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
    			$prodID = $row['iProductID'];
    			if (array_key_exists($prodID, $prodList)) {
    				if ($prodList[$prodID]['firstRead'] == true) {
    					if (! array_key_exists($row['iUnitID'], $prodList[$prodID]['units'])) {
    						$prodList[$prodID]['units'][$row['iUnitID']] = array('defaultUnit'=>0, 'description'=>$row['sDescription'], 'price'=>round($row['Price'],2));
    						if ($row['iUnitDefault'] == 1 && $prodList[$prodID]['defaultUnitSet'] == false) {
								$prodList[$prodID]['units'][$row['iUnitID']]['defaultUnit'] = 1;
								$prodList[$prodID]['defaultUnitSet'] = true;
								$prodList[$prodID]['discount'] = 0.0;
							}
    					}
    				}
    			}

    		}
    		$stmt->closeCursor();

    		// Generate the invoice
    		$invoice = array();
    		for ($i = 0 ; $i < $numItems ; $i++) {
    			$itemID = array_rand($prodList, 1);
    			while (array_key_exists($itemID, $invoice)) {
    				$itemID = array_rand($prodList, 1);
    			}
    			$invoice[$itemID] = array('unitID'=>array_rand($prodList[$itemID]['units'], 1), 'quantity'=>rand(1, 5));
    		}

    		// Get a new invoice number
			$stmt = $dbh->query ( "uspWebOOGetInvoiceNumber " . $_POST['locationSel'] . ", '" . date('n/j/Y') . "'" );
			foreach ( $stmt->fetchAll ( PDO::FETCH_ASSOC ) as $row ) {
				$saleID = $row ['iSaleID'];
			}
			$stmt->closeCursor ();

			// Begin the transaction to atomize the update.
			$dbh->beginTransaction( );

			// Define/init bindParam variables
			$notes = '';
			$prodID = 0;
			$unitID = 0;
			$quantityOrd = 0;
			$quantityShip = 0;
			$price = 0;
			$discOL = 0.0;
			$discPerc = 0.0;
			$discDoll = 0.0;
			$discOT = 0.0;

			// Get the driver assigned
			$stmt = $dbh->prepare("UPDATE tblSale SET iDriverID=:driverID WHERE iSaleID=:invoiceNum");
			$stmt->bindParam(':driverID', $_POST['driverSel']);
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->execute( );

			$stmt = $dbh->prepare("INSERT INTO tblSaleDetail
							(iSaleID, iProductID, iUnitID, fOrderQuantity, fShipQuantity,
							mUnitPrice, sItemNotes, iShort, iPurchaseFrom, iFilled,
							iFilledPurchase, iAddItem, fDiscount, fDiscountCategory, mDiscountCategory,
							iOnlineOrderItem, fDiscountOnTime, fCorporateDiscount, iOOCatalogID)
							VALUES
							(:invoiceNum, :prodID, :unitID, :quantityOrd, :quantityShip,
							:price, :notes, 0, 0, 0,
							0, 0, 0.0, 0.0, 0,
							1, 0.0, 0, :catalogID)");
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->bindParam(':prodID', $prodID);
			$stmt->bindParam(':unitID', $unitID);
			$stmt->bindParam(':quantityOrd', $quantityOrd);
			$stmt->bindParam(':quantityShip', $quantityShip);
			$stmt->bindParam(':price', $price);
			$stmt->bindParam(':notes', $notes);
			$stmt->bindParam(':catalogID', $currentCatalog);

			foreach ( $invoice as $prodID => $entry ) {
				$unitID = $entry['unitID'];
				$quantityOrd = $entry['quantity'];
				$quantityShip = $quantityOrd;
				$price = round($prodList[$prodID]['units'][$unitID]['price'], 2);
				$stmt->execute( );
			}
			unset($stmt);

			// Finally, change the iLocationSourceID from 2 to 1 to make the invoice live!
			$stmt = $dbh->prepare("UPDATE tblSale SET iLocationSourceID=1 WHERE iSaleID=:invoiceNum");
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->execute( );

			// Commit the statements.  If something fails, it will be rolled back...
			$result = $dbh->commit( );

    		$dbh = null;
		} catch (PDOException $e) {
			echo $e->getMessage();
			exit();
		}
	}
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>DART Dev - Create Invoice</title>
	<link rel="stylesheet" type="text/css" href="dartdev.css" />
</head>
<body>
<h3>Create test invoice</h3>
<form method="post" action="createinvoice.php">
<table cellpadding="2" cellspacing="2" border="0">
	<tr>
		<td>Location : </td>
		<td>
			<select name="locationSel" id="locationSel" size=1 style="font-size: 1em;">
				<option value="0">Select...</option>
				<option value="3291">Testing #1</option>
				<option value="3292">Testing #2</option>
				<option value="3293">Testing #3</option>
				<option value="3294">Testing #4</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Driver : </td>
		<td>
			<select name="driverSel" id="driverSel" size=1 style="font-size: 1em;">
				<option value="0">Select...</option>
				<option value="9215">Christopher</option>
				<option value="635">Roger</option>
			</select>
		</td>
	</tr>
	<tr>
		<td valign="top"># Items : </td>
		<td><input type="text" name="numItems" size="5" value="0"/><br/><span style="font-size: 0.7em; font-style: italic;">0 = random 1-10</span></td>
	</tr>
</table>
<input type="submit" name="ciSubmit" value="Submit"/>
</form>
<div class="msg"><?php echo $pageMsg; ?></div>
<?php if ($saleID > 0) { ?>
<h3>Invoice</h3>
<b>SaleID = <?php echo $saleID; ?></b><br/>
<pre>
<?php echo "Num Items = " . $numItems . "\n"; ?>
<?php print_r($invoice); ?>
</pre>
<h3>prodList</h3>
<pre>
<?php print_r($prodList); ?>
</pre>
<?php } ?>
</body>
</html>