<?php
// exit ();
ini_set('max_execution_time', 3600);
ini_set('memory_limit', '512M');
require_once 'global_CDC.php';
require_once '../dart_init.php';
require_once 'classes_SP/class_LocationSP.php';
require_once 'EDI_SP.php';
require_once 'classes_SP/class_SP_FTP.php';
require_once 'classes_SP/class_PHPMailerSP.php';
require_once 'classes_SP/class_AzureBlobSP.php';
include_once 'classes_SP/class_AzureFileSP.php';

$currentScript = basename($_SERVER["SCRIPT_NAME"]);

echo "Start : " . date('H:i:s') . "<br/>";

$numProcessAtATime = 10;
// Pacific Coast Spirits dtShip >= 10/1/20
// @formatter:off
$invoiceNumbers = array(
	5214694, 5214771, 5214882, 5214884, 5215866, 5216028, 5217487, 5217491, 5218562, 5218750, 5220007, 5220792, 5222740, 5223524, 5224684, 5224758, 5226110, 5226291, 5226504, 5227439, 5228044, 5228725, 5229539, 5230747, 5230866, 5231966, 5233332, 5233351, 5235213, 5235896, 5236959, 5237362, 5239285, 5239315, 5240528, 5240606, 5241782, 5241824, 5242377, 5243201, 5244222, 5244518, 5244650, 5244651, 5244822, 5245818, 5247494, 5247893, 5249062, 5249105, 5250176, 5250400, 5250506, 5250511, 5253051, 5253247, 5254139, 5255749, 5255785, 5256852, 5257099, 5257979, 5258261, 5258532, 5259262, 5259601, 5260636, 5260759, 5261345, 5264084
);
// @formatter:on

echo "<br/><br/>Working up " . count($invoiceNumbers) . " invoices.<br/>";

$offLinePOs = array();
$offLinePOcount = 1;

// Instantiate the mail stuff
$mail = new PHPMailerSP();
$mail->setApiKey('acct');
$emailLogFile = SPConsts::ErrorLogRoot . "dart_emails.txt";

