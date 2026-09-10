<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sale_columns.php';
require_once __DIR__ . '/config/sale_page.php';

$DOC_LABELS = [40 => 'Возврат от покупателя', 20 => 'Приход', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$typeop = (int)($_GET['typeop'] ?? 120);
if (!in_array($typeop, [40, 20, 100, 110, 120, 127], true)) $typeop = 120;

$salePageConfig['marks_session'] = 'sale_select_' . $typeop;
$salePageConfig['marks_tbl'] = 'docum_' . $typeop;

$tp = new TablePage($conn, $salePageConfig);
$tp->appendWhere("d.typeop = ?", [$typeop], 'i');

$tp->applyFilterWithLabel($conn, 'client_id', 'd.client_id', 'Контрагент', 'client', 'client_id', 'name');
$tp->applyFilterWithLabel($conn, 'store_id',  'd.store_id',  'Склад',      'store',  'store_id',  'name');

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$colValues = [
    'accept'   => fn($r) => (int)($r['accept_flag'] ?? 0) > 0 ? '1' : '',
    'number'   => fn($r) => (int)($r['number'] ?? 0) > 0 ? (int)$r['number'] : '',
    'date'     => fn($r) => ($dt = strtotime((string)($r['date'] ?? ''))) ? date('d-m-Y H:i', $dt) : '',
    'client'   => fn($r) => (string)($r['client_name'] ?? ''),
    'store'    => fn($r) => (string)($r['store_name'] ?? ''),
    'discount' => fn($r) => (string)($r['discount'] ?? ''),
    'sum'      => fn($r) => number_format((float)($r['sum'] ?? 0), 2, ',', ''),
    'sum_plat' => fn($r) => number_format((float)($r['sum_plat'] ?? 0), 2, ',', ''),
    'pos'      => fn($r) => (int)($r['pos'] ?? 0) > 0 ? (int)$r['pos'] : '',
    'note'     => fn($r) => (string)($r['note'] ?? ''),
];

$docLabel = $DOC_LABELS[$typeop] ?? 'Документ';
$baseName = $docLabel . ' ' . date('d.m.Y_H.i');

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
    $widths = sale_columns_widths_export();
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