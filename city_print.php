<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';

$tp = new TablePage($conn, $cityPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Города',
    'colValues' => [
        'id' => fn($r) => (int)$r['city_id'],
    ],
    'printWidths' => city_columns_widths_print(),
]);