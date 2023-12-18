<?php
// exit ();
ini_set('max_execution_time', 3600);
require_once 'global_CDC.php';
require_once '../dart_init.php';
require_once 'classes_SP/class_LocationSP.php';
require_once 'classes_SP/class_invoicePDF.php';
require_once 'classes_SP/class_InvoiceRSI.php';
// Hula Software became Restuarant Matrix - I changed the FTP folder but otherwise left the Hula naming scheme
require_once 'classes_SP/class_InvoiceHula.php';
require_once 'classes_SP/class_InvoiceProfitProPlus.php';
require_once 'classes_SP/class_InvoiceR365.php';
require_once 'classes_SP/class_InvoiceBevager.php';
require_once 'classes_SP/class_InvoiceCheftec.php';
require_once 'classes_SP/class_InvoicePlateIQ.php';
require_once 'classes_SP/class_InvoiceSP_Simple123.php';
require_once 'classes_SP/class_InvoiceSP_QSROnline.php';
require_once 'EDI_SP.php';
require_once 'classes_SP/class_SP_FTP.php';
require_once 'classes_SP/class_PHPMailerSP.php';
include_once 'classes_SP/class_SendGridSP.php';
require_once 'classes_SP/class_SRFaxSP.php';
require_once 'classes_SP/class_AzureBlobSP.php';
include_once 'classes_SP/class_AzureFileSP.php';
function sortLineItems($a, $b) {
	global $useCOG;
	if ($useCOG) {
		$cmpCOG = strnatcmp($a['cogAccount'], $b['cogAccount']);
		if ($cmpCOG == 0)
			return strnatcmp($a['description'], $b['description']);
		else
			return $cmpCOG;
	} else
		return strnatcmp($a['description'], $b['description']);
}

$currentScript = basename($_SERVER["SCRIPT_NAME"]);
if (preg_match('/adhoc/', $currentScript))
	$adhoc = true;
else
	$adhoc = false;

// Are we coming via a GET
$getSaleID = filter_input(INPUT_GET, 'sid', FILTER_VALIDATE_INT);
if ($getSaleID != null && $getSaleID !== false) {
	// Make sure we have a valid API key
	$incomingHeaders = getallheaders();
	if (!isset($incomingHeaders['X-API-Key']) || $incomingHeaders['X-API-Key'] != DART_SENDINVOICE_API_KEY) {
		SP_ErrorLogging("$currentScript : GET sid sent, $getSaleID, but invalid X-API-Key", true, DART_ERROR_LOG);
	} else {
		dartLogging($currentScript, "Called via HTTP : sid = " . $getSaleID);
		$argv = array(
			'sendinvoice.php',
			$getSaleID
		);
	}
}

$debug = false;
/* XXX */
$debugMail = 'christopher@specialtyproduce.com';
$debugName = 'Christopher Cilley';
$debugFax = '';
$pdfMail = true;
$pdfFax = true;
$processEDIs = true;
$rsiMail = true;
// Removed since no locations are using it, tblDartInvoiceSendHula, and need to reconfigure directories
$hulaMail = false;
$pppMail = true;
$bevagerFTP = false; // Not been used in 3 months - disable - AZURE update if this is getting turned back on!!!
$r365FTP = true;
$cheftecMail = true;
$plateIQMail = true;
$simple123CSV = true;
$qsronlineCSV = true;

// $resendArray = array (3123888,3123662,3123908,3123956,3123321);

// foreach ( $resendArray as $resendSaleID ) {

if ($adhoc) {
	/* XXX */
	echo "Ad Hoc Exiting...";
	exit();
	// $argv = array('adhoc_sendinvoice.php', 6873189, 6875546, 6876178, 6879627, 6880953, 6883294, 6884395, 6886619, 6888208, 6891059, 6892106);
	echo "<pre>\n";
	echo "Starting...\n\n";
	echo "count = " . count($argv) . "\n";
	error_log("$currentScript : START...");

	$debug = false;
	$pdfMail = false;
	$pdfFax = false;
	$processEDIs = false;
	$rsiMail = false;
	// Removed since no locations are using it, tblDartInvoiceSendHula, and need to reconfigure directories
	$hulaMail = false;
	$pppMail = false;
	$bevagerFTP = false; // Not been used in 3 months - disable - AZURE update if this is getting turned back on!!!
	$r365FTP = false;
	$cheftecMail = false;
	$plateIQMail = false;
	$simple123CSV = false;
	$qsronlineCSV = false;
}

if (count($argv) == 1) {
	SP_ErrorLogging("$currentScript : Called without arguments", true, DART_ERROR_LOG);
	exit();
}

$useCOG = false;

