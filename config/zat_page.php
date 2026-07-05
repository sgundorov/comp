<?php
$zatPageConfig = [
    'table'    => 'zat',
    'key'      => 'zat_id',
    'columns'  => zat_columns_defaults(),
    'search_cols' => [
        'id'      => 'z.zat_id',
        'name'    => 'z.name',
        'note'    => 'z.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'zat_select',
    'marks_tbl'       => 'zat',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'z.zat_id',
    'col_filters' => [],
    'select_sql'   => "SELECT z.zat_id, z.name, z.out_flag, z.note FROM `zat` z",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM `zat` z",
    'id_select_sql'=> "SELECT z.zat_id AS id FROM `zat` z",
    'base_url'     => 'zat.php',
];
