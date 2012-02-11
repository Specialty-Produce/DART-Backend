<?php
$state = filter_input ( INPUT_POST, 'state', FILTER_VALIDATE_INT );

$sf = fopen("driverstate.txt", "w");
fwrite($sf, $state);
fclose($sf);
echo ($state == 0) ? "Initial" : "Rsync";
?>