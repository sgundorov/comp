<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $tmcPageConfig);

$exportServiceMode = (string)($_GET['type'] ?? 'product');
if (!in_array($exportServiceMode, ['product', 'service'], true)) $exportServiceMode = 'product';
$tp->appendWhere("p.service_flag = ?", [$exportServiceMode === 'service' ? '1' : '0'], 's');

$tp->applyFilterWithLabel($conn, 'categ_id',   'p.categ_id',   'Категория',   'categ',   'categ_id',   'categ');
$tp->applyFilterWithLabel($conn, 'group_id',   'p.group_id',   'Группа',      '`group`', 'group_id',  'name');
$tp->applyFilterWithLabel($conn, 'sgroup_id',  'p.sgroup_id',  'Подгруппа',   'sgroup',  'sgroup_id', 'name');
$tp->applyFilterWithLabel($conn, 'country_id', 'p.country_id', 'Страна',      'country', 'country_id','country');

$rows = $tp->fetchAll($conn);
$exportBaseName = $exportServiceMode === 'service' ? 'Услуги' : 'Товары';
$tp->renderExport($format, $rows, [
    'baseName' => $exportBaseName,
    'colValues' => [
        'id'        => fn($r) => (string)(int)$r['product_id'],
        'name'      => fn($r) => (string)($r['name'] ?? ''),
        'code'      => fn($r) => (string)($r['code'] ?? ''),
        'article'   => fn($r) => (string)($r['article'] ?? ''),
        'categ'     => fn($r) => (string)($r['categ_name'] ?? ''),
        'group'     => fn($r) => (string)($r['group_name'] ?? ''),
        'sgroup'    => fn($r) => (string)($r['sgroup_name'] ?? ''),
        'quant'     => fn($r) => (string)(float)($r['quant'] ?? 0),
        'price_in'  => fn($r) => (string)(float)($r['price_in'] ?? 0),
        'price_out' => fn($r) => (string)(float)($r['price_out'] ?? 0),
        'note'      => fn($r) => (string)($r['note'] ?? ''),
    ],
]);