<?php
if (!defined('CONTYPE_COLUMNS_LOADED')) {
    define('CONTYPE_COLUMNS_LOADED', true);

    function contype_columns_defaults() {
        return [
            ['name' => 'id',             'label' => 'ID',                'sort_expr' => 'ct.contype_id', 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'contype',         'label' => 'Вид контакта',      'sort_expr' => 'ct.contype',    'search' => true, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'impotant_flag',   'label' => 'Важный',            'sort_expr' => 'ct.impotant_flag','search' => false,'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',            'label' => 'Примечание',        'sort_expr' => 'ct.note',       'search' => true, 'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function contype_columns_widths_export() {
        return [
            'id'            => 40,
            'contype'       => 200,
            'impotant_flag' => 80,
            'note'          => 120,
        ];
    }

    function contype_columns_widths_print() {
        return [
            'id'            => '60px',
            'contype'       => '',
            'impotant_flag' => '',
            'note'          => '',
        ];
    }
}
