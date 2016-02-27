<?php
include_once 'global_CDC.php';
include 'dart_init.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// On various errors and failures, we'll use the status BAD update XML
$badXML = <<< EOT
<?xml version="1.0"?>
<accidentreport status="failed" code="0" retry="true" errmsg="XXX">
</accidentreport>
EOT;

$checklistXML = <<< EOT
<?xml version="1.0"?>
<accidentreport status="success">
<checklisthtml>
<![CDATA[<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
body {
	font-family: Verdana, Arial, Helvetica, Sans-Serif;
	font-size: 1.1em;
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
function validate_form() {
	var errStr = '';
	// Accident info
	if (chkNumeric(document.accform.numvehicles.value) == false) errStr = errStr + "\\u2022 Number of vehicles.\\n";
	if (document.accform.location.value == '') errStr = errStr + "\\u2022 Location of accident.\\n";
	if (getRadioValue(document.accform.elements['privateprop']) == '') errStr = errStr + "\\u2022 On Private Property.\\n";
	if (document.accform.time.value == '') errStr = errStr + "\\u2022 Time of accident.\\n";
	if (getRadioValue(document.accform.elements['time12h']) == '') errStr = errStr + "\\u2022 AM of PM.\\n";
	if (getRadioValue(document.accform.elements['activity']) == '') errStr = errStr + "\\u2022 What were you doing.\\n";;
	// Other driver info
	if (document.accform.drivername.value == '') errStr = errStr + "\\u2022 Driver name.\\n";
	if (document.accform.driverlicense.value == '') errStr = errStr + "\\u2022 Driver license.\\n";
	if (document.accform.driveraddress.value == '') errStr = errStr + "\\u2022 Driver address.\\n";
	if (document.accform.drivercity.value == '') errStr = errStr + "\\u2022 Driver city.\\n";
	if (document.accform.driverstate.value == '') errStr = errStr + "\\u2022 Driver state.\\n";
	if (chkNumeric(document.accform.driverzip.value) == false) errStr = errStr + "\\u2022 Driver zip.\\n";
	if (chkNumeric(document.accform.phoneareawork.value) == false) errStr = errStr + "\\u2022 Driver work phone area code.\\n";
	if (chkNumeric(document.accform.phonework.value) == false) errStr = errStr + "\\u2022 Driver work phone.\\n";
	if (chkNumeric(document.accform.phoneareahome.value) == false) errStr = errStr + "\\u2022 Driver home phone area code.\\n";
	if (chkNumeric(document.accform.phonehome.value) == false) errStr = errStr + "\\u2022 Driver home phone.\\n";
	// Vehicle info
	if (document.accform.vehiclemake.value == '') errStr = errStr + "\\u2022 Vehicle year and make.\\n";
	if (document.accform.vehicleplate.value == '') errStr = errStr + "\\u2022 Vehicle plate or VIN.\\n";
	// Insurance info
	if (document.accform.insuranceco.value == '') errStr = errStr + "\\u2022 Insurance company name.\\n";
	if (document.accform.policynum.value == '') errStr = errStr + "\\u2022 Insurance policy number.\\n";
	if (errStr == '') return 'success';
	else return errStr;
}
function encode_form() {
	// Accident info
	var evStr = "encData = {'numvehicles':'" + document.accform.numvehicles.value + "',";
	evStr += "'location':'" + document.accform.location.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'privateprop':'" + getRadioValue(document.accform.elements['privateprop']) + "',";
	evStr += "'time':'" + document.accform.time.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'time12h':'" + getRadioValue(document.accform.elements['time12h']) + "',";
	evStr += "'activity':'" + getRadioValue(document.accform.elements['activity']) + "',";
	evStr += "'drivername':'" + document.accform.drivername.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'driverlicense':'" + document.accform.driverlicense.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'driveraddress':'" + document.accform.driveraddress.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'drivercity':'" + document.accform.drivercity.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'driverstate':'" + document.accform.driverstate.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'driverzip':'" + document.accform.driverzip.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'phoneareawork':'" + document.accform.phoneareawork.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'phonework':'" + document.accform.phonework.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'phoneareahome':'" + document.accform.phoneareahome.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'phonehome':'" + document.accform.phonehome.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'vehiclemake':'" + document.accform.vehiclemake.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'vehicleplate':'" + document.accform.vehicleplate.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'insuranceco':'" + document.accform.insuranceco.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'policynum':'" + document.accform.policynum.value.replace(/'/g, '\\\\\\'') + "',";
	evStr += "'comments':'" + encodeURIComponent(document.accform.comments.value).replace(/'/g, '\\\\\\'') + "'}";
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
//return the value of the radio button that is checked
//return an empty string if none are checked, or
//there are no radio buttons
function getRadioValue(radioObj) {
	if(!radioObj)
		return '';
	var radioLength = radioObj.length;
	if(radioLength == undefined)
		if(radioObj.checked)
			return radioObj.value;
		else
			return '';
	for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked) {
			return radioObj[i].value;
		}
	}
	return '';
}
</script>
</head>
<body>
<form action="" method="post" name="accform">
<ol>
<li>Number of Vehicles Involved? <input type="number" name="numvehicles" size="5"/></li>
<li>Location (City/County)? <input type="text" name="location" size="30"/></li>
<li>On Private Property? <input type="radio" name="privateprop" value="yes"/>Yes&nbsp;&nbsp;<input type="radio" name="privateprop" value="no"/>No</li>
<li>Time of Accident? <input type="text" name="time" size="10"/> <input type="radio" name="time12h" value="am"/>AM&nbsp;&nbsp;<input type="radio" name="time12h" value="pm"/>PM</li>
<li>I was...
			<table cellpadding="1" cellspacing="1" border="0">
				<tr>
					<td><input type="radio" name="activity" value="moving"/>Moving</td>
					<td><input type="radio" name="activity" value="parked"/>Parked</td>
					<td><input type="radio" name="activity" value="stoppedintraffic"/>Stopped in Traffic</td>
				</tr>
				<tr>
					<td><input type="radio" name="activity" value="pedestrian"/>Pedestrian</td>
					<td><input type="radio" name="activity" value="bicyclist"/>Bicyclist</td>
					<td><input type="radio" name="activity" value="other"/>Other (e.g. Rollaway)</td>
				</tr>
			</table>
<br/>
<b>Other Party's Information</b>
</li>
<li>Driver's Name (First, Middle, Last)? <input type="text" name="drivername" size="50"/></li>
<li>Driver License Number? <input type="text" name="driverlicense" size="20"/></li>
<li>Driver's Street Address? <input type="text" name="driveraddress" size="50"/></li>
<li>Driver's City? <input type="text" name="drivercity" size="30"/>&nbsp;&nbsp;State <input type="text" name="driverstate" size="4"/>&nbsp;&nbsp;Zip <input type="text" name="driverzip" size="6"/></li>
<li>Driver's Work Phone? (<input type="number" name="phoneareawork" size="4"/>) <input type="number" name="phonework" size="10"/>&nbsp;&nbsp;<span style="font-size: 0.8em;">(xxx) xxxxxxx</span></li>
<li>Driver's Home Phone? (<input type="number" name="phoneareahome" size="4"/>) <input type="number" name="phonehome" size="10"/></li>
<li>Vehicle (Year and Make)? <input type="text" name="vehiclemake" size="30"/></li>
<li>Vehicle License Plate of VIN? <input type="text" name="vehicleplate" size="30"/></li>
<li>Insurance Company Name? <input type="text" name="insuranceco" size="40"/></li>
<li>Policy Number? <input type="text" name="policynum" size="20"/><br/><br/></li>
<li>Comments:<br/>
<textarea name="comments" cols="60" rows="5"></textarea></li>
</ol>
</form>
</body>
</html>
]]>
</checklisthtml>
</accidentreport>
EOT;

echo $checklistXML;
exit();
?>