<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/config/client_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

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
$rows = $tp->fetchAll($conn);

$colValues = [
    'id'             => fn($r) => (int)$r['client_id'],
    'name'           => fn($r) => (string)($r['name'] ?? ''),
    'last_name'      => fn($r) => (string)($r['last_name'] ?? ''),
    'first_name'     => fn($r) => (string)($r['first_name'] ?? ''),
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

$baseName = 'Контрагенты';
$customName = trim((string)($_GET['filename'] ?? ''));
if ($customName !== '') {
    $customName = preg_replace('/[\x00-\x1F\x7F\/\\\\<>:"|?*]+/u', '_', $customName);
    $customName = trim($customName, ". \t\n\r\0\x0B");
    $customName = mb_substr($customName, 0, 120, 'UTF-8');
    if ($customName !== '') {
        $customName = preg_replace('/\.(csv|xls|pdf)$/i', '', $customName);
        if ($customName !== '') $baseName = $customName;
    }
}

if ($format === 'pdf') {
    require_once __DIR__ . '/lib/SimplePdf.php';
    $pdf = new SimplePdf();
    $pdf->addTitle($baseName);
    $pdf->addSubtitle('Сформировано: ' . date('d.m.Y H:i'), 9);
    $widths = client_columns_widths_export();
    $headers = [];
    foreach ($tp->visibleColumns as $vc) $headers[] = $vc['label'];
    $data = array_map(function ($r) use ($colValues, $tp) {
        $line = [];
        foreach ($tp->visibleColumns as $vc) {
            $cn = $vc['name'];
            $fn = $colValues[$cn] ?? null;
            $line[] = $fn ? $fn($r) : ((string)($r[$cn] ?? ''));
        }
        return $line;
    }, $rows);
    $pdf->addTable($widths, $headers, $data);
    $pdf->output($baseName . '.pdf');
} else {
    $tp->renderExport($format, $rows, ['baseName' => $baseName, 'colValues' => $colValues]);
}