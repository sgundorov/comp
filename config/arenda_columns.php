<?php
if (!defined('ARENDA_COLUMNS_LOADED')) {
    define('ARENDA_COLUMNS_LOADED', true);

    function arenda_columns_defaults() {
        return [
            ['name' => 'status',   'label' => '',          'sort_expr' => "CASE WHEN d.rezerv_flag=1 THEN 0 WHEN d.voz_flag=1 THEN 1 ELSE 2 END", 'search' => false, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true, 'no_resize' => true],
            ['name' => 'firm',     'label' => 'Фирма',         'sort_expr' => 'd.firm_id',                     'search' => true,  'filter' => false, 'export' => true,  'param' => null],
            ['name' => 'number',   'label' => 'Номер',         'sort_expr' => 'd.number',                      'search' => true,  'filter' => false, 'export' => true,  'param' => null, 'readonly' => true],
            ['name' => 'vremya',   'label' => 'Создано',         'sort_expr' => "CONCAT(d.date,' ',d.time)",     'search' => true,  'filter' => false, 'export' => true,  'param' => null, 'readonly' => true],
            ['name' => 'client',   'label' => 'Клиент',        'sort_expr' => 'cl.name',                       'search' => true,  'filter' => true,  'export' => true,  'param' => 'client_id'],
            ['name' => 'beg',      'label' => 'Начало',        'sort_expr' => "CONCAT(d.date_beg,' ',d.time_beg)", 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'vozvrat',  'label' => 'Возврат',       'sort_expr' => "CONCAT(d.date_voz,' ',d.time_voz)", 'search' => true, 'filter' => false, 'export' => true, 'param' => null, 'readonly' => true],
            ['name' => 'poz',      'label' => 'Позиций',       'sort_expr' => 'd.poz',                         'search' => false, 'filter' => false, 'export' => true,  'param' => null, 'readonly' => true],
            ['name' => 'sum',      'label' => 'Стоимость',     'sort_expr' => 'd.sum',                         'search' => true,  'filter' => false, 'export' => true,  'param' => null, 'readonly' => true],
            ['name' => 'sum_plat', 'label' => 'Оплата',        'sort_expr' => 'd.sum_plat',                    'search' => true,  'filter' => false, 'export' => true,  'param' => null, 'readonly' => true],
            ['name' => 'plat_type','label' => 'Вид оплаты',    'sort_expr' => 'd.plat_type',                   'search' => true,  'filter' => true,  'export' => true,  'param' => null],
            ['name' => 'sotr',     'label' => 'Сотрудник',     'sort_expr' => 'sl.last_name',                  'search' => true,  'filter' => true,  'export' => true,  'param' => 'sotr_id'],
            ['name' => 'note',     'label' => 'Примечание',    'sort_expr' => 'd.note',                        'search' => true,  'filter' => false, 'export' => true,  'param' => null],
        ];
    }

    function arenda_columns_widths_export() {
        return [
            'status' => 40, 'firm' => 50, 'number' => 70, 'vremya' => 140,
            'client' => 180, 'beg' => 140, 'vozvrat' => 140,
            'poz' => 50, 'sum' => 90, 'sum_plat' => 90, 'plat_type' => 90,
            'sotr' => 120, 'note' => 720,
        ];
    }

    function arenda_columns_widths_print() {
        return [
            'status' => '28px', 'firm' => '46px', 'number' => '60px',
            'vremya' => '120px', 'client' => '', 'beg' => '115px',
            'vozvrat' => '115px', 'poz' => '46px', 'sum' => '85px', 'sum_plat' => '85px',
            'plat_type' => '80px', 'sotr' => '100px', 'note' => '720px',
        ];
    }
}
