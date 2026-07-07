<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/contype_columns.php';
require_once __DIR__ . '/config/contype_page.php';

$tp = new TablePage($conn, $contypePageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Виды контактов',
    'colValues' => [
        'id' => fn($r) => (int)$r['contype_id'],
    ],
    'printWidths' => contype_columns_widths_print(),
]);