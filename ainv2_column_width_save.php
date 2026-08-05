<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/ainv_columns.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$tbl  = (string)($_POST['tbl'] ?? '');
$name = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) ? ($_POST['width'] === '' ? null : (int)$_POST['width']) : null;

if ($tbl !== 'ainv2') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid table']);
    exit;
}

$allowed = false;
foreach (ainv2_columns_defaults() as $c) {
    if ($c['name'] === $name) { $allowed = true; break; }
}
if (!$allowed) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid column']);
    exit;
}

save_column_width($conn, $tbl, $name, $width);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
