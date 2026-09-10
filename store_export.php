<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/store_columns.php';
require_once __DIR__ . '/config/store_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $storePageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Участки',
    'colValues' => [
        'id'      => fn($r) => (int)$r['store_id'],
        'name'    => fn($r) => (string)($r['name'] ?? ''),
        'address' => fn($r) => (string)($r['address'] ?? ''),
        'note'    => fn($r) => (string)($r['note'] ?? ''),
    ],
]);