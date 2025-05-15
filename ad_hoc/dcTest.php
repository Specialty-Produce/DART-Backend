<?php
include_once 'global_CDC.php';

$rogerJSON = '{"iSaleID":7343560,"dtDate":"2024-09-12","details":[{"iDSBID":9,"iSaleDetailID":124080624,"sNote":"","fQty":2,"fQtyN":2,"dtDateDetail":"2024-09-12"},{"iDSBID":11,"iSaleDetailID":124080625,"sNote":"Good Stuff","fQty":0,"fQtyN":0,"dtDateDetail":"2024-09-12"}]}';
$rjd = json_decode($rogerJSON);
// echo "<h3>Roger JD</h3><pre>" . print_r($rjd, true) . "</pre><br/><hr/><br/>";

// $appJSON = '{"userid":"196869","new_invoice_ship_today_list":[],"dartsessionid":"304011","deliveryjson":{"delivery":{"signerdeletion":[],"dark":"false","customernote":"","locationid":"2215","signerinfo":{"fname":"","phone":"","email":"","lname":""},"codinfo":{"checknum":"","checkamt":"","creditcardamt":"","cashamt":""},"signerid":"217375"},"invoice_list":[{"dsbid":"0","dsbNote":"","saleid":"7394777","cancelledbycustomer":"false","signatureimage":"xxx","lastupdatetime":"2024-10-15 08:32","greendiscountchanged":"true","greendiscountfinal":"-24.57","salestaxfinal":"0.00","signtimestamp":"2024-10-15 08:41","invoice_item_list":[{"dsbid":"5","dsbNote":"testbrand","finalunitprice":"4.5","dsbReplaceQtyToday":"1","edited":"true","dsbReplaceShipDateToday":"2024-10-15","lineid":"124655132","dsbReplaceQtyTomorrow":"2","dsbReplaceUnitTomorrow":"25","dsbReplaceShipDateTomorrow":"2024-10-16","finalunitid":"339","finalqship":"0","dsbReplaceUnitToday":"25","editreason":"41"},{"dsbid":"0","dsbNote":"","finalunitprice":"9","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655133","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"608","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"48.95","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655130","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"25","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"4.25","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655131","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"187","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"0.05","dsbReplaceQtyToday":"0","edited":"true","dsbReplaceShipDateToday":"","lineid":"124655139","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"184","finalqship":"0","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"-24.73","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655138","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"664","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"13.25","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655134","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"140","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"66.5","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655137","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"199","finalqship":"2","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"15.8","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655135","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"322","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"97.6","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655136","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"15","finalqship":"2","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"99.95","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655128","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"509","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"38.15","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655129","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"109","finalqship":"1","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"56.45","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124655127","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","finalunitid":"109","finalqship":"1","dsbReplaceUnitToday":"","editreason":""}],"status":"DELIVERED","salestaxchanged":"false"}]},"new_invoice_ship_tomorrow_list":[]}';
$appJSON = '{"userid":"196869","new_invoice_ship_today_list":[],"dartsessionid":"304232","deliveryjson":{"delivery":{"signerdeletion":[],"dark":"false","customernote":"","locationid":"2215","signerinfo":{"fname":"","phone":"","email":"","lname":""},"codinfo":{"checknum":"","checkamt":"","creditcardamt":"","cashamt":""},"signerid":"217375"},"invoice_list":[{"dsbid":"0","dsbNote":"","saleid":"7399696","cancelledbycustomer":"false","signatureimage":"xxx","lastupdatetime":"2024-10-18 09:06","greendiscountchanged":"true","greendiscountfinal":"-11.42","salestaxfinal":"0.00","signtimestamp":"2024-10-18 10:04","invoice_item_list":[{"dsbid":"0","dsbNote":"","finalunitprice":"25.4","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709051","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"514","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"5.35","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709052","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"735","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"1.9","dsbReplaceQtyToday":"0","edited":"true","dsbReplaceShipDateToday":"","lineid":"124709050","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"187","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":"37"},{"dsbid":"7","dsbNote":"","finalunitprice":"1.25","dsbReplaceQtyToday":"0","edited":"true","dsbReplaceShipDateToday":"","lineid":"124709047","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"299","finalqship":"0","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":"37"},{"dsbid":"0","dsbNote":"","finalunitprice":"36.5","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709046","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"138","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"0.1","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709058","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"184","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"16.25","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709049","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"14","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"-18.95","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709057","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"664","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"14.2","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709054","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"335","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"3","dsbNote":"poor","finalunitprice":"122.8","dsbReplaceQtyToday":"1","edited":"true","dsbReplaceShipDateToday":"2024-10-18","lineid":"124709056","dsbReplaceQtyTomorrow":"2","dsbReplaceUnitTomorrow":"196","dsbReplaceShipDateTomorrow":"2024-10-19","dsbReplacePriceTomorrow":"12.30","finalunitid":"15","finalqship":"1","dsbReplacePriceToday":"122.80","dsbReplaceUnitToday":"15","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"10.5","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709053","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"535","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"38.95","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709048","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"2","finalqship":"1","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""},{"dsbid":"0","dsbNote":"","finalunitprice":"6.7","dsbReplaceQtyToday":"0","edited":"false","dsbReplaceShipDateToday":"","lineid":"124709055","dsbReplaceQtyTomorrow":"0","dsbReplaceUnitTomorrow":"","dsbReplaceShipDateTomorrow":"","dsbReplacePriceTomorrow":"","finalunitid":"884","finalqship":"2","dsbReplacePriceToday":"","dsbReplaceUnitToday":"","editreason":""}],"status":"DELIVERED","salestaxchanged":"false"}]},"new_invoice_ship_tomorrow_list":[]}';
// $appJSON = '{"userid":"169699","new_invoice_ship_today_list":[],"dartsessionid":"304195","deliveryjson":{"delivery":{"signerdeletion":[],"dark":"false","customernote":"","locationid":"564","signerinfo":{"fname":"","phone":"","email":"","lname":""},"codinfo":{"checknum":"","checkamt":"","creditcardamt":"","cashamt":""},"signerid":"214750"},"invoice_list":[{"dsbid":"0","dsbNote":"","saleid":"7398760","cancelledbycustomer":"false","signatureimage":"xxx","lastupdatetime":"2024-10-18 05:08","greendiscountchanged":"false","greendiscountfinal":"-1.43","salestaxfinal":"0.00","signtimestamp":"2024-10-18 07:12","invoice_item_list":[{"lineid":"124695834","finalunitid":"133","finalunitprice":"31.25","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124703463","finalunitid":"664","finalunitprice":"-1.43","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124695835","finalunitid":"506","finalunitprice":"4.1","finalqship":"4","edited":"false","editreason":"","dsbid":"0","dsbNote":""}],"status":"DELIVERED","salestaxchanged":"false"},{"dsbid":"0","dsbNote":"","saleid":"7398971","cancelledbycustomer":"false","signatureimage":"xxx","lastupdatetime":"2024-10-18 05:08","greendiscountchanged":"true","greendiscountfinal":"-2.99","salestaxfinal":"0.00","signtimestamp":"2024-10-18 07:12","invoice_item_list":[{"lineid":"124697855","finalunitid":"108","finalunitprice":"15.9","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124697856","finalunitid":"145","finalunitprice":"16.95","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124703461","finalunitid":"664","finalunitprice":"-2.98","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124697857","finalunitid":"487","finalunitprice":"26.25","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""},{"lineid":"124697858","finalunitid":"679","finalunitprice":"40.3","finalqship":"1","edited":"false","editreason":"","dsbid":"0","dsbNote":""}],"status":"DELIVERED","salestaxchanged":"false"}]},"new_invoice_ship_tomorrow_list":[]}';
$jd = json_decode($appJSON);
if ($jd == FALSE || is_null($jd)) {
    echo "Error: JSON decode failed";
    exit;
}
echo "JSON Data : <br/><pre>" . print_r($jd, true) . "</pre><br/><hr/><br/>";

