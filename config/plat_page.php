<?php

$platPageConfig = [
    'table'         => 'plat',
    'key'           => 'plat_id',
    'columns'       => plat_columns_defaults(),
    'search_cols'   => [
        'id'        => 'p.plat_id',
        'datetime'  => 'p.datetime',
        'client'    => 'c.name',
        'zat'       => 'z.name',
        'sum'       => 'p.sum',
        'out_flag'  => "CASE WHEN p.out_flag = 1 THEN 'Расход' ELSE 'Приход' END",
        'plat_type' => 'p.plat_type',
        'doc_id'    => 'p.doc_id',
        'sotr'      => 's.doc_name',
        'note'      => 'p.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'desc'],
    'marks_session'   => 'plat_select',
    'marks_tbl'       => 'plat',
    'key_expr'        => 'p.plat_id',
    'col_filters' => [
        'client'    => ['p.client_id', 'client', 'client_id', 'name'],
        'zat'       => ['p.zat_id', 'zat', 'zat_id', 'name'],
        'sotr'      => ['p.sotr_id', 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))"],
        'plat_type' => [
            'col' => 'p.plat_type',
            'param' => 'plat_type',
            'options' => [
                ['id' => 1, 'name' => 'Наличные'],
                ['id' => 2, 'name' => 'Безнал.'],
                ['id' => 3, 'name' => 'Карта'],
                ['id' => 4, 'name' => 'Прочее'],
            ],
        ],
        'out_flag' => [
            'options' => [
                ['id' => 0, 'name' => 'Приход'],
                ['id' => 1, 'name' => 'Расход'],
            ],
            'col' => 'p.out_flag',
            'param' => 'out_type',
            'value_key' => 'id',
        ],
    ],
    'select_sql'      => "SELECT p.plat_id, p.datetime, p.client_id, p.zat_id, p.sum, p.out_flag, p.sum_in, p.sum_out, p.plat_type, p.doc_id, p.sotr_id, p.note, c.name AS client_name, z.name AS zat_name, s.doc_name AS sotr_name FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id LEFT JOIN sotr s ON p.sotr_id = s.sotr_id",
    'count_sql'       => "SELECT COUNT(*) AS cnt FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id LEFT JOIN sotr s ON p.sotr_id = s.sotr_id",
    'id_select_sql'   => "SELECT p.plat_id AS id FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id LEFT JOIN sotr s ON p.sotr_id = s.sotr_id",
    'base_url'        => 'plat.php',
];
