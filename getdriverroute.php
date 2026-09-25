<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);
$sendObj->webservice = $currentScript;

// Since we can have multiple connections writing to the log file, we'll add a random code to log file entries.
$codeStr = generateRandomCode(6);

// Log the data
$postData = (isset($_POST)) ? serialize($_POST) : 'none';
dartLogging($currentScript, "postdata=" . $postData, $codeStr);

// User ID
$userid = filter_input(INPUT_POST, 'userid', FILTER_SANITIZE_NUMBER_INT);
if ($userid == FALSE || is_null($userid)) {
	sendError(400, ERROR_CODES::ERROR_INVALID_DATA, 'No userid supplied', 'No userid supplied in POST request');
	dartLogging($sendObj->webservice, $_SERVER['REMOTE_ADDR'] . " : " . $_SERVER['HTTP_USER_AGENT'] . ' : ' . json_encode($sendObj), $codeStr);
	exit();
}

$sqlFailed = true;
$sqlAttemptCount = 1;
$sql = '';
while ($sqlFailed) {
	$sqlFailed = false;
	try {
		$dbh = new PDO('spdb', '', '');
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		// Get the driver route
		if ($userid == DEBUG_USERID) {
			// Get a sessionID
			$sql = "uspDARTInvoicesAssignDebug";
			$stmt = $dbh->query($sql);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$dartSessionID = $result['iDartSessionID'];
			$stmt->closeCursor();
			// Determine the Sync state
			$sf = fopen("driverstate.txt", "r");
			$state = fread($sf, 1);
			fclose($sf);
			// Get the route data
			$debugTable = ($state == "0") ? "tblDartDataDriverRoute" : "tblDartDataDriverRouteRsync";
			$sql = "select * from $debugTable order by iLocationDestinationID";
			$stmt = $dbh->query($sql);
			$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
			// Get the signers
			$sql = "select * from tblDartDataSigners";
			$stmt = $dbh->query($sql);
			$signerInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
		} else {
			// Complete the assignment of invoices to the driver and get the sessionid
			$sql = "uspDARTInvoicesAssign $userid";
			$assignResult = $dbh->exec($sql);
			// Get the invoices assigned to the driver
			$sql = "uspDARTGetDriverRoute $userid";
			$stmt = $dbh->query($sql);
			$routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
			// Get the signers
			$sql = "uspDARTGetDriverRouteSigners $userid";
			$stmt = $dbh->query($sql);
			$signerInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
			$stmt->closeCursor();
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

// Parse the route data to generate the arrays
// mb_convert_encoding ( xxx, "UTF-8", "Windows-1252" )
$location = array();
$locationList = array();
$currentLocation = -1;
foreach ($routeInfo as $entry) {
	$locID = $entry['iLocationDestinationID'];
	$lastUpdate = preg_replace('/(.*)\.\d{3}$/', '$1', $entry['dtDartLastUpdated']);
	if (! in_array($locID, $locationList)) {
		$locationList[] = $locID;
		// It is a new location to enter into the array
		// mb_convert_encoding ( $result['tSeasonal'], "UTF-8", "Windows-1252" )
		$location[$locID] = array(
			'id' => $locID,
			'sort' => 1,
			'lastupdate' => $lastUpdate,
			'name' => mb_convert_encoding($entry['sDescription'], "UTF-8", "Windows-1252"),
			'street' => mb_convert_encoding($entry['sAddress1'], "UTF-8", "Windows-1252"),
			'city' => mb_convert_encoding($entry['sCity'], "UTF-8", "Windows-1252"),
			'state' => mb_convert_encoding($entry['sState'], "UTF-8", "Windows-1252"),
			'zip' => mb_convert_encoding($entry['sPostalCode'], "UTF-8", "Windows-1252"),
			'gpslat' => trim($entry['sGpsLat']),
			'gpslon' => trim($entry['sGpsLon']),
			'phone' => mb_convert_encoding($entry['sPhone'], "UTF-8", "Windows-1252"),
			'deliverytime' => $entry['sTime'],
			'requirespaper' => $entry['iInvoiceException'],
			'allowdarkdrop' => ($entry['iNDS'] == 0) ? true : false,
			'locnotes' => mb_convert_encoding($entry['txtLocationNotes'], "UTF-8", "Windows-1252"),
			'salesname' => mb_convert_encoding($entry['txtSalesPerson'], "UTF-8", "Windows-1252"),
			'salesemail' => mb_convert_encoding($entry['txtEmail'], "UTF-8", "Windows-1252"),
			'salesphone' => formatPhone(mb_convert_encoding($entry['txtCellPhone'], "UTF-8", "Windows-1252")),
			'acctnote' => mb_convert_encoding($entry['sAccountingNote'], "UTF-8", "Windows-1252"),
			'terms' => mb_convert_encoding($entry['sTerms'], "UTF-8", "Windows-1252"),
			'invoices' => array(),
			'signers' => array()
		);
		// Add the invoice and set the update time
		$location[$locID]['invoices'][] = array(
			'lastupdate' => $lastUpdate,
			'number' => intval($entry['iSaleID']),
			'currentstate' => (is_null($entry['iDartStatusID'])) ? 0 : intval($entry['iDartStatusID']),
			'invnotes' => mb_convert_encoding($entry['txtInvoiceNotes'], "UTF-8", "Windows-1252")
		);
	} else {
		// We have already seen this location, so add the invoice and change the lastupdate time if needed
		$location[$locID]['invoices'][] = array(
			'lastupdate' => $lastUpdate,
			'number' => intval($entry['iSaleID']),
			'currentstate' => (is_null($entry['iDartStatusID'])) ? 0 : intval($entry['iDartStatusID']),
			'invnotes' => mb_convert_encoding($entry['txtInvoiceNotes'], "UTF-8", "Windows-1252")
		);
		if (strtotime($lastUpdate) > strtotime((string)$location[$locID]['lastupdate']))
			$location[$locID]['lastupdate'] = $lastUpdate;
	}
}

// Add the signer info for each location
foreach ($signerInfo as $entry) {
	$location[$entry['iLocationDestinationID']]['signers'][] = array(
		'userID' => intval($entry['iUserID']),
		'fname' => mb_convert_encoding($entry['txtFirstName'], "UTF-8", "Windows-1252"),
		'lname' => mb_convert_encoding($entry['txtLastName'], "UTF-8", "Windows-1252"),
		'cell' => formatPhone($entry['txtCellPhone']),
		'email' => $entry['txtEmail'] ?? ''
	);
}

$sendObj->data->locationList = array();
foreach ($locationList as $locID) {
	$requiresPaper = ($location[$locID]['requirespaper'] == -1) ? true : false;
	$signersList = array();
	if ($requiresPaper == false) {
		foreach ($location[$locID]['signers'] as $entry) {
			$signersList[] = array(
				'id' => intval($entry['userID']),
				'cell' => formatPhone($entry['cell']),
				'email' => $entry['email'] ?? '',
				'fname' => mb_convert_encoding($entry['fname'], "UTF-8", "Windows-1252"),
				'lname' => mb_convert_encoding($entry['lname'], "UTF-8", "Windows-1252")
			);
		}
	}
	$sendObj->data->locationList[] = array(
		'id' => intval($locID),
		'sort' => intval($location[$locID]['sort']),
		'lastupdate' => $location[$locID]['lastupdate'],
		'name' => $location[$locID]['name'],
		'street' => $location[$locID]['street'],
		'city' => $location[$locID]['city'],
		'state' => $location[$locID]['state'],
		'zip' => $location[$locID]['zip'],
		'gpslat' => floatval($location[$locID]['gpslat']),
		'gpslon' => floatval($location[$locID]['gpslon']),
		'phone' => formatPhone($location[$locID]['phone']),
		'deliverytime' => $location[$locID]['deliverytime'],
		'notes' => $location[$locID]['locnotes'],
		'salesname' => $location[$locID]['salesname'],
		'salesemail' => $location[$locID]['salesemail'],
		'salesphone' => formatPhone($location[$locID]['salesphone']),
		'accountingnote' => $location[$locID]['acctnote'],
		'accountingterms' => $location[$locID]['terms'],
		'requirespaper' => $requiresPaper,
		'allowdarkdrop' => $location[$locID]['allowdarkdrop'],
		'invoices' => $location[$locID]['invoices'],
		'signers' => $signersList
	);
}
sendResult();
dartLogging($currentScript, "  Success", $codeStr);
