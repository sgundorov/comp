<?php
require_once __DIR__ . '/status_columns.php';

$statusPageConfig = [
    'table'    => 'status',
    'key'      => 'status_id',
    'columns'  => status_columns_defaults(),
    'search_cols' => [
        'id'     => 'st.status_id',
        'status' => 'st.status',
        'note'   => 'st.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'status_select',
    'marks_tbl'       => 'status',
    'key_expr'        => 'st.status_id',
    'col_filters' => [],
    'select_sql'   => "SELECT st.status_id, st.status, st.color, st.note FROM status st",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM status st",
    'id_select_sql'=> "SELECT st.status_id AS id FROM status st",
    'base_url'     => 'status.php',
];