// Get the information on the location associated with these invoices
$locInfo = array();
$sendEmails = array();
$sendFaxes = array();
$offLinePOs = array();
$offLinePOcount = 1;
$rsiID = 0;
$hulaID = 0;
$r365Data = array();
$bevagerIDs = array();
$cheftecEmails = array();
$plateIQEmailAddress = '';
$plateIQPODefault = '';
$simple123IDs = array();
$qsronlineIDs = array();
$pppEmails = array();
$sqlFailed = true;
$sqlAttemptCount = 1;
$invXML = '';
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		// set the error reporting attribute.
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// The XML to get the invoice info
		$invXML = "<ROOT>\n";
		for ($i = 1; $i < count($argv); $i++)
			$invXML .= '<Rec rID="' . $argv[$i] . '"/>' . "\n";
		$invXML .= "</ROOT>\n";
		if ($adhoc) {
			echo "\$invXML = " . htmlentities($invXML) . "\n";
		}
		// Get the info
		$stmt = $dbh->query("uspDARTSendInvoiceInfo '" . $invXML . "'");
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$shipDate = date('n/j/Y', strtotime($row['dtShip']));
			$deliveryDate = date('n/j/Y g:i:s A', strtotime($row['dtDartDelivered']));
			$isDarkStop = ($row['iSigner'] == DARK_STOP_ID) ? true : false;
			// Determine if this is an offline PO for an EDI
			$ediID = trim($row['sInterchangeID']);
			$parentFTP = ($row['sParentFTPFolder'] == null) ? '' : trim($row['sParentFTPFolder']);
			$POnumber = trim($row['sPO']);
			if (strlen($ediID) > 0 && strlen($POnumber) == 0) {
				$requireEDIPO = constant('EDISPConsts::' . $ediID . "_REQUIREPO");
				if ($requireEDIPO != null) {
					$POnumber = 'SP-' . date('ymdHi') . '-' . sprintf("%02d", $offLinePOcount);
					$offLinePOs[$row['iSaleID']] = $POnumber;
					$offLinePOcount++;
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
				'showProdID' => $showProdID,
				'isCloverTransaction' => ($row['bIsCloverTransaction'] == -1)
			);
		}
		$stmt->closeCursor();

		// Get the emails
		$sql = "SELECT sEmail, sDescription FROM tblDartInvoiceSendEmails WHERE iLocationID=" . $locInfo[$argv[1]]['id'] . " and sEmail <> 'dontsendinvoices@specialtyproduce.com'";
		$stmt = $dbh->query($sql);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			$sendEmails[] = array(
				'name' => trim($row['sDescription']),
				'email' => trim($row['sEmail'])
			);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$sendEmails = array();
				if ($pdfMail)
					$sendEmails[] = array(
						'name' => $debugName,
						'email' => $debugMail
					);
			}
		} else {
			if ($debug)
				$sendEmails[] = array(
					'name' => $debugName,
					'email' => $debugMail
				);
		}

		// Get the faxes
		$stmt = $dbh->query("SELECT sFax, sDescription FROM tblDartInvoiceSendFaxes WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			$sendFaxes[] = array(
				'name' => $row['sDescription'],
				'fax' => $faxNumber = preg_replace('/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $row['sFax']),
				'faxSilent' => preg_replace('/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '$1-$2-$3', $row['sFax'])
			);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$sendFaxes = array();
				if ($pdfFax)
					$sendFaxes[] = array(
						'name' => $debugName,
						'fax' => $faxNumber = preg_replace('/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $debugFax)
					);
			}
		} else {
			if ($debug && strlen($debugFax) == 10)
				$sendFaxes[] = array(
					'name' => $debugName,
					'fax' => $faxNumber = preg_replace('/^\+?1?[^0-9]*\(?(\d{3})[^0-9]*(\d{3})[^0-9]*(\d{4})/', '+1 ($1) $2-$3', $debugFax)
				);
		}

		// RSI ID
		$stmt = $dbh->query("SELECT iRSIID FROM tblDartInvoiceSendRSI WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$rsiID = (is_bool($result) && $result === false) ? 0 : (($result['iRSIID'] > 0) ? $result['iRSIID'] : 0);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$rsiID = 0;
			}
		}

		// Hula ID
		$stmt = $dbh->query("SELECT iLocationID FROM tblDartInvoiceSendHula WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$hulaID = (is_bool($result) && $result === false) ? 0 : (($result['iLocationID'] > 0) ? $result['iLocationID'] : 0);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$hulaID = 0;
			}
		}

		// Profit Pro Plus
		$stmt = $dbh->query("SELECT iLocationID, tEmails FROM tblDartInvoiceSendProfitProPlus WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$pppEmails = (is_bool($result) && $result === false) ? array() : (($result['iLocationID'] > 0) ? explode(',', $result['tEmails']) : array());
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$pppEmails = array();
				if ($pppMail)
					$pppEmails[] = array(
						'name' => $debugName,
						'email' => $debugMail
					);
			}
		}

		// Restaurant 365
		$stmt = $dbh->query("SELECT sR365ID, ltrim(rtrim(sFTPUsername)) as sFTPUsername, ltrim(rtrim(sFTPPassword)) as sFTPPassword, sFTPFolder FROM tblDartInvoiceSendR365 WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$r365Data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$r365Data = array();
			}
		}

		// Bevager
		$stmt = $dbh->query("SELECT iLocationID FROM tblDartInvoiceSendBevager WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$bevagerIDs = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$bevagerIDs = array();
			}
		}

		// Cheftec
		$stmt = $dbh->query("SELECT iLocationID, tEmails FROM tblDartInvoiceSendCheftec WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$cheftecEmails = (is_bool($result) && $result === false) ? array() : (($result['iLocationID'] > 0) ? explode(',', $result['tEmails']) : array());
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$cheftecEmails = array();
				if ($cheftecMail)
					$cheftecEmails[] = array(
						'name' => $debugName,
						'email' => $debugMail
					);
			}
		}

		// PlateIQ
		$stmt = $dbh->query("SELECT iLocationID, sEmail, sPODefault FROM tblDartInvoiceSendPlateIQ WHERE bSummaryOnly=0 and iLocationID=" . $locInfo[$argv[1]]['id']);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		$plateIQEmailAddress = (is_bool($result) && $result === false) ? '' : (($result['iLocationID'] > 0) ? $result['sEmail'] : '');
		$plateIQPODefault = (is_bool($result) && $result === false) ? '' : (($result['iLocationID'] > 0) ? $result['sPODefault'] : '');
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$plateIQEmailAddress = '';
				if ($plateIQMail)
					$plateIQEmailAddress = $debugMail;
			}
		}

		// Simple123
		$stmt = $dbh->query("SELECT (case
								when g.iLocationID is null then s.iLocationID
								when g.iLocationSubParentID = 0 then g.iLocationID
								else g.iLocationSubParentID
								end) as iS123LocID
								FROM tblDARTInvoiceSendSimple123 s
								LEFT JOIN tblLocationGroupDetail g on g.iLocationID = s.iLocationID
								WHERE s.iLocationID=" . $locInfo[$argv[1]]['id']);
		$simple123IDs = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$simple123IDs = array();
			}
		}

		// QSROnline
		$stmt = $dbh->query("SELECT iLocationID FROM tblDartInvoiceSendQSROnline WHERE iLocationID=" . $locInfo[$argv[1]]['id']);
		$qsronlineIDs = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($adhoc) {
			if ($debug) {
				$qsronlineIDs = array();
			}
		}

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

// Get the COG Accounts information
try {
	list($useCOG, $cogShowZeroTotal, $cogMaster, $cogLocation) = LocationSP::getCOGSetup($locInfo[$argv[1]]['id']);
} catch (SP_Exception $e) {
	$errorTxt = $e->getFile() . " (" . $e->getLine() . ") : " . $e->getMessage();
	SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG);
	exit();
}

// Get Product ID COG exceptions
$cogExceptionProduct = LocationSP::cogGetProductIDExceptions($locInfo[$argv[1]]['id']);

