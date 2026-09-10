<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/firm_columns.php';
require_once __DIR__ . '/config/firm_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $firmPageConfig);

$tp->applyFilterWithLabel($conn, 'city_id', 'f.city_id', 'Город', 'city', 'city_id', 'city');

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$baseName = 'Фирмы';
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
    $widths = firm_columns_widths_export();
    $headers = [];
    foreach ($tp->visibleColumns as $vc) $headers[] = $vc['label'];
    $data = array_map(function ($r) use ($tp) {
        $line = [];
        foreach ($tp->visibleColumns as $vc) {
            $cn = $vc['name'];
            $line[] = match($cn) {
                'id' => (int)$r['firm_id'],
                'city' => (string)($r['city'] ?? ''),
                default => (string)($r[$cn] ?? ''),
            };
        }
        return $line;
    }, $rows);
    $pdf->addTable($widths, $headers, $data);
    $pdf->output($baseName . '.pdf');
} else {
    $tp->renderExport($format, $rows, ['baseName' => $baseName, 'colValues' => ['id' => fn($r) => (string)(int)$r['firm_id']]]);
}