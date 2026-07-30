<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $groupPageConfig);

$exportServiceMode = (string)($_GET['type'] ?? 'product');
if (!in_array($exportServiceMode, ['product', 'service'], true)) $exportServiceMode = 'product';
$tp->appendWhere("g.service_flag = ?", [$exportServiceMode === 'service' ? '1' : '0'], 's');

$rows = $tp->fetchAll($conn);

$exportBaseName = $exportServiceMode === 'service' ? 'Группы услуг' : 'Группы товаров';
$tp->renderExport($format, $rows, [
    'baseName' => $exportBaseName,
    'colValues' => [
        'id'   => fn($r) => (int)$r['group_id'],
        'name' => fn($r) => (string)($r['name'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
]);