// Work through each invoice and save the PDF
if ($pdfMail || $pdfFax) {
	for ($i = 1; $i < count($argv); $i++) {
		$invNum = $argv[$i];

		// Reset the COG totals
		if ($useCOG)
			foreach ($cogLocation as &$cl) {
				$cl['count'] = 0;
				$cl['total'] = 0;
			}

		$lineItems = array();
		$invTotal = 0.0;
		$trackInvoiceEdits = array();
		$sqlFailed = true;
		$sqlAttemptCount = 1;
		while ($sqlFailed) {
			$sqlFailed = false;
			try {
				$dbh = new PDO('spdb', '', '');
				// set the error reporting attribute.
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

				// Get the line items of the invoice.
				$sql = "uspWebXFInvoiceDetail " . $invNum;
				$stmt = $dbh->query($sql);
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$itemTotal = preg_replace('/^\$/', '', $row['Total']);
					$invTotal += $itemTotal;
					if ($useCOG) {
						$cogID = (array_key_exists($row['iProductID'], $cogExceptionProduct)) ? $cogExceptionProduct[$row['iProductID']] : $row['iCOGMasterID'];
						$cogAccount = ($cogMaster[$cogID] != null && $cogMaster[$cogID] != 0) ? $cogMaster[$cogID] : 0;
						$cogLocation[$cogAccount]['count'] += 1;
						$cogLocation[$cogAccount]['total'] += $itemTotal;
					} else {
						$cogAccount = '';
					}
					if ($row['iProductID'] == 9997)
						$lineItems[] = array(
							'prodID' => 9997,
							'unitID' => 'ea',
							'description' => 'Green Discount ...',
							'ordered' => 1,
							'shipped' => 1,
							'unitPrice' => sprintf("%0.2f", $itemTotal),
							'itemTotal' => $itemTotal,
							'status' => '',
							'cogAccount' => $cogAccount,
							'itemNotes' => ''
						);
					else
						$lineItems[] = array(
							'description' => $row['Description'],
							'ordered' => round($row['fOrderQuantity'], 2),
							'shipped' => round($row['fShipQuantity'], 2),
							'unitPrice' => sprintf("%0.2f", $row['mUnitPrice']),
							'itemTotal' => $itemTotal,
							'status' => $row['Status'],
							'prodID' => $row['iProductID'],
							'cogAccount' => $cogAccount,
							'itemNotes' => trim($row['sItemNotes'])
						);
				}
				$stmt->closeCursor();

				// Get the tracking info for initial entry
				$sql = "uspWebXFInvoiceTrackingInfo " . $invNum;
				$stmt = $dbh->query($sql);
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$ts = preg_replace('/^(.*) (\d+):(\d+):\d+:\d+(.)$/', '$1 $2:$3 $4M', $row['TimePlace']);
					$trackInvoiceEntry = array(
						'source' => $row['OrderSource'],
						'timeStamp' => date('M j, Y g:i A', strtotime($ts)),
						'driver' => $row['Driver'],
						'packer' => $row['Packer'],
						'orderTaker' => $row['OrderedTaker'],
						'ooUser' => $row['UserNameOrdered']
					);
				}
				$stmt->closeCursor();

				// Get the tracking info for edits
				$sql = "uspWebXFInvoiceTrackingEdits " . $invNum;
				$stmt = $dbh->query($sql);
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$trackInvoiceEdits[] = array(
						'modifiedBy' => $row['ModifiedBy'],
						'timeStamp' => $row['TimeModified']
					);
				}
				$stmt->closeCursor();

				$dbh = null;
			} catch (PDOException $e) {
				$eMessage = $e->getMessage();
				$errorTxt = $e->getFile() . " (" . $e->getLine() . ") : " . $e->getMessage();
				$errorTxt .= "\n\n\$sqlAttemptCount = $sqlAttemptCount";
				SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG);
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

		if ($useCOG)
			usort($lineItems, 'sortLineItems');

		$logoFile = ($adhoc) ? '../images/sp_logo_lg.jpg' : null;
		$pdf = new invoicePDF($locInfo[$invNum]['isCloverTransaction'], $logoFile);
		$qrcFile = ($adhoc) ? '../images/sp_trends_qrcode.png' : null;
		$pdf->setQRCode($qrcFile);
		$pdf->setLocation($locInfo[$invNum]['name'], $locInfo[$invNum]['address'], $locInfo[$invNum]['city'], $locInfo[$invNum]['state'], $locInfo[$invNum]['zip'], formatPhone($locInfo[$invNum]['phone']));
		$pdf->setInvoiceHeader($invNum, $locInfo[$invNum]['shipdate'], $locInfo[$invNum]['salesperson'], formatPhone($locInfo[$invNum]['salesphone']), $locInfo[$invNum]['po'], $locInfo[$invNum]['terms']);
		if ($locInfo[$invNum]['showProdID'])
			$pdf->showProdID();
		$pdf->startInvoice();
		// Item List
		$cogLastAccount = '';
		foreach ($lineItems as $line) {
			if ($useCOG && $line['cogAccount'] !== $cogLastAccount) {
				$cogLastAccount = $line['cogAccount'];
				$pdf->addCOGAccountLine($cogLastAccount . ' - ' . $cogLocation[$cogLastAccount]['description']);
			}
			$pdf->addLineItem($line['description'], $line['ordered'], $line['shipped'], $line['unitPrice'], $line['itemTotal'], $line['status'], $line['prodID'], $line['itemNotes']);
		}
		// Invoice Total
		if ($pdf->checkNoSpaceLeft(0.2))
			$pdf->markContinued();
		$pdf->addTotal($invTotal);
		// Add the signature and signer info
		if ($pdf->checkNoSpaceLeft(1.61))
			$pdf->markContinued();
		if ($locInfo[$invNum]['darkstop']) {
			if (is_file(DART_SIG_DIR . 'darkstop.png')) {
				$sigImage = DART_SIG_DIR . 'darkstop.png';
			} else {
				$sigImage = DART_SIG_BACKUP_DIR . 'darkstop.png';
			}
		} else {
			if (is_file(DART_SIG_DIR . $locInfo[$invNum]['id'] . '/' . $invNum . '.png')) {
				// Azure Storage IS working
				$sigImage = DART_SIG_DIR . $locInfo[$invNum]['id'] . '/' . $invNum . '.png';
			} else {
				// Azure Storage NOT working
				$sigImage = DART_SIG_BACKUP_DIR . $locInfo[$invNum]['id'] . '/' . $invNum . '.png';
				SP_ErrorLogging("$currentScript : Azure storage NOT working : $sigImage", true, DART_ERROR_LOG, "DART : Azure storage NOT working : $currentScript");
			}
		}
		$pdf->addSignatureImage($sigImage);
		// Add signer info
		$pdf->addSigner($locInfo[$invNum]['signer'], $locInfo[$invNum]['deldate']);
		// Add COG info
		if ($useCOG) {
			// Determine how many lines of space we need
			if ($cogShowZeroTotal)
				$numCOGLines = count($cogLocation);
			else {
				$numCOGLines = 0;
				foreach ($cogLocation as $entry) {
					$numCOGLines += ($entry['count'] > 0) ? 1 : 0;
				}
			}
			if ($pdf->checkNoSpaceLeft(0.2 * ($numCOGLines + 2)))
				$pdf->markContinued();
			// Calc max Account length
			$pdf->SetFont('Arial', 'B', 12);
			$maxLengthAccount = $pdf->GetStringWidth('Account');
			$maxLengthDesc = $pdf->GetStringWidth('Description');
			$maxLengthTotal = $pdf->GetStringWidth('Amount');
			foreach ($cogLocation as $accKey => $entry) {
				if ($cogShowZeroTotal == false && $cogLocation[$accKey]['count'] == 0)
					continue;
				$tmpLen = $pdf->GetStringWidth($accKey);
				$maxLengthAccount = ($tmpLen > $maxLengthAccount) ? $tmpLen : $maxLengthAccount;
				$tmpLen = $pdf->GetStringWidth($entry['description']);
				$maxLengthDesc = ($tmpLen > $maxLengthDesc) ? $tmpLen : $maxLengthDesc;
				$tmpLen = $pdf->GetStringWidth(sprintf('%0.2f', $entry['total']));
				$maxLengthTotal = ($tmpLen > $maxLengthTotal) ? $tmpLen : $maxLengthTotal;
			}
			$pdf->addCOGHeader($maxLengthAccount, $maxLengthDesc, $maxLengthTotal);
			foreach ($cogLocation as $accKey => $entry) {
				if ($accKey == '0' && $entry['count'] == 0)
					continue;
				if ($cogShowZeroTotal == false && $cogLocation[$accKey]['count'] == 0)
					continue;
				$pdf->addCOGLine($accKey, $entry['description'], sprintf('%0.2f', $entry['total']));
			}
			$pdf->addCOGTotal(sprintf("%0.2f", $invTotal));
		}
		// Add the tracking information
		if (isset($trackInvoiceEntry)) {
			if ($pdf->checkNoSpaceLeft(0.25))
				$pdf->markContinued();
			$pdf->addTrackingInfo($trackInvoiceEntry['source'], (preg_match('/Online/', $trackInvoiceEntry['source']) > 0) ? $trackInvoiceEntry['ooUser'] : $trackInvoiceEntry['orderTaker'], $trackInvoiceEntry['timeStamp'], $trackInvoiceEntry['packer'], $trackInvoiceEntry['driver']);
			if (count($trackInvoiceEdits) > 0) {
				if ($pdf->checkNoSpaceLeft(0.25 * count($trackInvoiceEdits)))
					$pdf->markContinued();
				$pdf->addTrackingEdits();
				foreach ($trackInvoiceEdits as $row) {
					$pdf->addTrackingEditLine($row['modifiedBy'], $row['timeStamp']);
				}
			}
		}
		// Output the PDF
		$outFile = DART_PDF_DIR . $invNum . ".pdf";
		$pdf->Output($outFile, 'F');
		$pdf = null;
	}
}

