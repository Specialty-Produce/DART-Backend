<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
	<title>DART Driver State</title>
	<script type="text/javascript" src="mootools.js"></script>
	<script type="text/javascript" src="dart.js"></script>
</head>
<body>
<h3>Set DART Driver State</h3>
Current State : <span id="currentState" style="font-weight: bold;">Initial</span>
<br/><br/>
Set state to : 
<input type="radio" id="newstate" name="newstate" value="0" checked="checked"/>Initial&nbsp;&nbsp;<input type="radio" id="newstate" name="newstate" value="1"/>Rsync
<br/><br/>
<button onclick="setDriverState();">Submit</button>
</body>
</html>