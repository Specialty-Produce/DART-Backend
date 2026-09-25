<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_AzureFileSP.php';

$currentScript = basename($_SERVER["SCRIPT_NAME"]);
if (preg_match('/adhoc/', $currentScript)) {
	$adhoc = true;
	include '../dart_init.php';
	echo "Adhoc Mode : $codeStr<br>";
} else {
	$adhoc = false;
	include 'dart_init.php';
}

if ($adhoc) {
	/* Add/Remove as needed */
	echo "Ad Hoc Exiting...";
	exit();
}
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Get the POST data
if (isset($_POST['jsondata'])) {
	$appJSON = $_POST['jsondata'];
	dartLoggingHour($currentScript, "jsondata=" . $appJSON, $codeStr);
} else {
	if ($adhoc) {
		$appJSON = file_get_contents('adhoc_dc_json.txt');
		if ($appJSON === false) {
			echo "file_get_contents failed on adhoc_dc_json.txt<br>";
			exit();
		} else {
			echo "file_get_contents succeeded on adhoc_dc_json.txt<br>";
		}
	} else {
		$appJSON = false;
	}
}

if (isset($_POST['debuginfo'])) {
	dartLoggingHour($currentScript, "debuginfo=" . $_POST['debuginfo'], $codeStr);
}

