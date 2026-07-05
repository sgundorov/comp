<?php
$objectPageConfig = [
    'table'    => 'object',
    'key'      => 'object_id',
    'columns'  => object_columns_defaults(),
    'search_cols' => [
        'id'      => 'o.object_id',
        'object'  => 'o.object',
        'name'    => 'o.name',
        'type'    => 'o.type',
        'note'    => 'o.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'object_select',
    'marks_tbl'       => 'object',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'o.object_id',
    'col_filters' => [],
    'select_sql'   => "SELECT o.object_id, o.object, o.name, o.type, o.note FROM `object` o",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM `object` o",
    'id_select_sql'=> "SELECT o.object_id AS id FROM `object` o",
    'base_url'     => 'object.php',
];