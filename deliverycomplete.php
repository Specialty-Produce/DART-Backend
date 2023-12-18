<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_AzureFileSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<deliverycomplete status="failed" code="0" retry="true" errmsg="XXX">
</deliverycomplete>
EOT;

$successXML = <<< EOT
<?xml version="1.0"?>
<deliverycomplete status="success">
</deliverycomplete>
EOT;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// TODO : CRC check
// jsonCRC32

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	// dartLogging ( $currentScript, "jsondata=" . (preg_replace ( '/(,"signatureimage":")[^"]+(","status")/', '$1 --- $2', $appJSON )), $codeStr );
	dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);
	// dartLogging ( $currentScript, "POST=" . print_r($_POST, true), $codeStr );
} else {
	$appJSON = false;
}

if (isset($_POST['debuginfo'])) {
	dartLogging($currentScript, "debuginfo=" . $_POST['debuginfo'], $codeStr);
}

if (MAINTENANCE_MODE) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : Maintenace Mode', $badXML);
	// $badXML = preg_replace ( '/retry="true"/', 'retry="false"', $badXML );
	echo $badXML;
	dartLogging($currentScript, " in Maintenace Mode", $codeStr);
	exit();
}

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	dartLogging($currentScript, "    jsondata is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : No jsondata supplied', $badXML);
	// $badXML = preg_replace ( '/retry="true"/', 'retry="false"', $badXML );
	echo $badXML;
	exit();
}

// Truncated appJSON - implies the connection was lost in midtransmission
if (!preg_match('/,"userid":"\d+"}$/', $appJSON)) {
	if (!(preg_match('/^{"userid":"\d+"/', $appJSON) && preg_match('/]}$/', $appJSON))) {
		dartLogging($currentScript, "    jsondata is TRUNCATED", $codeStr);
		exit();
	}
}

// TODO CRC check

