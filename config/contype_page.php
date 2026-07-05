<?php
require_once __DIR__ . '/contype_columns.php';

$contypePageConfig = [
    'table'    => 'contype',
    'key'      => 'contype_id',
    'columns'  => contype_columns_defaults(),
    'search_cols' => [
        'id'      => 'ct.contype_id',
        'contype' => 'ct.contype',
        'note'    => 'ct.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'contype_select',
    'marks_tbl'       => 'contype',
    'key_expr'        => 'ct.contype_id',
    'col_filters' => [],
    'select_sql'   => "SELECT ct.contype_id, ct.contype, ct.impotant_flag, ct.note FROM contype ct",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM contype ct",
    'id_select_sql'=> "SELECT ct.contype_id AS id FROM contype ct",
    'base_url'     => 'contype.php',
];
