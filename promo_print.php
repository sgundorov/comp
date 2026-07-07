<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/promo_columns.php';
require_once __DIR__ . '/config/promo_page.php';

$tp = new TablePage($conn, $promoPageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Источники рекламы',
    'colValues' => [
        'promo_id' => fn($r) => (int)$r['promo_id'],
        'promo'    => fn($r) => (string)($r['promo'] ?? ''),
        'bdate'    => fn($r) => (string)($r['bdate'] ?? ''),
        'edate'    => fn($r) => (string)($r['edate'] ?? ''),
        'count'    => fn($r) => (string)($r['count'] ?? ''),
        'procent'  => fn($r) => (string)($r['procent'] ?? ''),
        'note'     => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => promo_columns_widths_print(),
]);