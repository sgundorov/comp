<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/object_columns.php';
require_once __DIR__ . '/config/object_page.php';

$tp = new TablePage($conn, $objectPageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Объекты доступа',
    'colValues' => [
        'id'     => fn($r) => (int)$r['object_id'],
        'object' => fn($r) => (string)($r['object'] ?? ''),
        'name'   => fn($r) => (string)($r['name'] ?? ''),
        'type'   => fn($r) => (string)($r['type'] ?? ''),
        'note'   => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => object_columns_widths_print(),
]);