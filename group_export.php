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
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Группы товаров',
    'colValues' => [
        'id'   => fn($r) => (int)$r['group_id'],
        'name' => fn($r) => (string)($r['name'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
]);