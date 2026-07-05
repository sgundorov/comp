<?php
if (!defined('REPMENU_COLUMNS_LOADED')) {
    define('REPMENU_COLUMNS_LOADED', true);

    function repmenu_columns_defaults() {
        return [
            ['name' => 'id',     'label' => 'Номер',        'sort_expr' => 'm.number',     'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',   'label' => 'Название',      'sort_expr' => 'm.name',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'fname',  'label' => 'Файл шаблон',   'sort_expr' => 'm.fname',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'quant',  'label' => 'Количество',    'sort_expr' => 'm.quant',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',   'label' => 'Примечание',    'sort_expr' => 'm.note',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function repmenu_columns_widths_export() {
        return [
            'id'    => 60,
            'name'  => 200,
            'fname' => 150,
            'quant' => 80,
            'note'  => 200,
        ];
    }

    function repmenu_columns_widths_print() {
        return [
            'id'    => '60px',
            'name'  => '',
            'fname' => '',
            'quant' => '80px',
            'note'  => '',
        ];
    }
}