// Keep track of how many places this is sent to successfully. If we are at "0" at the end, print for sales person
$sendCount = 0;

// Instantiate the mail stuff
$mail = new PHPMailerSP();
$mail->setApiKey('acct');

// Send the emails
if ($pdfMail) {
	if ($adhoc)
		echo "Sending PDFs via email...\n";
	// Setup Info
	$emailLogFile = SPConsts::ErrorLogRoot . "dart_emails.txt";
	$loggingToFile = true;
	$usingSendGrid = false;
	if (count($sendEmails) > 0) {
		if ($loggingToFile)
			$fp = fopen($emailLogFile, "a");

		// Body
		$mailBody = <<< EOT
Dear Customer,

Your latest signed invoice is attached.  Remember, you can always view your invoice history and proof of delivery by logging into your account at www.specialtyproduce.com.

Refer to attached invoice for your payment terms. Payment is due in our office by your payment terms date.

You can remit payment two ways...
+ Online : Simply log into your account at www.specialtyproduce.com and click the green bar for "Accounting - Online Bill Pay".  Please note, you will need to first contact our accounting department at ar@specialtyproduce.com or (619) 876-4070 to activate your account for online bill pay.
+ Mail : Make checks payable to Specialty Produce and mail it to: P.O. Box 82066, San Diego, CA 92138

Should you have any questions, please contact accounting department at ar@specialtyproduce.com or (619) 876-4070.

We appreciate your business.

Sincerely,
Specialty Produce

EOT;
		// Subject line
		$subjectStr = (count($argv) == 2) ? 'SP Invoice : ' : 'SP Invoices : ';
		$subjectStr .= $argv[1];
		$subjectStr .= (strlen($locInfo[$argv[1]]['po']) > 0) ? ' (' . $locInfo[$argv[1]]['po'] . ')' : '';
		for ($i = 2; $i < count($argv); $i++) {
			$subjectStr .= ', ' . $argv[$i];
			$subjectStr .= (strlen($locInfo[$argv[$i]]['po']) > 0) ? ' (' . $locInfo[$argv[$i]]['po'] . ')' : '';
		}
		if ($loggingToFile)
			fwrite($fp, date('[d-M-Y H:i:s]') . " : " . $subjectStr . " -");

		if ($usingSendGrid) {
			// Instantiate SendGrid Mail
			$sgmail = SendGridSP::getEmail();
			$sgmail->setFrom("ar@specialtyproduce.com", "Specialty Produce Accounting");
			$sgmail->setSubject($subjectStr);
			$sgmail->setReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
			// Add the body
			$sgmail->addContent("text/plain", $mailBody);
			// Add the PDFs
			$saleIDs = array();
			foreach (array_keys($locInfo) as $key) {
				$saleIDs[] = $key;
				$outFile = DART_PDF_DIR . $key . ".pdf";
				$outFileName = $key . ".pdf";
				$file_encoded = base64_encode(file_get_contents($outFile));
				$sgmail->addAttachment(
					$file_encoded,
					"application/pdf",
					$outFileName
				);
			}
			// Send
			if ($debug)
				$sgmail->addBcc($debugMail, $debugName);
			$emailList = array();
			$badEmails = array();
			foreach ($sendEmails as $entry) {
				if (strlen($entry['email']) > 0) {
					if ($loggingToFile)
						fwrite($fp, " " . $entry['email']);
					if ($entry['email'] != DONT_SEND_INVOICE_EMAIL) {
						if (!filter_var($entry['email'], FILTER_VALIDATE_EMAIL))
							$badEmails[] = $entry;
						else
							$emailList[] = $entry;
					}
				}
			}
			if (count($emailList) > 0) {
				try {
					SendGridSP::sendDartInvoicePDFEmail('acct', $sgmail, $emailList, json_encode($saleIDs));
				} catch (SP_Exception $spe) {
					$errorTxt = $spe->getFile() . " (" . $spe->getLine() . ") : " . $spe->getMessage();
					SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG, 'DART PDF Email SendGrid Error');
				}
			}
			if (count($badEmails) > 0) {
				$errorTxt = "Bad Emails for " . $locInfo[$saleIDs[0]]['name'] . " (" . $locInfo[$saleIDs[0]]['id'] . ")\n";
				$errorTxt .= "SaleIDs = " . implode(", ", $saleIDs) . "\n" . json_encode($badEmails);
				SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG, 'DART PDF Bad Emails', 'ar@specialtyproduce.com');
			}
		} else {
			$mail->FromName = "Specialty Produce Accounting";
			$mail->From = "ar@specialtyproduce.com";
			$mail->Subject = $subjectStr;
			$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
			// Add the PDFs
			foreach (array_keys($locInfo) as $key) {
				$outFile = DART_PDF_DIR . $key . ".pdf";
				$mail->AddAttachment($outFile, "$key.pdf");
			}
			$mail->Body = $mailBody;

			// Send the emails
			$badEmails = array();
			if ($debug)
				$mail->AddBCC($debugMail, $debugName);
			foreach ($sendEmails as $entry) {
				if (strlen($entry['email']) > 0) {
					if ($loggingToFile)
						fwrite($fp, " " . $entry['email']);
					if ($entry['email'] == DONT_SEND_INVOICE_EMAIL) {
						$sendCount++;
					} else {
						$mail->AddAddress($entry['email'], $entry['name']);
						if (!$mail->Send()) {
							$badEmails[] = $entry['email'];
						} else {
							$sendCount++;
						}
						$mail->ClearAddresses();
						$mail->ClearBCCs();
					}
				}
			}
			$mail->ClearAttachments();
			$mail->ClearAllRecipients();
		}
		if ($loggingToFile) {
			fwrite($fp, "(" . $sendCount . ")\n");
			fclose($fp);
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending PDFs via email...\n";
}

