<?php
require_once __DIR__ . '/config.php';
$page = file_get_contents(__DIR__ . '/../comp/sale.php');
// Extract first JS block
if (preg_match('#<script>\s*(.*?)\s*</script>#s', $page, $m)) {
    file_put_contents(__DIR__ . '/test1.js', $m[1]);
}
// Extract second JS block
if (preg_match_all('#<script>\s*(.*?)\s*</script>#s', $page, $m)) {
    if (isset($m[1][1])) file_put_contents(__DIR__ . '/test2.js', $m[1][1]);
}
