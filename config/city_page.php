<?php
$cityPageConfig = [
    'table'    => 'city',
    'key'      => 'city_id',
    'columns'  => city_columns_defaults(),
    'search_cols' => [
        'id'      => 'c.city_id',
        'city'    => 'c.city',
        'country' => 'co.country',
        'note'    => 'c.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'city_select',
    'marks_tbl'       => 'city',
    'country_filter_field' => 'country_id',
    'country_filter_expr'  => 'c.country_id',
    'key_expr'             => 'c.city_id',
    'col_filters' => [
        'country' => [null, 'country', 'country_id', 'country'],
    ],
    'select_sql'   => "SELECT c.city_id, c.city, c.country_id, c.note, co.country AS country_name FROM city c LEFT JOIN country co ON co.country_id = c.country_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM city c LEFT JOIN country co ON co.country_id = c.country_id",
    'id_select_sql'=> "SELECT c.city_id AS id FROM city c LEFT JOIN country co ON co.country_id = c.country_id",
    'base_url'     => 'city.php',
];
