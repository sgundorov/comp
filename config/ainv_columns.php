<?php
if (!defined('AINV_COLUMNS_LOADED')) {
    define('AINV_COLUMNS_LOADED', true);

    function ainv_columns_defaults() {
        return [
            ['name' => 'accept_flag', 'label' => 'Утверждено', 'sort_expr' => 'a.accept_flag', 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'number',      'label' => '№',           'sort_expr' => 'a.number',      'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'date',        'label' => 'Дата',        'sort_expr' => 'a.date',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'time',        'label' => 'Время',       'sort_expr' => 'a.time',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'mesto_id',    'label' => 'Место',       'sort_expr' => 'm.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'mesto_id'],
            ['name' => 'pos',         'label' => 'Позиций',     'sort_expr' => 'a.poz',         'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'sum',         'label' => 'Сумма',       'sort_expr' => 'a.sum',         'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'first_card',  'label' => 'Первая карточка', 'sort_expr' => 'a.first_card', 'search' => true, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'last_card',   'label' => 'Последняя карточка', 'sort_expr' => 'a.last_card', 'search' => true, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'firm_id',     'label' => 'Фирма',       'sort_expr' => 'f.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'firm_id'],
            ['name' => 'sotr_id',     'label' => 'Сотрудник',   'sort_expr' => 's.doc_name',    'search' => true,  'filter' => true,  'export' => true, 'param' => 'sotr_id'],
            ['name' => 'note',        'label' => 'Примечание',  'sort_expr' => 'a.note',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function ainv_columns_widths_export() {
        return [
            'accept_flag' => 25,
            'number'      => 60,
            'date'        => 80,
            'time'        => 60,
            'mesto_id'    => 100,
            'pos'         => 60,
            'sum'         => 90,
            'first_card'  => 60,
            'last_card'   => 60,
            'firm_id'     => 120,
            'sotr_id'     => 120,
            'note'        => 600,
        ];
    }

    function ainv_columns_widths_print() {
        return [
            'accept_flag' => '30px',
            'number'      => '60px',
            'date'        => '80px',
            'time'        => '60px',
            'mesto_id'    => '',
            'pos'         => '60px',
            'sum'         => '90px',
            'first_card'  => '50px',
            'last_card'   => '50px',
            'firm_id'     => '',
            'sotr_id'     => '',
            'note'        => '',
        ];
    }

    function ainv2_columns_defaults() {
        return [
            ['name' => 'product_name', 'label' => 'Товар'],
            ['name' => 'quant_old',    'label' => 'Документальный остаток'],
            ['name' => 'quant',        'label' => 'Фактический остаток'],
            ['name' => 'dif',          'label' => 'Разница'],
            ['name' => 'price',        'label' => 'Цена'],
            ['name' => 'sum',          'label' => 'Сумма'],
            ['name' => 'state',        'label' => 'Состояние'],
            ['name' => 'note',         'label' => 'Примечание'],
        ];
    }
}
