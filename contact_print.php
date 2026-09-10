<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/config/contact_columns.php';
require_once __DIR__ . '/config/contact_page.php';

$tp = new TablePage($conn, $contactPageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Контакты',
    'colValues' => [
        'id'            => fn($r) => (int)$r['contact_id'],
        'client'        => fn($r) => (string)($r['cli_name'] ?? ''),
        'datetime'      => function ($r) { $raw = (string)($r['datetime'] ?? ''); return $raw !== '' ? date('d.m.Y H:i', strtotime($raw)) : ''; },
        'impotant_flag' => fn($r) => (string)($r['impotant_flag'] ?? '0') === '1' ? 'Да' : 'Нет',
        'contype'       => fn($r) => (string)($r['contype'] ?? ''),
        'sotr'          => fn($r) => (string)($r['sotr_name'] ?? ''),
        'note'          => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => contact_columns_widths_print(),
]);