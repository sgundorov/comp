<?php
if (!defined('CITY_COLUMNS_LOADED')) {
    define('CITY_COLUMNS_LOADED', true);

    function city_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',          'sort_expr' => 'c.city_id',    'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'city',    'label' => 'Город',        'sort_expr' => 'c.city',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'country', 'label' => 'Страна',       'sort_expr' => 'co.country',   'search' => true,  'filter' => true,  'export' => true, 'param' => 'country_id'],
            ['name' => 'note',    'label' => 'Примечание',   'sort_expr' => 'c.note',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function city_columns_widths_export() {
        return [
            'id'      => 40,
            'city'    => 200,
            'country' => 160,
            'note'    => 120,
        ];
    }

    function city_columns_widths_print() {
        return [
            'id'      => '60px',
            'city'    => '',
            'country' => '',
            'note'    => '',
        ];
    }
}