// Fax it
if ($pdfFax) {
	if ($locInfo[$argv[1]]['id'] > 0) {
		if ($adhoc)
			echo "Sending PDFs via fax...\n";
		$faxNumCount = 0;
		$srFax = new SRFaxSP('acct');
		foreach ($sendFaxes as $faxInfo) {
			$faxNumCount++;
			foreach ($locInfo as $invoice) {
				$outName = $invoice['saleID'] . ".pdf";
				$outPath = DART_PDF_DIR . $invoice['saleID'] . ".pdf";
				$srFax->sendFax($invoice['id'], 'Invoice', $faxInfo['faxSilent'], $outName, $outPath);
			}
		}
		$srFax->writeQueueLogToDB();
		if (count($srFax->errorLog) > 0)
			SP_ErrorLogging("SR Fax Send Errors : $currentScript \n" . implode("\n", $srFax->errorLog), true, '', "SR Fax Send Errors");
	}
} else {
	if ($adhoc)
		echo "NOT sending PDFs via fax...\n";
}

// Process the EDI invoices
if ($processEDIs) {
	if ($adhoc)
		echo "Sending via EDI...\n";
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
						if ($debug)
							$mail->AddBCC($debugMail, $debugName);
						// Add the PDFs
						$mail->AddAttachment(DART_PDF_DIR . $saleID . ".pdf", "$saleID.pdf");
						// Add the body
						$mail->Body = <<< EOT
Dear Customer,

The attached invoice did not have a PO number.
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
				include_once 'EDI_SP/Receivers/' . $loc['ediID'] . '/ts810.php';
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
				$outPath = EDISPConsts::EDI_BACKUP;
				$outPath .= (strlen($loc['parentFTP']) > 0) ? $loc['parentFTP'] . '/' : '';
				$outPath .= $loc['ediID'] . '/outgoing';
				if (!is_dir($outPath)) {
					$success = mkdir($outPath, 0777, true);
				}
				$outFile = $outPath . '/' . $outFileName;
				error_log("ProcessEDI : " . $outFile);
				if (!file_put_contents($outFile, $msg)) {
					$errMsg = "Error writing outgoing 810 : $outFile" . "\nfor invoice # " . $loc['saleID'];
					SP_errorLogging($errMsg, true, EDISPConsts::EDI_ERROR_LOG, $currentScript . " - EDI error");
					continue;
				}
				// Write to Azure Blob
				$azFTPDir = (strlen($loc['parentFTP']) > 0) ? $loc['parentFTP'] . '/' : '';
				$azFTPDir .= $loc['ediID'] . '/outgoing/';
				$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_FTP_DIR, $azFTPDir, $outFileName, $outFile, 'text/plain');
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
					}
				}
			}
			// Need to sleep 2 seconds so we don't overwrite a file
			sleep(2);
		}
	} catch (SP_Exception $e) {
		$errorTxt = $e->getFile() . " (" . $e->getLine() . ") : " . $e->getMessage();
		SP_ErrorLogging($errorTxt, true, DART_ERROR_LOG);
	}
	$mail->ClearAllRecipients();
} else {
	if ($adhoc)
		echo "NOT sending via EDI...\n";
}

