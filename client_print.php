<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/config/client_page.php';

$tp = new TablePage($conn, $clientPageConfig);

$tp->applyFilterWithLabel($conn, 'cli_categ_id', 'c.cli_categ_id', 'Категория', 'cli_categ', 'cli_categ_id', 'cli_categ');
$tp->applyFilterWithLabel($conn, 'city_id',      'c.city_id',      'Город',     'city',      'city_id',     'city');
$tp->applyFilterWithLabel($conn, 'country_id',   'c.country_id',   'Страна',    'country',   'country_id',  'country');

$tagFilter = (string)($_GET['tag_id'] ?? '');
if ($tagFilter !== '') {
    $tagIds = array_values(array_filter(array_map('intval', explode(',', $tagFilter)), fn($v) => $v > 0));
    if (count($tagIds) > 0) {
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $tp->appendWhere("c.client_id IN (SELECT client_id FROM client_tag WHERE tag_id IN ($ph))", $tagIds, str_repeat('i', count($tagIds)));
    }
}

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$skipCols = ['last_name', 'first_name'];
$tp->visibleColumns = array_values(array_filter($tp->visibleColumns, fn($c) => !in_array($c['name'], $skipCols, true)));

$colValues = [
    'id'             => fn($r) => (int)$r['client_id'],
    'name'           => fn($r) => (string)($r['name'] ?? ''),
    'title'          => fn($r) => (string)($r['title'] ?? ''),
    'cli_categ_id'   => fn($r) => (string)($r['cli_categ_name'] ?? ''),
    'supplier_flag'  => fn($r) => (string)($r['supplier_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
    'problem_flag'   => fn($r) => (string)($r['problem_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
    'juridical_flag' => fn($r) => (string)($r['juridical_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
    'hide_flag'      => fn($r) => (string)($r['hide_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
    'phone'          => fn($r) => (string)($r['phone'] ?? ''),
    'cphone'         => fn($r) => (string)($r['cphone'] ?? ''),
    'email'          => fn($r) => (string)($r['email'] ?? ''),
    'site'           => fn($r) => (string)($r['site'] ?? ''),
    'city_id'        => fn($r) => (string)($r['city_name'] ?? ''),
    'country_id'     => fn($r) => (string)($r['country_name'] ?? ''),
    'postindex'      => fn($r) => (string)($r['postindex'] ?? ''),
    'address_jur'    => fn($r) => (string)($r['address_jur'] ?? ''),
    'address'        => fn($r) => (string)($r['address'] ?? ''),
    'pasport'        => fn($r) => (string)($r['pasport'] ?? ''),
    'pasp_date'      => fn($r) => ($v = (string)($r['pasp_date'] ?? '')) !== '' ? date('d.m.Y', strtotime($v)) : '',
    'pasp_vydan'     => fn($r) => (string)($r['pasp_vydan'] ?? ''),
    'birthday'       => fn($r) => ($v = (string)($r['birthday'] ?? '')) !== '' ? date('d.m.Y', strtotime($v)) : '',
    'promo_id'       => fn($r) => (string)($r['promo_name'] ?? ''),
    'inn'            => fn($r) => (string)($r['inn'] ?? ''),
    'kpp'            => fn($r) => (string)($r['kpp'] ?? ''),
    'ogrn'           => fn($r) => (string)($r['ogrn'] ?? ''),
    'jur_name'       => fn($r) => (string)($r['jur_name'] ?? ''),
    'director'       => fn($r) => (string)($r['director'] ?? ''),
    'glavbuh'        => fn($r) => (string)($r['glavbuh'] ?? ''),
    'bank'           => fn($r) => (string)($r['bank'] ?? ''),
    'bik'            => fn($r) => (string)($r['bik'] ?? ''),
    'schet'          => fn($r) => (string)($r['schet'] ?? ''),
    'kschet'         => fn($r) => (string)($r['kschet'] ?? ''),
    'okonh'          => fn($r) => (string)($r['okonh'] ?? ''),
    'okpo'           => fn($r) => (string)($r['okpo'] ?? ''),
    'disc_goods'     => fn($r) => (string)($r['disc_goods'] ?? ''),
    'sum_nach'       => fn($r) => (string)($r['sum_nach'] ?? '0'),
    'sum_plat'       => fn($r) => (string)($r['sum_plat'] ?? '0'),
    'sum_balans'     => fn($r) => (string)($r['sum_balans'] ?? '0'),
    'bdate'          => fn($r) => ($v = (string)($r['bdate'] ?? '')) !== '' ? date('d.m.Y', strtotime($v)) : '',
    'dop1'           => fn($r) => (string)($r['dop1'] ?? ''),
    'tags'           => fn($r) => (string)($r['tags_concat'] ?? ''),
    'note'           => fn($r) => (string)($r['note'] ?? ''),
];

$printWidths = [
    'id' => '35px', 'name' => '120px',
    'title' => '80px', 'cli_categ_id' => '80px', 'phone' => '90px', 'cphone' => '90px',
    'email' => '100px', 'site' => '80px', 'city_id' => '80px',
    'postindex' => '50px', 'address' => '100px', 'inn' => '80px', 'kpp' => '60px',
    'jur_name' => '100px', 'director' => '100px', 'schet' => '80px', 'kschet' => '80px',
    'bank' => '80px', 'bik' => '50px', 'dop1' => '60px', 'tags' => '80px',
];

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Контрагенты',
    'orientation' => 'landscape',
    'colValues' => $colValues,
    'printWidths' => $printWidths,
]);