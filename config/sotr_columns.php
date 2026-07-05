<?php
if (!defined('SOTR_COLUMNS_LOADED')) {
    define('SOTR_COLUMNS_LOADED', true);

    function sotr_columns_defaults() {
        return [
            ['name' => 'id',         'label' => 'ID',           'sort_expr' => 's.sotr_id',    'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'last_name',  'label' => 'Фамилия',      'sort_expr' => 's.last_name',   'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'first_name', 'label' => 'Имя',          'sort_expr' => 's.first_name',  'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'second_name','label' => 'Отчество',     'sort_expr' => 's.second_name', 'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'role',       'label' => 'Роль',         'sort_expr' => 'rl.role',       'search' => true,  'filter' => true, 'export' => true, 'param' => 'role_id'],
            ['name' => 'sphone',     'label' => 'Сотовый',      'sort_expr' => 's.sphone',      'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'address',    'label' => 'Адрес',        'sort_expr' => 's.address',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'title',      'label' => 'Должность',    'sort_expr' => 's.title',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'user_status','label' => 'Онлайн',       'sort_expr' => 's.user_status', 'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'note',       'label' => 'Примечание',   'sort_expr' => 's.note',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function sotr_columns_widths_export() {
        return [
            'id'          => 40,
            'last_name'   => 120,
            'first_name'  => 120,
            'second_name' => 120,
            'role'        => 120,
            'sphone'      => 120,
            'address'     => 200,
            'title'       => 150,
            'user_status' => 60,
            'note'        => 200,
        ];
    }

    function sotr_columns_widths_print() {
        return [
            'id'          => '50px',
            'last_name'   => '',
            'first_name'  => '',
            'second_name' => '',
            'role'        => '',
            'sphone'      => '',
            'address'     => '',
            'title'       => '',
            'user_status' => '60px',
            'note'        => '',
        ];
    }
}
