<?php
if (!defined('CATEG_COLUMNS_LOADED')) {
    define('CATEG_COLUMNS_LOADED', true);

    function categ_columns_defaults() {
        return [
            ['name' => 'id',            'label' => 'ID',              'sort_expr' => 'c.categ_id',      'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'categ',         'label' => 'Категория',       'sort_expr' => 'c.categ',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'noquant_flag',  'label' => 'Не учитывать количество', 'sort_expr' => 'c.noquant_flag', 'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'service_flag',  'label' => 'Услуга',          'sort_expr' => 'c.service_flag',  'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',          'label' => 'Примечание',      'sort_expr' => 'c.note',          'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function categ_columns_widths_export() {
        return [
            'id'           => 40,
            'categ'        => 250,
            'noquant_flag' => 100,
            'service_flag' => 100,
            'note'         => 200,
        ];
    }

    function categ_columns_widths_print() {
        return [
            'id'           => '60px',
            'categ'        => '',
            'noquant_flag' => '100px',
            'service_flag' => '100px',
            'note'         => '',
        ];
    }
}
