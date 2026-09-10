<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/country_columns.php';
require_once __DIR__ . '/config/country_page.php';

$tp = new TablePage($conn, $countryPageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Страны',
    'colValues' => [
        'id'      => fn($r) => (int)$r['country_id'],
        'country' => fn($r) => (string)($r['country'] ?? ''),
        'note'    => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => country_columns_widths_print(),
]);