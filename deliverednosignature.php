<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_LocationSP.php';
include_once 'classes_SP/class_SP_Exception.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);
} else {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

if (MAINTENANCE_MODE) {
	sendError(503, ERROR_CODES::ERROR_MAINTENANCE_MODE, 'Site undergoing maintenance. Please try again.', 'Maintenance Mode is ON', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Truncated appJSON - implies the connection was lost in midtransmission
if (! preg_match('/,"userid":"\d+"}$/', $appJSON)) {
	if (! (preg_match('/^{"userid":"\d+"/', $appJSON) && preg_match('/]}$/', $appJSON))) {
		dartLogging($currentScript, "    jsondata is TRUNCATED", $codeStr);
		exit();
	}
}

// Force success for a SaleID that had bad JSON that was fixed and adhoc completed
$invoiceBadJSON = false;
if ($invoiceBadJSON !== false && preg_match('/"saleid":"' . $invoiceBadJSON . '"/', $appJSON)) {
	sendResult();
	SP_ErrorLogging("Bad JSON invoice $invoiceBadJSON send SUCCESS JSON.", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
	exit();
}

// Good to go...
$jd = json_decode($appJSON);
if ($jd == FALSE || is_null($jd)) {
	// Capture bad JSON data that has been fixed already...
	if (strpos($appJSON, '"saleid":"4095326"') !== false) {
		dartLoggingHour($currentScript, "    FIXED decoded jsondata is FALSE or NULL", $codeStr);
		sendResult();
		SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. FIXED", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
		exit();
	} else {
		sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Bad JSON data', 'Decoded jsondata is FALSE or NULL');
		dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
		SP_ErrorLogging("Decoded JSON data is invalid for codeStr = $codeStr. Hand fix and adhoc enter data", true, DART_ERROR_LOG, "DART - $currentScript - Invalid JSON data");
		exit();
	}
}

// Done if the DEBUG user
if ($jd->userid == DEBUG_USERID) {
	sendResult();
	exit();
}

// Pull these out for easier reference
$locationID = $jd->deliveryjson->delivery->locationid;
$signerID = $jd->deliveryjson->delivery->signerid;
if ($signerID != DELIVERY_NO_SIGNATURE_ID) {
	SP_ErrorLogging("Invalid No Signature signer ID for codeStr = $codeStr.", true, DART_ERROR_LOG, "DART - $currentScript - Invalid No Signature ID");
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'Wrong signature ID', 'Invalid NO SIGNATURE signer ID supplied');
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Get the invoices marked as "delivered", which is code 2 for this stored procedure
$updateCode = 4;
$invXML = '';
$result = false;
$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
$notifyUserID = 0;
$notifyEmail = '';
$notifyLocation = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Prep for the XML version of invoice list for the stored procedure
		$invXML = "<ROOT>\n";
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			$invXML .= '<Rec rID="' . $invoice->saleid . '" dtDelTime="' . date('Y-m-d G:i') . '"/>' . "\n";
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

		// Get the notification information
		$notSaleID = $jd->deliveryjson->invoice_list[0]->saleid;
		$sql = "select dbo.fnLocationDefaultEmail($locationID) as sEmail, dbo.fnLocationDefaultUserID($locationID) as iUserID, dbo.fnLocationDescriptionBySaleID($notSaleID) as sLocDesc";
		$stmt = $dbh->query($sql);
		$notResult = $stmt->fetch(PDO::FETCH_ASSOC);
		$notifyUserID = $notResult['iUserID'];
		$notifyEmail = $notResult['sEmail'];
		$notifyLocation = $notResult['sLocDesc'];
		$stmt->closeCursor();

		// Add to Missing table
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			$addMissingResult = LocationSP::createWebSignInfo($invoice->saleid, 1, $notifyUserID);
		}

		// We need to build the list of invoices that have lastupdatetime values different between database and ipad
		$updateAtDeliveryFailXML = '';
		$saleDetailXML = '';
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			$getAllLines = false;
			if ($invoice->lastupdatetime != $invTimesDB[$invoice->saleid]) {
				$getAllLines = true;
				$updateAtDeliveryFailXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
			}
			foreach ($invoice->invoice_item_list as $line) {
				if ($line->edited == "true" || $getAllLines == true) {
					$saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . '' . '"/>' . "\n";
				}
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

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ncodeStr = $codeStr\n";
		$errMsg .= "\ninvXML = " . $invXML;
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
			$sqlParts = explode(' ', $sql);
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount++;
				$sqlFailed = true;
				sleep(DART_SQL_TIMEOUT_SLEEP);
			} else {
				sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		} else {
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
			sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			exit();
		}
	}
}

if ($resultDelivered === false) {
	$errMsg = "$currentScript : uspDARTDelivered $updateCode, $signerID, $invXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

if ($resultUpdateChanges === false) {
	$errMsg = "$currentScript : uspDARTDeliveryCompleteUpdates $saleDetailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

if ($resultDeliveryFail === false) {
	$errMsg = "$currentScript : uspDARTUpdateAtDeliveryFail $updateAtDeliveryFailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// Instantiate the mail stuff
$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$mail->isHTML(true);
$mail->FromName = "Specialty Produce Accounting";
$mail->From = "missingsig@specialtyproduce.com";
$mail->AddReplyTo("missingsig@specialtyproduce.com", "Specialty Produce Accounting");
$mail->Subject = "Specialty Produce missing signature - $notifyLocation";
// Send the email alerting to no signature
if (strlen($notifyEmail) == 0) {
	$notifyEmail = "missingsig@specialtyproduce.com";
	$mail->Subject = "Missing Signature - No Sales Email Set - $notifyLocation";
}
$mail->addAddress($notifyEmail);
$mail->addBCC("christopher@specialtyproduce.com");
$sigLinks = '';
foreach ($jd->deliveryjson->invoice_list as $invoice) {
	$link = 'https://azdart.specialtyproduce.com/websign/index.php?sid=' . urlencode(simple_openssl_encrypt($invoice->saleid));
	$sigLinks .= $invoice->saleid . ' : <a clicktracking=off href="' . $link . '" target="_blank">' . $link . "</a><br/>";
}
$mail->Body = "Dear Sir or Madam,
<p>The following invoice(s) for <b>$notifyLocation</b> have already been delivered but for whatever reason no signature was gathered at the time, or they need to be re-signed.</p>
Please click on the link(s) to load a web page wherein you can sign with mouse or finger.<br/><br/>";
$mail->Body .= $sigLinks;
$mail->Body .= '<p>Thank you for your prompt attention to this.</p>Best,<br/>Specialty Produce Accounting<br/>missingsig@specialtyproduce.com';
if (! $mail->Send()) {
	$errMsg = "Error sending email!!!\nTo : $notifyEmail" . "\nError : " . $mail->ErrorInfo . "\n\nBody:\n" . $mail->Body;
	SP_errorLogging($errMsg, true, DART_ERROR_LOG);
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
				if (isset($item->parentsdid))
					$parentSDID = $item->parentsdid;
				else
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
			if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
				$sqlParts = explode(' ', $sql);
				if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
					$sqlAttemptCount++;
					$sqlFailed = true;
					sleep(DART_SQL_TIMEOUT_SLEEP);
				} else {
					sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
					dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
					exit();
				}
			} else {
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
				sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
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
				if (isset($item->parentsdid))
					$parentSDID = $item->parentsdid;
				else
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
			if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage)) {
				$sqlParts = explode(' ', $sql);
				if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
					$sqlAttemptCount++;
					$sqlFailed = true;
					sleep(DART_SQL_TIMEOUT_SLEEP);
				} else {
					sendError(504, ERROR_CODES::ERROR_DATABASE_TIMEOUT, 'Database is running slow, try again', 'Database timed out : ' . $sqlParts[0], true);
					dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
					exit();
				}
			} else {
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : $currentScript Serious");
				sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database is down', 'Database error, see ' . DART_ERROR_LOG . ' log', false);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		}
	}
}

sendResult();
dartLogging($currentScript, "  Success : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
