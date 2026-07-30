<?php
$groupPageConfig = [
    'table'    => 'group',
    'key'      => 'group_id',
    'columns'  => group_columns_defaults(),
    'search_cols' => [
        'id'   => 'g.group_id',
        'name' => 'g.name',
        'note' => 'g.note',
    ],
    'search_labels' => [
        'id'   => 'ID',
        'name' => 'Название',
        'note' => 'Примечание',
    ],
    'default_sort'        => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'       => 'group_select',
    'marks_tbl'           => 'group',
    'column_visibility_tbl' => 'group',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'g.group_id',
    'col_filters' => [],
    'select_sql'   => "SELECT g.group_id, g.name, g.pos, g.note FROM `group` g",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM `group` g",
    'id_select_sql'=> "SELECT g.group_id AS id FROM `group` g",
    'base_url'     => 'group.php',
    'default_column_widths' => ['id' => 46, 'name' => 250, 'note' => 500],
];
