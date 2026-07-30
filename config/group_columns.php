<?php
if (!defined('GROUP_COLUMNS_LOADED')) {
    define('GROUP_COLUMNS_LOADED', true);

    function group_columns_defaults() {
        return [
            ['name' => 'id',   'label' => 'ID',          'sort_expr' => 'g.group_id',  'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name', 'label' => 'Название',     'sort_expr' => 'g.name',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'pos',  'label' => 'Позиций',      'sort_expr' => 'g.pos',       'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'note', 'label' => 'Примечание',   'sort_expr' => 'g.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function group_columns_widths_export() {
        return [
            'id'   => 40,
            'name' => 250,
            'pos'  => 60,
            'note' => 200,
        ];
    }

    function group_columns_widths_print() {
        return [
            'id'   => '60px',
            'name' => '',
            'pos'  => '60px',
            'note' => '',
        ];
    }
}
