<?php
$categPageConfig = [
    'table'    => 'categ',
    'key'      => 'categ_id',
    'columns'  => categ_columns_defaults(),
    'search_cols' => [
        'id'    => 'c.categ_id',
        'categ' => 'c.categ',
        'note'  => 'c.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'categ_select',
    'marks_tbl'       => 'categ',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'c.categ_id',
    'col_filters' => [],
    'select_sql'   => "SELECT c.categ_id, c.categ, c.noquant_flag, c.service_flag, c.note FROM `categ` c",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM `categ` c",
    'id_select_sql'=> "SELECT c.categ_id AS id FROM `categ` c",
    'base_url'     => 'categ.php',
];
