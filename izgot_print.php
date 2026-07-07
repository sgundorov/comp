<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/izgot_columns.php';
require_once __DIR__ . '/config/izgot_page.php';

$tp = new TablePage($conn, $izgotPageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Производители',
    'colValues' => [
        'id'      => fn($r) => (int)$r['izgot_id'],
        'izgot'   => fn($r) => (string)($r['izgot'] ?? ''),
        'country' => fn($r) => (string)($r['country'] ?? ''),
        'note'    => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => izgot_columns_widths_print(),
]);