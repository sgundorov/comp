<?php
require_once __DIR__ . '/prplan_columns.php';

$prplanPageConfig = [
    'table'    => 'prplan',
    'key'      => 'prplan_id',
    'columns'  => prplan_columns_defaults(),
    'search_cols' => [
        'id'     => 'c.prplan_id',
        'prplan' => 'c.prplan',
        'bdate'  => 'c.bdate',
        'edate'  => 'c.edate',
        'note'   => 'c.note',
    ],
    'search_labels' => [
        'id'     => 'ID',
        'prplan' => 'Тарифный план',
        'bdate'  => 'Начало сезона',
        'edate'  => 'Конец сезона',
        'note'   => 'Примечание',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'prplan_select',
    'marks_tbl'       => 'prplan',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'c.prplan_id',
    'col_filters' => [],
    'select_sql'   => "SELECT c.prplan_id, c.prplan, c.bdate, c.edate, c.note FROM prplan c",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM prplan c",
    'id_select_sql'=> "SELECT c.prplan_id AS id FROM prplan c",
    'base_url'     => 'prplan.php',
];
