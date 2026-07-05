<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sale_columns.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$name  = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) ? ($_POST['width'] === '' ? null : (int)$_POST['width']) : null;

$allowed = false;
foreach (sale_columns_defaults() as $c) {
    if ($c['name'] === $name) { $allowed = true; break; }
}
if (!$allowed) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid column']);
    exit;
}

save_column_width($conn, 'sale', $name, $width);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
