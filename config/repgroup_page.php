<?php
require_once __DIR__ . '/repgroup_columns.php';

$repgroupPageConfig = [
    'table'    => 'repgroup',
    'key'      => 'gr_id',
    'columns'  => repgroup_columns_defaults(),
    'search_cols' => [
        'id'   => 'rg.gr_id',
        'name' => 'rg.name',
        'note' => 'rg.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'repgroup_select',
    'marks_tbl'       => 'repgroup',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'rg.gr_id',
    'col_filters' => [],
    'select_sql'   => "SELECT rg.gr_id, rg.name, rg.note FROM repgroup rg",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM repgroup rg",
    'id_select_sql'=> "SELECT rg.gr_id AS id FROM repgroup rg",
    'base_url'     => 'repgroup.php',
];
