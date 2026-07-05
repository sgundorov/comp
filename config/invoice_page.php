<?php
require_once __DIR__ . '/invoice_columns.php';

$invoicePageConfig = [
    'table'         => 'invoice',
    'key'           => 'invoice_id',
    'columns'       => invoice_columns_defaults(),
    'search_cols'   => [
        'number'       => 'i.number',
        'date'         => 'i.date',
        'client'       => 'c.name',
        'state'        => 'i.state',
        'store'        => 'st.name',
        'discount'     => 'i.discount',
        'sum'          => 'i.sum',
        'pos'          => 'i.pos',
        'sotr'         => 's.doc_name',
        'note'         => 'i.note',
    ],
    'default_sort'    => ['col' => 'number', 'dir' => 'desc'],
    'marks_session'   => 'invoice_select',
    'marks_tbl'       => 'invoice',
    'key_expr'        => 'i.invoice_id',
    'col_filters' => [
        'client' => [null, 'client', 'client_id', 'name'],
        'store'  => [null, 'store', 'store_id', 'name'],
        'sotr'   => [null, 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))"],
    ],
    'select_sql'      => "SELECT i.invoice_id, i.number, i.date, i.time, i.client_id, i.state, i.store_id, i.zakaz_num, i.zakaz_id, i.zakaz_type, i.payment_type, i.discount, i.sum_discount, i.sum, i.sum_nds, i.sum_plat, i.date_plat, i.sotr_id, i.pos, i.note, c.name AS client_name, st.name AS store_name, s.doc_name AS sotr_name FROM invoice i LEFT JOIN client c ON i.client_id = c.client_id LEFT JOIN store st ON i.store_id = st.store_id LEFT JOIN sotr s ON i.sotr_id = s.sotr_id",
    'count_sql'       => "SELECT COUNT(*) AS cnt FROM invoice i LEFT JOIN client c ON i.client_id = c.client_id LEFT JOIN store st ON i.store_id = st.store_id LEFT JOIN sotr s ON i.sotr_id = s.sotr_id",
    'id_select_sql'   => "SELECT i.invoice_id AS id FROM invoice i LEFT JOIN client c ON i.client_id = c.client_id LEFT JOIN store st ON i.store_id = st.store_id LEFT JOIN sotr s ON i.sotr_id = s.sotr_id",
    'base_url'        => 'invoice.php',
];
