<pre>
<?php
require_once 'global_CDC.php';

$locationID = 21;

$sigdir = "\\\\vServices\\dartsigs\$\\";

$filedir = $sigdir . $locationID;

echo "filedir = $filedir\n\n";

if (! is_dir ( $filedir )) {
	echo "    NOT is_dir\n";
} else {
	echo "    IS is_dir\n";
}




echo "\n\n-----\nDONE...\n";
?>
</pre>