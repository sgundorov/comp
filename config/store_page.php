<?php
$storePageConfig = [
    'table'    => 'store',
    'key'      => 'store_id',
    'columns'  => store_columns_defaults(),
    'search_cols' => [
        'id'      => 's.store_id',
        'name'    => 's.name',
        'address' => 's.address',
        'note'    => 's.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'store_select',
    'marks_tbl'       => 'store',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 's.store_id',
    'col_filters' => [],
    'select_sql'   => "SELECT s.store_id, s.name, s.address, s.note FROM store s",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM store s",
    'id_select_sql'=> "SELECT s.store_id AS id FROM store s",
    'base_url'     => 'store.php',
];