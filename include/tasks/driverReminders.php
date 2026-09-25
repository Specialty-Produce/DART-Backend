<?php
include_once 'global_CDC.php';
include_once 'classes_SP/class_DART.php';
include_once 'classes_SP/class_ADPWFN_SP.php';
require_once 'classes_SP/class_TwilioSMS.php';
include '../../dart_init.php';

$currentScript = basename($_SERVER["SCRIPT_NAME"]);

$today = date('Y-m-d');

$driverList = DART::getDriverListByDate($today);

echo "<pre>" . print_r($driverList, true) . "</pre>";
$userIDs = array_column($driverList, 'uid');
echo "<pre>" . print_r($userIDs, true) . "</pre>";

$punches = ADPWFN_SP::getPunchesByUserIDs($userIDs, $today);

echo "<hr/>Punches:\n<pre>" . print_r($punches, true) . "</pre>";

for ($i = 0; $i < count($driverList); $i++) {
    if (array_key_exists($driverList[$i]['uid'], $punches)) {
        $driverList[$i]['punches'] = $punches[$driverList[$i]['uid']];
    } else {
        $driverList[$i]['punches'] = array();
    }
}
echo "<hr/>DriverList:\n<pre>" . print_r($driverList, true) . "</pre>";