// Get the information on the location associated with these invoices
while (count($invoiceNumbers) > 0) {
	$sqlFailed = true;
	$sqlAttemptCount = 1;
	$invXML = '';
	$sql = '';
	while ($sqlFailed) {
		$sqlFailed = false;
		$locInfo = array();
		try {
			$dbh = new PDO('spdb', '', '');
			// set the error reporting attribute.
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// The XML to get the invoice info
			$invXML = "<ROOT>\n";
			for ($i = 0; $i < $numProcessAtATime; $i++) {
				if (count($invoiceNumbers) == 0)
					break;
				$sID = array_shift($invoiceNumbers);
				error_log(count($invoiceNumbers) . " : sID = $sID");
				$invXML .= '<Rec rID="' . $sID . '"/>' . "\n";
			}
			$invXML .= "</ROOT>\n";
			// Get the info
			$stmt = $dbh->query("uspDARTSendInvoiceInfo '" . $invXML . "'");
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$shipDate = date('n/j/Y', strtotime($row['dtShip']));
				$deliveryDate = date('n/j/Y g:i:s A', strtotime($row['dtDartDelivered']));
				$isDarkStop = ($row['iSigner'] == DARK_STOP_ID) ? true : false;
				// Determine if this is an offline PO for an EDI
				$ediID = trim($row['sInterchangeID']);
				$parentFTP = ($row['sParentFTPFolder'] == null) ? '' : trim($row['sParentFTPFolder']);
				$POnumber = trim($row['sPo']);
				if (strlen($ediID) > 0 && strlen($POnumber) == 0) {
					$requireEDIPO = constant('EDISPConsts::' . $ediID . "_REQUIREPO");
					if ($requireEDIPO != null) {
						echo "Requires PONumbers and one is missing : {$row['iSaleID']}  - exiting...";
						exit();
					}
				}
				$showProdID = ($row['iShowProductID'] == -1) ? true : false;
				$add2 = ($row['sAddress2'] == null) ? '' : "\n" . trim($row['sAddress2']);
				$locInfo[$row['iSaleID']] = array(
					'id' => $row['iLocationDestinationID'],
					'saleID' => $row['iSaleID'],
					'name' => $row['sDescription'],
					'address' => $row['sAddress1'] . $add2,
					'city' => $row['sCity'],
					'state' => $row['sState'],
					'zip' => $row['sPostalCode'],
					'phone' => $row['sPhone'],
					'salesperson' => $row['txtSalesPerson'],
					'salesphone' => $row['txtCellPhone'],
					'salesemail' => $row['txtSalesEmail'],
					'terms' => $row['sTerms'],
					'po' => $POnumber,
					'darkstop' => $isDarkStop,
					'signer' => $row['txtSigner'],
					'shipdate' => $shipDate,
					'deldate' => $deliveryDate,
					'greenYTD' => $row['mYTD'],
					'ediID' => $ediID,
					'parentFTP' => $parentFTP,
					'showProdID' => $showProdID
				);
			}
			$stmt->closeCursor();

			$dbh = null;
		} catch (PDOException $e) {
			$eMessage = $e->getMessage();
			$errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
			$errMsg .= "\n\nSQL = $sql";
			$errMsg .= "\nargv = " . print_r($argv, true);
			$errMsg .= "\ninvXML = " . htmlentities($invXML);
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'sendinvoice.php DB');
			if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage) || preg_match('/Schema changed/', $eMessage)) {
				if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
					$sqlAttemptCount++;
					$sqlFailed = true;
					sleep(DART_SQL_TIMEOUT_SLEEP);
				} else {
					exit();
				}
			} else {
				exit();
			}
		}
	}
	error_log("locInfo count = " . count($locInfo));

	// Process the EDI invoices
	$ftpConnector = new SP_FTP();
	try {
		$azb = new AzureBlobSP('specprodstorage', false);
		foreach ($locInfo as $loc) {
			if (strlen($loc['ediID']) > 0) {
				$saleID = $loc['saleID'];
				if (array_key_exists($saleID, $offLinePOs)) {
					// Newly generated Offline PO
					try {
						$dbh = new PDO('spdb', '', '');
						$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						$stmt = $dbh->query("uspEDIOfflinePORegister " . $saleID . ", '" . $offLinePOs[$saleID] . "'");
						$opoEmails = $stmt->fetchAll(PDO::FETCH_BOTH);
						$dbh = null;
					} catch (PDOException $e) {
						$errorTxt = $e->getFile() . " (" . $e->getLine() . ") : " . $e->getMessage();
						SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG);
						exit();
					}
					if (count($opoEmails) > 0) {
						$fp = fopen($emailLogFile, "a");
						$subjectStr = "SP Offline PO : " . $offLinePOs[$saleID];
						fwrite($fp, date('[d-M-Y H:i:s]') . " : " . $subjectStr . " -");

						$mail->FromName = "Specialty Produce Accounting";
						$mail->From = "ar@specialtyproduce.com";
						$mail->Subject = $subjectStr;
						$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
						// Add the body
						$mail->Body = <<< EOT
Dear Customer,

These invoices do no thave POs.
Since you use a buying group that requires us to transfer POs and invoices with them electronically, we have generated an offline PO number for this invoice.

Please make sure you take the appropriate steps in your buying group's online system to accept this PO:

EOT;
						$mail->Body .= "\tInvoice # " . $saleID . ", PO # " . $offLinePOs[$saleID] . "\n";
						$mail->Body .= <<< EOT

We appreciate your business.

Sincerely,
Specialty Produce

EOT;
						$sendCount = 0;
						foreach ($opoEmails as $entry) {
							fwrite($fp, " " . $entry['txtEmail']);
							$mail->AddAddress($entry['txtEmail'], $entry['txtName']);
							if ($mail->Send()) {
								$sendCount++;
							}
							$mail->ClearAddresses();
						}
						$mail->ClearAttachments();
						$mail->ClearBCCs();
						fwrite($fp, "(" . $sendCount . ")\n");
						fclose($fp);
					}
				}
				// Send the PO
				include_once 'EDI_SP\Receivers\\' . $loc['ediID'] . '\ts810.php';
				$tsFunction = $loc['ediID'] . '_810';
				list($success, $msg) = $tsFunction($loc['saleID']);
				if (!$success) {
					$errMsg = "$tsFunction returned false : $msg";
					$errMsg .= "\nsaleID = " . $loc['saleID'] . ", ediID = " . $loc['ediID'];
					SP_errorLogging($errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error");
					continue;
				}
				if (strlen($msg) == 0) {
					$errMsg = "$tsFunction returned empty EDI string";
					$errMsg .= "\nsaleID = " . $loc['saleID'] . ", ediID = " . $loc['ediID'];
					SP_errorLogging($errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error");
					continue;
				}
				$outFileName = 'O_SP_' . date('ymd_His') . '_' . $loc['saleID'] . '.810';
				$outPath = EDISPConsts::FTP_ROOT;
				$outPath .= (strlen($loc['parentFTP']) > 0) ? $loc['parentFTP'] . '\\' : '';
				$outPath .= $loc['ediID'] . '\\outgoing\\';
				$outFile = $outPath . $outFileName;
				if (!file_put_contents($outFile, $msg)) {
					$errMsg = "Error writing outgoing 810 : $outFile" . "\nfor invoice # " . $loc['saleID'];
					SP_errorLogging($errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error");
					continue;
				}
				// Write to Azure Blob
				$azFTPDir = (strlen($loc['parentFTP']) > 0) ? $loc['parentFTP'] . '/' : '';
				$azFTPDir .= $loc['ediID'] . '/outgoing/';
				$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_FTP_DIR, $azFTPDir, $outFileName, $outFile);
				// Set up the ftp connection, if needed
				$sentSuccessfully = true;
				if (constant('EDISPConsts::' . $loc['ediID'] . "_SENDFTP")) {
					$ftpConnector->server = constant('EDISPConsts::' . $loc['ediID'] . "_FTP");
					$ftpConnector->username = constant('EDISPConsts::' . $loc['ediID'] . "_USERNAME");
					$ftpConnector->password = constant('EDISPConsts::' . $loc['ediID'] . "_PASSWORD");
					try {
						$ftpConnector->sendFile($outPath, $outFileName);
					} catch (SP_Exception $spe) {
						SP_ErrorLogging($spe, true, DART_ERROR_LOG, "DART Error : cURL send");
						$sentSuccessfully = false;
					}
					if ($sentSuccessfully) {
						dartLogging($currentScript, "    Successfully FTP'd " . $outFile);
					} else {
						$errMsg = "File not sent successfully, moved to flagged folder on vDart:\n$outFile\nfor invoice # " . $loc['saleID'];
						SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, $currentScript . " - EDI error");
						flagFTPFile($loc['ediID'], $outFile);
					}
				}
				// Save the outgoing file to the EDI dir
				if ($sentSuccessfully) {
					// $savePath = EDISPConsts::EDI_SAVE_DIR . $loc ['ediID'] . '\\outgoing\\' . $outFileName;
					// if (! copy ( $outFile, $savePath )) {
					// $errMsg = "Error saving $outFile to $savePath\nfor invoice # " . $loc ['saleID'];
					// SP_errorLogging ( $errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error" );
					// continue;
					// }
					// Unlink the file if we sent it, otherwise it will sit waiting to be picked up and subsequently deleted.
					if (constant('EDISPConsts::' . $loc['ediID'] . "_SENDFTP")) {
						if (!unlink($outFile)) {
							$errMsg = "Error unlinking $outFile\nfor invoice # " . $loc['saleID'];
							SP_errorLogging($errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error");
							continue;
						}
					}
				}
			}
		}
	} catch (SP_Exception $e) {
		$errorTxt = $e->getFile() . " (" . $e->getLine() . ") : " . $e->getMessage();
		SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG);
	}
	$mail->ClearAllRecipients();
}
error_log("Done");
echo "End : " . date('H:i:s') . "<br/>";
exit(0);