$userID = $jd->userid;

$getAllLines = false;
foreach ($jd->deliveryjson->invoice_list as $invoice) {
    echo "<h3>" . $invoice->saleid . "</h3>";
    $dsbEntry = new stdClass();
    $dsbEntry->iSaleID = intval($invoice->saleid);
    $dsbEntry->dtDate = date('Y-m-d');
    $dsbEntry->details = array();
    if (isset($invoice->dsbid) && intval($invoice->dsbid) > 0) {
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
        echo "Would execute uspDartMenuProcess for <b>INVOICE</b> : <br/><pre>" . print_r($dsbEntry, true) . "</pre><hr/>";
    } else {
        // Capture diff between old and new DART using dsbReplaceQtyToday in first line item ???
    }

    if (count($dsbEntry->details) > 0) {
        echo "DSBID > 0 for this invoice. Continuing...<br/><hr/>";
        continue;
    }
    $saleDetailXML = '';
    foreach ($invoice->invoice_item_list as $line) {
        echo $line->edited . " : " . $line->lineid . " : " . $line->dsbid . "<br/>";
        if (isset($line->dsbid)) {
            // DSBID
            if (intval($line->dsbid) > 0) {
                echo "A...<br/>";
                if (isset($line->dsbReplaceQtyToday)) {
                    /* NEW DART MENU PROCESS */
                    $qtyToday = intval($line->dsbReplaceQtyToday);
                    $qtyTomorrow = intval($line->dsbReplaceQtyTomorrow);
                    if ($qtyToday == 0 && $qtyTomorrow == 0) {
                        // Delivered quantity changed, but no replacement requested
                        $dsbDetail = new stdClass();
                        $dsbDetail->iDSBID = intval($line->dsbid);
                        $dsbDetail->iSaleDetailID = intval($line->lineid);
                        $dsbDetail->sNote = $line->dsbNote;
                        $dsbDetail->fQty = $line->finalqship;
                        $dsbDetail->iUnitID = $line->finalunitid;
                        $dsbDetail->mUnitPrice = $line->finalunitprice;
                        $dsbDetail->fQtyN = 0.00;
                        $dsbDetail->iUnitN = 0;
                        $dsbDetail->mPriceN = 0.00;
                        $dsbDetail->dtDateDetail = date('Y-m-d');
                        $dsbEntry->details[] = $dsbDetail;
                    } else {
                        // We'll have either a today, tomorrow or both replacements
                        if (intval($line->dsbReplaceQtyToday) > 0) {
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
                        if (intval($line->dsbReplaceQtyTomorrow) > 0) {
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
                    error_log("$currentScript : $codeStr : LINE ITEM DSBID > 0 : dsbData JSON String =" . json_encode($dsbData));
                    dartLogging($currentScript, "    LINE ITEM DSBID > 0 : dsbData JSON String =" . json_encode($dsbData), $codeStr);
                    error_log("$currentScript : $codeStr : LINE ITEM JSON = " . json_encode($line));
                    $sql = "uspDartMenuProcess ?";
                    $stmt = $dbh->prepare($sql);
                    $stmt->execute(array(json_encode($dsbData)));
                }
            } elseif ($line->edited == "true" || $getAllLines == true) {
                echo "B...<br/>";
                $saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . $line->editreason . '"/>' . "\n";
            }
        } elseif ($line->edited == "true" || $getAllLines == true) {
            // No DSBID
            $saleDetailXML .= '<Rec rID="' . $line->lineid . '" iUnitID="' . $line->finalunitid . '" fQty="' . $line->finalqship . '" mUnitPrice="' . $line->finalunitprice . '" iStatus= "' . $line->editreason . '"/>' . "\n";
        }
    }
    if (count($dsbEntry->details) > 0) {
        echo "Would execute uspDartMenuProcessJSON for <b>LINE ITEMS</b> : <br/><pre>" . print_r($dsbEntry, true) . "</pre>";
        echo "dsbEntry JSON = " . json_encode($dsbEntry) . "<br/><hr/>";
    }
    if ($saleDetailXML != '') {
        $saleDetailXML = "<ROOT>\n" . $saleDetailXML . "</ROOT>";
        echo "Would execute:<br/><pre>uspDARTDeliveryCompleteUpdates '" . htmlspecialchars($saleDetailXML) . "'</pre><hr/>";
    }
}
