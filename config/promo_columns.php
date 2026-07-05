<?php
if (!defined('PROMO_COLUMNS_LOADED')) {
    define('PROMO_COLUMNS_LOADED', true);

    function promo_columns_defaults() {
        return [
            ['name' => 'promo_id', 'label' => 'ID',              'sort_expr' => 'p.promo_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'promo',    'label' => 'Вид рекламы',      'sort_expr' => 'p.promo',    'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'visible' => false],
            ['name' => 'bdate',    'label' => 'Дата начала',      'sort_expr' => 'p.bdate',    'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'visible' => false],
            ['name' => 'edate',    'label' => 'Дата окончания',   'sort_expr' => 'p.edate',    'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'visible' => false],
            ['name' => 'count',    'label' => 'Кол-во клиентов','sort_expr' => 'p.count',  'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'procent',  'label' => 'Процент',          'sort_expr' => 'p.procent',  'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',     'label' => 'Примечание',       'sort_expr' => 'p.note',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function promo_columns_widths_export() {
        return ['promo_id' => 40, 'promo' => 200, 'count' => 60, 'procent' => 60, 'note' => 120];
    }

    function promo_columns_widths_print() {
        return ['promo_id' => '60px', 'promo' => '', 'count' => '80px', 'procent' => '80px', 'note' => ''];
    }
}
