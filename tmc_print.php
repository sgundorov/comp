<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';

$tp = new TablePage($conn, $tmcPageConfig);

$tp->applyFilterWithLabel($conn, 'categ_id',   'p.categ_id',   'Категория',   'categ',   'categ_id',   'categ');
$tp->applyFilterWithLabel($conn, 'group_id',   'p.group_id',   'Группа',      '`group`', 'group_id',  'name');
$tp->applyFilterWithLabel($conn, 'sgroup_id',  'p.sgroup_id',  'Подгруппа',   'sgroup',  'sgroup_id', 'name');
$tp->applyFilterWithLabel($conn, 'country_id', 'p.country_id', 'Страна',      'country', 'country_id','country');

$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Товары',
    'colValues' => [
        'id'        => fn($r) => (int)$r['product_id'],
        'name'      => fn($r) => (string)($r['name'] ?? ''),
        'article'   => fn($r) => (string)($r['article'] ?? ''),
        'categ'     => fn($r) => (string)($r['categ_name'] ?? ''),
        'group'     => fn($r) => (string)($r['group_name'] ?? ''),
        'sgroup'    => fn($r) => (string)($r['sgroup_name'] ?? ''),
        'country'   => fn($r) => (string)($r['country_name'] ?? ''),
        'quant'     => fn($r) => number_format((float)($r['quant'] ?? 0), 3, '.', ' '),
        'price_in'  => fn($r) => number_format((float)($r['price_in'] ?? 0), 2, '.', ' '),
        'price_out' => fn($r) => number_format((float)($r['price_out'] ?? 0), 2, '.', ' '),
        'note'      => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => tmc_columns_widths_print(),
]);