<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repgroup_columns.php';
require_once __DIR__ . '/config/repgroup_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) { http_response_code(400); echo 'Unknown format'; exit; }

$tp = new TablePage($conn, $repgroupPageConfig);
$onlySelected = ((string)($_GET['all'] ?? '0') === '1');
$skipQuery = false;

if ($onlySelected) {
    $marks = load_marks_set($conn, $tp->marksTbl);
    $selectedIds = array_keys($marks);
    if (count($selectedIds) === 0) { $skipQuery = true; }
    else {
        $place = implode(',', array_fill(0, count($selectedIds), '?'));
        $tp->appendWhere("{$tp->key} IN ($place)", $selectedIds, str_repeat('i', count($selectedIds)));
    }
}

$rows = [];
if (!$skipQuery) {
    $sql = str_placeholder($tp->selectSql, $tp->whereSql()) . ' ORDER BY ' . $tp->orderBy;
    $stmt = @mysqli_prepare($conn, $sql);
    if ($stmt) { if ($tp->types !== '') stmt_bind($stmt, $tp->types, $tp->params); $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r; $stmt->close(); }
}

$COL_META = [];
foreach ($tp->columns as $c) $COL_META[$c['name']] = ['label' => $c['label'], 'value' => null];
$COL_META['id']['value']   = function ($r) { return (int)$r['gr_id']; };
$COL_META['name']['value'] = function ($r) { return (string)($r['name'] ?? ''); };
$COL_META['note']['value'] = function ($r) { return (string)($r['note'] ?? ''); };

$baseName = 'Группы шаблонов документов';
if ($format === 'csv') { exportCsv($rows, $baseName, $tp->visibleColumns, $COL_META); }
elseif ($format === 'xls') { exportXls($rows, $baseName, $tp->visibleColumns, $COL_META); }
return;

function exportCsv(array $rows, string $baseName, array $visibleCols, array $colMeta) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $baseName . '.csv"'); header('Cache-Control: no-cache'); header('Pragma: no-cache');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    $headers = []; foreach ($visibleCols as $vc) $headers[] = $colMeta[$vc['name']]['label'];
    fputcsv($out, $headers, ';', '"', '\\');
    foreach ($rows as $r) { $cells = []; foreach ($visibleCols as $vc) $cells[] = $colMeta[$vc['name']]['value']($r); fputcsv($out, $cells, ';', '"', '\\'); }
    fclose($out);
}
function exportXls(array $rows, string $baseName, array $visibleCols, array $colMeta) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $baseName . '.xls"'); header('Cache-Control: no-cache'); header('Pragma: no-cache');
    echo '<html><head><meta charset="UTF-8"><style>table{border-collapse:collapse;font-family:Arial;font-size:14px}th,td{border:1px solid #888;padding:4px 8px}th{background:#ddd;font-weight:bold}</style></head><body><table><thead><tr>';
    foreach ($visibleCols as $vc) echo '<th>' . h($colMeta[$vc['name']]['label']) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) { echo '<tr>'; foreach ($visibleCols as $vc) echo '<td>' . h((string)$colMeta[$vc['name']]['value']($r)) . '</td>'; echo '</tr>'; }
    echo '</tbody></table></body></html>';
}
