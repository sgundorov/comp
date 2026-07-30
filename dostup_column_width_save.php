<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$tbl = (string)($_POST['tbl'] ?? '');
$name = (string)($_POST['name'] ?? '');
$width = isset($_POST['width']) && $_POST['width'] !== '' ? (int)$_POST['width'] : null;

if ($tbl === '' || $name === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

$ok = save_column_width($conn, $tbl, $name, $width);
echo json_encode(['ok' => $ok]);
