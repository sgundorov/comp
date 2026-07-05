<?php
function invoice_columns_defaults() {
    return [
        ['name' => 'number',        'label' => 'Счет №',         'sort_expr' => 'i.number',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'date',          'label' => 'Дата',            'sort_expr' => 'i.date',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'client',        'label' => 'Контрагент',      'sort_expr' => 'c.name',         'search' => true,  'filter' => false, 'export' => true, 'param' => 'client_id'],
        ['name' => 'state',         'label' => 'Состояние',       'sort_expr' => 'i.state',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'store',         'label' => 'Участок',         'sort_expr' => 'st.name',        'search' => true,  'filter' => false, 'export' => true, 'param' => 'store_id'],
        ['name' => 'discount',      'label' => 'Скидка',          'sort_expr' => 'i.discount',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'sum',           'label' => 'Сумма',           'sort_expr' => 'i.sum',          'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'sotr',          'label' => 'Сотрудник',       'sort_expr' => 's.doc_name',     'search' => true,  'filter' => false, 'export' => true, 'param' => 'sotr_id'],
        ['name' => 'pos',           'label' => 'Позиций',         'sort_expr' => 'i.pos',          'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
        ['name' => 'note',          'label' => 'Примечание',      'sort_expr' => 'i.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
    ];
}

function invoice_columns_widths_export() {
    return ['number'=>80, 'date'=>90, 'client'=>200, 'state'=>100, 'store'=>150, 'discount'=>80, 'sum'=>100, 'sotr'=>150, 'pos'=>60, 'note'=>200];
}

function invoice_columns_widths_print() {
    return ['number'=>'80px', 'date'=>'90px', 'client'=>'', 'state'=>'100px', 'store'=>'105px', 'discount'=>'80px', 'sum'=>'105px', 'sotr'=>'105px', 'pos'=>'60px', 'note'=>''];
}

function invoice2_columns_defaults() {
    return [
        ['name' => 'product_name', 'label' => 'Товар'],
        ['name' => 'quant',        'label' => 'Кол-во'],
        ['name' => 'price',        'label' => 'Цена'],
        ['name' => 'discount',     'label' => 'Скидка'],
        ['name' => 'sum',          'label' => 'Сумма'],
        ['name' => 'note',         'label' => 'Примечание'],
    ];
}
