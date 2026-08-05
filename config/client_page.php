<?php
require_once __DIR__ . '/client_columns.php';

$clientPageConfig = [
    'table'    => 'client',
    'key'      => 'client_id',
    'columns'  => client_columns_defaults(),
    'search_cols' => [
        'id'            => 'c.client_id',
        'name'          => "TRIM(CONCAT_WS(' ', c.last_name, c.first_name))",
        'last_name'     => 'c.last_name',
        'first_name'    => 'c.first_name',
        'title'         => 'c.title',
        'cli_categ_id'  => 'cat.categ',
        'phone'         => 'c.phone',
        'cphone'        => 'c.cphone',
        'email'         => 'c.email',
        'site'          => 'c.site',
        'city_id'       => 'ci.city',
        'country_id'    => 'co.country',
        'postindex'     => 'c.postindex',
        'address_jur'   => 'c.address_jur',
        'address'       => 'c.address',
        'pasport'       => 'c.pasport',
        'pasp_vydan'    => 'c.pasp_vydan',
        'promo_id'      => 'pr.promo',
        'dop1'          => 'c.dop1',
        'note'          => 'c.note',
    ],
    'default_sort'    => ['col' => 'id', 'dir' => 'desc'],
    'marks_session'   => 'client_select',
    'marks_tbl'       => 'client',
    'key_expr'        => 'c.client_id',
    'col_filters' => [
        'cli_categ_id' => ['c.cli_categ_id', 'cli_categ', 'cli_categ_id', 'categ'],
        'city_id'      => ['c.city_id', 'city', 'city_id', 'city'],
        'country_id'   => ['c.country_id', 'country', 'country_id', 'country'],
        'tags'         => ['col' => 'tag_id', 'table' => 'tag', 'id' => 'tag_id', 'label' => 'tag', 'subquery' => true],
    ],
    'select_sql'   => "SELECT c.client_id,
        c.name,
        c.last_name, c.first_name, c.title,
        c.cli_categ_id, cat.categ AS cli_categ_name,
        cat.color AS cli_categ_color,
        c.supplier_flag, c.problem_flag, c.juridical_flag, c.hide_flag,
        c.phone, c.cphone, c.email, c.site,
        c.city_id, ci.city AS city_name,
        c.country_id, co.country AS country_name,
        c.postindex, c.address_jur, c.address,
        c.pasport, c.pasp_date, c.pasp_vydan, c.birthday,
        c.promo_id, pr.promo AS promo_name,
        c.inn, c.kpp, c.ogrn, c.jur_name, c.director, c.glavbuh,
        c.bank, c.bik, c.schet, c.kschet, c.okonh, c.okpo,
        c.disc_goods, c.sum_nach, c.sum_plat,
        (c.sum_plat - c.sum_nach) AS sum_balans,
        c.bdate, c.dop1, c.note,
        (SELECT GROUP_CONCAT(tg.tag SEPARATOR ', ') FROM client_tag ctg JOIN tag tg ON tg.tag_id = ctg.tag_id WHERE ctg.client_id = c.client_id) AS tags_concat,
        (SELECT GROUP_CONCAT(ctg.tag_id SEPARATOR ',') FROM client_tag ctg WHERE ctg.client_id = c.client_id) AS tag_ids
        FROM client c
        LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
        LEFT JOIN city ci ON ci.city_id = c.city_id
        LEFT JOIN country co ON co.country_id = c.country_id
        LEFT JOIN promo pr ON pr.promo_id = c.promo_id",
    'count_sql'    => "SELECT COUNT(*) AS cnt FROM client c
        LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
        LEFT JOIN city ci ON ci.city_id = c.city_id
        LEFT JOIN country co ON co.country_id = c.country_id
        LEFT JOIN promo pr ON pr.promo_id = c.promo_id",
    'id_select_sql'=> "SELECT c.client_id AS id FROM client c
        LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
        LEFT JOIN city ci ON ci.city_id = c.city_id
        LEFT JOIN country co ON co.country_id = c.country_id
        LEFT JOIN promo pr ON pr.promo_id = c.promo_id",
    'base_url'     => 'client.php',
];
