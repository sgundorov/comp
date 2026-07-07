<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invo_columns.php';
require_once __DIR__ . '/config/invo_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $invoPageConfig);
$tp->appendWhere("i.doctype_id = ?", [10], 'i');

$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Счета',
    'colValues' => [
        'number'       => fn($r) => (string)$r['number'],
        'date'         => fn($r) => (string)$r['date'],
        'client'       => fn($r) => (string)($r['client_name'] ?? ''),
        'state'        => function ($r) { $s = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return $s[(string)$r['state']] ?? $r['state']; },
        'store'        => fn($r) => (string)($r['store_name'] ?? ''),
        'discount'     => fn($r) => (int)$r['discount'] ? (string)(int)$r['discount'] . '%' : '',
        'sum'          => fn($r) => (float)$r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') : '',
        'sotr'         => fn($r) => (string)($r['sotr_name'] ?? ''),
        'pos'          => fn($r) => (int)$r['pos'] ? (string)(int)$r['pos'] : '',
        'sum_plat'     => fn($r) => (float)$r['sum_plat'] ? number_format((float)$r['sum_plat'], 2, '.', ' ') : '',
        'sum_nds'      => fn($r) => (float)$r['sum_nds'] ? number_format((float)$r['sum_nds'], 2, '.', ' ') : '',
        'date_plat'    => fn($r) => (string)($r['date_plat'] ?? ''),
        'sum_discount' => fn($r) => (float)$r['sum_discount'] ? number_format((float)$r['sum_discount'], 2, '.', ' ') : '',
        'time'         => fn($r) => (string)($r['time'] ?? ''),
        'note'         => fn($r) => (string)$r['note'],
    ],
]);