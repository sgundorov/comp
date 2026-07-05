<?php
require_once __DIR__ . '/contact_columns.php';

$contactPageConfig = [
    'table'    => 'contact',
    'key'      => 'contact_id',
    'columns'  => contact_columns_defaults(),
    'search_cols' => [
        'id'             => 'ct.contact_id',
        'client'         => 'ct.cli_name',
        'datetime'       => 'ct.datetime',
        'impotant_flag'  => 'ct.impotant_flag',
        'contype'        => 'ct.contype',
        'sotr'           => "TRIM(CONCAT_WS(' ', st.last_name, st.first_name))",
        'note'           => 'ct.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'desc'],
    'marks_session'   => 'contact_select',
    'marks_tbl'       => 'contact',
    'key_expr'        => 'ct.contact_id',
    'col_filters' => [
        'client'  => [null, 'client', 'client_id', 'name'],
        'contype' => [null, 'contype', 'contype_id', 'contype'],
        'sotr'    => [null, 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))"],
    ],
    'select_sql'   => "SELECT ct.contact_id, ct.client_id, ct.cli_name, ct.datetime, ct.impotant_flag, ct.contype_id, ct.contype, ct.sotr_id, ct.note,
        TRIM(CONCAT_WS(' ', st.last_name, st.first_name)) AS sotr_name
        FROM contact ct
        LEFT JOIN sotr st ON st.sotr_id = ct.sotr_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM contact ct LEFT JOIN sotr st ON st.sotr_id = ct.sotr_id",
    'id_select_sql'=> "SELECT ct.contact_id AS id FROM contact ct LEFT JOIN sotr st ON st.sotr_id = ct.sotr_id",
    'base_url'     => 'contact.php',
];
