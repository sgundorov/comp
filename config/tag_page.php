<?php
require_once __DIR__ . '/tag_columns.php';

$tagPageConfig = [
    'table'    => 'tag',
    'key'      => 'tag_id',
    'columns'  => tag_columns_defaults(),
    'search_cols' => [
        'id'   => 'tg.tag_id',
        'tag'  => 'tg.tag',
        'note' => 'tg.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'tag_select',
    'marks_tbl'       => 'tag',
    'key_expr'        => 'tg.tag_id',
    'col_filters' => [],
    'select_sql'   => "SELECT tg.tag_id, tg.tag, tg.note FROM tag tg",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM tag tg",
    'id_select_sql'=> "SELECT tg.tag_id AS id FROM tag tg",
    'base_url'     => 'cli_tag.php',
];
