<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/unit_columns.php';
require_once __DIR__ . '/config/unit_page.php';

$tp = new TablePage($conn, $unitPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Единицы измерения',
    'colValues' => [
        'id' => fn($r) => (int)$r['unit_id'],
    ],
    'printWidths' => unit_columns_widths_print(),
]);