<?php
include_once 'spring.php';
include_once 'Kurier.php';


$kurier = new Kurier('https://mtapi.net/?testMode=1', $params);

try {
    $trackingNumber = $kurier->newPackage($order, $params);
    $kurier->packagePDF($trackingNumber);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}