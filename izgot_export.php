<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/izgot_columns.php';
require_once __DIR__ . '/config/izgot_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $izgotPageConfig);
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Производители',
    'colValues' => [
        'id'      => fn($r) => (int)$r['izgot_id'],
        'izgot'   => fn($r) => (string)($r['izgot'] ?? ''),
        'country' => fn($r) => (string)($r['country'] ?? ''),
        'note'    => fn($r) => (string)($r['note'] ?? ''),
    ],
]);