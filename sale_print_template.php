<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/template-engine.php';
require_once __DIR__ . '/lib/sum_propis.php';

$saleId = (int)($_GET['id'] ?? 0);
$templateFile = (string)($_GET['template'] ?? '');

if ($saleId <= 0) {
    echo '<p>Не указан ID документа.</p>';
    exit;
}

// --- Документ (docum) ---
$stmt = $conn->prepare("
    SELECT d.docum_id, d.number, d.date, d.time, d.client_id, d.store_id, d.store2_id,
           d.discount, d.sum, d.sum_discount, d.pos, d.note, d.zakaz_num,
           d.sotr_id, d.sotr2_id,
           c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
           c.inn AS client_inn, c.kpp AS client_kpp,
           c.director AS client_director, c.jur_name AS client_jur_name,
           c.bank AS client_bank, c.bik AS client_bik,
           c.schet AS client_schet, c.kschet AS client_kschet, c.title AS client_title,
           st.name AS store_name,
           st2.name AS store2_name,
           s.doc_name AS sotr_name,
           s2.doc_name AS sotr2_name
    FROM docum d
    LEFT JOIN client c ON d.client_id = c.client_id
    LEFT JOIN store st ON d.store_id = st.store_id
    LEFT JOIN store st2 ON d.store2_id = st2.store_id
    LEFT JOIN sotr s ON d.sotr_id = s.sotr_id
    LEFT JOIN sotr s2 ON d.sotr2_id = s2.sotr_id
    WHERE d.docum_id = ?
");
$stmt->bind_param('i', $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    echo '<p>Документ не найден.</p>';
    exit;
}

// --- Поставщик (firm) ---
$curFirmId = (int)($appSettings['firm_id'] ?? 0);
$firm = [];
if ($curFirmId > 0) {
    $sf = $conn->prepare("SELECT f.name, f.address, f.phone, f.email, f.director, f.glavbuh, f.requisites, f.ogrn, ct.city AS city_name FROM firm f LEFT JOIN city ct ON f.city_id = ct.city_id WHERE f.firm_id = ?");
    $sf->bind_param('i', $curFirmId);
    $sf->execute();
    $firm = $sf->get_result()->fetch_assoc() ?: [];
    $sf->close();
}

$firmRequisites = parse_requisites($firm['requisites'] ?? '');

// --- Парсинг даты ---
$day   = '';
$month = '';
$year  = '';
$monthNames = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
if (!empty($sale['date'])) {
    $ts = strtotime((string)$sale['date']);
    if ($ts !== false) {
        $day   = date('j', $ts);
        $month = $monthNames[(int)date('n', $ts)] ?? '';
        $year  = date('Y', $ts);
    }
}

// --- Товары ---
$items = [];
$stmt2 = $conn->prepare("SELECT d2.product_name, COALESCE(NULLIF(d2.code, ''), p.article, '') AS code, d2.quant, d2.price, d2.discount, d2.sum, d2.note, p.unit_id, u.unit AS unit_name FROM docum2 d2 LEFT JOIN product p ON d2.product_id = p.product_id LEFT JOIN unit u ON p.unit_id = u.unit_id WHERE d2.docum_id = ? ORDER BY d2.docum2_id");
$stmt2->bind_param('i', $saleId);
$stmt2->execute();
$res2 = $stmt2->get_result();
$idx = 0;
$totSum = 0;
$totQuant = 0;
while ($row = $res2->fetch_assoc()) {
    $idx++;
    $itemSum = (float)$row['sum'];
    $itemQuant = (float)$row['quant'];
    $totSum += $itemSum;
    $totQuant += $itemQuant;
    $priceParts = explode('.', number_format((float)$row['price'], 2, '.', ''));
    $sumParts = explode('.', number_format($itemSum, 2, '.', ''));
    $items[] = [
        'ItemNum'       => (string)$idx,
        'N'             => (string)$idx,
        'ItemArticle'   => (string)($row['code'] ?? ''),
        'ItemName'      => (string)$row['product_name'],
        'ItemEd'        => (string)($row['unit_name'] ?? ''),
        'ItemQuant'     => rtrim(rtrim(number_format($itemQuant, 3, '.', ''), '0'), '.'),
        'ItemPrice'     => number_format((float)$row['price'], 2, '.', ' '),
        'ItemPriceRub'  => $priceParts[0] ?? '0',
        'ItemPriceKop'  => $priceParts[1] ?? '00',
        'ItemSum'       => number_format($itemSum, 2, '.', ' '),
        'ItemSumRub'    => $sumParts[0] ?? '0',
        'ItemSumKop'    => $sumParts[1] ?? '00',
    ];
}
$stmt2->close();

$totParts = explode('.', number_format($totSum, 2, '.', ''));

$templatePath = __DIR__ . '/' . ltrim($templateFile, '/');

$vars = [
    'Number'     => (string)$sale['number'],
    'Date'       => !empty($sale['date']) ? date('d-m-Y', strtotime((string)$sale['date'])) : '',
    'Time'       => (string)$sale['time'],
    'Day'        => $day,
    'Month'      => $month,
    'Year'       => $year,

    // Поставщик
    'FirmName'     => (string)($firm['name'] ?? ''),
    'FirmPhone'    => (string)($firm['phone'] ?? ''),
    'FirmAddress'  => (string)($firm['address'] ?? ''),
    'FirmCity'     => (string)($firm['city_name'] ?? ''),
    'FirmEmail'    => (string)($firm['email'] ?? ''),
    'FirmDirector' => (string)($firm['director'] ?? ''),
    'FirmGlavbuh'  => (string)($firm['glavbuh'] ?? $firmRequisites['glavbuh'] ?? ''),
    'FirmOGRN'     => (string)($firm['ogrn'] ?? ''),
    'FirmFIO'      => (string)($firm['director'] ?? ''),
    'FirmPhoneLine'=> trim($firm['phone'] ?? '') !== '' ? 'Тел.: ' . $firm['phone'] : '',
    'FirmINN'      => (string)($firmRequisites['inn'] ?? ''),
    'FirmKPP'      => (string)($firmRequisites['kpp'] ?? ''),
    'FirmKPPLine'  => trim($firmRequisites['kpp'] ?? '') !== '' ? 'КПП: ' . $firmRequisites['kpp'] : '',
    'FirmSchet'    => (string)($firmRequisites['schet'] ?? ''),
    'FirmBank'     => (string)($firmRequisites['bank'] ?? ''),
    'FirmBIK'      => (string)($firmRequisites['bik'] ?? ''),
    'FirmKSchet'   => (string)($firmRequisites['kschet'] ?? ''),

    // Склады
    'Store'  => (string)($sale['store_name'] ?? ''),
    'Store2' => (string)($sale['store2_name'] ?? ''),

    // Клиент (общие поля)
    'ClientName'     => (string)($sale['client_name'] ?? ''),
    'ClientPhone'    => (string)($sale['client_phone'] ?? ''),
    'ClientPhoneLine'=> trim($sale['client_phone'] ?? '') !== '' ? 'Тел.: ' . $sale['client_phone'] : '',
    'ClientAddress'  => (string)($sale['client_address'] ?? ''),
    'ClientINN'      => (string)($sale['client_inn'] ?? ''),
    'ClientKPP'      => (string)($sale['client_kpp'] ?? ''),
    'ClientDirector' => (string)($sale['client_director'] ?? ''),
    'ClientFIO'      => (string)($sale['client_director'] ?? ''),
    'ClientTitle'    => (string)($sale['client_title'] ?? ''),
    'ClientBank'     => (string)($sale['client_bank'] ?? ''),
    'ClientBIK'      => (string)($sale['client_bik'] ?? ''),
    'ClientSchet'    => (string)($sale['client_schet'] ?? ''),
    'ClientKSchet'   => (string)($sale['client_kschet'] ?? ''),
    'Name'           => (string)($sale['client_name'] ?? ''),
    'OrgName'        => (string)($sale['client_name'] ?? ''),
    'CompName'       => (string)($sale['client_jur_name'] ?? ''),

    // Сотрудники
    'Sotr'  => (string)($sale['sotr_name'] ?? ''),
    'Sotr2' => (string)($sale['sotr2_name'] ?? ''),

    // Номер заказа
    'ZakazNum'     => (string)($sale['zakaz_num'] ?? ''),
    'ZakazNumLine' => trim($sale['zakaz_num'] ?? '') !== '' ? 'По счету №: ' . $sale['zakaz_num'] : '',

    // Доверенность
    'DoverNum'  => '',
    'DoverDate' => '',

    // Примечание
    'Note' => (string)($sale['note'] ?? ''),

    // Итого
    'TotQuant'   => rtrim(rtrim(number_format($totQuant, 3, '.', ''), '0'), '.'),
    'TotSum'     => number_format($totSum, 2, '.', ' '),
    'TotSumN'    => '0',
    'TotSSum'    => number_format($totSum, 2, '.', ' '),
    'TotSumNo'   => number_format($totSum, 2, '.', ' '),
    'SumNDS'     => '0',
    'TotSumRub'  => $totParts[0] ?? '0',
    'TotSumKop'  => $totParts[1] ?? '00',
    'TotSumAll'  => number_format($totSum, 2, ',', ' '),

    // НДС
    'NDS_Str' => '',

    // Сумма прописью
    'SumProp' => sum_propis($totSum),
];

$details = [
    'D1' => $items,
];

$output = render_template($templatePath, $vars, $details);
echo $output;

// --- Парсинг строки реквизитов ---
function parse_requisites(string $text): array {
    $out = ['inn' => '', 'kpp' => '', 'schet' => '', 'bank' => '', 'bik' => '', 'kschet' => '', 'glavbuh' => ''];
    if ($text === '') return $out;

    if (preg_match('~ИНН[:\s]+(\S+)~u', $text, $m)) $out['inn'] = $m[1];
    if (preg_match('~КПП[:\s]+(\S+)~u', $text, $m)) $out['kpp'] = $m[1];
    if (preg_match('~(?:Расч[\.\s]*счет|Расч[\.\s]*счёт|р/с|счёт|счет)[:\s]+(\S+)~iu', $text, $m)) $out['schet'] = $m[1];
    if (preg_match('~в\s+(.+?)(?:\s*,|\s*БИК|$)~u', $text, $m)) $out['bank'] = trim($m[1]);
    if (preg_match('~БИК[:\s]+(\d+)~u', $text, $m)) $out['bik'] = $m[1];
    if (preg_match('~(?:Корр?[\.\s]*счет|Корр?[\.\s]*счёт|к/с)[:\s]+(\S+)~iu', $text, $m)) $out['kschet'] = $m[1];
    if (preg_match('~(?:Гл\.?\s*бух|главный\s*бухгалтер)[:\s]+(.+?)(?:\s*,|$)~iu', $text, $m)) $out['glavbuh'] = trim($m[1]);
    return $out;
}