// Force success for a SaleID that had bad JSON that was fixed and adhoc completed
$invoiceBadJSON = false;
if ($invoiceBadJSON !== false && preg_match('/"saleid":"' . $invoiceBadJSON . '"/', $appJSON)) {
	echo $successXML;
	SP_ErrorLogging("Bad JSON invoice $invoiceBadJSON send SUCCESS XML.", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
	exit();
}

// Good to go...
$jd = json_decode($appJSON);
if ($jd == FALSE || is_null($jd)) {
	// Capture bad JSON data that has been fixed already...
	if (strpos($appJSON, '"saleid":"4095326"') !== false) {
		dartLogging($currentScript, "    FIXED decoded jsondata is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
		echo $successXML;
		SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. FIXED", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
		exit();
	} else {
		dartLogging($currentScript, "    decoded jsondata is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
		$badXML = preg_replace('/XXX/', $currentScript . ' : Invalid jsondata supplied', $badXML);
		echo $badXML;
		SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. Hand fix and adhoc enter data", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
		exit();
	}
}

// Done if the DEBUG user
if ($jd->userid == DEBUG_USERID) {
	echo $successXML;
	exit();
}

// Pull these out for easier reference
$locationID = $jd->deliveryjson->delivery->locationid;
$signerID = $jd->deliveryjson->delivery->signerid;
if ($signerID < 0) {
	$isDarkDrop = true;
	$signerID = DARK_STOP_ID;
} else {
	$isDarkDrop = false;
}

// Ignore printed invoice signers
if ($signerID != PRINTED_INVOICE_ID && $isDarkDrop == false) {
	// SIGNATURE IMAGE
	$testImageFile = DART_SIG_DIR . 'darkstop.png';
	if (is_file($testImageFile)) {
		// Azure Storage IS working
		$filedir = DART_SIG_DIR . $jd->deliveryjson->delivery->locationid;
	} else {
		// Azure Storage NOT working
		$filedir = DART_SIG_BACKUP_DIR . $jd->deliveryjson->delivery->locationid;
		SP_ErrorLogging("$currentScript : Azure storage NOT working : $testImageFile", true, DART_ERROR_LOG, "DART : Azure storage NOT working : $currentScript");
	}
	if (!is_dir($filedir)) {
		if (!mkdir($filedir)) {
			$errMsg = "DART : Could not create folder for locationID = " . $jd->deliveryjson->delivery->locationid . " : $filedir";
			dartLogging($currentScript, "    Could not create folder for locationID = " . $jd->deliveryjson->delivery->locationid, $codeStr);
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : Could not create folder : $currentScript");
			$badXML = preg_replace('/XXX/', $currentScript . ' : Could not create folder for locationID = ' . $jd->deliveryjson->delivery->locationid, $badXML);
			echo $badXML;
			exit();
		}
	}

	// Convert the image to 24-bit and save
	foreach ($jd->deliveryjson->invoice_list as $invoice) {
		$file = $filedir . '/' . $invoice->saleid . ".png";
		// Create from the encoded string
		if (!$imgSrc = imagecreatefromstring(base64_decode($invoice->signatureimage))) {
			$errMsg = "Could not create image from signatureimage data, saleID = " . $invoice->saleid . ", code = " . $codeStr;
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
			dartLogging($currentScript, "    Could not create image from signatureimage data", $codeStr);
			$badXML = preg_replace('/XXX/', $currentScript . ' : Could not create image from signatureimage data', $badXML);

			// Capture and clear repeating call **********
			if ($invoice->saleid == 3415845) {
				$badXML = $successXML;
				$errMsg = "Captured bad image saleID and sent success, saleID = " . $invoice->saleid . ", code = " . $codeStr;
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
				dartLogging($currentScript, "    Captured bad image saleID and sent success", $codeStr);
				continue;
			}
			echo $badXML;
			exit();
		} else {
			// Make the new image
			$width = imagesx($imgSrc);
			$height = imagesy($imgSrc);
			if (!$imgDest = imagecreatetruecolor($width, $height)) {
				dartLogging($currentScript, "    Could not create new true color image", $codeStr);
				$badXML = preg_replace('/XXX/', $currentScript . ' : Could not create new true color image', $badXML);
				echo $badXML;
				exit();
			} else {
				// Copy sent into new
				if (!imagecopy($imgDest, $imgSrc, 0, 0, 0, 0, $width, $height)) {
					dartLogging($currentScript, "    Could not copy source image to new image", $codeStr);
					$badXML = preg_replace('/XXX/', $currentScript . ' : Could not copy source image to new image', $badXML);
					echo $badXML;
					exit();
				} else {
					// Write it out
					if (!imagepng($imgDest, $file)) {
						dartLogging($currentScript, "    Could not save png image", $codeStr);
						$badXML = preg_replace('/XXX/', $currentScript . ' : Could not save png image', $badXML);
						echo $badXML;
						exit();
					}
				}
			}
		}
	}
}

// Get the invoices marked as "delivered", which is code 2 for this stored procedure
$updateCode = 2;
$invXML = '';
$result = false;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Prep for the XML version of invoice list for the stored procedure and collect cancelled invoices
		$cancelledByCustomerSaleIDs = array();
		$invXML = "<ROOT>\n";
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			$invXML .= '<Rec rID="' . $invoice->saleid . '" dtDelTime="' . $invoice->signtimestamp . '"/>' . "\n";
			if ($invoice->status == 'CANCELLED BY CUSTOMER')
				$cancelledByCustomerSaleIDs[] = $invoice->saleid;
		}
		$invXML .= "</ROOT>";

		// Check if this is a repeat call to deliverycomplete.php
		$repeatCall = false;
		$sql = "uspDARTCheckDeliveryDate '" . $invXML . "'";
		$stmt = $dbh->query($sql);
		foreach ($stmt->fetchAll(PDO::FETCH_BOTH) as $row) {
			if ($row['dtDartDelivered'] != '') {
				$repeatCall = true;
			}
		}
		$stmt->closeCursor();
		if ($repeatCall) {
			dartLogging($currentScript, "  Repeat Call : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
			$dbh = null;
			echo $successXML;
			exit();
		}

		// Process any signer deletions
		foreach ($jd->deliveryjson->delivery->signerdeletion as $deletionEntry) {
			$sql = "uspDARTAddSigner " . $deletionEntry->signerid . ", $locationID, '', '', '', 4, ''";
			$stmt = $dbh->query($sql);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$deletedID = $result['iUserID'];
			dartLogging($currentScript, "    uspDARTAddSigner iUserID=" . $deletedID . " DELETED", $codeStr);
			$stmt->closeCursor();
		}

		// First check to see if there is a signer
		if ($signerID == 0) {
			// Add the signer
			$sql = "uspDARTAddSigner 0, $locationID, '" . substr(preg_replace('/\'+/', '\'\'', trim($jd->deliveryjson->delivery->signerinfo->fname)), 0, 75) . "', ";
			$sql .= "'" . substr(preg_replace('/\'+/', '\'\'', trim($jd->deliveryjson->delivery->signerinfo->lname)), 0, 75) . "', ";
			$sigEmail = trim($jd->deliveryjson->delivery->signerinfo->email);
			$sql .= ($sigEmail == '') ? "''" : "'" . $sigEmail . "'";
			$sql .= ", 1, ";
			$sigPhone = formatPhone(trim($jd->deliveryjson->delivery->signerinfo->phone));
			$sql .= ($sigPhone == '') ? "''" : "'" . $sigPhone . "'";
			dartLogging($currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr);
			$stmt = $dbh->query($sql);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$signerID = $result['iUserID'];
			dartLogging($currentScript, "    uspDARTAddSigner iUserID=" . $signerID, $codeStr);
			$stmt->closeCursor();
		} elseif ($signerID > 0 && strlen(trim($jd->deliveryjson->delivery->signerinfo->fname)) > 1) {
			// Update the signer
			$sql = "uspDARTAddSigner $signerID, $locationID, '" . substr(preg_replace('/\'+/', '\'\'', trim($jd->deliveryjson->delivery->signerinfo->fname)), 0, 75) . "', ";
			$sql .= "'" . substr(preg_replace('/\'+/', '\'\'', trim($jd->deliveryjson->delivery->signerinfo->lname)), 0, 75) . "', ";
			$sigEmail = trim($jd->deliveryjson->delivery->signerinfo->email);
			$sql .= ($sigEmail == '') ? "''" : "'" . $sigEmail . "'";
			$sql .= ", 3, ";
			$sigPhone = formatPhone(trim($jd->deliveryjson->delivery->signerinfo->phone));
			$sql .= ($sigPhone == '') ? "''" : "'" . $sigPhone . "'";
			dartLogging($currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr);
			$stmt = $dbh->query($sql);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$signerID = $result['iUserID'];
			$stmt->closeCursor();
		}

		// Get the last update time according to the database
		$invTimesDB = array();
		$sql = "uspDARTCheckLastUpdate '" . $invXML . "'";
		$stmt = $dbh->query($sql);
		foreach ($stmt->fetchAll(PDO::FETCH_BOTH) as $row) {
			$lastUpdate = preg_replace('/(.*):\d{2}\.\d{3}$/', '$1', $row['dtDartLastUpdated']);
			$invTimesDB[$row['iSaleID']] = $lastUpdate;
		}
		$stmt->closeCursor();

		// Now mark the invoice as "delivered"
		$sql = "uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'";
		$resultDelivered = $dbh->exec($sql);

		// Update any "Cancelled by customer" invoices
		if (count($cancelledByCustomerSaleIDs) > 0) {
			// $sql = "????? '" . implode(',', $cancelledByCustomerSaleIDs);
			// $resultUpdateCancelled = $dbh->exec ( $sql );
		}

		// We need to build the list of invoices that have lastupdatetime values different between database and ipad
		$updateAtDeliveryFailXML = '';
		$saleDetailXML = '';
		$saleXML = '';
		$saleJSON = array();
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			$getAllLines = false;
			if ($invoice->lastupdatetime != $invTimesDB[$invoice->saleid]) {
				$getAllLines = true;
				$updateAtDeliveryFailXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
			}
			foreach ($invoice->invoice_item_list as $line) {
				if ($line->edited == "true" || $getAllLines == true) {
					$editReason = (isset($line->editreason)) ? $line->editreason : '';
					$saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . $editReason . '"/>' . "\n";
				}
			}
			// Green Discount Updates
			if ($invoice->greendiscountchanged == "true" || $getAllLines) {
				$saleXML .= '<Rec rID="' . $invoice->saleid . '" mUnitPrice="' . $invoice->greendiscountfinal . '"/>' . "\n";
				$gdAmt = preg_replace('/(-)?[^0-9.]/', '', $invoice->greendiscountfinal);
				$gdAmt = abs($gdAmt) * -1;
				$saleJSON[] = (object) [
					'iSaleID' => $invoice->saleid,
					'mUnitPrice' => $gdAmt
				];
			}
		}

		// Now call the stored procedures, as needed, if my XML strings are not empty
		$resultUpdateChanges = true;
		if ($saleDetailXML != '') {
			$saleDetailXML = "<ROOT>\n" . $saleDetailXML . "</ROOT>";
			dartLogging($currentScript, "    saleDetailXML=" . $saleDetailXML, $codeStr);
			$sql = "uspDARTDeliveryCompleteUpdates '" . $saleDetailXML . "'";
			$resultUpdateChanges = $dbh->exec($sql);
		}
		$resultDeliveryFail = true;
		if ($updateAtDeliveryFailXML != '') {
			$updateAtDeliveryFailXML = "<ROOT>\n" . $updateAtDeliveryFailXML . "</ROOT>";
			dartLogging($currentScript, "    updateAtDeliveryFailXML=" . $updateAtDeliveryFailXML, $codeStr);
			$sql = "uspDARTUpdateAtDeliveryFail '" . $updateAtDeliveryFailXML . "'";
			$resultDeliveryFail = $dbh->exec($sql);
		}
		$resultUpdateGreenDiscount = true;

		// if ($saleXML != '') {
		// 	$saleXML = "<ROOT>\n" . $saleXML . "</ROOT>";
		// 	dartLogging($currentScript, "    $saleXML=" . $saleXML, $codeStr);
		// 	$sql = "uspDARTDeliveryCompleteUpdatesGreenDiscount '" . $saleXML . "'";
		// 	$resultUpdateGreenDiscount = $dbh->exec($sql);
		// }
		if (count($saleJSON) > 0) {
			dartLogging($currentScript, "    saleJSON=" . json_encode($saleJSON), $codeStr);
			$sql = "uspDARTDeliveryCompleteUpdatesGreenDiscount '" . json_encode($saleJSON) . "'";
			$resultUpdateGreenDiscount = $dbh->exec($sql);
		}

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		$errMsg .= "invXML =  $invXML\n";
		$errMsg .= "saleDetailXML =  $saleDetailXML\n";
		$errMsg .= "saleJSON =  " . json_encode($saleJSON) . "\n";
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
			$sqlParts = explode(' ', $sql);
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete Retry : ' . $sqlParts[0]);
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount++;
				$sqlFailed = true;
				sleep(DART_SQL_TIMEOUT_SLEEP);
			} else {
				$badXML = preg_replace('/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML);
				$badXML = preg_replace('/code="0"/', 'code="1"', $badXML);
				echo $badXML;
				exit();
			}
		} else {
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete Serious');
			dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
			$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
			echo $badXML;
			exit();
		}
	}
}

