<?php
if (!defined('SGROUP_COLUMNS_LOADED')) {
    define('SGROUP_COLUMNS_LOADED', true);

    function sgroup_columns_defaults() {
        return [
            ['name' => 'id',    'label' => 'ID',          'sort_expr' => 'sg.sgroup_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',  'label' => 'Название подгруппы', 'sort_expr' => 'sg.name',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'group', 'label' => 'Группа',       'sort_expr' => 'g.name',       'search' => true,  'filter' => true,  'export' => true, 'param' => 'group_id'],
            ['name' => 'note',  'label' => 'Примечание',   'sort_expr' => 'sg.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function sgroup_columns_widths_export() {
        return [
            'id'    => 40,
            'name'  => 250,
            'group' => 200,
            'note'  => 120,
        ];
    }

    function sgroup_columns_widths_print() {
        return [
            'id'    => '60px',
            'name'  => '',
            'group' => '',
            'note'  => '',
        ];
    }
}
