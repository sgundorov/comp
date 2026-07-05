<?php
$sgroupKind = (string)($_GET['kind'] ?? '');
$sgroupIsService = ($sgroupKind === 'service');
$sgroupServiceFlag = $sgroupIsService ? '1' : '0';

$sgroupPageConfig = [
    'table'    => 'sgroup',
    'key'      => 'sgroup_id',
    'columns'  => sgroup_columns_defaults(),
    'search_cols' => [
        'id'    => 'sg.sgroup_id',
        'name'  => 'sg.name',
        'group' => 'g.name',
        'note'  => 'sg.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'asc'],
    'marks_session'   => 'sgroup_select',
    'marks_tbl'       => 'sgroup',
    'country_filter_field' => null,
    'country_filter_expr'  => null,
    'key_expr'             => 'sg.sgroup_id',
    'col_filters' => [
        'group' => [null, 'group', 'group_id', 'group'],
    ],
    'select_sql'   => "SELECT sg.sgroup_id, sg.name, sg.group_id, sg.note, g.name AS group_name FROM sgroup sg LEFT JOIN `group` g ON g.group_id = sg.group_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM sgroup sg LEFT JOIN `group` g ON g.group_id = sg.group_id",
    'id_select_sql'=> "SELECT sg.sgroup_id AS id FROM sgroup sg",
    'base_url'     => 'sgroup.php',
];
