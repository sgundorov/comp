<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/groupserv_page.php';

$tp = new TablePage($conn, $groupservPageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Группы услуг',
    'colValues' => [
        'id'   => fn($r) => (int)$r['group_id'],
        'name' => fn($r) => (string)($r['name'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => group_columns_widths_print(),
]);