// Process RSI Invoices
if ($rsiMail) {
	if ($adhoc)
		echo "Sending via RSI...\n";
	if ($rsiID > 0) {
		$rsiLocationName = $locInfo[$argv[1]]['name'];
		// $rsiMailtoAddress = "xtophersd@yahoo.com";
		$rsiMailtoAddress = $rsiID . "@restacct.com";
		$rsiFilename = preg_replace('/[^a-zA-Z0-9]/', '', $rsiLocationName) . '_' . date('Ymd_Hi') . '.txt';
		$rsiFile = DART_RSI_DIR . $rsiFilename;
		$rsiFH = fopen($rsiFile, "w");
		foreach ($locInfo as $loc) {
			$invRSI = new InvoiceRSI();
			try {
				$invRSI->retrieveInvoice($loc['saleID']);
				fwrite($rsiFH, $invRSI->generateRSIOutput());
			} catch (SP_Exception $spe) {
				$errMsg = "RSI : Retrieve invoice error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - RSI error");
				continue;
			}
		}
		fclose($rsiFH);
		$mail->FromName = "Specialty Produce Accounting";
		$mail->From = "ar@specialtyproduce.com";
		$mail->AddAddress($rsiMailtoAddress);
		if ($debug)
			$mail->AddBCC($debugMail, $debugName);
		$mail->Subject = "Specialty Produce Imported Invoice";
		$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
		$mail->AddAttachment($rsiFile, $rsiFilename);
		// Add the body
		$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $rsiLocationName . "\n - Specialty Produce System";
		// Send the email
		if (!$mail->Send()) {
			$errMsg = "RSI : Send mail error : " . $rsiMailtoAddress;
			SP_errorLogging($errMsg, true, '', $currentScript . " - RSI error");
		}
		$mail->ClearAttachments();
		$mail->ClearAllRecipients();
		sleep(3);
		unlink($rsiFile);
	}
	$mail->ClearAllRecipients();
} else {
	if ($adhoc)
		echo "NOT sending via RSI...\n";
}

// Process Hula Invoices
// Disabled, see $hulaMail above
// if ($hulaMail) {
// 	if ($adhoc)
// 		echo "Sending via HULA...\n";
// 	if ($hulaID > 0) {
// 		$hulaFilenamePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', preg_replace('/\s/', '_', $locInfo[$argv[1]]['name']));
// 		foreach ($locInfo as $loc) {
// 			$invHula = new InvoiceHula();
// 			try {
// 				$invHula->retrieveInvoice($loc['saleID']);
// 				$hulaFile = DART_HULA_DIR . $hulaFilenamePrefix . "_" . $loc['saleID'] . ".txt";
// 				$hulaFH = fopen($hulaFile, "w");
// 				fwrite($hulaFH, $invHula->generateHulaOutput());
// 				fclose($hulaFH);
// 			} catch (SP_Exception $spe) {
// 				$errMsg = "Hula : Retrieve invoice error : " . $spe->getMessage();
// 				SP_errorLogging($errMsg, true, '', $currentScript . " - Hula error");
// 				continue;
// 			}
// 		}
// 	}
// } else {
// 	if ($adhoc)
// 		echo "NOT sending via HULA...\n";
// }

// Process Profit Pro Plus Invoices
if ($pppMail) {
	if ($adhoc)
		echo "Sending via PPM...\n";
	if (count($pppEmails) > 0) {
		// Start
		$pppLocationName = $locInfo[$argv[1]]['name'];
		$mail->FromName = "Specialty Produce Accounting";
		$mail->From = "ar@specialtyproduce.com";
		$mail->Subject = "$pppLocationName : Specialty Produce Imported Invoice";
		$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
		// Add the body
		$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $pppLocationName . "\n - Specialty Produce System";

		foreach ($locInfo as $loc) {
			// Add the Addresses
			foreach ($pppEmails as $pppMailToAddress)
				$mail->AddAddress($pppMailToAddress);
			if ($debug)
				$mail->AddBCC($debugMail, $debugName);
			// Generate the file
			$pppFileName = $loc['saleID'] . '.txt';
			$pppFile = DART_PPP_DIR . $pppFileName;
			$pppFH = fopen($pppFile, "w");
			$invPPP = new InvoiceProfitProPlus();
			try {
				$invPPP->retrieveInvoice($loc['saleID']);
				fwrite($pppFH, $invPPP->generateProfitProPlusOutput());
			} catch (SP_Exception $spe) {
				$errMsg = "PPP : Retrieve invoice error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - PPP error");
				continue;
			}
			fclose($pppFH);
			$mail->AddAttachment($pppFile, $pppFileName);
			// Send the email
			if (!$mail->Send()) {
				$errMsg = "PPP : Send mail error : " . implode(',', $pppEmails);
				SP_errorLogging($errMsg, true, '', $currentScript . " - PPP error");
			}
			$mail->ClearAllRecipients();
			$mail->ClearAttachments();
		}
		sleep(3);
		foreach ($locInfo as $loc) {
			$pppFileName = $loc['saleID'] . '.txt';
			$pppFile = DART_PPP_DIR . $pppFileName;
			unlink($pppFile);
		}
	}
	$mail->ClearAllRecipients();
	$mail->ClearAttachments();
} else {
	if ($adhoc)
		echo "NOT sending via PPM...\n";
}

// Process Restaurant 365
if ($r365FTP) {
	if ($adhoc)
		echo "Sending via FTP to R365...\n";
	if (count($r365Data) > 0) {
		$r365InvoiceList = array();
		foreach ($locInfo as $loc) {
			$invR365 = new InvoiceR365($r365Data[0]);
			// Retrieve and add to CSV each invoice
			$invR365->retrieveInvoice($loc['saleID']);
			$r365InvoiceList[] = $loc['saleID'];
			$invR365->generateCSVLineItems();
			$invR365->detail = array();
			try {
				$invR365->ftpCSV();
			} catch (SP_Exception $spe) {
				$errMsg = "R365 : FTP CSV error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - R365 error");
				dartLogging($currentScript, "    R365 FTP Failed : " . implode(',', $r365InvoiceList));
			}
		}
		error_log($currentScript . " : R365 FTP Sent : " . implode(',', $r365InvoiceList));
		dartLogging($currentScript, "    R365 FTP Sent : " . implode(',', $r365InvoiceList));
	}
} else {
	if ($adhoc)
		echo "NOT sending via FTP to R365...\n";
}

