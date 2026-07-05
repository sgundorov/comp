<?php
if (!defined('OBJECT_COLUMNS_LOADED')) {
    define('OBJECT_COLUMNS_LOADED', true);

    function object_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',          'sort_expr' => 'o.object_id', 'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'object',  'label' => 'Обозначение',  'sort_expr' => 'o.object',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'name',    'label' => 'Объект',       'sort_expr' => 'o.name',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'type',    'label' => 'Тип',          'sort_expr' => 'o.type',      'search' => true,  'filter' => true,  'export' => true, 'param' => null],
            ['name' => 'note',    'label' => 'Примечание',   'sort_expr' => 'o.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function object_columns_widths_export() {
        return [
            'id'      => 40,
            'object'  => 100,
            'name'    => 300,
            'type'    => 100,
            'note'    => 200,
        ];
    }

    function object_columns_widths_print() {
        return [
            'id'      => '60px',
            'object'  => '',
            'name'    => '',
            'type'    => '',
            'note'    => '',
        ];
    }
}