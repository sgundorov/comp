<?php
require_once __DIR__ . '/country_columns.php';

$countryPageConfig = [
    'table'    => 'country',
    'key'      => 'country_id',
    'columns'  => country_columns_defaults(),
    'search_cols' => [
        'id'      => 'c.country_id',
        'country' => 'c.country',
        'note'    => 'c.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'country_select',
    'marks_tbl'       => 'country',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'c.country_id',
    'col_filters' => [],
    'select_sql'   => "SELECT c.country_id, c.country, c.note FROM country c",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM country c",
    'id_select_sql'=> "SELECT c.country_id AS id FROM country c",
    'base_url'     => 'country.php',
];
