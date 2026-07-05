<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
$_GET['mode'] = 'new';
$_GET['docum_id'] = '1';
$_GET['ajax'] = '1';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
ob_start();
include __DIR__ . '/../docum2_form.php';
$html = ob_get_clean();
echo 'Length: ' . strlen($html) . "\n";
echo substr($html, 0, 500);
