<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_APN_SP.php';
$currentScript = basename ( $_SERVER ["SCRIPT_NAME"] );

// APN constants
// $cert = array ();
$cert = 'include\Enterprise_PROD_aps_MAY2020.pem';
// $cert ['p'] = 'include\190412_APN_PROD.pem';
$host = array ();
$host ['d'] = 'gateway.sandbox.push.apple.com';
$host ['p'] = 'gateway.push.apple.com';
$port = 2195;

// Set some values
$errMsg = '';
$apnToken = '';
$contentAvailable = '';
$alertText = '';
$defSound = '';
$contentID = '';
$aptVal = '';
$iid = '';
$pushType = '';
$priority = '';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>AdHoc DART APN Send</title>
</head>
<body>
	<h3>AdHoc DART APN Send</h3>
<?php
$prodChecked = false;
if (isset ( $_POST ['apnSubmit'] )) {
	// apnToken
	$apnToken = filter_input ( INPUT_POST, 'apnToken', FILTER_SANITIZE_STRING );
	if ($apnToken === FALSE || is_null ( $apnToken )) {
		$errMsg .= "<br/>Invalid APN Token!";
	}
	$tokenType = filter_input ( INPUT_POST, 'tokenType', FILTER_SANITIZE_STRING );
	if ($tokenType === FALSE || is_null ( $tokenType )) {
		$errMsg .= "<br/>Invalid Token Type!";
	}
	$prodChecked = $_POST ['tokenType'] == 'p' ? true : false;
	// content available
	$contentAvailable = filter_input ( INPUT_POST, 'contentAvailable', FILTER_SANITIZE_STRING );
	if ($contentAvailable == FALSE || is_null ( $contentAvailable )) {
		$contentAvailable = "";
	}
	// alertText
	$alertText = filter_input ( INPUT_POST, 'alertText', FILTER_SANITIZE_STRING );
	if ($alertText === FALSE || is_null ( $alertText )) {
		$alertText = "";
	}
	// default sound
	$defSound = filter_input ( INPUT_POST, 'defSound', FILTER_SANITIZE_STRING );
	if ($defSound === FALSE || is_null ( $defSound )) {
		$defSound = "";
	}
	// content ID
	$contentID = filter_input ( INPUT_POST, 'contentID', FILTER_SANITIZE_STRING );
	if ($contentID === FALSE || is_null ( $contentID )) {
		$contentID = "";
	}
	// aptVal
	$aptVal = filter_input ( INPUT_POST, 'aptVal', FILTER_SANITIZE_STRING );
	if ($aptVal === FALSE || is_null ( $aptVal )) {
		$aptVal = '';
	}
	// invoice ID
	$iid = filter_input ( INPUT_POST, 'iID', FILTER_SANITIZE_STRING );
	if ($iid === FALSE || is_null ( $iid )) {
		$iid = '';
	}
	// push type
	$pushType = filter_input ( INPUT_POST, 'aptPushType', FILTER_SANITIZE_STRING );
	if ($pushType === FALSE || is_null ( $pushType )) {
		$pushType = '';
	}
	// priority
	$priority = filter_input ( INPUT_POST, 'aptPriority', FILTER_SANITIZE_STRING );
	if ($priority === FALSE || is_null ( $priority )) {
		$priority = '';
	}
	error_log ( "$currentScript : sending : " . print_r ( $_POST, true ) );
	
	// Initialize
	$apn = new APN_SP ( $cert, $host [$tokenType], 2195, 'include\Entrust_CA_2048.pem' );
	$apn->setPushPriority ( $pushType, $priority );
	list ( $initResult, $errMsg ) = $apn->initialize ();
	if ($initResult == false) {
		echo "<br/><br/>initialize() returned FALSE : error message = " . $errMsg . "<br/><br/>\n";
	} else {
		$apsComma = false;
		$payload = '{"aps":{';
		if (strlen ( $contentAvailable ) > 0) {
			$payload .= '"content-available":' . $contentAvailable;
			$apsComma = true;
		}
		if (strlen ( $alertText ) > 0) {
			if ($apsComma)
				$payload .= ',';
			$payload .= '"alert":"' . $alertText . '"';
			$apsComma = true;
		}
		if (strlen ( $defSound ) > 0) {
			if ($apsComma)
				$payload .= ',';
			$payload .= '"sound":"' . $defSound . '"';
		}
		$payload .= '}';
		
		// other
		if (strlen ( $contentID ) > 0) {
			$payload .= ',';
			$payload .= '"content-id":' . $contentID;
		}
		if (strlen ( $aptVal ) > 0) {
			$payload .= ',';
			$payload .= '"apt":"' . $aptVal . '"';
		}
		if (strlen ( $iid ) > 0) {
			$payload .= ',';
			$payload .= '"iid":"' . $iid . '"';
		}
		$payload .= '}';
		
		error_log ( "$currentScript : $apnToken : $payload" );
		
		list ( $sendResult, $errMsg ) = $apn->sendPayload ( $apnToken, $payload );
		if ($sendResult == false) {
			echo "sendPayload() returned FALSE : error message = $errMsg<br/><br/>\n";
		} else {
			echo "Sent!<br/>Token = $apnToken";
			echo "<br/>\n";
			echo "Message : $alertText";
			echo "<br/><br/>\n";
			echo "<pre>Payload = " . $payload . "</pre>\n<br/>";
		}
	}
}
?>
<form name="ahapnsend" method="post"
		action="<?php
		echo $currentScript;
		?>">
		Token :
		<input type="text" size="70" name="apnToken"
			value="<?php
			echo $apnToken;
			?>" />
		<br />
		<br />Prod or Dev :
		<input type="radio" name="tokenType" value="p"
			<?php
			echo ($prodChecked) ? " checked" : "";
			?>> Prod&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="tokenType"
			value="d" <?php
			echo (! $prodChecked) ? " checked" : "";
			?>> Dev <br /> <br />Content Available : <input type="text"
				name="contentAvailable"
				value="<?php
				echo $contentAvailable;
				?>" /> <br /> <br />Alert Text : <input type="text" name="alertText"
				value="<?php
				echo $alertText;
				?>" /> <br /> <br />Default Sound : <input type="text"
				name="defSound" value="<?php
				echo $defSound;
				?>" /> <br /> <br />Content ID : <input type="text" name="contentID"
				value="<?php
				echo $contentID;
				?>" /> <br /> <br />"apt" value : <input type="text" name="aptVal"
				value="<?php
				echo $aptVal;
				?>" /> <br /> <br />"invoice" id : <input type="text" name="iID"
				value="<?php
				echo $iid;
				?>" /> <br /> <br />"push-type" value : <input type="text"
				name="aptPushType" value="<?php
				echo $pushType;
				?>" /> <br /> <br />"priority" value : <input type="text"
				name="aptPriority" value="<?php
				echo $priority;
				?>" /> <br /> <!--
Additional key-value pairs:<br/>
<input type="text" name="key1" value="<?php //echo $key1; ?>"/> - <input type="text" name="val1" value="<?php //echo $val1; ?>"/><br/>
<input type="text" name="key2" value="<?php //echo $key2; ?>"/> - <input type="text" name="val2" value="<?php //echo $val2; ?>"/><br/>
<input type="text" name="key3" value="<?php //echo $key3; ?>"/> - <input type="text" name="val3" value="<?php //echo $val3; ?>"/><br/>
 --> <br /> <input type="submit" name="apnSubmit" value="Send" />
	
	</form>
</body>
</html>