<?php
if (!defined('CLI_CATEG_COLUMNS_LOADED')) {
    define('CLI_CATEG_COLUMNS_LOADED', true);

    function cli_categ_columns_defaults() {
        return [
            ['name' => 'id',             'label' => 'ID',                    'sort_expr' => 'cc.cli_categ_id', 'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'categ',           'label' => 'Категория',            'sort_expr' => 'cc.categ',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'supplier_flag',   'label' => 'Поставщик',            'sort_expr' => 'cc.supplier_flag','search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'problem_flag',    'label' => 'Проблемный',           'sort_expr' => 'cc.problem_flag', 'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'color',           'label' => 'Цвет',                 'sort_expr' => 'cc.color',        'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',            'label' => 'Примечание',           'sort_expr' => 'cc.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function cli_categ_columns_widths_export() {
        return [
            'id'           => 40,
            'categ'        => 200,
            'supplier_flag'=> 80,
            'problem_flag' => 80,
            'color'        => 100,
            'note'         => 120,
        ];
    }

    function cli_categ_columns_widths_print() {
        return [
            'id'           => '60px',
            'categ'        => '',
            'supplier_flag'=> '',
            'problem_flag' => '',
            'color'        => '',
            'note'         => '',
        ];
    }
}
