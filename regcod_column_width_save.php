<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403); exit;
}
$tbl = 'regcod';
$name = (string)($_POST['name'] ?? '');
$width = (int)($_POST['width'] ?? 0);
if ($name !== '' && $width > 0) {
    save_column_width($conn, $tbl, $name, $width);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
