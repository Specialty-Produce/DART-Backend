<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

$returnVals = array ();
$returnVals ['result'] = true;
$returnVals ['errorMsg'] = '';

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode ( 6 );

// Get the POST data
$saleID = filter_input ( INPUT_POST, 'saleid', FILTER_VALIDATE_INT );
$locationID = filter_input ( INPUT_POST, 'locid', FILTER_VALIDATE_INT );
$signerInfo = filter_input ( INPUT_POST, 'signerinfo', FILTER_SANITIZE_STRING );
$signTS = filter_input ( INPUT_POST, 'signts' );
$sigData = filter_input ( INPUT_POST, 'sigdata' );

// Log the data
if (isset ( $_POST ['saleid'] )) {
	dartLogging ( $currentScript, "$saleID : $locationID : $signerInfo : $signTS : $sigData", $codeStr );
}

// Process SignerID
if (strpos ( $signerInfo, ':' ) !== false) {
	$signerParts = explode ( ':', $signerInfo );
	$signerID = 0;
	$signerNameFirst = $signerParts [1];
	$signerNameLast = $signerParts [2];
	$sigEmail = (isset ( $signerParts [3] )) ? $signerParts [3] : '';
	$sigPhone = (isset ( $signerParts [4] )) ? formatPhone ( $signerParts [4] ) : '';
} else {
	$signerID = $signerInfo;
}

// Other constants
$isDarkDrop = false;

