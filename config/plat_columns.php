<?php

function plat_columns_defaults() {
    return [
        ['name' => 'id',        'label' => 'ID',              'sort_expr' => 'p.plat_id',     'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'datetime',  'label' => 'Дата/Время',       'sort_expr' => 'p.datetime',    'search' => true, 'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'client',    'label' => 'Контрагент',       'sort_expr' => 'c.name',        'search' => true,  'filter' => false, 'export' => true, 'param' => 'client_id'],
        ['name' => 'zat',       'label' => 'Вид операции',     'sort_expr' => 'z.name',        'search' => true,  'filter' => false, 'export' => true, 'param' => 'zat_id'],
        ['name' => 'sum',       'label' => 'Сумма',            'sort_expr' => 'p.sum',         'search' => true, 'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'plat_type', 'label' => 'Вид платежа',      'sort_expr' => 'p.plat_type',   'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'doc_id',    'label' => 'Документ №',       'sort_expr' => 'p.doc_id',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'out_flag',  'label' => 'Тип',               'sort_expr' => 'p.out_flag',    'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'sotr',      'label' => 'Сотрудник',        'sort_expr' => 's.doc_name',    'search' => true,  'filter' => false, 'export' => true, 'param' => 'sotr_id'],
        ['name' => 'note',      'label' => 'Примечание',       'sort_expr' => 'p.note',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
    ];
}

function plat_columns_widths_export() {
    return ['id'=>40, 'datetime'=>120, 'client'=>250, 'zat'=>150, 'sum'=>100, 'plat_type'=>100, 'doc_id'=>80, 'out_flag'=>80, 'sotr'=>150, 'note'=>200];
}

function plat_columns_widths_print() {
    return ['id'=>'60px', 'datetime'=>'', 'client'=>'', 'zat'=>'', 'sum'=>'', 'plat_type'=>'', 'doc_id'=>'', 'out_flag'=>'', 'sotr'=>'', 'note'=>''];
}
