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

$name  = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) && $_POST['width'] !== '' ? (int)$_POST['width'] : null;

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'bad name']);
    exit;
}

$allowed = [];
require_once __DIR__ . '/config/invo_columns.php';
foreach (invo_columns_defaults() as $c) $allowed[$c['name']] = true;
if (!isset($allowed[$name])) {
    echo json_encode(['ok' => false, 'error' => 'unknown column']);
    exit;
}

$ok = save_column_width($conn, 'invoice', $name, $width);
echo json_encode(['ok' => (bool)$ok]);
