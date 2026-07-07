<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/unit_columns.php';
require_once __DIR__ . '/config/unit_page.php';

$tp = new TablePage($conn, $unitPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Единицы измерения',
    'colValues' => [
        'id' => fn($r) => (int)$r['unit_id'],
    ],
    'printWidths' => unit_columns_widths_print(),
]);