if ($resultDelivered === false) {
	$errMsg = "$currentScript : uspDARTDelivered $updateCode, $signerID, $invXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

if ($resultUpdateChanges === false) {
	$errMsg = "uspDARTDeliveryCompleteUpdates $saleDetailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

if ($resultDeliveryFail === false) {
	$errMsg = "uspDARTUpdateAtDeliveryFail $updateAtDeliveryFailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

if ($resultUpdateGreenDiscount === false) {
	$errMsg = "uspDARTDeliveryCompleteUpdatesGreenDiscount $saleXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

// Submit any new invoices
if (count($jd->new_invoice_ship_today_list) > 0) {
	$sqlFailed = true;
	$sqlAttemptCount = 1;
	$sql = '';
	while ($sqlFailed) {
		$sqlFailed = false;
		try {
			$dbh = new PDO('spdb', '', '');
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// Get an invoice number
			$saleID = 0;
			$sql = "uspWebOOGetInvoiceNumber " . $jd->deliveryjson->delivery->locationid . ", '" . date('n/j/Y') . "'";
			$stmt = $dbh->query($sql);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$saleID = $row['iSaleID'];
			}
			$stmt->closeCursor();

			// Add the item to tblSaleDetail
			$prodID = 0;
			$unitID = 0;
			$quantity = 0;
			$price = 0.0;
			$parentSDID = 0;
			$poParentSDID = 0;
			$stmt = $dbh->prepare("INSERT INTO tblSaleDetail
							(iSaleID, iProductID, iUnitID, fOrderQuantity, fShipQuantity, mUnitPrice, iParentSDID)
							VALUES
							(:invoiceNum, :prodID, :unitID, :quantityOrd, :quantityShip, :price, :parentSDID)");
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->bindParam(':prodID', $prodID);
			$stmt->bindParam(':unitID', $unitID);
			$stmt->bindParam(':quantityOrd', $quantity);
			$stmt->bindParam(':quantityShip', $quantity);
			$stmt->bindParam(':price', $price);
			$stmt->bindParam(':parentSDID', $parentSDID);
			foreach ($jd->new_invoice_ship_today_list as $item) {
				$prodID = $item->itemid;
				$unitID = $item->unitid;
				$quantity = round($item->shipquantity, 2);
				$price = sprintf('%0.2f', $item->price);
				if (isset($item->parentsdid)) {
					$parentSDID = $item->parentsdid;
					$poParentSDID = $item->parentsdid;
				} else
					$parentSDID = 0;
				$stmt->execute();
			}
			unset($stmt);

			// Make it live
			$stmt = $dbh->prepare("UPDATE tblSale SET iLocationSourceID=1, sNotes='DART generated by the driver (" . $jd->userid . ")', iUserID=:userID WHERE iSaleID=:invoiceNum");
			$stmt->bindParam(':userID', $jd->userid);
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->execute();
			unset($stmt);

			// Get any PO values copied
			if ($poParentSDID > 0) {
				$stmt = $dbh->prepare("uspDARTGenerateInvoicePO :sdID, :invoiceNum");
				$stmt->bindParam(':sdID', $poParentSDID);
				$stmt->bindParam(':invoiceNum', $saleID);
				$stmt->execute();
				unset($stmt);
			}

			// Alert the salesperson
			try {
				$sql = "uspEmailDARTGeneratedInvoice $saleID, 1, " . $jd->userid;
				$alertResult = $dbh->exec($sql);
			} catch (PDOException $e) {
				// Do nothing
			}

			$dbh = null;
		} catch (PDOException $e) {
			$errMsg = "SQL = $sql\n";
			$eMessage = $e->getMessage();
			$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
			$errMsg .= "\n\ncodeStr = $codeStr\n";
			$errMsg .= "\ninvXML = " . $invXML;
			if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
				$sqlParts = explode(' ', $sql);
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete : New Invoice Retry : ' . $sqlParts[0]);
				if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
					$sqlAttemptCount++;
					$sqlFailed = true;
					sleep(DART_SQL_TIMEOUT_SLEEP);
				} else {
					$badXML = preg_replace('/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML);
					$badXML = preg_replace('/code="0"/', 'code="1"', $badXML);
					echo $badXML;
					exit();
				}
			} else {
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete : New Invoice Serious');
				dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
				$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
				echo $badXML;
				exit();
			}
		}
	}
}

if (count($jd->new_invoice_ship_tomorrow_list) > 0) {
	$sqlFailed = true;
	$sqlAttemptCount = 1;
	$sql = '';
	while ($sqlFailed) {
		$sqlFailed = false;
		try {
			$dbh = new PDO('spdb', '', '');
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// Get an invoice number
			$saleID = 0;
			$sql = "uspWebOOGetInvoiceNumber " . $jd->deliveryjson->delivery->locationid . ", '" . date('n/j/Y', strtotime("tomorrow")) . "'";
			$stmt = $dbh->query($sql);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$saleID = $row['iSaleID'];
			}
			$stmt->closeCursor();

			// Add the item to tblSaleDetail
			$prodID = 0;
			$unitID = 0;
			$quantity = 0;
			$price = 0.0;
			$parentSDID = 0;
			$poParentSDID = 0;
			$stmt = $dbh->prepare("INSERT INTO tblSaleDetail
							(iSaleID, iProductID, iUnitID, fOrderQuantity, fShipQuantity, mUnitPrice, iParentSDID)
							VALUES
							(:invoiceNum, :prodID, :unitID, :quantityOrd, :quantityShip, :price, :parentSDID)");
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->bindParam(':prodID', $prodID);
			$stmt->bindParam(':unitID', $unitID);
			$stmt->bindParam(':quantityOrd', $quantity);
			$stmt->bindParam(':quantityShip', $quantity);
			$stmt->bindParam(':price', $price);
			$stmt->bindParam(':parentSDID', $parentSDID);
			foreach ($jd->new_invoice_ship_tomorrow_list as $item) {
				$prodID = $item->itemid;
				$unitID = $item->unitid;
				$quantity = round($item->shipquantity, 2);
				$price = sprintf('%0.2f', $item->price);
				if (isset($item->parentsdid)) {
					$parentSDID = $item->parentsdid;
					$poParentSDID = $item->parentsdid;
				} else
					$parentSDID = 0;
				$stmt->execute();
			}
			unset($stmt);

			// Make it live
			$stmt = $dbh->prepare("UPDATE tblSale SET iLocationSourceID=1, sNotes='DART generated by the driver (" . $jd->userid . ")', iUserID=:userID WHERE iSaleID=:invoiceNum");
			$stmt->bindParam(':userID', $jd->userid);
			$stmt->bindParam(':invoiceNum', $saleID);
			$stmt->execute();
			unset($stmt);

			// Get any PO values copied
			if ($poParentSDID > 0) {
				$stmt = $dbh->prepare("uspDARTGenerateInvoicePO :sdID, :invoiceNum");
				$stmt->bindParam(':sdID', $poParentSDID);
				$stmt->bindParam(':invoiceNum', $saleID);
				$stmt->execute();
				unset($stmt);
			}

			// Alert the salesperson
			try {
				$sql = "uspEmailDARTGeneratedInvoice $saleID, 2, " . $jd->userid;
				$alertResult = $dbh->exec($sql);
			} catch (PDOException $e) {
				// Do nothing
			}

			$dbh = null;
		} catch (PDOException $e) {
			$errMsg = "SQL = $sql\n";
			$eMessage = $e->getMessage();
			$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
			$errMsg .= "\n\ncodeStr = $codeStr\n";
			$errMsg .= "\ninvXML = " . $invXML;
			if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
				$sqlParts = explode(' ', $sql);
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete : New Invoice Retry : ' . $sqlParts[0]);
				if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
					$sqlAttemptCount++;
					$sqlFailed = true;
					sleep(DART_SQL_TIMEOUT_SLEEP);
				} else {
					$badXML = preg_replace('/XXX/', $currentScript . ' : Database timeout, see ' . DART_ERROR_LOG . ' log', $badXML);
					$badXML = preg_replace('/code="0"/', 'code="1"', $badXML);
					echo $badXML;
					exit();
				}
			} else {
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : DeliveryComplete : New Invoice Serious');
				dartLogging($currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr);
				$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
				echo $badXML;
				exit();
			}
		}
	}
}

// Call sendinvoices.php with the invoice list to generate the PDFs and send them out for non-PRINTED INVOICE
if ($signerID != PRINTED_INVOICE_ID) {
	$invoiceStr = '';
	foreach ($jd->deliveryjson->invoice_list as $invoice)
		$invoiceStr .= " " . $invoice->saleid;
	shell_exec('php sendinvoice.php' . $invoiceStr . ' > /dev/null 2>&1 &');
}

echo $successXML;
dartLogging($currentScript, "  Success : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
exit();
