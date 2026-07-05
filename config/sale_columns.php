<?php
function sale_columns_defaults() {
    return [
        ['name' => 'accept',   'label' => 'Утв.',       'sort_expr' => '',  'search' => false, 'filter' => false, 'export' => false,'param' => null, 'readonly' => true, 'no_resize' => true],
        ['name' => 'number',   'label' => 'Док. №',     'sort_expr' => 'd.number',       'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'date',     'label' => 'Дата',        'sort_expr' => 'd.date',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'client',   'label' => 'Контрагент',  'sort_expr' => 'c.name',         'search' => true,  'filter' => true,  'export' => true, 'param' => 'client_id'],
        ['name' => 'store',    'label' => 'Участок',     'sort_expr' => 'st.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'store_id'],
        ['name' => 'store2',   'label' => 'Куда',        'sort_expr' => 'st2.name',       'search' => true,  'filter' => true,  'export' => true, 'param' => 'store2_id'],
        ['name' => 'discount', 'label' => 'Скидка %',    'sort_expr' => 'd.discount',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'sum',      'label' => 'Сумма',       'sort_expr' => 'd.sum',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'sum_plat', 'label' => 'Оплачено',    'sort_expr' => 'd.sum_plat',     'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'pos',      'label' => 'Позиций',     'sort_expr' => 'd.pos',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'note',     'label' => 'Примечание',  'sort_expr' => 'd.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
    ];
}

function sale_columns_widths_export() {
    return ['number'=>80, 'date'=>90, 'client'=>200, 'store'=>150, 'store2'=>150, 'discount'=>80, 'sum'=>100, 'sum_plat'=>100, 'pos'=>60, 'note'=>200];
}

function sale_columns_widths_print() {
    return ['accept'=>'40px', 'number'=>'80px', 'date'=>'90px', 'client'=>'', 'store'=>'105px', 'store2'=>'105px', 'discount'=>'80px', 'sum'=>'105px', 'sum_plat'=>'105px', 'pos'=>'60px', 'note'=>''];
}
