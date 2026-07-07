<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/zat_columns.php';
require_once __DIR__ . '/config/zat_page.php';

$tp = new TablePage($conn, $zatPageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Виды операций с деньгами',
    'colValues' => [
        'id'       => fn($r) => (int)$r['zat_id'],
        'name'     => fn($r) => (string)($r['name'] ?? ''),
        'out_flag' => fn($r) => (int)($r['out_flag'] ?? 0) ? 'Расход' : 'Приход',
        'note'     => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => zat_columns_widths_print(),
]);