if (MAINTENANCE_MODE) {
	sendError(503, ERROR_CODES::ERROR_MAINTENANCE_MODE, 'Site undergoing maintenance. Please try again.', 'Maintenance Mode is ON', true);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No jsondata supplied', 'No jsondata supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

// Truncated appJSON - implies the connection was lost in midtransmission
// 240411 : CDC : Removed this since the JSON data string apparently is not always organized the same
/*
if (!preg_match('/,"userid":"\d+"}$/', $appJSON)) {
	if (!(preg_match('/^{"userid":"\d+"/', $appJSON) && preg_match('/]}$/', $appJSON))) {
		dartLoggingHour($currentScript, "    jsondata is TRUNCATED", $codeStr);
		exit();
	}
}
*/
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
		dartLoggingHour($currentScript, "    FIXED decoded jsondata is FALSE or NULL : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
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

if ($adhoc) {
	echo "JSON Data : <br/><pre>" . print_r($jd, true) . "</pre><br/><hr/><br/>";
}

// Done if the DEBUG user
if ($jd->userid == DEBUG_USERID) {
	sendResult();
	exit();
}

$userID = $jd->userid;

// Pull these out for easier reference
$locationID = $jd->deliveryjson->delivery->locationid;
$signerID = $jd->deliveryjson->delivery->signerid;
if ($signerID < 0) {
	$isDarkDrop = true;
	$signerID = DARK_STOP_ID;
} else {
	$isDarkDrop = false;
}

// Ignore printed invoice signers and save the signature images
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
			sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', "Could not create folder for locationID = " . $jd->deliveryjson->delivery->locationid, true);
			dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, "DART : Could not create folder : $currentScript");
			exit();
		}
	}

	// Convert the image to 24-bit and save
	foreach ($jd->deliveryjson->invoice_list as $invoice) {
		$file = $filedir . '/' . $invoice->saleid . ".png";
		// Create from the encoded string
		if (!$imgSrc = imagecreatefromstring(base64_decode($invoice->signatureimage))) {
			// Capture and clear repeating call **********
			if ($invoice->saleid == 3415845) {
				$errMsg = "Captured bad image saleID and sent success, saleID = " . $invoice->saleid . ", code = " . $codeStr;
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
				dartLoggingHour($currentScript, "    Captured bad image saleID and sent success", $codeStr);
				sendResult();
				exit();
			} else {
				$errMsg = "Could not create image from signatureimage data, saleID = " . $invoice->saleid . ", code = " . $codeStr . " : file = $file";
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
				sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', $errMsg, true);
				dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
				exit();
			}
		} else {
			// Make the new image
			$width = imagesx($imgSrc);
			$height = imagesy($imgSrc);
			if (!$imgDest = imagecreatetruecolor($width, $height)) {
				sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', "Could not create new true color image.", true);
				dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
				exit();
			} else {
				// Copy sent into new
				if (!imagecopy($imgDest, $imgSrc, 0, 0, 0, 0, $width, $height)) {
					sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', "Could not copy source image to new image.", true);
					dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
					exit();
				} else {
					// Write it out
					if (!imagepng($imgDest, $file)) {
						sendError(500, ERROR_CODES::ERROR_INVALID_DATA, 'Error saving signature image.', "Could not save png image.", true);
						dartLogging($sendObj->webservice, $json_encode($sendObj), $codeStr);
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

		$invXML = "<ROOT>\n";
		$dsbData = array();
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			if ($adhoc) {
				echo "Invoice : {$invoice->saleid} : {$invoice->dsbid}";
			}
			// DSBID
			if (intval($invoice->dsbid) > 0) {
				if (isset($invoice->invoice_item_list[0]->dsbReplaceQtyToday)) {
					// "NEW" DSBID
					$dsbEntry = new stdClass();
					$dsbEntry->iSaleID = intval($invoice->saleid);
					$dsbEntry->dtDate = date('Y-m-d');
					$dsbEntry->details = array();
					$dsbDetail = new stdClass();
					$dsbDetail->iDSBID = intval($invoice->dsbid);
					$dsbDetail->iSaleDetailID = 0;
					$dsbDetail->sNote = $invoice->dsbNote;
					$dsbDetail->fQty = 0;
					$dsbDetail->iUnitID = 0;
					$dsbDetail->mUnitPrice = 0.00;
					$dsbDetail->fQtyN = 0;
					$dsbDetail->iUnitN = 0;
					$dsbDetail->mPriceN = 0.00;
					$dsbDetail->dtDateDetail = date('Y-m-d');
					$dsbEntry->details[] = $dsbDetail;
					error_log("$currentScript : $codeStr : NEW INVOICE DSBID > 0 : dsbEntry JSON String =" . json_encode($dsbEntry));
					$sql = "uspDARTMenuProcessJSON ?";
					$stmt = $dbh->prepare($sql);
					$stmt->execute(array(json_encode($dsbEntry)));
				} else {
					// "OLD" DSBID
					$dsbData =  array(
						'iDSBID' => intval($invoice->dsbid),
						'iSaleID' => intval($invoice->saleid),
						'iSaleDetailID' => 0,
						'dtDate' => date('Y-m-d'),
						'sNote' => $invoice->dsbNote,
						'fQty' => 0.0
					);
					error_log("$currentScript : $codeStr : OLD INVOICE DSBID > 0 : dsbData JSON String =" . json_encode($dsbData));
					$sql = "uspDartMenuProcess ?";
					$stmt = $dbh->prepare($sql);
					$stmt->execute(array(json_encode($dsbData)));
				}
			}
			$invXML .= '<Rec rID="' . $invoice->saleid . '" dtDelTime="' . $invoice->signtimestamp . '"/>' . "\n";
		}
		$invXML .= "</ROOT>";

		if ($adhoc) {
			echo "invXML : " . htmlentities($invXML) . "<br>";
		}

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
			dartLoggingHour($currentScript, "  Repeat Call : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
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
			dartLoggingHour($currentScript, "    uspDARTAddSigner iUserID=" . $deletedID . " DELETED", $codeStr);
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
			dartLoggingHour($currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr);
			$stmt = $dbh->query($sql);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$signerID = $result['iUserID'];
			dartLoggingHour($currentScript, "    uspDARTAddSigner iUserID=" . $signerID, $codeStr);
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
			dartLoggingHour($currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr);
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

		// We need to build the list of invoices that have lastupdatetime values different between database and ipad
		$updateAtDeliveryFailXML = '';
		$saleDetailXML = '';
		$saleGD_JSON = array();
		$saleST_JSON = array();
		$userID = $jd->userid;
		$dsbData = array();
		foreach ($jd->deliveryjson->invoice_list as $invoice) {
			// DBSID for invoice > 0 means line items already dealt with as part of invoice-wide DSBID...
			if (intval($invoice->dsbid) > 0) {
				continue;
			}
			$getAllLines = false;
			if ($invoice->lastupdatetime != $invTimesDB[$invoice->saleid]) {
				$getAllLines = true;
				$updateAtDeliveryFailXML .= '<Rec rID="' . $invoice->saleid . '"/>' . "\n";
			}
			/* NEW DART MENU PROCESS */
			$dsbEntry = new stdClass();
			$dsbEntry->iSaleID = intval($invoice->saleid);
			$dsbEntry->dtDate = date('Y-m-d');
			$dsbEntry->details = array();
			foreach ($invoice->invoice_item_list as $line) {
				// DSBID
				if (intval($line->dsbid) > 0) {
					if (isset($line->dsbReplaceQtyToday)) {
						/* NEW DART MENU PROCESS */
						$qtyToday = $line->dsbReplaceQtyToday;
						$qtyTomorrow = $line->dsbReplaceQtyTomorrow;
						if ($qtyToday == 0 && $qtyTomorrow == 0) {
							// Delivered quantity changed, but no replacement requested
							$dsbDetail = new stdClass();
							$dsbDetail->iDSBID = intval($line->dsbid);
							$dsbDetail->iSaleDetailID = intval($line->lineid);
							$dsbDetail->sNote = $line->dsbNote;
							$dsbDetail->fQty = $line->finalqship;
							$dsbDetail->iUnitID = $line->finalunitid;
							$dsbDetail->mUnitPrice = $line->finalunitprice;
							$dsbDetail->fQtyN = 0;
							$dsbDetail->iUnitN = 0;
							$dsbDetail->mPriceN = 0.00;
							$dsbDetail->dtDateDetail = date('Y-m-d');
							$dsbEntry->details[] = $dsbDetail;
						} else {
							// We'll have either a today, tomorrow or both replacements
							if ($line->dsbReplaceQtyToday > 0) {
								$dsbDetail = new stdClass();
								$dsbDetail->iDSBID = intval($line->dsbid);
								$dsbDetail->iSaleDetailID = intval($line->lineid);
								$dsbDetail->sNote = $line->dsbNote;
								$dsbDetail->fQty = $line->finalqship;
								$dsbDetail->iUnitID = $line->finalunitid;
								$dsbDetail->mUnitPrice = $line->finalunitprice;
								$dsbDetail->fQtyN = $line->dsbReplaceQtyToday;
								$dsbDetail->iUnitN = intval($line->dsbReplaceUnitToday);
								$dsbDetail->mPriceN = $line->dsbReplacePriceToday;
								$dsbDetail->dtDateDetail = $line->dsbReplaceShipDateToday;
								$dsbEntry->details[] = $dsbDetail;
							}
							if ($line->dsbReplaceQtyTomorrow > 0) {
								$dsbDetail = new stdClass();
								$dsbDetail->iDSBID = intval($line->dsbid);
								$dsbDetail->iSaleDetailID = intval($line->lineid);
								$dsbDetail->sNote = $line->dsbNote;
								$dsbDetail->fQty = $line->finalqship;
								$dsbDetail->iUnitID = $line->finalunitid;
								$dsbDetail->mUnitPrice = $line->finalunitprice;
								$dsbDetail->fQtyN = $line->dsbReplaceQtyTomorrow;
								$dsbDetail->iUnitN = intval($line->dsbReplaceUnitTomorrow);
								$dsbDetail->mPriceN = $line->dsbReplacePriceTomorrow;
								$dsbDetail->dtDateDetail = $line->dsbReplaceShipDateTomorrow;
								$dsbEntry->details[] = $dsbDetail;
							}
						}
					} else {
						$dsbData =  array(
							'iDSBID' => intval($line->dsbid),
							'iSaleID' => intval($invoice->saleid),
							'iSaleDetailID' => intval($line->lineid),
							'dtDate' => date('Y-m-d'),
							'sNote' => $line->dsbNote,
							'fQty' => floatval($line->finalqship)
						);
						error_log("$currentScript : $codeStr : OLD LINE ITEM DSBID > 0 : dsbData JSON String =" . json_encode($dsbData));
						dartLoggingHour($currentScript, "    OLD LINE ITEM DSBID > 0 : dsbData JSON String =" . json_encode($dsbData), $codeStr);
						error_log("$currentScript : $codeStr : OLD LINE ITEM JSON = " . json_encode($line));
						$sql = "uspDartMenuProcess ?";
						$stmt = $dbh->prepare($sql);
						$stmt->execute(array(json_encode($dsbData)));
					}
				} elseif ($line->edited == "true" || $getAllLines == true) {
					// Catch instances where just the UNIT was changed - this does not currently trigger a DSBID > 0
					$saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . $line->editreason . '"/>' . "\n";
				}
			}
			/* NEW DART MENU PROCESS */
			if (count($dsbEntry->details) > 0) {
				error_log("$currentScript : $codeStr : NEW INVOICE LINE ITEM(s) DSBID > 0 : dsbEntry JSON String =" . json_encode($dsbEntry));
				dartLoggingHour($currentScript, "    NEW INVOICE LINE ITEM(s) DSBID > 0 : dsbEntry JSON String =" . json_encode($dsbEntry), $codeStr);
				$sql = "uspDartMenuProcessJSON ?";
				$stmt = $dbh->prepare($sql);
				$stmt->execute(array(json_encode($dsbEntry)));
			}
			// Green Discount Updates
			if ($invoice->greendiscountchanged == "true" || $getAllLines) {
				$gdAmt = preg_replace('/(-)?[^0-9.]/', '', $invoice->greendiscountfinal);
				$gdAmt = abs($gdAmt) * -1;
				$saleGD_JSON[] = (object) [
					'iSaleID' => $invoice->saleid,
					'mUnitPrice' => $gdAmt
				];
			}
			// Sales Tax Updates
			if (isset($invoice->salestaxchanged) && ($invoice->salestaxchanged == "true" || $getAllLines)) {
				$stAmt = preg_replace('/(-)?[^0-9.]/', '', $invoice->salestaxfinal);
				$saleST_JSON[] = (object) [
					'iSaleID' => $invoice->saleid,
					'mUnitPrice' => $stAmt
				];
			}
		}

		// Now call the stored procedures, as needed, if my XML strings are not empty
		$resultUpdateChanges = true;
		if ($saleDetailXML != '') {
			$saleDetailXML = "<ROOT>\n" . $saleDetailXML . "</ROOT>";
			dartLoggingHour($currentScript, "    saleDetailXML=" . $saleDetailXML, $codeStr);
			$sql = "uspDARTDeliveryCompleteUpdates '" . $saleDetailXML . "'";
			$resultUpdateChanges = $dbh->exec($sql);
		}
		$resultDeliveryFail = true;
		if ($updateAtDeliveryFailXML != '') {
			$updateAtDeliveryFailXML = "<ROOT>\n" . $updateAtDeliveryFailXML . "</ROOT>";
			dartLoggingHour($currentScript, "    updateAtDeliveryFailXML=" . $updateAtDeliveryFailXML, $codeStr);
			$sql = "uspDARTUpdateAtDeliveryFail '" . $updateAtDeliveryFailXML . "'";
			$resultDeliveryFail = $dbh->exec($sql);
		}
		$resultUpdateGreenDiscount = true;

		if (count($saleGD_JSON) > 0) {
			dartLoggingHour($currentScript, "    Green Discount saleJSON=" . json_encode($saleGD_JSON), $codeStr);
			$sql = "uspDARTDeliveryCompleteUpdatesGreenDiscount '" . json_encode($saleGD_JSON) . "'";
			$resultUpdateGreenDiscount = $dbh->exec($sql);
		}

		if (count($saleST_JSON) > 0) {
			dartLoggingHour($currentScript, "    Sales Tax saleJSON=" . json_encode($saleST_JSON), $codeStr);
			$sql = "uspDARTDeliveryCompleteUpdatesSalesTax '" . json_encode($saleST_JSON) . "'";
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
		$errMsg .= "saleJSON =  " . json_encode($saleGD_JSON) . "\n";
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
	$errMsg = "uspDARTDeliveryCompleteUpdates $saleDetailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

if ($resultDeliveryFail === false) {
	$errMsg = "uspDARTUpdateAtDeliveryFail $updateAtDeliveryFailXML returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
	exit();
}

if ($resultUpdateGreenDiscount === false) {
	$errMsg = "uspDARTDeliveryCompleteUpdatesGreenDiscount " . json_encode($saleGD_JSON) . " returned FALSE : $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	sendError(500, ERROR_CODES::ERROR_DATABASE, 'Database error.', $errMsg);
	dartLogging($sendObj->webservice, json_encode($sendObj), $codeStr);
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

			$dbsArray = array();

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

				// DBSID
				$dbsid = intval($item->dbsid ?? 0);
				if ($dbsid > 0)
					$dbsArray[] = array('dbsid' => $dbsid, 'lineid' => intval($item->lineid), 'userid' => intval($jd->userid));
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

			foreach ($dbsArray as $dbEntry) {
				$dbh->exec("uspDARTSentBackByDriver {$dbEntry['dbsid']}, 0, {$dbEntry['linid']}, {$dbEntry['userid']}");
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

			$dbsArray = array();

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

				// DBSID
				$dbsid = intval($item->dbsid);
				if ($dbsid > 0)
					$dbsArray[] = array('dbsid' => $dbsid, 'lineid' => intval($item->lineid), 'userid' => intval($jd->userid));
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

			foreach ($dbsArray as $dbEntry) {
				$dbh->exec("uspDARTSentBackByDriver {$dbEntry['dbsid']}, 0, {$dbEntry['linid']}, {$dbEntry['userid']}");
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

// Call sendinvoices.php with the invoice list to generate the PDFs and send them out for non-PRINTED INVOICE
if ($signerID != PRINTED_INVOICE_ID) {
	$invoiceStr = '';
	foreach ($jd->deliveryjson->invoice_list as $invoice)
		$invoiceStr .= " " . $invoice->saleid;
	if ($adhoc) {
		echo "Would call : php sendinvoice.php" . $invoiceStr;
	} else {
		shell_exec('php sendinvoice.php' . $invoiceStr . ' > /dev/null 2>&1 &');
	}
}

sendResult();
dartLoggingHour($currentScript, "  Success : " . $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'], $codeStr);
