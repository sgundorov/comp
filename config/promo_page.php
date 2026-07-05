<?php
require_once __DIR__ . '/promo_columns.php';

$promoPageConfig = [
    'table'    => 'promo',
    'key'      => 'promo_id',
    'columns'  => promo_columns_defaults(),
    'search_cols' => [
        'id'     => 'p.promo_id',
        'promo'  => 'p.promo',
        'count'  => 'p.count',
        'procent'=> 'p.procent',
        'note'   => 'p.note',
    ],
    'default_sort'    => ['col' => 'promo_id', 'dir' => 'asc'],
    'marks_session'   => 'promo_select',
    'marks_tbl'       => 'promo',
    'key_expr'        => 'p.promo_id',
    'col_filters' => [],
    'select_sql'   => "SELECT p.promo_id, p.promo, p.bdate, p.edate, p.count, p.procent, p.note FROM promo p",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM promo p",
    'id_select_sql'=> "SELECT p.promo_id AS id FROM promo p",
    'base_url'     => 'promo.php',
];