// Get the invoices marked as "delivered", which is code 2 for this stored procedure
$updateCode = 2;
$invXML = '';
$result = false;
$sqlFailed = true;
$sigSaved = false;
$sqlAttemptCount = 1;
$sql = '';
while ( $sqlFailed ) {
	$sqlFailed = false;
	try {
		$dbh = new PDO ( 'spdb', '', '' );
		$dbh->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		
		// Prep for the XML version of invoice list for the stored procedure
		$signtimestamp = date ( 'Y-m-d H:i', $signTS );
		$invXML = "<ROOT>\n" . '<Rec rID="' . $saleID . '" dtDelTime="' . $signtimestamp . '"/>' . "\n" . "</ROOT>";
		
		// Check if this is a repeat call to deliverycomplete.php
		$repeatCall = false;
		$sql = "uspDARTCheckDeliveryDate '" . $invXML . "'";
		$stmt = $dbh->query ( $sql );
		foreach ( $stmt->fetchAll ( PDO::FETCH_BOTH ) as $row ) {
			if ($row ['dtDartDelivered'] != '') {
				$repeatCall = true;
			}
		}
		$stmt->closeCursor ();
		if ($repeatCall) {
			dartLogging ( $currentScript, "  Repeat Call : " . $_SERVER ['REMOTE_ADDR'], $codeStr );
			$dbh = null;
			$returnVals ['result'] = false;
			$returnVals ['errorMsg'] = "$saleID already marked delivered in the system.<br/>If you need to update it, you have to 'Clear DART' first...";
			echo json_encode ( $returnVals );
			exit ();
		}
		
		// Ignore printed invoice signers
		if ($signerID != PRINTED_INVOICE_ID && $isDarkDrop == false && $sigSaved == false) {
			// SIGNATURE IMAGE
			$filedir = DART_SIG_DIR . $locationID;
			if (! is_dir ( $filedir )) {
				if (! mkdir ( $filedir )) {
					$errMsg = "Could not create folder for locationID = " . $locationID;
					dartLogging ( $currentScript, "    Could not create folder for locationID = " . $locationID, $codeStr );
					SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
					$returnVals ['result'] = false;
					$returnVals ['errorMsg'] = 'Could not create folder for locationID = ' . $locationID;
					echo json_encode ( $returnVals );
					exit ();
				}
			}
			// Convert the image to 24-bit and save
			$file = $filedir . '/' . $saleID . ".png";
			$sigSRC = imagecreatefromstring ( base64_decode ( explode ( ",", $sigData ) [1] ) );
			// Write it out
			if (! imagepng ( $sigSRC, $file )) {
				dartLogging ( $currentScript, "    Could not save png image", $codeStr );
				$returnVals ['result'] = false;
				$returnVals ['errorMsg'] = 'Could not create folder for locationID = ' . $locationID;
				echo json_encode ( $returnVals );
				exit ();
			}
			$sigSaved = true;
		}
		
		// First check to see if there is a signer
		if ($signerID == 0) {
			// Add the signer
			$sql = "uspDARTAddSigner 0, $locationID, '" . substr ( preg_replace ( '/\'+/', '\'\'', trim ( $signerNameFirst ) ), 0, 75 ) . "', ";
			$sql .= "'" . substr ( preg_replace ( '/\'+/', '\'\'', trim ( $signerNameLast ) ), 0, 75 ) . "', ";
			// $sigEmail = '';
			$sql .= ($sigEmail == '') ? "''" : "'" . $sigEmail . "'";
			$sql .= ", 1, ";
			// $sigPhone = '';
			$sql .= ($sigPhone == '') ? "''" : "'" . $sigPhone . "'";
			dartLogging ( $currentScript, "    uspDARTAddSigner sql=" . $sql, $codeStr );
			$stmt = $dbh->query ( $sql );
			$result = $stmt->fetch ( PDO::FETCH_ASSOC );
			$signerID = $result ['iUserID'];
			dartLogging ( $currentScript, "    uspDARTAddSigner iUserID=" . $signerID, $codeStr );
			$stmt->closeCursor ();
		}
		
		// Get the last update time according to the database
		$invTimesDB = array ();
		$sql = "uspDARTCheckLastUpdate '" . $invXML . "'";
		$stmt = $dbh->query ( $sql );
		foreach ( $stmt->fetchAll ( PDO::FETCH_BOTH ) as $row ) {
			$lastUpdate = preg_replace ( '/(.*):\d{2}\.\d{3}$/', '$1', $row ['dtDartLastUpdated'] );
			$invTimesDB [$row ['iSaleID']] = $lastUpdate;
		}
		$stmt->closeCursor ();
		
		// Now mark the invoice as "delivered"
		$sql = "uspDARTDelivered $updateCode, $signerID, '" . $invXML . "'";
		$resultDelivered = $dbh->exec ( $sql );
		
		$dbh = null;
	} catch ( PDOException $e ) {
		$errMsg = "SQL = $sql\n";
		$eMessage = $e->getMessage ();
		$errMsg .= $e->getFile () . ' (' . $e->getLine () . ')' . " sqlAttemptCount=$sqlAttemptCount : " . $eMessage;
		$errMsg .= "\ninvXML = " . $invXML;
		SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
		if (preg_match ( '/Timeout expired/', $eMessage ) || preg_match ( '/SQL Server does not exist or access denied/', $eMessage ) || preg_match ( '/deadlock victim/', $eMessage )) {
			if ($sqlAttemptCount < DART_SQL_TIMEOUT_MAX_TRIES) {
				$sqlAttemptCount ++;
				$sqlFailed = true;
				sleep ( DART_SQL_TIMEOUT_SLEEP );
			} else {
				$returnVals ['result'] = false;
				$returnVals ['errorMsg'] = 'Database timeout, see ' . DART_ERROR_LOG . ' log';
				echo json_encode ( $returnVals );
				exit ();
			}
		} else {
			dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
			$returnVals ['result'] = false;
			$returnVals ['errorMsg'] = 'Database error, see ' . DART_ERROR_LOG . ' log';
			echo json_encode ( $returnVals );
			exit ();
		}
	}
}

if ($resultDelivered === false) {
	$errMsg = "$currentScript : uspDARTDelivered $updateCode, $signerID, $invXML returned FALSE";
	SP_ErrorLogging ( $errMsg, true, DART_ERROR_LOG );
	dartLogging ( $currentScript, "    Database error, see " . DART_ERROR_LOG, $codeStr );
	$returnVals ['result'] = false;
	$returnVals ['errorMsg'] = 'Database error, see ' . DART_ERROR_LOG . ' log';
	echo json_encode ( $returnVals );
	exit ();
}

// Call sendinvoices.php with the invoice list to generate the PDFs and send them out for non-PRINTED INVOICE
if ($signerID != PRINTED_INVOICE_ID) {
	$invoiceStr = " " . $saleID;
	if (phpversion () == "5.6.20") {
		pclose ( popen ( "start /B C:\PROGRA~2\PHP\V56~1.20\php.exe sendinvoice.php$invoiceStr", "r" ) );
	} else {
		pclose ( popen ( "start /B php sendinvoice.php$invoiceStr", "r" ) );
	}
}

echo json_encode ( $returnVals );
dartLogging ( $currentScript, "  Success : " . $_SERVER ['REMOTE_ADDR'], $codeStr );
exit ();
?>