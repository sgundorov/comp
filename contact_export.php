<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/config/contact_columns.php';
require_once __DIR__ . '/config/contact_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $contactPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Контакты',
    'colValues' => [
        'id'            => fn($r) => (int)$r['contact_id'],
        'client'        => fn($r) => (string)($r['cli_name'] ?? ''),
        'datetime'      => function ($r) { $raw = (string)($r['datetime'] ?? ''); return $raw !== '' ? date('d.m.Y H:i', strtotime($raw)) : ''; },
        'impotant_flag' => fn($r) => (string)($r['impotant_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
        'contype'       => fn($r) => (string)($r['contype'] ?? ''),
        'sotr'          => fn($r) => (string)($r['sotr_name'] ?? ''),
        'note'          => fn($r) => (string)($r['note'] ?? ''),
    ],
]);