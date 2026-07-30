<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';

$tp = new TablePage($conn, $groupPageConfig);

$printServiceMode = (string)($_GET['type'] ?? 'product');
if (!in_array($printServiceMode, ['product', 'service'], true)) $printServiceMode = 'product';
$tp->appendWhere("g.service_flag = ?", [$printServiceMode === 'service' ? '1' : '0'], 's');

[$rows, $pagination] = $tp->fetchPage($conn);

$printTitle = $printServiceMode === 'service' ? 'Группы услуг' : 'Группы товаров';
$tp->renderPrintPage($rows, $pagination, [
    'title' => $printTitle,
    'colValues' => [
        'id'   => fn($r) => (int)$r['group_id'],
        'name' => fn($r) => (string)($r['name'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => group_columns_widths_print(),
]);