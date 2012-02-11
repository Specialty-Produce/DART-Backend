<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );
/*
$testXML = <<< EOT
<?xml version="1.0"?>
<startchecklist status="failed" code="0" retry="unknown" errmsg="Test error message.">
</startchecklist>
EOT;

echo $testXML;
exit();
*/
$checklistXML = <<< EOT
<?xml version="1.0"?>
<startchecklist status="success">
<checklisthtml>
<![CDATA[
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
body {
	font-family: Verdana, Arial, Helvetica, Sans-Serif;
	font-size: 0.9em;
}
table {
	margin: 0px;
}
th {
	text-align: left;
	padding-top: 10px;
}
.tdcb {
	padding: 0 5px 0 10px;
}
</style>
<script type="text/javascript">
var oldodometer = 1000;
function setoldodometer(odovalue) {
	oldodometer = odovalue;
	return 'success';
}
function validate_form() {
	var errStr = '';
	if (chkNumeric(document.clform.odometer.value) == false) errStr = errStr + "\\u2022 Please enter a valid odometer reading.\\n";
	if (chkNumeric(document.clform.odometer.value)) {
		var newodometer = document.clform.odometer.value;
		if (newodometer < oldodometer) errStr = errStr + "\\u2022 The odometer reading you entered is less than the one you entered on the Start Checklist (" + oldodometer + ").\\n";
		if ((newodometer-oldodometer) > 999) errStr = errStr + "\\u2022 The odometer reading you entered is 1000 miles or more than the one you entered on the Start Checklist (" + oldodometer + ").\\n";
	}
	if (document.clform.testschecked.checked != true) errStr = errStr + "\\u2022 You must check that you reviewed all the test items.\\n";
	if (errStr == '') return 'success';
	else return errStr;
}
function encode_form() {
    // Odometer
    var evStr = "encData = {'odometer':'" + document.clform.odometer.value + "',";

    // All the checked test items
    evStr += "'test_items':[";
    var testItems = document.clform.test_list;
    var notFirstItem = false;
	for (i = 0; i < testItems.length; i++)
		if (testItems[i].checked == true) {
			if (notFirstItem) evStr += ",";
			else notFirstItem = true;
			evStr += "'" + testItems[i].value + "'";
	}
	evStr += "],";

	// Test comments
	evStr += "'comments':'" + encodeURIComponent(document.clform.comments.value) + "'}";
        
    eval(evStr);
    var encStr = JSON.stringify(encData);
    return encStr;
}
//check for valid numeric string
function chkNumeric(strString) {
	var strValidChars = "0123456789";
	var strChar;
	var blnResult = true;

	if (strString.length == 0) return false;

	//test strString consists of valid characters listed above
	for (i = 0; i < strString.length && blnResult == true; i++) {
		strChar = strString.charAt(i);
		if (strValidChars.indexOf(strChar) == -1) {
			blnResult = false;
		}
	}
	return blnResult;
}
</script>
</head>
<body>
<form action="" method="post" name="clform">
1. Odometer Reading? <input type="text" name="odometer" size="9"/>
<br />
2. Check anything that <b>needs attention</b>.<br/>
<fieldset style="margin: 10px 10px 10px 20px; padding: 0 10px; display: inline-block;">
<div style="float: left;">
<table cellpadding="0" cellspacing="0" border="0">
<tr>
	<th colspan="2">Gauges</th>
</tr>
<tr>
	<td class="tdcb">Fuel</td>
	<td><input type="checkbox" name="test_list" value="gauges_fuel" /></td>
</tr>
<tr>
	<td class="tdcb">Temperature</td>
	<td><input type="checkbox" name="test_list" value="gauges_temperature" /></td>
</tr>
<tr>
	<td class="tdcb">Dashoard warning light</td>
	<td><input type="checkbox" name="test_list" value="gauges_dashwarninglight" /></td>
</tr>
<tr>
	<th colspan="2">Leaks (look underneath)</th>
</tr>
<tr>
	<td class="tdcb">Oil</td>
	<td><input type="checkbox" name="test_list" value="leaks_oil" /></td>
</tr>
<tr>
	<td class="tdcb">Other</td>
	<td><input type="checkbox" name="test_list" value="leaks_other" /></td>
</tr>

<tr>
	<th colspan="2">Lights</th>
</tr>
<tr>
	<td class="tdcb">Headlights</td>
	<td><input type="checkbox" name="test_list" value="lights_headlights" /></td>
</tr>
<tr>
	<td class="tdcb">Brake lights</td>
	<td><input type="checkbox" name="test_list" value="lights_brakelights" /></td>
</tr>
<tr>
	<td class="tdcb">Turn signals</td>
	<td><input type="checkbox" name="test_list" value="lights_turnsignals" /></td>
</tr>
<tr>
	<td class="tdcb">Hazard lights</td>
	<td><input type="checkbox" name="test_list" value="lights_hazardlights" /></td>
</tr>
<tr>
	<td class="tdcb">Box lights</td>
	<td><input type="checkbox" name="test_list" value="lights_boxlights" /></td>
</tr>
<tr>
	<th colspan="2">Tires</th>
</tr>
<tr>
	<td class="tdcb">Proper inflation</td>
	<td><input type="checkbox" name="test_list" value="tires_properinflation" /></td>
</tr>
<tr>
	<td class="tdcb">Adequate tread</td>
	<td><input type="checkbox" name="test_list" value="tires_adequatetread" /></td>
</tr>
</table>
</div>
<div style="float: left; margin-left: 20px;">
<table cellpadding="0" cellspacing="0" border="0">
<tr>
	<th colspan="2">Noises (unusual)</th>
</tr>
<tr>
	<td class="tdcb">Noises</td>
	<td><input type="checkbox" name="test_list" value="noises_noises" /></td>
</tr>
<tr>
	<th colspan="2">Safety Equipment</th>
</tr>
<tr>
	<td class="tdcb">Fire extinguisher</td>
	<td><input type="checkbox" name="test_list" value="safety_fireextinguisher" /></td>
</tr>
<tr>
	<td class="tdcb">Reflective triangles</td>
	<td><input type="checkbox" name="test_list" value="safety_reflectivetriangles" /></td>
</tr>
<tr>
	<td class="tdcb">Flares</td>
	<td><input type="checkbox" name="test_list" value="safety_flares" /></td>
</tr>
<tr>
	<td class="tdcb">Emergency contact info</td>
	<td><input type="checkbox" name="test_list" value="safety_emergencycontactinfo" /></td>
</tr>
<tr>
	<td class="tdcb">Cell phone/2-way radio</td>
	<td><input type="checkbox" name="test_list" value="safety_cellphone2-wayradio" /></td>
</tr>
<tr>
	<td class="tdcb">Seat belts</td>
	<td><input type="checkbox" name="test_list" value="safety_seatbelts" /></td>
</tr>
<tr>
	<th colspan="2">Miscellaneous</th>
</tr>
<tr>
	<td class="tdcb">Windshield</td>
	<td><input type="checkbox" name="test_list" value="misc_windshield" /></td>
</tr>
<tr>
	<td class="tdcb">Fans and defroster</td>
	<td><input type="checkbox" name="test_list" value="misc_fansanddefroster" /></td>
</tr>
<tr>
	<td class="tdcb">Brakes</td>
	<td><input type="checkbox" name="test_list" value="misc_brakes" /></td>
</tr>
<tr>
	<td class="tdcb">Parking brake</td>
	<td><input type="checkbox" name="test_list" value="misc_parkingbrake" /></td>
</tr>
<tr>
	<td class="tdcb">Mirrors</td>
	<td><input type="checkbox" name="test_list" value="misc_mirrors" /></td>
</tr>
<tr>
	<td class="tdcb">Horn</td>
	<td><input type="checkbox" name="test_list" value="misc_horn" /></td>
</tr>
<tr>
	<td class="tdcb">Exhaust system</td>
	<td><input type="checkbox" name="test_list" value="misc_exhaustsystem" /></td>
</tr>
</table>
</div>
<div style="clear: both; padding: 10px 0;"><input type="checkbox" name="testschecked" value="1" /> <b>I have looked over the above list and marked any problems.</b></div>
</fieldset>
<br/>
4. Be sure to <b>sweep out the truck box</b>.
<br/>
<br/>
3. Comments:<br/>
<textarea name="comments" cols="60" rows="5"></textarea>
</form>
</body>
</html>
]]>
</checklisthtml>
</startchecklist>
EOT;

echo $checklistXML;

exit();
?>