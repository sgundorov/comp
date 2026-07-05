<?php
if (!defined('ROLE_COLUMNS_LOADED')) {
    define('ROLE_COLUMNS_LOADED', true);

    function role_columns_defaults() {
        return [
            ['name' => 'id',   'label' => 'ID',          'sort_expr' => 'r.role_id',  'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'role', 'label' => 'Роль',        'sort_expr' => 'r.role',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note', 'label' => 'Примечание',  'sort_expr' => 'r.note',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function role_columns_widths_export() {
        return [
            'id'   => 40,
            'role' => 250,
            'note' => 200,
        ];
    }

    function role_columns_widths_print() {
        return [
            'id'   => '60px',
            'role' => '',
            'note' => '',
        ];
    }
}
