<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $cityPageConfig);
$rows = $tp->fetchAll($conn);

$baseName = 'Города';
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
    $widths = city_columns_widths_export();
    $headers = [];
    $data = [];
    foreach ($tp->visibleColumns as $vc) {
        $headers[] = $vc['label'];
    }
    foreach ($rows as $r) {
        $line = [];
        foreach ($tp->visibleColumns as $vc) {
            $cn = $vc['name'];
            $v = match($cn) {
                'id' => (int)$r['city_id'],
                'city' => (string)($r['city'] ?? ''),
                'country' => (string)($r['country_name'] ?? ''),
                'note' => (string)($r['note'] ?? ''),
                default => (string)($r[$cn] ?? ''),
            };
            $line[] = $v;
        }
        $data[] = $line;
    }
    $pdf->addTable($widths, $headers, $data);
    $pdf->output($baseName . '.pdf');
} else {
    $tp->renderExport($format, $rows, ['baseName' => $baseName, 'colValues' => ['id' => fn($r) => (string)(int)$r['city_id']]]);
}