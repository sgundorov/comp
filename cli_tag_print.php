<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tag_columns.php';
require_once __DIR__ . '/config/tag_page.php';

$tp = new TablePage($conn, $tagPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Виды деятельности',
    'colValues' => [
        'id' => fn($r) => (int)$r['tag_id'],
    ],
    'printWidths' => tag_columns_widths_print(),
]);