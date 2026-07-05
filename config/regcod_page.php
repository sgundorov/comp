<?php
require_once __DIR__ . '/regcod_columns.php';

$regcodPageConfig = [
    'table'        => 'regcod',
    'table_name'   => 'regcod',
    'table_alias'  => 'r',
    'key'          => 'regcod_id',
    'id_field'     => 'regcod_id',
    'select_sql'   => 'SELECT r.regcod_id, r.client_id, r.client, r.datetime, r.regcod, r.product_id, r.product, r.quant, r.date, r.city, r.signat, r.days, r.block_flag, r.note'
                      . ' FROM regcod r'
                      . ' LEFT JOIN client c ON c.client_id = r.client_id',
    'count_sql'    => 'SELECT COUNT(*) FROM regcod r LEFT JOIN client c ON c.client_id = r.client_id',
    'id_select_sql'=> 'SELECT regcod_id FROM regcod WHERE regcod_id = ?',
    'marks_tbl'    => 'regcod',
    'column_visibility_tbl' => 'regcod',
    'search_cols' => [
        'product'  => 'r.product',
        'regcod'   => 'r.regcod',
        'quant'    => 'r.quant',
        'date'     => 'r.date',
        'name'     => 'c.name',
        'city'     => 'r.city',
        'signat'   => 'r.signat',
        'days'     => 'r.days',
        'note'     => 'r.note',
    ],
    'search_labels' => [
        'product'  => 'Товар',
        'regcod'   => 'Рег. код',
        'quant'    => 'Количество',
        'date'     => 'Дата',
        'name'     => 'Клиент',
        'city'     => 'Город',
        'signat'   => 'Сигнатура',
        'days'     => 'Дней',
        'note'     => 'Примечание',
    ],
    'col_filters' => [],
];
