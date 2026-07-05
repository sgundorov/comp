<?php
if (!defined('TMC_COLUMNS_LOADED')) {
    define('TMC_COLUMNS_LOADED', true);

    function tmc_columns_defaults() {
        return [
            ['name' => 'id',       'label' => 'ID',              'sort_expr' => 'p.product_id', 'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'name',     'label' => 'Название',        'sort_expr' => 'p.product_name','search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'article',  'label' => 'Артикул',         'sort_expr' => 'p.article',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'categ',    'label' => 'Категория',       'sort_expr' => 'c.categ',       'search' => true,  'filter' => true,  'export' => true, 'param' => 'categ_id'],
            ['name' => 'group',    'label' => 'Группа',          'sort_expr' => 'g.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'group_id'],
            ['name' => 'sgroup',   'label' => 'Подгруппа',       'sort_expr' => 'sg.name',       'search' => true,  'filter' => true,  'export' => true, 'param' => 'sgroup_id'],
            ['name' => 'country',  'label' => 'Страна',          'sort_expr' => 'co.country',    'search' => true,  'filter' => true,  'export' => true, 'param' => 'country_id'],
            ['name' => 'quant',    'label' => 'Количество',      'sort_expr' => 'p.residue',     'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'price_in', 'label' => 'Закупочная цена', 'sort_expr' => 'p.price_in',    'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'price_out','label' => 'Розничная цена',  'sort_expr' => 'p.price_out',   'search' => false, 'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'note',     'label' => 'Примечание',      'sort_expr' => 'p.note',        'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function tmc_columns_widths_export() {
        return [
            'id'        => 8,
            'name'      => 40,
            'article'   => 15,
            'categ'     => 18,
            'group'     => 18,
            'sgroup'    => 18,
            'country'   => 15,
            'quant'     => 12,
            'price_in'  => 14,
            'price_out' => 14,
            'note'      => 30,
        ];
    }

    function tmc_columns_widths_print() {
        return [
            'id'        => 40,
            'name'      => 250,
            'article'   => 80,
            'categ'     => 120,
            'group'     => 120,
            'sgroup'    => 120,
            'country'   => 100,
            'quant'     => 80,
            'price_in'  => 100,
            'price_out' => 100,
            'note'      => 200,
        ];
    }
}
