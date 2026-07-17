<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/template-engine.php';
require_once __DIR__ . '/lib/sum_propis.php';

$invoiceId = (int)($_GET['id'] ?? 0);
$templateFile = (string)($_GET['template'] ?? 'sdoc/invoice_template.html');
$invoiceKind = (string)($_GET['kind'] ?? $_SESSION['invoice_kind'] ?? '');
$invoiceIsOffer = ($invoiceKind === 'offer');

if ($invoiceId <= 0) {
    echo '<p>Не указан ID счета.</p>';
    exit;
}

// --- Счёт ---
$stmt = $conn->prepare("
    SELECT i.number, i.date, i.time, i.client_id, i.state, i.store_id,
           i.discount, i.sum, i.sum_nds, i.sum_plat, i.sotr_id, i.pos, i.note,
           c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
           c.inn AS client_inn, c.kpp AS client_kpp,
           c.director AS client_director, c.bank AS client_bank,
           c.bik AS client_bik, c.schet AS client_schet, c.kschet AS client_kschet,
            c.title AS client_title, c.jur_name AS client_jur_name,
            st.name AS store_name, s.doc_name AS sotr_name
    FROM invoice i
    LEFT JOIN client c ON i.client_id = c.client_id
    LEFT JOIN store st ON i.store_id = st.store_id
    LEFT JOIN sotr s ON i.sotr_id = s.sotr_id
    WHERE i.invoice_id = ?
");
$stmt->bind_param('i', $invoiceId);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$inv) {
    echo '<p>' . ($invoiceIsOffer ? 'Коммерческое предложение' : 'Счет') . ' не найден.</p>';
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

// --- Месяцы (русские, родительный падеж) ---
$monthNames = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

// --- Парсинг даты ---
$day   = '';
$month = '';
$year  = '';
if (!empty($inv['date'])) {
    $ts = strtotime((string)$inv['date']);
    if ($ts !== false) {
        $day   = date('j', $ts);
        $month = $monthNames[(int)date('n', $ts)] ?? '';
        $year  = date('Y', $ts);
    }
}

// --- Товары ---
$items = [];
$stmt2 = $conn->prepare("SELECT i2.product_name, COALESCE(NULLIF(i2.code, ''), p.article, '') AS code, i2.quant, i2.price, i2.discount, i2.sum, i2.sum_nds, i2.note FROM invoice2 i2 LEFT JOIN product p ON i2.product_id = p.product_id WHERE i2.invoice_id = ? ORDER BY i2.invoice2_id");
$stmt2->bind_param('i', $invoiceId);
$stmt2->execute();
$res2 = $stmt2->get_result();
$idx = 0;
$totSum = 0;
$totSumNds = 0;
$totSSum = 0;
$totQuant = 0;
while ($row = $res2->fetch_assoc()) {
    $idx++;
    $itemSum    = (float)$row['sum'] - (float)$row['sum_nds'];
    $itemSumNds = (float)$row['sum_nds'];
    $itemSSum   = (float)$row['sum'];
    $totSum    += $itemSum;
    $totSumNds += $itemSumNds;
    $totSSum   += $itemSSum;
    $totQuant  += (float)$row['quant'];
    $items[] = [
        'ItemNum'     => (string)$idx,
        'ItemArticle' => (string)($row['code'] ?? ''),
        'ItemName'    => (string)$row['product_name'],
        'ItemQuant'   => rtrim(rtrim(number_format((float)$row['quant'], 3, '.', ''), '0'), '.'),
        'ItemPrice'   => number_format((float)$row['price'], 2, '.', ' '),
        'ItemSum'     => number_format($itemSum, 2, '.', ' '),
        'ItemSumN'    => number_format($itemSumNds, 2, '.', ' '),
        'ItemSSum'    => number_format($itemSSum, 2, '.', ' '),
        'ItemSumNo'   => number_format($itemSum, 2, '.', ' '),
        'ItemSumNDS'  => number_format($itemSumNds, 2, '.', ' '),
    ];
}
$stmt2->close();

// --- Итоги ---
$invSumNds = (float)$inv['sum_nds'];
$ndsStr = '';
if ($invSumNds > 0) {
    $ndsStr = 'В т.ч. НДС 20% — ' . number_format($invSumNds, 2, '.', ' ') . ' руб.';
}

$templatePath = __DIR__ . '/' . ltrim($templateFile, '/');

$vars = [
    'Number'     => (string)$inv['number'],
    'Date'       => !empty($inv['date']) ? date('d-m-Y', strtotime((string)$inv['date'])) : '',
    'Time'       => (string)$inv['time'],
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
    'FirmINN'      => (string)($firmRequisites['inn'] ?? ''),
    'FirmKPP'      => (string)($firmRequisites['kpp'] ?? ''),
    'FirmKPPLine'  => trim($firmRequisites['kpp'] ?? '') !== '' ? 'КПП: ' . $firmRequisites['kpp'] : '',
    'FirmSchet'    => (string)($firmRequisites['schet'] ?? ''),
    'FirmBank'     => (string)($firmRequisites['bank'] ?? ''),
    'FirmBIK'      => (string)($firmRequisites['bik'] ?? ''),
    'FirmKSchet'   => (string)($firmRequisites['kschet'] ?? ''),

    // Плательщик
    'ClientName'    => (string)($inv['client_jur_name'] ?? ''),
    'ClientPhone'   => (string)($inv['client_phone'] ?? ''),
    'ClientPhoneLine' => trim($inv['client_phone'] ?? '') !== '' ? 'Тел.: ' . $inv['client_phone'] : '',
    'ClientAddress' => (string)($inv['client_address'] ?? ''),
    'ClientINN'     => (string)($inv['client_inn'] ?? ''),
    'ClientKPP'     => (string)($inv['client_kpp'] ?? ''),
    'ClientDirector'=> (string)($inv['client_director'] ?? ''),
    'ClientFIO'     => (string)($inv['client_director'] ?? ''),
    'ClientTitle'   => (string)($inv['client_title'] ?? ''),
    'ClientBank'    => (string)($inv['client_bank'] ?? ''),
    'ClientBIK'     => (string)($inv['client_bik'] ?? ''),
    'ClientSchet'   => (string)($inv['client_schet'] ?? ''),
    'ClientKSchet'  => (string)($inv['client_kschet'] ?? ''),

    // Синонимы для шаблона договора
    'Name'     => (string)($inv['client_name'] ?? ''),
    'OrgName'  => (string)($inv['client_name'] ?? ''),
    'CompName' => (string)($inv['client_jur_name'] ?? ''),

    // Примечание
    'Note' => (string)($inv['note'] ?? ''),

    // Итого
    'TotQuant' => rtrim(rtrim(number_format($totQuant, 3, '.', ''), '0'), '.'),
    'TotSum'   => number_format($totSum, 2, '.', ' '),
    'TotSumN'  => number_format($totSumNds, 2, '.', ' '),
    'TotSSum'  => number_format($totSSum, 2, '.', ' '),
    'TotSumNo' => number_format($totSum, 2, '.', ' '),
    'SumNDS'   => number_format($totSumNds, 2, '.', ' '),

    // НДС
    'NDS_Str' => $ndsStr,

    // Сумма прописью
    'SumProp' => sum_propis((float)$inv['sum']),
];

$details = [
    'D1' => $items,
];

$output = render_template($templatePath, $vars, $details);
echo $output;

// --- Парсинг строки реквизитов ---
// Пример: "ИНН: 502402299282 , Расч. счет: 40802810702750000087 в АО "Альфа-Банк", БИК: 044525593, Корсчет: 30101810200000000593"
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
