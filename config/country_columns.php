<?php
if (!defined('COUNTRY_COLUMNS_LOADED')) {
    define('COUNTRY_COLUMNS_LOADED', true);

    function country_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',          'sort_expr' => 'c.country_id', 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'country', 'label' => 'Страна',       'sort_expr' => 'c.country',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',    'label' => 'Примечание',   'sort_expr' => 'c.note',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function country_columns_widths_export() {
        return [
            'id'      => 40,
            'country' => 200,
            'note'    => 120,
        ];
    }

    function country_columns_widths_print() {
        return [
            'id'      => '60px',
            'country' => '',
            'note'    => '',
        ];
    }
}
