<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sgroup_columns.php';
require_once __DIR__ . '/config/sgroup_page.php';

$tp = new TablePage($conn, $sgroupPageConfig);

$tp->appendWhere("sg.service_flag = ?", [$sgroupServiceFlag], 's');

$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId > 0) {
    $tp->appendWhere('sg.group_id = ?', [$groupId], 'i');
}

[$rows, $pagination] = $tp->fetchPage($conn);

$printTitle = $sgroupIsService ? 'Услуги' : 'Подгруппы товаров';

$tp->renderPrintPage($rows, $pagination, [
    'title' => $printTitle,
    'colValues' => [
        'id'    => fn($r) => (int)$r['sgroup_id'],
        'name'  => fn($r) => (string)($r['name'] ?? ''),
        'group' => fn($r) => (string)($r['group_name'] ?? ''),
        'note'  => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => sgroup_columns_widths_print(),
]);