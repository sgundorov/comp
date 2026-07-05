<?php
require_once __DIR__ . '/izgot_columns.php';

$izgotPageConfig = [
    'table'    => 'izgot',
    'key'      => 'izgot_id',
    'columns'  => izgot_columns_defaults(),
    'search_cols' => [
        'id'      => 'i.izgot_id',
        'izgot'   => 'i.izgot',
        'country' => 'co.country',
        'note'    => 'i.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'izgot_select',
    'marks_tbl'       => 'izgot',
    'country_filter_field' => 'country_id',
    'country_filter_expr'  => 'i.country_id',
    'key_expr'             => 'i.izgot_id',
    'col_filters' => [
        'country' => [null, 'country', 'country_id', 'country'],
    ],
    'select_sql'   => "SELECT i.izgot_id, i.izgot, i.country_id, co.country, i.note FROM izgot i LEFT JOIN country co ON co.country_id = i.country_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM izgot i LEFT JOIN country co ON co.country_id = i.country_id",
    'id_select_sql'=> "SELECT i.izgot_id AS id FROM izgot i",
    'base_url'     => 'izgot.php',
];
