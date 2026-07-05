<?php
function regcod_columns_defaults() {
    return [
        ['name' => 'product',   'label' => 'Товар',     'sort_expr' => 'r.product',   'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'regcod',    'label' => 'Рег. код',  'sort_expr' => 'r.regcod',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'quant',     'label' => 'Кол-во',    'sort_expr' => 'r.quant',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'date',      'label' => 'Дата',      'sort_expr' => 'r.date',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'name',      'label' => 'Клиент',    'sort_expr' => 'c.name',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'city',      'label' => 'Город',     'sort_expr' => 'r.city',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'signat',    'label' => 'Сигнатура', 'sort_expr' => 'r.signat',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'days',      'label' => 'Дней',      'sort_expr' => 'r.days',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ['name' => 'note',      'label' => 'Примечание','sort_expr' => 'r.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
    ];
}

function regcod_columns_widths_export() {
    return ['product' => 150, 'regcod' => 120, 'quant' => 60, 'date' => 90, 'name' => 150, 'city' => 120, 'signat' => 120, 'days' => 60, 'note' => 200];
}

function regcod_columns_widths_print() {
    return ['product' => '120px', 'regcod' => '100px', 'quant' => '60px', 'date' => '80px', 'name' => '120px', 'city' => '100px', 'signat' => '100px', 'days' => '50px', 'note' => '150px'];
}
