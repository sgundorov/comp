<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/status_columns.php';
require_once __DIR__ . '/config/status_page.php';

$tp = new TablePage($conn, $statusPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Статусы',
    'colValues' => [
        'id' => fn($r) => (int)$r['status_id'],
    ],
    'printWidths' => status_columns_widths_print(),
]);