<?php
if (!defined('PRPLAN_COLUMNS_LOADED')) {
    define('PRPLAN_COLUMNS_LOADED', true);

    function prplan_columns_defaults() {
        return [
            ['name' => 'id',    'label' => 'ID',              'sort_expr' => 'c.prplan_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'prplan','label' => 'Тарифный план',   'sort_expr' => 'c.prplan',    'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'bdate', 'label' => 'Начало сезона',   'sort_expr' => 'c.bdate',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'edate', 'label' => 'Конец сезона',    'sort_expr' => 'c.edate',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',  'label' => 'Примечание',      'sort_expr' => 'c.note',      'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function prplan_columns_widths_export() {
        return [
            'id'     => 40,
            'prplan' => 200,
            'bdate'  => 100,
            'edate'  => 100,
            'note'   => 150,
        ];
    }

    function prplan_columns_widths_print() {
        return [
            'id'     => '60px',
            'prplan' => '',
            'bdate'  => '90px',
            'edate'  => '90px',
            'note'   => '',
        ];
    }
}
