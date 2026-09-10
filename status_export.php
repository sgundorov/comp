<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/status_columns.php';
require_once __DIR__ . '/config/status_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $statusPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);
$tp->renderExport($format, $rows, ['baseName' => 'Статусы', 'colValues' => ['id' => fn($r) => (string)(int)$r['status_id']]]);