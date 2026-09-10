<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tag_columns.php';
require_once __DIR__ . '/config/tag_page.php';

$tp = new TablePage($conn, $tagPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Виды деятельности',
    'colValues' => [
        'id' => fn($r) => (int)$r['tag_id'],
    ],
    'printWidths' => tag_columns_widths_print(),
]);