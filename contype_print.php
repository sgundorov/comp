<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/contype_columns.php';
require_once __DIR__ . '/config/contype_page.php';

$tp = new TablePage($conn, $contypePageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Виды контактов',
    'colValues' => [
        'id' => fn($r) => (int)$r['contype_id'],
    ],
    'printWidths' => contype_columns_widths_print(),
]);