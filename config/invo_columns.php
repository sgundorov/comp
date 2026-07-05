<?php
if (!defined('INVO_COLUMNS_LOADED')) {
    define('INVO_COLUMNS_LOADED', true);

    function invo_columns_defaults() {
        return [
            ['name' => 'number',      'label' => 'Счет №',       'sort_expr' => 'i.number',       'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'date',        'label' => 'Дата',         'sort_expr' => 'i.date',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'time',        'label' => 'Время',        'sort_expr' => 'i.time',         'search' => true,  'filter' => false, 'export' => false, 'param' => null, 'readonly' => true, 'visible' => false],
            ['name' => 'client',      'label' => 'Контрагент',   'sort_expr' => 'c.name',         'search' => true,  'filter' => true,  'export' => true, 'param' => 'client_id'],
            ['name' => 'state',       'label' => 'Состояние',    'sort_expr' => 'i.state',        'search' => true,  'filter' => true,  'export' => true, 'param' => null],
            ['name' => 'store',       'label' => 'Участок',      'sort_expr' => 'st.name',        'search' => true,  'filter' => true,  'export' => true, 'param' => 'store_id'],
            ['name' => 'discount',    'label' => 'Скидка',       'sort_expr' => 'i.discount',     'search' => true,  'filter' => false, 'export' => true, 'param' => null],
            ['name' => 'sum_discount','label' => 'Сумма скидки', 'sort_expr' => 'i.sum_discount', 'search' => true,  'filter' => false, 'export' => false, 'param' => null, 'readonly' => true, 'visible' => false],
            ['name' => 'sum',         'label' => 'Сумма',        'sort_expr' => 'i.sum',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'sum_plat',    'label' => 'Оплачено',     'sort_expr' => 'i.sum_plat',     'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true, 'visible' => true],
            ['name' => 'sum_nds',     'label' => 'Сумма НДС',    'sort_expr' => 'i.sum_nds',      'search' => true,  'filter' => false, 'export' => false, 'param' => null, 'readonly' => true, 'visible' => false],
            ['name' => 'date_plat',   'label' => 'Дата оплаты',  'sort_expr' => 'i.date_plat',    'search' => true,  'filter' => false, 'export' => false, 'param' => null, 'readonly' => true, 'visible' => false],
            ['name' => 'sotr',        'label' => 'Сотрудник',    'sort_expr' => 's.doc_name',     'search' => true,  'filter' => true,  'export' => true, 'param' => 'sotr_id'],
            ['name' => 'pos',         'label' => 'Позиций',      'sort_expr' => 'i.pos',          'search' => true,  'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'note',        'label' => 'Примечание',   'sort_expr' => 'i.note',         'search' => true,  'filter' => false, 'export' => true, 'param' => null],
        ];
    }

    function invo_columns_widths_export() {
        return [
            'number'   => 80,
            'date'     => 80,
            'client'   => 150,
            'state'    => 80,
            'store'    => 100,
            'discount' => 60,
            'sum'      => 80,
            'sotr'     => 100,
            'pos'      => 60,
            'note'     => 120,
        ];
    }

    function invo_columns_widths_print() {
        return [
            'number'      => '80px',
            'date'        => '80px',
            'time'        => '60px',
            'client'      => '',
            'state'       => '80px',
            'store'       => '',
            'discount'    => '60px',
            'sum_discount'=> '80px',
            'sum'         => '80px',
            'sum_plat'    => '80px',
            'sum_nds'     => '80px',
            'date_plat'   => '80px',
            'sotr'        => '',
            'pos'         => '60px',
            'note'        => '',
        ];
    }
}
