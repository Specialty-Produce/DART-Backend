<?php
include_once 'global_CDC.php';
global $invoiceList, $priceList;

foreach ($invoiceList as $inv) {
    if (!isset($inv['saleID'])) {
        SP_ErrorLogging('invoiceXML : saleID NOT set : ' . print_r($inv, true), false, DART_ERROR_LOG);
    }
    $resultStr .= '<invoice saleid="' . $inv['saleID'] . '" locid="' . $inv['locID'] . '" lastupdate="' . $inv['lastupdate'] . '" date="' . $inv['date'] . '">' . "\n";
    $resultStr .= "<notes>" . $inv['notes'] . "</notes>\n";
    $resultStr .= "<ponumber>" . $inv['po'] . "</ponumber>\n";
    $resultStr .= "<terms>" . $inv['terms'] . "</terms>\n";
    if (!isset($inv['packerlocation'])) {
        SP_ErrorLogging('invoiceXML : packerlocation NOT set : ' . $inv['saleID'], false, DART_ERROR_LOG);
    }
    $resultStr .= "<packerlocation>" . $inv['packerlocation'] . "</packerlocation>\n";
    $resultStr .= "<invoice_item_list>\n";
    $sortCount = 1;
    foreach ($inv['items'] as $item) {
        $resultStr .= '<item lineid="' . $item['lineid'] . '" sort="' . $sortCount . '">' . "\n";
        $sortCount++;
        $resultStr .= "<prodid>" . $item['prodid'] . "</prodid>\n";
        $resultStr .= "<proddesc>" . $item['proddesc'] . "</proddesc>\n";
        $resultStr .= "<unitid>" . $item['unitid'] . "</unitid>\n";
        $resultStr .= "<qorder>" . sprintf('%0.2f', $item['qorder']) . "</qorder>\n";
        $resultStr .= "<qship>" . sprintf('%0.2f', $item['qship']) . "</qship>\n";
        $resultStr .= "<status>" . $item['status'] . "</status>\n";
        $resultStr .= "<itemspec>" . $item['itemspec'] . "</itemspec>\n";
        $resultStr .= "<greendiscount>" . $item['greendiscount'] . "</greendiscount>\n";
        $resultStr .= "<pricing_unit_list>\n";
        foreach ($priceList[$item['lineid']] as $unitID => $entry) {
            $resultStr .= '<unit id="' . $unitID . '">' . "\n";
            $resultStr .= "<desc>" . $entry['desc'] . "</desc>\n";
            $resultStr .= "<cost>" . sprintf('%0.2f', $entry['cost']) . "</cost>\n";
            $resultStr .= '<crvinfo crvid="' . $entry['crvID'] . '">' . sprintf('%0.2f', $entry['crvPrice']) . '</crvinfo>' . "\n";
            $resultStr .= '</unit>' . "\n";
        }
        $resultStr .= "</pricing_unit_list>\n";
        $resultStr .= "</item>\n";
    }
    $resultStr .= "</invoice_item_list>\n";
    $resultStr .= "</invoice>\n";
}
