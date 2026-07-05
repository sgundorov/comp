<?php
require_once __DIR__ . '/firm_columns.php';

$firmPageConfig = [
    'table'    => 'firm',
    'key'      => 'firm_id',
    'columns'  => firm_columns_defaults(),
    'search_cols' => [
        'id'       => 'f.firm_id',
        'name'     => 'f.name',
        'address'  => 'f.address',
        'phone'    => 'f.phone',
        'email'    => 'f.email',
        'city'     => 'c.city',
        'director' => 'f.director',
        'note'     => 'f.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'firm_select',
    'marks_tbl'       => 'firm',
    'key_expr'        => 'f.firm_id',
    'col_filters' => [
        'city' => [null, 'city', 'city_id', 'city'],
    ],
    'select_sql'   => "SELECT f.firm_id, f.name, f.address, f.phone, f.email, f.city_id, f.director, f.note, c.city FROM firm f LEFT JOIN city c ON c.city_id = f.city_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM firm f LEFT JOIN city c ON c.city_id = f.city_id",
    'id_select_sql'=> "SELECT f.firm_id AS id FROM firm f",
    'base_url'     => 'firm.php',
];
