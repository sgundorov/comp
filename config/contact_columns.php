<?php
if (!defined('CONTACT_COLUMNS_LOADED')) {
    define('CONTACT_COLUMNS_LOADED', true);

    function contact_columns_defaults() {
        return [
            ['name' => 'id',             'label' => 'ID',                'sort_expr' => 'ct.contact_id',   'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'client',          'label' => 'Контрагент',        'sort_expr' => 'ct.cli_name',     'search' => true,  'filter' => true,  'export' => true, 'param' => 'client_id'],
            ['name' => 'datetime',        'label' => 'Дата/время',        'sort_expr' => 'ct.datetime',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'impotant_flag',   'label' => 'Важный',            'sort_expr' => 'ct.impotant_flag','search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'contype',          'label' => 'Вид контакта',     'sort_expr' => 'ct.contype',      'search' => true,  'filter' => true,  'export' => true, 'param' => 'contype_id'],
            ['name' => 'sotr',             'label' => 'Сотрудник',        'sort_expr' => 'st.last_name',    'search' => true,  'filter' => true,  'export' => true, 'param' => 'sotr_id'],
            ['name' => 'note',            'label' => 'Содержание контакта', 'sort_expr' => 'ct.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function contact_columns_widths_export() {
        return [
            'id'            => 40,
            'client'        => 200,
            'datetime'      => 160,
            'impotant_flag' => 80,
            'contype'       => 150,
            'sotr'          => 180,
            'note'          => 300,
        ];
    }

    function contact_columns_widths_print() {
        return [
            'id'            => '60px',
            'client'        => '',
            'datetime'      => '',
            'impotant_flag' => '',
            'contype'       => '',
            'sotr'          => '',
            'note'          => '',
        ];
    }
}
