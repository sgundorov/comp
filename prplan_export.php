<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/prplan_columns.php';
require_once __DIR__ . '/config/prplan_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $prplanPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Тарифные планы',
    'colValues' => [
        'id'     => fn($r) => (int)$r['prplan_id'],
        'prplan' => fn($r) => (string)($r['prplan'] ?? ''),
        'bdate'  => fn($r) => (string)($r['bdate'] ?? ''),
        'edate'  => fn($r) => (string)($r['edate'] ?? ''),
        'note'   => fn($r) => (string)($r['note'] ?? ''),
    ],
]);
