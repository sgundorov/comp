<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/categ_columns.php';
require_once __DIR__ . '/config/categ_page.php';

$tp = new TablePage($conn, $categPageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Категории товаров',
    'colValues' => [
        'id'           => fn($r) => (int)$r['categ_id'],
        'categ'        => fn($r) => (string)($r['categ'] ?? ''),
        'noquant_flag' => fn($r) => !empty($r['noquant_flag']) ? 'Да' : 'Нет',
        'service_flag' => fn($r) => !empty($r['service_flag']) ? 'Да' : 'Нет',
        'note'         => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => categ_columns_widths_print(),
]);