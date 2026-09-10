<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/status_columns.php';
require_once __DIR__ . '/config/status_page.php';

$tp = new TablePage($conn, $statusPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Статусы',
    'colValues' => [
        'id' => fn($r) => (int)$r['status_id'],
    ],
    'printWidths' => status_columns_widths_print(),
]);