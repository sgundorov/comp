<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/arenda_columns.php';
require_once __DIR__ . '/config/arenda_page.php';

$arendaPageConfig['marks_session'] = 'arenda_select';
$arendaPageConfig['marks_tbl'] = 'arenda';

$tp = new TablePage($conn, $arendaPageConfig);
$tp->appendWhere("d.typeop = ?", [90], "i");
/* Колоночные фильтры (client/plat_type/sotr) — из GET */
$tp->processColumnFilters($conn);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->buildFilters();

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Аренда',
    'orientation' => 'landscape',
    'colValues' => [
        'status'    => fn($r) => (int)$r['rezerv_flag'] === 1 ? 'Резерв' : ((int)$r['voz_flag'] === 1 ? 'Возврат' : ''),
        'firm'      => fn($r) => (int)$r['firm_id'] > 0 ? (string)(int)$r['firm_id'] : '',
        'number'    => fn($r) => (int)$r['number'],
        'vremya'    => fn($r) => trim(trim((string)$r['date']) . ' ' . trim((string)$r['time'])),
        'client'    => fn($r) => (string)($r['client_name'] ?? ''),
        'beg'       => fn($r) => trim(trim((string)$r['date_beg']) . ' ' . trim((string)$r['time_beg'])),
        'vozvrat'   => fn($r) => trim(trim((string)$r['date_voz']) . ' ' . trim((string)$r['time_voz'])),
        'poz'       => fn($r) => (int)$r['poz'],
        'sum'       => fn($r) => number_format((float)$r['sum'], 2, '.', ''),
        'sum_plat'  => fn($r) => number_format((float)$r['sum_plat'], 2, '.', ''),
        'plat_type' => fn($r) => (string)($r['plat_type'] ?? ''),
        'sotr'      => fn($r) => (string)($r['sotr_name'] ?? ''),
        'note'      => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => arenda_columns_widths_print(),
]);
