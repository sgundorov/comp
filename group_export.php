<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $groupPageConfig);

$idsParam = trim((string)($_GET['ids'] ?? ''));
$explicitIds = [];
if ($idsParam !== '') {
    $explicitIds = array_values(array_filter(array_map('intval', explode(',', $idsParam)), fn($v) => $v > 0));
}
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;

if (count($explicitIds) > 0) {
    $place = implode(',', array_fill(0, count($explicitIds), '?'));
    $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
    $extra = "$keyExpr IN ($place)";
    $tp->appendWhere($extra, $explicitIds, str_repeat('i', count($explicitIds)));
} elseif ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) {
        $skipQuery = true;
    } else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $keyExpr = $tp->keyExpr ?? ($tp->table . '.' . $tp->key);
        $extra = "$keyExpr IN ($place)";
        $tp->appendWhere($extra, $selectedIds, str_repeat('i', count($selectedIds)));
    }
}

$rows = [];
if (!$skipQuery) {
    $sql = str_placeholder($tp->selectSql, $tp->whereSql())
         . ' ORDER BY ' . $tp->orderBy;
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($tp->types !== '') stmt_bind($stmt, $tp->types, $tp->params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

$COL_META = [];
foreach ($tp->columns as $c) {
    $COL_META[$c['name']] = [
        'label' => $c['label'],
        'value' => null,
    ];
}
$COL_META['id']['value']   = function ($r) { return (int)$r['group_id']; };
$COL_META['name']['value'] = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['note']['value'] = function ($r) { return (string)($r['note'] ?? ''); };

$baseName = 'Группы товаров';

$customName = trim((string)($_GET['filename'] ?? ''));
if ($customName !== '') {
    $customName = preg_replace('/[\x00-\x1F\x7F\/\\\\<>:"|?*]+/u', '_', $customName);
    $customName = trim($customName, ". \t\n\r\0\x0B");
    $customName = mb_substr($customName, 0, 120, 'UTF-8');
    if ($customName !== '') {
        $customName = preg_replace('/\.(csv|xls)$/i', '', $customName);
        if ($customName !== '') {
            $baseName = $customName;
        }
    }
}

if ($format === 'csv') {
    exportCsv($rows, $baseName, $tp->visibleColumns, $COL_META);
} elseif ($format === 'xls') {
    exportXls($rows, $baseName, $tp->visibleColumns, $COL_META);
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
