<?php
if (!defined('UNIT_COLUMNS_LOADED')) {
    define('UNIT_COLUMNS_LOADED', true);

    function unit_columns_defaults() {
        return [
            ['name' => 'id',   'label' => 'ID',             'sort_expr' => 'u.unit_id', 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'unit', 'label' => 'Ед. измерения',  'sort_expr' => 'u.unit',    'search' => true, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note', 'label' => 'Примечание',     'sort_expr' => 'u.note',    'search' => true, 'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function unit_columns_widths_export() {
        return [
            'id'   => 40,
            'unit' => 200,
            'note' => 120,
        ];
    }

    function unit_columns_widths_print() {
        return [
            'id'   => '60px',
            'unit' => '',
            'note' => '',
        ];
    }
}
