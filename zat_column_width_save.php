<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$isAjax = ((string)($_GET['ajax'] ?? '') === '1')
       || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
if (!$isAjax) {
    echo json_encode(['ok' => false, 'error' => 'ajax required']);
    exit;
}

$tbl = (string)($_POST['tbl'] ?? '');
if ($tbl !== 'zat') {
    echo json_encode(['ok' => false, 'error' => 'unknown table']);
    exit;
}

require_once __DIR__ . '/config/zat_columns.php';
$allowed = [];
foreach (zat_columns_defaults() as $c) $allowed[$c['name']] = true;

$name  = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) && $_POST['width'] !== '' ? (int)$_POST['width'] : null;

if (!isset($allowed[$name])) {
    echo json_encode(['ok' => false, 'error' => 'unknown column']);
    exit;
}

$ok = save_column_width($conn, $tbl, $name, $width);
echo json_encode(['ok' => $ok]);
