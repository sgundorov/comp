<?php
require_once __DIR__ . '/arenda_columns.php';

$arendaPageConfig = [
    'table'         => 'docum',
    'key'           => 'docum_id',
    'columns'       => arenda_columns_defaults(),
    'search_cols'   => [
        'number'    => 'd.number',
        'vremya'    => "CONCAT(d.date,' ',d.time)",
        'client'    => 'cl.name',
        'beg'       => "CONCAT(d.date_beg,' ',d.time_beg)",
        'vozvrat'   => "CONCAT(d.date_voz,' ',d.time_voz)",
        'note'      => 'd.note',
        'plat_type' => 'd.plat_type',
        'firm'      => 'd.firm_id',
    ],
    'search_labels' => [
        'number'    => 'Номер',
        'vremya'    => 'Создано',
        'client'    => 'Клиент',
        'beg'       => 'Начало',
        'vozvrat'   => 'Возврат',
        'note'      => 'Примечание',
        'plat_type' => 'Вид оплаты',
        'firm'      => 'Фирма',
    ],
    'default_sort'    => ['col' => 'number', 'dir' => 'desc'],
    'marks_session'   => 'arenda_select',
    'marks_tbl'       => 'arenda',
    'column_visibility_tbl' => 'arenda',
    'key_expr'        => 'd.docum_id',
    'col_filters' => [
        'client'   => ['d.client_id', 'client', 'client_id', 'name'],
        'sotr'     => ['d.sotr_id', 'sotr', 'sotr_id', 'last_name'],
        'plat_type' => [
            'col'      => 'd.plat_type',
            'param'    => 'plat_type',
            'value_key'=> 'name',
            'options'  => [
                ['id' => 1, 'name' => 'Наличные'],
                ['id' => 2, 'name' => 'Безнал.'],
                ['id' => 3, 'name' => 'Карта'],
                ['id' => 4, 'name' => 'Прочее'],
            ],
        ],
    ],
    'select_sql'      => "SELECT d.docum_id, d.rezerv_flag, d.voz_flag, d.firm_id, d.number, d.date, d.time, d.client_id, d.date_beg, d.time_beg, d.date_voz, d.time_voz, d.pos AS poz, d.sum, d.sum_plat, d.plat_type, d.sotr_id, d.note, cl.name AS client_name, sl.last_name AS sotr_name FROM docum d LEFT JOIN client cl ON d.client_id = cl.client_id LEFT JOIN sotr sl ON d.sotr_id = sl.sotr_id",
    'count_sql'       => "SELECT COUNT(*) AS cnt FROM docum d LEFT JOIN client cl ON d.client_id = cl.client_id LEFT JOIN sotr sl ON d.sotr_id = sl.sotr_id",
    'id_select_sql'   => "SELECT d.docum_id AS id FROM docum d LEFT JOIN client cl ON d.client_id = cl.client_id LEFT JOIN sotr sl ON d.sotr_id = sl.sotr_id",
    'base_url'        => 'arenda.php',
];
