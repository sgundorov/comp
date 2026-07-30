<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

$tbl  = (string)($_POST['tbl'] ?? '');
$col  = (string)($_POST['col'] ?? '');
$width = (int)($_POST['width'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

if ($tbl === '' || $col === '' || $width <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}

ensure_columns_config_table($conn);

$stmt = $conn->prepare("INSERT INTO column_visibility (tbl, column_name, visible, sort_order, width) VALUES (?, ?, 1, 0, ?) ON DUPLICATE KEY UPDATE width = VALUES(width)");
$stmt->bind_param('ssi', $tbl, $col, $width);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);
