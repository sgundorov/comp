<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/regcod_columns.php';
require_once __DIR__ . '/config/regcod_page.php';

$tp = new TablePage($regcodPageConfig);
$tp->parseRequest();
$tp->buildWhere();
$tp->buildOrderBy();
$rows = $tp->getRows($conn);
$visibleColumns = $tp->getVisibleColumns();

$format = $_GET['format'] ?? 'csv';
$exportTimestamp = date('d.m.Y_H.i');
$filename = 'Регистрационные коды ' . $exportTimestamp;

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    $header = [];
    foreach ($visibleColumns as $vc) { $header[] = $vc['label']; }
    fputcsv($out, $header, ';');
    foreach ($rows as $r) {
        $line = [];
        foreach ($visibleColumns as $vc) { $line[] = $r[$vc['name']] ?? ''; }
        fputcsv($out, $line, ';');
    }
    fclose($out);
} else {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo '<table border="1"><tr>';
    foreach ($visibleColumns as $vc) { echo '<th>' . h($vc['label']) . '</th>'; }
    echo '</tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($visibleColumns as $vc) { echo '<td>' . h($r[$vc['name']] ?? '') . '</td>'; }
        echo '</tr>';
    }
    echo '</table>';
}
