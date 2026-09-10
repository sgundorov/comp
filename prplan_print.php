<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/prplan_columns.php';
require_once __DIR__ . '/config/prplan_page.php';

$tp = new TablePage($conn, $prplanPageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Тарифные планы',
    'colValues' => [
        'id'     => fn($r) => (int)$r['prplan_id'],
        'prplan' => fn($r) => (string)($r['prplan'] ?? ''),
        'bdate'  => fn($r) => (string)($r['bdate'] ?? ''),
        'edate'  => fn($r) => (string)($r['edate'] ?? ''),
        'note'   => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => prplan_columns_widths_print(),
]);
