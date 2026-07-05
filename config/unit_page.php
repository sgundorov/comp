<?php
require_once __DIR__ . '/unit_columns.php';

$unitPageConfig = [
    'table'    => 'unit',
    'key'      => 'unit_id',
    'columns'  => unit_columns_defaults(),
    'search_cols' => [
        'id'   => 'u.unit_id',
        'unit' => 'u.unit',
        'note' => 'u.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'unit_select',
    'marks_tbl'       => 'unit',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'u.unit_id',
    'col_filters' => [],
    'select_sql'   => "SELECT u.unit_id, u.unit, u.note FROM unit u",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM unit u",
    'id_select_sql'=> "SELECT u.unit_id AS id FROM unit u",
    'base_url'     => 'unit.php',
];
