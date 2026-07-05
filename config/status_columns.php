<?php
if (!defined('STATUS_COLUMNS_LOADED')) {
    define('STATUS_COLUMNS_LOADED', true);

    function status_columns_defaults() {
        return [
            ['name' => 'id',             'label' => 'ID',                'sort_expr' => 'st.status_id',  'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'status',          'label' => 'Статус',            'sort_expr' => 'st.status',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'color',           'label' => 'Цвет',              'sort_expr' => 'st.color',      'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',            'label' => 'Примечание',        'sort_expr' => 'st.note',       'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function status_columns_widths_export() {
        return [
            'id'     => 40,
            'status' => 200,
            'color'  => 100,
            'note'   => 120,
        ];
    }

    function status_columns_widths_print() {
        return [
            'id'     => '60px',
            'status' => '',
            'color'  => '',
            'note'   => '',
        ];
    }
}
