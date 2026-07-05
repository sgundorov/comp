<?php
if (!defined('REPGROUP_COLUMNS_LOADED')) {
    define('REPGROUP_COLUMNS_LOADED', true);

    function repgroup_columns_defaults() {
        return [
            ['name' => 'id',   'label' => 'ID',          'sort_expr' => 'rg.gr_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name', 'label' => 'Название',     'sort_expr' => 'rg.name', 'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note', 'label' => 'Примечание',   'sort_expr' => 'rg.note', 'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function repgroup_columns_widths_print() {
        return [
            'id'   => '60px',
            'name' => '',
            'note' => '',
        ];
    }
}
