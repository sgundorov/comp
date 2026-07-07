<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sgroup_columns.php';
require_once __DIR__ . '/config/sgroup_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $sgroupPageConfig);

$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId > 0) {
    $tp->appendWhere('sg.group_id = ?', [$groupId], 'i');
}

$rows = $tp->fetchAll($conn);

$baseName = $sgroupIsService ? 'Услуги' : 'Подгруппы товаров';

$tp->renderExport($format, $rows, [
    'baseName' => $baseName,
    'colValues' => [
        'id'    => fn($r) => (int)$r['sgroup_id'],
        'name'  => fn($r) => (string)($r['name'] ?? ''),
        'group' => fn($r) => (string)($r['group_name'] ?? ''),
        'note'  => fn($r) => (string)($r['note'] ?? ''),
    ],
]);