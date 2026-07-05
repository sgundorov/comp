<?php
if (!defined('ZAT_COLUMNS_LOADED')) {
    define('ZAT_COLUMNS_LOADED', true);

    function zat_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',          'sort_expr' => 'z.zat_id',    'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',    'label' => 'Операция',    'sort_expr' => 'z.name',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'out_flag', 'label' => 'Расход',      'sort_expr' => 'z.out_flag',   'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',    'label' => 'Примечание',  'sort_expr' => 'z.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function zat_columns_widths_export() {
        return [
            'id'      => 40,
            'name'    => 200,
            'out_flag' => 80,
            'note'    => 200,
        ];
    }

    function zat_columns_widths_print() {
        return [
            'id'      => '60px',
            'name'    => '',
            'out_flag' => '60px',
            'note'    => '',
        ];
    }
}
