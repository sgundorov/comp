<?php
function docum2_columns_defaults() {
    return [
        ['name' => 'product_name', 'label' => 'Товар'],
        ['name' => 'quant',        'label' => 'Кол-во'],
        ['name' => 'price',        'label' => 'Цена'],
        ['name' => 'discount',     'label' => 'Скидка'],
        ['name' => 'sum',          'label' => 'Сумма'],
        ['name' => 'note',         'label' => 'Примечание'],
    ];
}

function docum2_columns_widths_export() {
    return ['product_name' => 250, 'quant' => 80, 'price' => 90, 'discount' => 80, 'sum' => 100, 'note' => 200];
}

function docum2_columns_widths_print() {
    return ['product_name' => '', 'quant' => '80px', 'price' => '90px', 'discount' => '80px', 'sum' => '100px', 'note' => '200px'];
}
