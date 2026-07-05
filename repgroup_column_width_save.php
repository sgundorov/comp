<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['ok' => false]); exit; }
$col = (string)($_POST['col'] ?? ''); $width = (int)($_POST['width'] ?? 0);
if ($col !== '' && $width > 0) save_column_width($conn, 'repgroup', $col, $width);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
