<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/unit_columns.php';
require_once __DIR__ . '/config/unit_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $unitPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderExport($format, $rows, ['baseName' => 'Единицы измерения', 'colValues' => ['id' => fn($r) => (string)(int)$r['unit_id']]]);