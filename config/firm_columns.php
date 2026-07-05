<?php
if (!defined('FIRM_COLUMNS_LOADED')) {
    define('FIRM_COLUMNS_LOADED', true);

    function firm_columns_defaults() {
        return [
            ['name' => 'id',       'label' => 'ID',           'sort_expr' => 'f.firm_id',  'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',     'label' => 'Название',     'sort_expr' => 'f.name',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'address',  'label' => 'Адрес',        'sort_expr' => 'f.address',  'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'phone',    'label' => 'Телефон',      'sort_expr' => 'f.phone',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'email',    'label' => 'Email',        'sort_expr' => 'f.email',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'city',     'label' => 'Город',        'sort_expr' => 'c.city',     'search' => true,  'filter' => true,  'export' => true, 'param' => 'city_id'],
            ['name' => 'director', 'label' => 'Директор',     'sort_expr' => 'f.director', 'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',     'label' => 'Примечание',   'sort_expr' => 'f.note',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function firm_columns_widths_export() {
        return [
            'id'       => 40,
            'name'     => 200,
            'address'  => 200,
            'phone'    => 120,
            'email'    => 180,
            'city'     => 150,
            'director' => 150,
            'note'     => 120,
        ];
    }

    function firm_columns_widths_print() {
        return [
            'id'       => '60px',
            'name'     => '',
            'address'  => '',
            'phone'    => '',
            'email'    => '',
            'city'     => '',
            'director' => '',
            'note'     => '',
        ];
    }
}