// Bevager
if ($bevagerFTP) {
	if ($adhoc)
		echo "Sending via FTP to Bevager...\n";
	if (count($bevagerIDs) > 0) {
		$azb = new AzureBlobSP('specprodstorage', false);
		foreach ($locInfo as $loc) {
			try {
				$bevFilename = $loc['id'] . '_' . $loc['saleID'] . '.csv';
				$invBev = new InvoiceBevager();
				$bevString = $invBev->getBevHeader();
				$invBev->retrieveInvoice($loc['saleID']);
				$bevString .= $invBev->generateBevOutput();
				$bevFile = SPConsts::TempDir . $bevFilename;
				file_put_contents($bevFile, $bevString);
				$ftpDir = $invBev::FILEMAGE_FTP_DIR . $invBev::FTP_INVOICES;
				$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_FTP_DIR, $ftpDir, $bevFilename, $bevFile, 'text/csv');
				sleep(3);
				SP_ErrorLogging("Bevager file created : $ftpDir : $bevFilename", true, '', "Bevager EDI : $bevFilename");
				unlink($bevFile);
			} catch (SP_Exception $spe) {
				$errMsg = "Bevager : Retrieve invoice error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - Bevager error");
				continue;
			}
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending via FTP to Bevager...\n";
}

// Cheftec Invoices
if ($cheftecMail) {
	if ($adhoc)
		echo "Sending via Cheftec...\n";
	if (count($cheftecEmails) > 0) {
		// Start
		$cheftecLocationName = $locInfo[$argv[1]]['name'];
		$mail->FromName = "Specialty Produce Accounting";
		$mail->From = "ar@specialtyproduce.com";
		$mail->Subject = "$cheftecLocationName : Specialty Produce Imported Invoice";
		$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
		// Add the body
		$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $cheftecLocationName . "\n - Specialty Produce System";

		foreach ($locInfo as $loc) {
			// Add the Addresses
			foreach ($cheftecEmails as $cheftecMailToAddress)
				$mail->AddAddress($cheftecMailToAddress);
			if ($debug)
				$mail->AddBCC($debugMail, $debugName);
			// Generate the file
			$cheftecFileName = $loc['saleID'] . '.csv';
			$cheftecFile = DART_CT_DIR . $cheftecFileName;
			$cheftecFH = fopen($cheftecFile, "w");

			$invCT = new InvoiceCheftec();
			try {
				$invCT->retrieveInvoice($loc['saleID']);
				fwrite($cheftecFH, $invCT->generateCheftecOutput());
			} catch (SP_Exception $spe) {
				$errMsg = "CT : Retrieve invoice error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - CT error");
				continue;
			}
			fclose($cheftecFH);
			$mail->AddAttachment($cheftecFile, $cheftecFileName);
		}
		// Send the email
		if (!$mail->Send()) {
			$errMsg = "CT : Send mail error : " . implode(',', $cheftecEmails);
			SP_errorLogging($errMsg, true, '', $currentScript . " - CT error");
		}
		$mail->ClearAllRecipients();
		$mail->ClearAttachments();
		sleep(3);
		foreach ($locInfo as $loc) {
			$cheftecFileName = $loc['saleID'] . '.csv';
			$cheftecFile = DART_CT_DIR . $cheftecFileName;
			unlink($cheftecFile);
		}
	}
	$mail->ClearAllRecipients();
	$mail->ClearAttachments();
} else {
	if ($adhoc)
		echo "NOT sending via CT...\n";
}

// Process PlateIQ Invoices
if ($plateIQMail) {
	if ($adhoc)
		echo "Sending via PlateIQ...\n";
	if (strtoupper($plateIQEmailAddress) == 'FTP') {
		try {
			$azb = new AzureBlobSP('specprodstorage', false);
			$invPlateIQ = new InvoicePlateIQ();
			$invPlateIQ->PODefault = $plateIQPODefault;
			foreach ($locInfo as $loc) {
				try {
					$piqString = $invPlateIQ->getPIQHeader();
					$invPlateIQ->retrieveInvoice($loc['saleID']);
					$piqString .= $invPlateIQ->generatePIQOutput();
					$piqFilename = $loc['id'] . '_' . $loc['saleID'] . '_' . date('ymd_His') . '.csv';
					$piqFile = DART_PLATEIQ_DIR . $piqFilename;
					$piqFH = fopen($piqFile, "w");
					fwrite($piqFH, $piqString);
					fclose($piqFH);
					$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_FTP_DIR, $invPlateIQ::FILEMAGE_FTP_DIR, $piqFilename, $piqFile, 'text/csv');
					sleep(3);
					unlink($piqFile);
				} catch (SP_Exception $spe) {
					$errMsg = "PlateIQ : invoice error : " . $spe->getMessage();
					SP_errorLogging($errMsg, true, '', $currentScript . " - PlateIQ error");
					continue;
				}
			}
		} catch (Exception $e) {
			SP_ErrorLogging("PlateIQ Error : " . $e->getMessage(), true, '', 'DART Plate IQ Error');
		}
	} else {
		if (strlen($plateIQEmailAddress) > 0) {
			$plateIQFilename = $loc['saleID'] . '.csv';
			$plateIQFile = DART_PLATEIQ_DIR . $plateIQFilename;
			$plateIQFH = fopen($plateIQFile, "w");
			$invPlateIQ = new InvoicePlateIQ();
			$invPlateIQ->PODefault = $plateIQPODefault;
			$piqString = $invPlateIQ->getPIQHeader();
			foreach ($locInfo as $loc) {
				try {
					$invPlateIQ->retrieveInvoice($loc['saleID']);
					$piqString .= $invPlateIQ->generatePIQOutput();
				} catch (SP_Exception $spe) {
					$errMsg = "RSI : Retrieve invoice error : " . $spe->getMessage();
					SP_errorLogging($errMsg, true, '', $currentScript . " - RSI error");
					continue;
				}
			}
			fwrite($plateIQFH, $piqString);
			fclose($plateIQFH);
			$mail->FromName = "Specialty Produce Accounting";
			$mail->From = "ar@specialtyproduce.com";
			$mail->AddAddress($plateIQEmailAddress);
			if ($debug || $adhoc)
				$mail->AddBCC($debugMail, $debugName);
			$mail->Subject = "Specialty Produce Imported Invoice";
			$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
			$mail->AddAttachment($plateIQFile, $plateIQFilename);
			// Add the body
			$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $locInfo[$argv[1]]['name'] . "\n - Specialty Produce System";
			// Send the email
			if (!$mail->Send()) {
				$errMsg = "PlateIQ : Send mail error : " . $plateIQEmailAddress;
				SP_errorLogging($errMsg, true, '', $currentScript . " - RSI error");
			}
			$mail->ClearAttachments();
			$mail->ClearAllRecipients();
			// SP_ErrorLogging ( "PlateIQ sent for $plateIQFilename : " . file_get_contents ( $plateIQFile ), true, "", "PlateIQ Alert" );
			sleep(3);
			unlink($plateIQFile);
		}
		$mail->ClearAllRecipients();
	}
} else {
	if ($adhoc)
		echo "NOT sending via RSI...\n";
}

