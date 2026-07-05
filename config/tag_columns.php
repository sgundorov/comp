<?php
if (!defined('TAG_COLUMNS_LOADED')) {
    define('TAG_COLUMNS_LOADED', true);

    function tag_columns_defaults() {
        return [
            ['name' => 'id',   'label' => 'ID',             'sort_expr' => 'tg.tag_id', 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'tag',  'label' => 'Вид деятельности','sort_expr' => 'tg.tag',    'search' => true, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note', 'label' => 'Примечание',     'sort_expr' => 'tg.note',   'search' => true, 'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function tag_columns_widths_export() {
        return [
            'id'   => 40,
            'tag'  => 200,
            'note' => 120,
        ];
    }

    function tag_columns_widths_print() {
        return [
            'id'   => '60px',
            'tag'  => '',
            'note' => '',
        ];
    }
}
