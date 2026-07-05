<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/lib/SimplePdf.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$search  = trim((string)($_GET['q'] ?? ''));
$searchActive = ((string)($_GET['sf'] ?? '0') === '1');
$searchCols = [];
$rawCols = (string)($_GET['cols'] ?? '');
$clientSearchCols = ['id', 'name', 'last_name', 'first_name', 'title', 'cli_categ_id', 'phone', 'cphone', 'email', 'site', 'city_id', 'country_id', 'postindex', 'address_jur', 'address', 'pasport', 'pasp_vydan', 'promo_id', 'inn', 'kpp', 'ogrn', 'jur_name', 'director', 'glavbuh', 'bank', 'bik', 'schet', 'kschet', 'okonh', 'okpo', 'dop1', 'tags', 'note'];
if ($rawCols !== '') {
    $searchCols = array_values(array_filter(array_map('trim', explode(',', $rawCols)), function ($k) {
        global $clientSearchCols;
        return in_array($k, $clientSearchCols, true);
    }));
}
$searchCond = (string)($_GET['cond'] ?? 'contains');
$allowedCond = ['contains', 'not_contains', 'starts_with', 'ends_with', 'equals', 'not_equals', 'gt', 'lt'];
if (!in_array($searchCond, $allowedCond, true)) $searchCond = 'contains';
if (!$searchActive) {
    $search = '';
    $searchCols = [];
}
if ($searchActive && $search !== '' && count($searchCols) === 0) {
    $searchCols = $clientSearchCols;
}

$sortRaw = trim((string)($_GET['sort'] ?? ''));
$sortLevels = [];
$clientSortCols = ['id', 'name', 'last_name', 'first_name', 'title', 'cli_categ_id', 'supplier_flag', 'problem_flag', 'juridical_flag', 'hide_flag', 'phone', 'cphone', 'email', 'site', 'city_id', 'country_id', 'postindex', 'address_jur', 'address', 'pasport', 'pasp_date', 'pasp_vydan', 'birthday', 'promo_id', 'inn', 'kpp', 'ogrn', 'jur_name', 'director', 'glavbuh', 'bank', 'bik', 'schet', 'kschet', 'okonh', 'okpo', 'disc_goods', 'sum_nach', 'sum_plat', 'sum_balans', 'bdate', 'dop1', 'tags', 'note'];
if ($sortRaw !== '') {
    foreach (explode(',', $sortRaw) as $lv) {
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, $clientSortCols, true) && in_array($dk, ['asc', 'desc'], true)) {
            $sortLevels[] = ['col' => $ck, 'dir' => $dk];
        }
    }
}
if (count($sortLevels) === 0) {
    $sortLevels = [['col' => 'id', 'dir' => 'desc']];
}
$colToOrderSql = [
    'id'             => 'c.client_id',
    'name'           => 'c.last_name',
    'last_name'      => 'c.last_name',
    'first_name'     => 'c.first_name',
    'title'          => 'c.title',
    'cli_categ_id'   => 'cat.categ',
    'supplier_flag'  => 'c.supplier_flag',
    'problem_flag'   => 'c.problem_flag',
    'juridical_flag' => 'c.juridical_flag',
    'hide_flag'      => 'c.hide_flag',
    'phone'          => 'c.phone',
    'cphone'         => 'c.cphone',
    'email'          => 'c.email',
    'site'           => 'c.site',
    'city_id'        => 'ci.city',
    'country_id'     => 'co.country',
    'postindex'      => 'c.postindex',
    'address_jur'    => 'c.address_jur',
    'address'        => 'c.address',
    'pasport'        => 'c.pasport',
    'pasp_date'      => 'c.pasp_date',
    'pasp_vydan'     => 'c.pasp_vydan',
    'birthday'       => 'c.birthday',
    'promo_id'       => 'pr.promo',
    'inn'            => 'c.inn',
    'kpp'            => 'c.kpp',
    'ogrn'           => 'c.ogrn',
    'jur_name'       => 'c.jur_name',
    'director'       => 'c.director',
    'glavbuh'        => 'c.glavbuh',
    'bank'           => 'c.bank',
    'bik'            => 'c.bik',
    'schet'          => 'c.schet',
    'kschet'         => 'c.kschet',
    'okonh'          => 'c.okonh',
    'okpo'           => 'c.okpo',
    'disc_goods'     => 'c.disc_goods',
    'sum_nach'       => 'c.sum_nach',
    'sum_plat'       => 'c.sum_plat',
    'sum_balans'     => 'c.sum_balans',
    'bdate'          => 'c.bdate',
    'dop1'           => 'c.dop1',
    'tags'           => 'tags_concat',
    'note'           => 'c.note',
];
$orderParts = [];
foreach ($sortLevels as $sl) {
    $orderParts[] = ($colToOrderSql[$sl['col']] ?? 'c.client_id') . ' ' . strtoupper($sl['dir']);
}
$orderBy = implode(', ', $orderParts);

