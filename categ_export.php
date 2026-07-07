<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/categ_columns.php';
require_once __DIR__ . '/config/categ_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $categPageConfig);
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Категории товаров',
    'colValues' => [
        'id'           => fn($r) => (int)$r['categ_id'],
        'categ'        => fn($r) => (string)($r['categ'] ?? ''),
        'noquant_flag' => fn($r) => !empty($r['noquant_flag']) ? 'Да' : 'Нет',
        'service_flag' => fn($r) => !empty($r['service_flag']) ? 'Да' : 'Нет',
        'note'         => fn($r) => (string)($r['note'] ?? ''),
    ],
]);