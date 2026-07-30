<?php
require_once __DIR__ . '/repmenu_columns.php';

$repmenuPageConfig = [
    'table'    => 'repmenu',
    'key'      => 'rp_id',
    'columns'  => repmenu_columns_defaults(),
    'search_cols' => [
        'id'    => 'm.number',
        'name'  => 'm.name',
        'fname' => 'm.fname',
        'note'  => 'm.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'repmenu_select',
    'marks_tbl'       => 'repmenu',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'm.rp_id',
    'col_filters' => [],
    'select_sql'   => "SELECT m.rp_id, m.number, m.gr_id, m.name, m.fname, m.HIDE_FLAG, m.note, rg.name AS group_name FROM repmenu m LEFT JOIN repgroup rg ON rg.gr_id = m.gr_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM repmenu m LEFT JOIN repgroup rg ON rg.gr_id = m.gr_id",
    'id_select_sql'=> "SELECT m.rp_id AS id FROM repmenu m LEFT JOIN repgroup rg ON rg.gr_id = m.gr_id",
    'base_url'     => 'repmenu.php',
];
