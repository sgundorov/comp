<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';

$tp = new TablePage($conn, $cityPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Города',
    'colValues' => [
        'id' => fn($r) => (int)$r['city_id'],
    ],
    'printWidths' => city_columns_widths_print(),
]);