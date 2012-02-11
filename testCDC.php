<html>
<head>
<title>Test CDC Page</title>
</head>
<body>
<h3>Test CDC Page...</h3>
<pre>
<?php
echo date('n/j/Y H:i:s') . "\n";
$WshShell = new COM("WScript.Shell");
$oExec = $WshShell->Run("php testCDC2.php foobar", 0, false);
// DOES NOT WORK : exec("php testCDC2.php foobar > NUL 2>&1 & echo $!", $output);
// DOES NOT WORK : exec("C:\\wwwroot\\DART\\testCDC2.php foobar >/dev/null 2>&1 &");
// WORKS : pclose(popen("start /B php testCDC2.php foobar", "r"));
echo date('n/j/Y H:i:s') . "\n";
?>
</pre>
Done...
</body>
</html>