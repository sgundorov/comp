<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';
require_once __DIR__ . '/config/invoice_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
$validFormats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $validFormats, true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $invoicePageConfig);
$tp->appendWhere("i.doctype_id = ?", [$invoiceDoctypeId], 'i');

$tp->applyFilterWithLabel($conn, 'client_id', 'i.client_id', 'Контрагент', 'client', 'client_id', 'name');
$tp->applyFilterWithLabel($conn, 'store_id',  'i.store_id',  'Склад',      'store',  'store_id',  'name');
$tp->applyFilterWithLabel($conn, 'sotr_id',   'i.sotr_id',   'Сотрудник',  'sotr',   'sotr_id',   'name');

$stateFilter = (string)($_GET['state'] ?? '');
if ($stateFilter !== '') {
    $stateMap = [1 => 'Черновик', 2 => 'Выставлен', 3 => 'Оплачен', 4 => 'Отменен'];
    $stateIds = array_values(array_filter(array_map('intval', explode(',', $stateFilter)), fn($v) => isset($stateMap[$v])));
    if (count($stateIds) > 0) {
        $stateNames = array_map(fn($id) => $stateMap[$id], $stateIds);
        $ph = implode(',', array_fill(0, count($stateNames), '?'));
        $tp->appendWhere("i.state IN ($ph)", $stateNames, str_repeat('s', count($stateNames)));
        $labels = [];
        foreach ($stateIds as $sid) $labels[] = $stateMap[$sid] ?? $sid;
        $tp->filters[] = ['kind' => 'col_filter', 'text' => 'Статус = ' . implode(', ', $labels), 'clear' => null];
    }
}

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$colValues = [
    'number' => fn($r) => (int)($r['number'] ?? 0) > 0 ? (int)$r['number'] : '',
    'date'   => fn($r) => ($dt = strtotime((string)($r['date'] ?? ''))) ? date('d-m-Y H:i', $dt) : '',
    'client' => fn($r) => (string)($r['client_name'] ?? ''),
    'state'  => function ($r) { $s = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return $s[(string)($r['state'] ?? '')] ?? (string)($r['state'] ?? ''); },
    'store'  => fn($r) => (string)($r['store_name'] ?? ''),
    'sum'    => fn($r) => number_format((float)($r['sum'] ?? 0), 2, ',', ''),
    'sotr'   => fn($r) => (string)($r['sotr_name'] ?? ''),
    'note'   => fn($r) => (string)($r['note'] ?? ''),
];

$baseName = $invoiceIsOffer ? 'Коммерческие предложения' : 'Счета';

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
    $widths = invoice_columns_widths_export();
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