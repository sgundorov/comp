<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403); exit;
}
$tbl = 'regcod';
$cols = json_decode((string)($_POST['columns'] ?? '[]'), true);
if (!is_array($cols)) $cols = [];
save_columns_config($conn, $tbl, $cols);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