$COLUMN_DEFAULTS = client_columns_defaults();
$COL_META = [];
foreach ($COLUMN_DEFAULTS as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']             = function ($r) { return (int)$r['client_id']; };
$COL_META['name']['value']           = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['last_name']['value']      = function ($r) { return (string)($r['last_name'] ?? ''); };
$COL_META['first_name']['value']     = function ($r) { return (string)($r['first_name'] ?? ''); };
$COL_META['title']['value']          = function ($r) { return (string)($r['title'] ?? ''); };
$COL_META['cli_categ_id']['value']   = function ($r) { return (string)($r['cli_categ_name'] ?? ''); };
$COL_META['supplier_flag']['value']  = function ($r) { return (string)($r['supplier_flag'] ?? '0'); };
$COL_META['problem_flag']['value']   = function ($r) { return (string)($r['problem_flag'] ?? '0'); };
$COL_META['juridical_flag']['value'] = function ($r) { return (string)($r['juridical_flag'] ?? '0'); };
$COL_META['hide_flag']['value']      = function ($r) { return (string)($r['hide_flag'] ?? '0'); };
$COL_META['phone']['value']          = function ($r) { return (string)($r['phone'] ?? ''); };
$COL_META['cphone']['value']         = function ($r) { return (string)($r['cphone'] ?? ''); };
$COL_META['email']['value']          = function ($r) { return (string)($r['email'] ?? ''); };
$COL_META['site']['value']           = function ($r) { return (string)($r['site'] ?? ''); };
$COL_META['city_id']['value']        = function ($r) { return (string)($r['city_name'] ?? ''); };
$COL_META['country_id']['value']     = function ($r) { return (string)($r['country_name'] ?? ''); };
$COL_META['postindex']['value']      = function ($r) { return (string)($r['postindex'] ?? ''); };
$COL_META['address_jur']['value']    = function ($r) { return (string)($r['address_jur'] ?? ''); };
$COL_META['address']['value']        = function ($r) { return (string)($r['address'] ?? ''); };
$COL_META['pasport']['value']        = function ($r) { return (string)($r['pasport'] ?? ''); };
$COL_META['pasp_date']['value']      = function ($r) { $v = (string)($r['pasp_date'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['pasp_vydan']['value']     = function ($r) { return (string)($r['pasp_vydan'] ?? ''); };
$COL_META['birthday']['value']       = function ($r) { $v = (string)($r['birthday'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['promo_id']['value']       = function ($r) { return (string)($r['promo_name'] ?? ''); };
$COL_META['inn']['value']            = function ($r) { return (string)($r['inn'] ?? ''); };
$COL_META['kpp']['value']            = function ($r) { return (string)($r['kpp'] ?? ''); };
$COL_META['ogrn']['value']           = function ($r) { return (string)($r['ogrn'] ?? ''); };
$COL_META['jur_name']['value']       = function ($r) { return (string)($r['jur_name'] ?? ''); };
$COL_META['director']['value']       = function ($r) { return (string)($r['director'] ?? ''); };
$COL_META['glavbuh']['value']        = function ($r) { return (string)($r['glavbuh'] ?? ''); };
$COL_META['bank']['value']           = function ($r) { return (string)($r['bank'] ?? ''); };
$COL_META['bik']['value']            = function ($r) { return (string)($r['bik'] ?? ''); };
$COL_META['schet']['value']          = function ($r) { return (string)($r['schet'] ?? ''); };
$COL_META['kschet']['value']         = function ($r) { return (string)($r['kschet'] ?? ''); };
$COL_META['okonh']['value']          = function ($r) { return (string)($r['okonh'] ?? ''); };
$COL_META['okpo']['value']           = function ($r) { return (string)($r['okpo'] ?? ''); };
$COL_META['disc_goods']['value']     = function ($r) { return (string)($r['disc_goods'] ?? ''); };
$COL_META['sum_nach']['value']       = function ($r) { return (string)($r['sum_nach'] ?? '0'); };
$COL_META['sum_plat']['value']       = function ($r) { return (string)($r['sum_plat'] ?? '0'); };
$COL_META['sum_balans']['value']     = function ($r) { return (string)($r['sum_balans'] ?? '0'); };
$COL_META['bdate']['value']          = function ($r) { $v = (string)($r['bdate'] ?? ''); return $v !== '' ? date('d.m.Y', strtotime($v)) : ''; };
$COL_META['dop1']['value']           = function ($r) { return (string)($r['dop1'] ?? ''); };
$COL_META['tags']['value']           = function ($r) { return (string)($r['tags_concat'] ?? ''); };
$COL_META['note']['value']           = function ($r) { return (string)($r['note'] ?? ''); };
$COL_WIDTHS = client_columns_widths_export();

$columnsConfig  = load_columns_config($conn, 'client', $COLUMN_DEFAULTS);
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where  = '';
$params = [];
$types  = '';
if ($search !== '' && count($searchCols) > 0) {
    $colToExpr = [
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
        'inn'           => 'c.inn',
        'kpp'           => 'c.kpp',
        'ogrn'          => 'c.ogrn',
        'jur_name'      => 'c.jur_name',
        'director'      => 'c.director',
        'glavbuh'       => 'c.glavbuh',
        'bank'          => 'c.bank',
        'bik'           => 'c.bik',
        'schet'         => 'c.schet',
        'kschet'        => 'c.kschet',
        'okonh'         => 'c.okonh',
        'okpo'          => 'c.okpo',
        'dop1'          => 'c.dop1',
        'tags'          => 'tags_concat',
        'note'          => 'c.note',
    ];
    $condToOp = [
        'contains'     => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'starts_with'  => 'LIKE',
        'ends_with'    => 'LIKE',
        'equals'       => '=',
        'not_equals'   => '<>',
        'gt'           => '>',
        'lt'           => '<',
    ];
    $op = $condToOp[$searchCond] ?? 'LIKE';
    $parts = [];
    foreach ($searchCols as $col) {
        if (!isset($colToExpr[$col])) continue;
        $parts[] = $colToExpr[$col] . ' ' . $op . ' ?';
        switch ($searchCond) {
            case 'contains':     $params[] = '%' . $search . '%'; break;
            case 'not_contains': $params[] = '%' . $search . '%'; break;
            case 'starts_with':  $params[] = $search . '%'; break;
            case 'ends_with':    $params[] = '%' . $search; break;
            case 'equals':       $params[] = $search; break;
            case 'not_equals':   $params[] = $search; break;
            case 'gt':           $params[] = $search; break;
            case 'lt':           $params[] = $search; break;
            default:             $params[] = '%' . $search . '%';
        }
        $types .= 's';
    }
    if (count($parts) > 0) {
        $where = 'WHERE (' . implode(' OR ', $parts) . ')';
    }
}

$cliCategFilter = (string)($_GET['cli_categ_id'] ?? '');
if ($cliCategFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $cliCategFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.cli_categ_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
    }
}

$cityFilter = (string)($_GET['city_id'] ?? '');
if ($cityFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.city_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
    }
}

$countryFilter = (string)($_GET['country_id'] ?? '');
if ($countryFilter !== '') {
    $ids = array_values(array_filter(array_map('intval', explode(',', $countryFilter)), fn($v) => $v > 0));
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $extra = "c.country_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $ids);
        $types  = $types . str_repeat('i', count($ids));
    }
}

$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;
if ($onlySelected) {
    $marks = load_marks_set($conn, 'client');
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $extra = "c.client_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : 'WHERE (' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $selectedIds);
        $types  = $types . str_repeat('i', count($selectedIds));
    }
}

$sql = "SELECT c.client_id,
    TRIM(CONCAT_WS(' ', c.last_name, c.first_name)) AS name,
    c.last_name, c.first_name, c.title,
    c.cli_categ_id, cat.categ AS cli_categ_name,
    c.supplier_flag, c.problem_flag, c.juridical_flag, c.hide_flag,
    c.phone, c.cphone, c.email, c.site,
    c.city_id, ci.city AS city_name,
    c.country_id, co.country AS country_name,
    c.postindex, c.address_jur, c.address,
    c.pasport, c.pasp_date, c.pasp_vydan, c.birthday,
    c.promo_id, pr.promo AS promo_name,
    c.inn, c.kpp, c.ogrn, c.jur_name, c.director, c.glavbuh,
    c.bank, c.bik, c.schet, c.kschet, c.okonh, c.okpo,
    c.disc_goods, c.sum_nach, c.sum_plat, c.sum_balans,
    c.bdate, c.dop1, c.note,
    (SELECT GROUP_CONCAT(tg.tag SEPARATOR ', ') FROM client_tag ctg JOIN tag tg ON tg.tag_id = ctg.tag_id WHERE ctg.client_id = c.client_id) AS tags_concat
    FROM client c
    LEFT JOIN cli_categ cat ON cat.cli_categ_id = c.cli_categ_id
    LEFT JOIN city ci ON ci.city_id = c.city_id
    LEFT JOIN country co ON co.country_id = c.country_id
    LEFT JOIN promo pr ON pr.promo_id = c.promo_id
    $where
    ORDER BY $orderBy";
$rows = [];
if (!$skipQuery) {
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($types !== '') stmt_bind($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

$baseName = 'Контрагенты';

$customName = trim((string)($_GET['filename'] ?? ''));
if ($customName !== '') {
    $customName = preg_replace('/[\x00-\x1F\x7F\/\\\\<>:"|?*]+/u', '_', $customName);
    $customName = trim($customName, ". \t\n\r\0\x0B");
    $customName = mb_substr($customName, 0, 120, 'UTF-8');
    if ($customName !== '') {
        $customName = preg_replace('/\.(csv|xls|pdf)$/i', '', $customName);
        if ($customName !== '') {
            $baseName = $customName;
        }
    }
}

if ($format === 'csv') {
    exportCsv($rows, $baseName, $visibleColumns, $COL_META);
} elseif ($format === 'xls') {
    exportXls($rows, $baseName, $visibleColumns, $COL_META);
} elseif ($format === 'pdf') {
    exportPdf($rows, $baseName, $visibleColumns, $COL_META, $COL_WIDTHS);
}
return;

function exportCsv(array $rows, string $baseName, array $visibleCols, array $colMeta) {
    $filename = $baseName . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    $headers = [];
    foreach ($visibleCols as $vc) $headers[] = $colMeta[$vc['name']]['label'];

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';', '"', '\\');
    foreach ($rows as $r) {
        $cells = [];
        foreach ($visibleCols as $vc) $cells[] = $colMeta[$vc['name']]['value']($r);
        fputcsv($out, $cells, ';', '"', '\\');
    }
    fclose($out);
}

function exportXls(array $rows, string $baseName, array $visibleCols, array $colMeta) {
    $filename = $baseName . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><title>' . htmlspecialchars($baseName, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; } th, td { border: 1px solid #888; padding: 4px 8px; } th { background: #ddd; font-weight: bold; }</style>';
    echo '</head><body>';
    echo '<table>';
    echo '<thead><tr>';
    foreach ($visibleCols as $vc) echo '<th>' . htmlspecialchars($colMeta[$vc['name']]['label'], ENT_QUOTES, 'UTF-8') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($visibleCols as $vc) {
            $v = $colMeta[$vc['name']]['value']($r);
            echo '<td>' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
}

function exportPdf(array $rows, string $baseName, array $visibleCols, array $colMeta, array $colWidths) {
    $filename = $baseName . '.pdf';
    $pdf = new SimplePdf();
    $pdf->addTitle($baseName);
    $pdf->addSubtitle('Сформировано: ' . date('d.m.Y H:i'), 9);
    $widths = [];
    $headers = [];
    foreach ($visibleCols as $vc) {
        $widths[]  = $colWidths[$vc['name']] ?? 120;
        $headers[] = $colMeta[$vc['name']]['label'];
    }
    $data = array_map(function ($r) use ($visibleCols, $colMeta) {
        $row = [];
        foreach ($visibleCols as $vc) $row[] = $colMeta[$vc['name']]['value']($r);
        return $row;
    }, $rows);
    $pdf->addTable($widths, $headers, $data);
    $pdf->output($filename);
}
