<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tag_columns.php';
require_once __DIR__ . '/config/tag_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $tagPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);
$tp->renderExport($format, $rows, ['baseName' => 'Виды деятельности', 'colValues' => ['id' => fn($r) => (string)(int)$r['tag_id']]]);