// Simple123
if ($simple123CSV) {
	if ($adhoc)
		echo "Emailing via Simple123...\n";
	if (count($simple123IDs) > 0) {
		$s123LocID = $simple123IDs[0]['iS123LocID'];
		$s123Filename = $s123LocID . '-invoice-' . date('ymd-His') . '-' . $locInfo[$argv[1]]['id'] . '.csv';
		$s123File = SPConsts::TempDir . $s123Filename;
		$s123FH = fopen($s123File, "w");
		$firstEntry = true;
		$sIDsToEmail = array();
		foreach ($locInfo as $loc) {
			try {
				$inv = new InvoiceSP_Simple123();
				$inv->retrieveInvoice($loc['saleID']);
				$inv->resetLocationID($s123LocID);
				if ($firstEntry) {
					fwrite($s123FH, $inv->getCSVHeader() . "\n");
					$firstEntry = false;
				}
				fwrite($s123FH, $inv->generateInvoiceCSV());
			} catch (SP_Exception $spe) {
				$errMsg = "Simple123 : Retrieve invoice error : " . $spe->getMessage();
				SP_errorLogging($errMsg, true, '', $currentScript . " - Simple123 error");
				continue;
			}
		}
		fclose($s123FH);
		// Is there a CSV to email?
		if ($firstEntry == false) {
			$mail->FromName = "Specialty Produce Accounting";
			$mail->From = "ar@specialtyproduce.com";
			$mail->AddAddress(InvoiceSP_Simple123::EMAIL_RECIPIENT_CSV);
			if ($debug)
				$mail->AddBCC($debugMail, $debugName);
			$mail->Subject = "Specialty Produce Invoice";
			$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
			$mail->AddAttachment($s123File, $s123Filename);
			// Add the body
			$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $locInfo[$argv[1]]['name'] . "\n - Specialty Produce System";
			// Send the email
			if (!$mail->Send()) {
				$errMsg = "Simple123 : Send mail error : " . InvoiceSP_Simple123::EMAIL_RECIPIENT_CSV;
				SP_errorLogging($errMsg, true, '', $currentScript . " - Simple123 error");
			}
			$mail->ClearAttachments();
			$mail->ClearAllRecipients();
			sleep(3);
			// Put up in Azure
			// $azb = new AzureBlobSP ( 'specprodstorage', false );
			// $azb->putBlockBlobFile ( AzureBlobSP::AZURE_STORAGE_FTP_DIR, DART_SIMPLE123_FTP_DIR, $s123Filename, $s123File );
			unlink($s123File);
		}
		// Any PDFs to email?
		if (count($sIDsToEmail) > 0) {
			$mail->FromName = "Specialty Produce Accounting";
			$mail->From = "ar@specialtyproduce.com";
			$mail->AddAddress(InvoiceSP_Simple123::EMAIL_RECIPIENT_PDF);
			if ($debug)
				$mail->AddBCC($debugMail, $debugName);
			$mail->AddBCC($debugMail, $debugName);
			$mail->Subject = "Specialty Produce Invoice";
			$mail->AddReplyTo("ar@specialtyproduce.com", "Specialty Produce Accounting");
			foreach ($sIDsToEmail as $saleID)
				$mail->AddAttachment(DART_PDF_DIR . $saleID . ".pdf", "$saleID.pdf");
			// Add the body
			$mail->Body = "Dear Sir or Madam,\nAttached is the invoice information for a recent delivery to " . $locInfo[$argv[1]]['name'] . "\n - Specialty Produce System";
			// Send the email
			if (!$mail->Send()) {
				$errMsg = "Simple123 : Send mail error : " . InvoiceSP_Simple123::EMAIL_RECIPIENT_PDF;
				SP_errorLogging($errMsg, true, '', $currentScript . " - Simple123 error");
			}
			$mail->ClearAttachments();
			$mail->ClearAllRecipients();
			sleep(3);
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending via FTP to Simple123...\n";
}

// QSROnline
if ($qsronlineCSV) {
	if ($adhoc)
		echo "Sending via FTP to QSROnline...\n";
	if (count($qsronlineIDs) > 0) {
		try {
			$azb = new AzureBlobSP('specprodstorage', false);
			foreach ($locInfo as $loc) {
				$qsrFilename = $loc['id'] . '_' . $loc['saleID'] . '_' . date('ymd_His') . '.csv';
				$invQSR = new InvoiceSP_QSROnline();
				$invQSR->retrieveInvoice($loc['saleID']);
				$qsrString = $invQSR->generateInvoiceCSV();
				$qsrFile = SPConsts::TempDir . $qsrFilename;
				file_put_contents($qsrFile, $qsrString);
				$azb->putBlockBlobFile(AzureBlobSP::AZURE_STORAGE_FTP_DIR, $invQSR::FILEMAGE_FTP_DIR, $qsrFilename, $qsrFile, 'text/csv');
				sleep(3);
				unlink($qsrFile);
			}
		} catch (SP_Exception $spe) {
			$errMsg = "QSROnline :  error : " . $spe->getMessage();
			SP_errorLogging($errMsg, true, '', $currentScript . " - QSROnline error");
		}
	}
} else {
	if ($adhoc)
		echo "NOT sending via FTP to QSROnline...\n";
}

// Remove the PDFs
// Do not remove the PDF files, we're going to let them stay for 90 days and delete them with a Scheduled Task
if ($adhoc) {
	echo "Done...";
	echo "</pre>\n";
	error_log("$currentScript : DONE...");
}
// }
// error_log("$currentScript : sent : " . html_entity_decode($invXML) . " : " . print_r($argv, true));
exit(0);
