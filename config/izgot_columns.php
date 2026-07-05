<?php
if (!defined('IZGOT_COLUMNS_LOADED')) {
    define('IZGOT_COLUMNS_LOADED', true);

    function izgot_columns_defaults() {
        return [
            ['name' => 'id',      'label' => 'ID',             'sort_expr' => 'i.izgot_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'izgot',   'label' => 'Производитель',  'sort_expr' => 'i.izgot',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'country', 'label' => 'Страна',         'sort_expr' => 'co.country', 'search' => true,  'filter' => true,  'export' => true, 'param' => 'country_id'],
            ['name' => 'note',    'label' => 'Примечание',     'sort_expr' => 'i.note',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function izgot_columns_widths_export() {
        return [
            'id'      => 40,
            'izgot'   => 200,
            'country' => 150,
            'note'    => 120,
        ];
    }

    function izgot_columns_widths_print() {
        return [
            'id'      => '60px',
            'izgot'   => '',
            'country' => '',
            'note'    => '',
        ];
    }
}
