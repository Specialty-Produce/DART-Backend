<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename($_SERVER["SCRIPT_NAME"]);

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<invoices_invoice_list status="failed" code="0" retry="false" errmsg="XXX">
</invoices_invoice_list>
EOT;

// Get the POST data
$appJSON = $_POST['jsondata'];
dartLogging($currentScript, "jsondata=" . $appJSON);

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
$numSaleIDs = count($saleIDs);
if ($numSaleIDs == 0) {
    $badXML = preg_replace('/XXX/', $currentScript . ' : No saleids', $badXML);
    echo $badXML;
    exit();
}

try {
    $dbh = new PDO('spdb', '', '');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Get the driver route
    $sql = "";

    // Determine the Sync state
    $sf = fopen("driverstate.txt", "r");
    $state = fread($sf, 1);
    fclose($sf);
    // Prep for the IN clause of the SQL
    $saleStr = $saleIDs[0];
    foreach ($saleIDs as $id)
        $saleStr .= ",$id";
    // Get the route data
    $debugRouteTable = ($state == "0") ? "tblDartDataDriverRoute" : "tblDartDataDriverRouteRsync";
    $sql = "select * from $debugRouteTable where iSaleID in ($saleStr) order by iLocationDestinationID";
    $stmt = $dbh->query($sql);
    $routeInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
    $stmt->closeCursor();
    // Get the invoice data
    $debugInvoiceTable = ($state == "0") ? "tblDartDataInvoices" : "tblDartDataInvoicesRsync";
    $sql = "select * from $debugInvoiceTable where iSaleID in ($saleStr) order by iLocationDestinationID, iSaleID, sDescription, iProductID";
    $stmt = $dbh->query($sql);
    $invInfo = $stmt->fetchAll(PDO::FETCH_BOTH);
    $stmt->closeCursor();

    $dbh = null;
} catch (PDOException $e) {
    $errMsg = $e->getFile() . ' (' . $e->getLine() . ')' . $e->getMessage();
    $errMsg .= "\nSQL = $sql\n";
    SP_ErrorLogging($errMsg, true, DART_ERROR_LOG);
    $badXML = preg_replace('/XXX/', $currentScript . ' : Database error, see ' . DART_ERROR_LOG . ' log', $badXML);
    $badXML = preg_replace('/retry="false"/', 'retry="true"', $badXML);
    echo $badXML;
    exit();
}

// Parse the route data to generate the arrays for the different parts of the XML
$invoiceList = array();
foreach ($routeInfo as $entry) {
    if (in_array($entry['iSaleID'], $saleIDs)) {
        $lastUpdate = preg_replace('/(.*)\.\d{3}$/', '$1', $entry['dtDartLastUpdated']);
        $packerLocation = (is_null($entry['sPackerLocation'])) ? '' : mb_convert_encoding($entry['sPackerLocation'], "UTF-8", "Windows-1252");
        $invoiceList[$entry['iSaleID']] = array(
            'saleID' => $entry['iSaleID'],
            'locID' => $entry['iLocationDestinationID'],
            'date' => strftime("%m/%d/%Y"),
            'lastupdate' => $lastUpdate,
            'notes' => mb_convert_encoding($entry['txtInvoiceNotes'], "UTF-8", "Windows-1252"),
            'po' => mb_convert_encoding($entry['sPO'], "UTF-8", "Windows-1252"),
            'terms' => mb_convert_encoding($entry['sTerms'], "UTF-8", "Windows-1252"),
            'packerlocation' => $packerLocation
        );
        $invoiceList[$entry['iSaleID']]['items'] = array();
    }
}

// Parse the invoice data to generate the arrays for the different parts of the XML
$priceList = array();
foreach ($invInfo as $item) {
    // Add the line pricing info
    if (!isset($priceList[$item['iSaleDetailID']]))
        $priceList[$item['iSaleDetailID']] = array();
    $priceList[$item['iSaleDetailID']][$item['iUnitID']] = array(
        'desc' => mb_convert_encoding($item['UnitDescription'], "UTF-8", "Windows-1252"),
        'cost' => $item['mUnitPrice'],
        'crvID' => $item['iCRVProductID'],
        'crvPrice' => $item['mPrice']
    );
    // Only add to the invoice the actual unitID set items
    if ($item['iInvoiceDefault'] == 1)
        $invoiceList[$item['iSaleID']]['items'][] = array(
            'lineid' => $item['iSaleDetailID'],
            'prodid' => $item['iProductID'],
            'proddesc' => mb_convert_encoding($item['sDescription'], "UTF-8", "Windows-1252"),
            'unitid' => $item['iUnitID'],
            'qorder' => $item['fOrderQuantity'],
            'qship' => $item['fShipQuantity'],
            'status' => mb_convert_encoding($item['iShort'], "UTF-8", "Windows-1252"),
            'itemspec' => mb_convert_encoding($item['sItemNotes'], "UTF-8", "Windows-1252"),
            'greendiscount' => sprintf("%0.2f", 100.0 * ($item['fDiscountOnline'] + $item['fDiscountOnTime']))
        );
}

// Generate the XML
$resultStr = '<?xml version="1.0"?>' . "\n";
$resultStr .= '<invoices_invoice_list status="success">' . "\n";
include 'include/invoiceXMLTest.php';
$resultStr .= "</invoices_invoice_list>";
echo $resultStr;
exit();
