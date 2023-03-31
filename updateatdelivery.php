<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<updateinvoices status="failed" code="0" retry="false" errmsg="XXX">
</updateinvoices>
EOT;

// Get the POST data
$appJSON = $_POST['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON, $codeStr);

// appJSON
if ($appJSON == FALSE || is_null($appJSON)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No jsondata supplied', $badXML);
	echo $badXML;
	exit();
}

$jd = json_decode($appJSON);
// userid
$userid = filter_var($jd->userid, FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No userid', $badXML);
	echo $badXML;
	exit();
}

// invJSON
$invJSON = $jd->invoicesjson;
if ($invJSON == FALSE || is_null($invJSON)) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No saleids JSON', $badXML);
	echo $badXML;
	exit();
}
$saleIDs = json_decode($invJSON);

// Check for invoices
if (count($saleIDs) == 0) {
	$badXML = preg_replace('/XXX/', $currentScript . ' : No saleids', $badXML);
	echo $badXML;
	dartLogging($currentScript, "no sale ids", $codeStr);
	exit();
}

// Get the invoices marked as "being delivered", which is code 1 for this stored procedure
$updateCode = 1;
$signerID = 0; // Only matters for when we are running deliverycomplete.php
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

		// Prep for the XML version of invoice list for the stored procedure
		$invXML = "<ROOT>\n";
		for ($idx = 0; $idx < count($saleIDs); $idx++) {
			$invXML .= '<Rec rID = "';
			$invXML .= $saleIDs[$idx];
			$invXML .= '"/>' . "\n";
		}
		$invXML .= "</ROOT>\n";
		// dartLogging ( $currentScript, "invXML=" . $invXML );
		$sql = "uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'";
		$result = $dbh->exec($sql);

		// Get the invoice data
		$sql = "uspDARTCheckLastUpdate '" . $invXML . "'";
		$stmt = $dbh->query($sql);
		$invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
		$stmt->closeCursor();

		$dbh = null;
	} catch (PDOException $e) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage();
		$errMsg .= $e->getFile() . ' (' . $e->getLine() . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\n\ninvXML = " . htmlentities($invXML);
		$errMsg .= "\n\n\$codeStr = $codeStr";
		$errMsg .= "\n\n\$sqlAttemptCount = $sqlAttemptCount";
		if (preg_match('/Timeout expired/', $eMessage) || preg_match('/SQL Server does not exist or access denied/', $eMessage) || preg_match('/deadlock victim/', $eMessage) || preg_match('/Schema changed/', $eMessage)) {
			if ($sqlAttemptCount > 1)
				SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : UpdateAtDelivery Retry');
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
			SP_ErrorLogging($errMsg, true, DART_ERROR_LOG, 'DART : UpdateAtDelivery Serious');
			$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
			echo $badXML;
			exit();
		}
	}
}

if ($result === false) {
	$errMsg = "$currentScript : uspDARTDelivered $updateCode, $invXML returned FALSE";
	$errMsg .= "\n\n\$codeStr = $codeStr";
	SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
	$badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
	echo $badXML;
	exit();
}

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<updateinvoices status="success">' . "\n";
$resultStr .= '<invoices_invoice_list>' . "\n";
foreach ($invInfo as $item) {
	$lastUpdate = preg_replace('/(.*)\.\d{3}$/', '$1', $item['dtDartLastUpdated']);
	$resultStr .= '<invoice saleid="' . $item['iSaleID'] . '" locid="' . $item['iLocationDestinationID'] . '" lastupdate="' . $lastUpdate . '" />' . "\n";
}
$resultStr .= '</invoices_invoice_list>' . "\n";
$resultStr .= '</updateinvoices>' . "\n";
echo $resultStr;
exit();
