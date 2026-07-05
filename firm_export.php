<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/firm_columns.php';
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
if ($rawCols !== '') {
    $searchCols = array_values(array_filter(array_map('trim', explode(',', $rawCols)), function ($k) {
        return in_array($k, ['id', 'name', 'address', 'phone', 'email', 'city', 'director', 'note'], true);
    }));
}
$searchCond = (string)($_GET['cond'] ?? 'contains');
$allowedCond = ['contains', 'not_contains', 'starts_with', 'ends_with', 'equals', 'not_equals', 'gt', 'lt'];
if (!in_array($searchCond, $allowedCond, true)) $searchCond = 'contains';
if (!$searchActive) { $search = ''; $searchCols = []; }
if ($searchActive && $search !== '' && count($searchCols) === 0) {
    $searchCols = ['name', 'address', 'phone', 'email', 'city', 'director', 'note'];
}

$cityFilter = (string)($_GET['city_id'] ?? '');
$cityIds = [];
if ($cityFilter !== '') {
    $cityIds = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
}

$sortRaw = trim((string)($_GET['sort'] ?? ''));
$sortLevels = [];
if ($sortRaw !== '') {
    foreach (explode(',', $sortRaw) as $lv) {
        $pp = explode(':', $lv);
        $ck = $pp[0] ?? '';
        $dk = strtolower($pp[1] ?? 'asc');
        if (in_array($ck, ['id','name','address','phone','email','city','director','note'], true) && in_array($dk, ['asc','desc'], true)) {
            $sortLevels[] = ['col' => $ck, 'dir' => $dk];
        }
    }
}
if (count($sortLevels) === 0) {
    $sortLevels = [['col' => 'id', 'dir' => 'asc']];
}
$colToOrderSql = [
    'id'       => 'f.firm_id',
    'name'     => 'f.name',
    'address'  => 'f.address',
    'phone'    => 'f.phone',
    'email'    => 'f.email',
    'city'     => 'c.city',
    'director' => 'f.director',
    'note'     => 'f.note',
];
$orderParts = [];
foreach ($sortLevels as $sl) {
    $orderParts[] = $colToOrderSql[$sl['col']] . ' ' . strtoupper($sl['dir']);
}
$orderBy = implode(', ', $orderParts);

$COLUMN_DEFAULTS = firm_columns_defaults();
$COL_META = [];
foreach ($COLUMN_DEFAULTS as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']       = function ($r) { return (int)$r['firm_id']; };
$COL_META['name']['value']     = function ($r) { return (string)$r['name']; };
$COL_META['address']['value']  = function ($r) { return (string)$r['address']; };
$COL_META['phone']['value']    = function ($r) { return (string)$r['phone']; };
$COL_META['email']['value']    = function ($r) { return (string)$r['email']; };
$COL_META['city']['value']     = function ($r) { return (string)($r['city'] ?? ''); };
$COL_META['director']['value'] = function ($r) { return (string)$r['director']; };
$COL_META['note']['value']     = function ($r) { return (string)$r['note']; };
$COL_WIDTHS = firm_columns_widths_export();

$columnsConfig  = load_columns_config($conn, 'firm', $COLUMN_DEFAULTS);
$visibleColumns = array_values(array_filter($columnsConfig, function ($c) { return !empty($c['visible']); }));

$where  = '';
$params = [];
$types  = '';
if ($search !== '' && count($searchCols) > 0) {
    $colToExpr = [
        'name'     => 'f.name',
        'address'  => 'f.address',
        'phone'    => 'f.phone',
        'email'    => 'f.email',
        'city'     => 'c.city',
        'director' => 'f.director',
        'note'     => 'f.note',
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
if (count($cityIds) > 0) {
    $place = implode(',', array_fill(0, count($cityIds), '?'));
    $extra = "f.city_id IN ($place)";
    $where = $where === '' ? "WHERE $extra" : '(' . substr($where, 6) . ") AND $extra";
    $params = array_merge($params, $cityIds);
    $types  = $types . str_repeat('i', count($cityIds));
}

$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;
if ($onlySelected) {
    $marks = load_marks_set($conn, 'firm');
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $extra = "f.firm_id IN ($place)";
        $where = $where === '' ? "WHERE $extra" : '(' . substr($where, 6) . ") AND $extra";
        $params = array_merge($params, $selectedIds);
        $types  = $types . str_repeat('i', count($selectedIds));
    }
}

$sql = "SELECT f.firm_id, f.name, f.address, f.phone, f.email, f.director, f.note, c.city
        FROM firm f
        LEFT JOIN city c ON c.city_id = f.city_id
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

$baseName = 'Фирмы';

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
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><title>' . htmlspecialchars($baseName, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; } th, td { border: 1px solid #888; padding: 4px 8px; } th { background: #ddd; font-weight: bold; }</style>';
    echo '</head><body>';
    echo '<table><thead><tr>';
    foreach ($visibleCols as $vc) echo '<th>' . htmlspecialchars($colMeta[$vc['name']]['label'], ENT_QUOTES, 'UTF-8') . '</th>';
    echo '</tr></thead><tbody>';
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
