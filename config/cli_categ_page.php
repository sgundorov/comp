<?php
require_once __DIR__ . '/cli_categ_columns.php';

$cliCategPageConfig = [
    'table'    => 'cli_categ',
    'key'      => 'cli_categ_id',
    'columns'  => cli_categ_columns_defaults(),
    'search_cols' => [
        'id'    => 'cc.cli_categ_id',
        'categ' => 'cc.categ',
        'note'  => 'cc.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'cli_categ_select',
    'marks_tbl'       => 'cli_categ',
    'key_expr'        => 'cc.cli_categ_id',
    'col_filters' => [],
    'select_sql'   => "SELECT cc.cli_categ_id, cc.categ, cc.supplier_flag, cc.problem_flag, cc.color, cc.note FROM cli_categ cc",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM cli_categ cc",
    'id_select_sql'=> "SELECT cc.cli_categ_id AS id FROM cli_categ cc",
    'base_url'     => 'cli_categ.php',
];
