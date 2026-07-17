<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$tbl  = (string)($_POST['tbl'] ?? '');
$name = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) ? ($_POST['width'] === '' ? null : (int)$_POST['width']) : null;

$allowedTbls = ['invoice', 'invoice2', 'kom', 'kom2'];
if (!in_array($tbl, $allowedTbls, true)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid table']);
    exit;
}

if ($tbl === 'invoice2') {
    $allowed = false;
    foreach (invoice2_columns_defaults() as $c) {
        if ($c['name'] === $name) { $allowed = true; break; }
    }
} else {
    $allowed = false;
    foreach (invoice_columns_defaults() as $c) {
        if ($c['name'] === $name) { $allowed = true; break; }
    }
}
if (!$allowed) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid column']);
    exit;
}

save_column_width($conn, $tbl, $name, $width);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
