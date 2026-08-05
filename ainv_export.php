<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/ainv_columns.php';
require_once __DIR__ . '/config/ainv_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $ainvPageConfig);

$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Инвентаризация',
    'colValues' => [
        'accept_flag' => function ($r) { return (int)$r['accept_flag'] ? 'Да' : ''; },
        'number'      => fn($r) => (string)$r['number'],
        'date'        => fn($r) => (string)$r['date'],
        'time'        => fn($r) => (string)$r['time'],
        'mesto_id'    => fn($r) => (string)($r['mesto_name'] ?? ''),
        'pos'         => fn($r) => (int)$r['poz'] ? (string)(int)$r['poz'] : '',
        'sum'         => fn($r) => (float)$r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') : '',
        'first_card'  => fn($r) => (int)$r['first_card'] ? (string)(int)$r['first_card'] : '',
        'last_card'   => fn($r) => (int)$r['last_card'] ? (string)(int)$r['last_card'] : '',
        'firm_id'     => fn($r) => (string)($r['firm_name'] ?? ''),
        'sotr_id'     => fn($r) => (string)($r['sotr_name'] ?? ''),
        'note'        => fn($r) => (string)$r['note'],
    ],
]);
