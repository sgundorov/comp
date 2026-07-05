<?php
$SALES_COLUMNS_LOADED = 1;

function sales_columns_defaults() {
    return [
        ['name' => 'accept',   'label' => '',           'sort_expr' => 'd.accept_flag', 'search' => false, 'filter' => false, 'export' => false,'param' => null, 'readonly' => true],
        ['name' => 'number',   'label' => 'Док. №',     'sort_expr' => 'd.number',       'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'date',     'label' => 'Дата',        'sort_expr' => 'd.date',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'time',     'label' => 'Время',       'sort_expr' => 'd.time',         'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'visible' => false, 'readonly' => true],
        ['name' => 'client',   'label' => 'Контрагент',  'sort_expr' => 'c.name',         'search' => true,  'filter' => true,  'export' => true, 'param' => 'client_id'],
        ['name' => 'store',    'label' => 'Участок',     'sort_expr' => 'st.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'store_id'],
        ['name' => 'discount', 'label' => 'Скидка %',    'sort_expr' => 'd.discount',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'sum_discount','label' => 'Скидка',   'sort_expr' => 'd.sum_discount', 'search' => true,  'filter' => false, 'export' => false,'param' => null,'visible' => false, 'readonly' => true],
        ['name' => 'sum',      'label' => 'Сумма',       'sort_expr' => 'd.sum',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'sum_plat', 'label' => 'Оплачено',    'sort_expr' => 'd.sum_plat',     'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'date_plat','label' => 'Дата оплаты', 'sort_expr' => 'd.date_plat',    'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'visible' => false],
        ['name' => 'sotr',     'label' => 'Сотрудник',   'sort_expr' => 'so.doc_name',    'search' => true,  'filter' => true,  'export' => true, 'param' => 'sotr_id'],
        ['name' => 'pos',      'label' => 'Позиций',     'sort_expr' => 'd.pos',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'note',     'label' => 'Примечание',  'sort_expr' => 'd.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
    ];
}

function sales_columns_widths_export() {
    return ['number'=>80, 'date'=>90, 'client'=>200, 'store'=>150, 'discount'=>80, 'sum'=>100, 'sum_plat'=>100, 'sotr'=>150, 'pos'=>60, 'note'=>200];
}

function sales_columns_widths_print() {
    return ['number'=>'80px', 'date'=>'90px', 'client'=>'', 'store'=>'105px', 'discount'=>'80px', 'sum'=>'105px', 'sum_plat'=>'105px', 'sotr'=>'', 'pos'=>'60px', 'note'=>''];
}
