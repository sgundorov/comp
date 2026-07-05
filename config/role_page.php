<?php
$rolePageConfig = [
    'table'    => 'role',
    'key'      => 'role_id',
    'columns'  => role_columns_defaults(),
    'search_cols' => [
        'id'   => 'r.role_id',
        'role' => 'r.role',
        'note' => 'r.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'role_select',
    'marks_tbl'       => 'role',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'r.role_id',
    'col_filters' => [],
    'select_sql'   => "SELECT r.role_id, r.role, r.note FROM `role` r",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM `role` r",
    'id_select_sql'=> "SELECT r.role_id AS id FROM `role` r",
    'base_url'     => 'role.php',
];
