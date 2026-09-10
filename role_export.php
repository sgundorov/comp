<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/role_columns.php';
require_once __DIR__ . '/config/role_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $rolePageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Роли сотрудников',
    'colValues' => [
        'id'   => fn($r) => (int)$r['role_id'],
        'role' => fn($r) => (string)($r['role'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
]);