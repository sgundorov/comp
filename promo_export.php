<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/promo_columns.php';
require_once __DIR__ . '/config/promo_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $promoPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Источники рекламы',
    'colValues' => [
        'promo_id' => fn($r) => (int)$r['promo_id'],
        'promo'    => fn($r) => (string)($r['promo'] ?? ''),
        'bdate'    => fn($r) => (string)($r['bdate'] ?? ''),
        'edate'    => fn($r) => (string)($r['edate'] ?? ''),
        'count'    => fn($r) => (string)($r['count'] ?? ''),
        'procent'  => fn($r) => (string)($r['procent'] ?? ''),
        'note'     => fn($r) => (string)($r['note'] ?? ''),
    ],
]);