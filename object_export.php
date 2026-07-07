<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/object_columns.php';
require_once __DIR__ . '/config/object_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $objectPageConfig);
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Объекты доступа',
    'colValues' => [
        'id'     => fn($r) => (int)$r['object_id'],
        'object' => fn($r) => (string)($r['object'] ?? ''),
        'name'   => fn($r) => (string)($r['name'] ?? ''),
        'type'   => fn($r) => (string)($r['type'] ?? ''),
        'note'   => fn($r) => (string)($r['note'] ?? ''),
    ],
]);