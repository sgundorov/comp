<?php
if (!defined('STORE_COLUMNS_LOADED')) {
    define('STORE_COLUMNS_LOADED', true);

    function store_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',          'sort_expr' => 's.store_id',   'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',    'label' => 'Участок',     'sort_expr' => 's.name',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'address', 'label' => 'Адрес',       'sort_expr' => 's.address',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',    'label' => 'Примечание',  'sort_expr' => 's.note',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function store_columns_widths_export() {
        return [
            'id'      => 40,
            'name'    => 200,
            'address' => 250,
            'note'    => 200,
        ];
    }

    function store_columns_widths_print() {
        return [
            'id'      => '60px',
            'name'    => '',
            'address' => '',
            'note'    => '',
        ];
    }
}