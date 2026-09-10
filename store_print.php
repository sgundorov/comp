<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/store_columns.php';
require_once __DIR__ . '/config/store_page.php';

$tp = new TablePage($conn, $storePageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Участки',
    'colValues' => [
        'id'      => fn($r) => (int)$r['store_id'],
        'name'    => fn($r) => (string)($r['name'] ?? ''),
        'address' => fn($r) => (string)($r['address'] ?? ''),
        'note'    => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => store_columns_widths_print(),
]);