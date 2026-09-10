<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/firm_columns.php';
require_once __DIR__ . '/config/firm_page.php';

$tp = new TablePage($conn, $firmPageConfig);

$tp->applyFilterWithLabel($conn, 'city_id', 'f.city_id', 'Город', 'city', 'city_id', 'city');

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Фирмы',
    'colValues' => [
        'id' => fn($r) => (int)$r['firm_id'],
    ],
    'printWidths' => firm_columns_widths_print(),
]);