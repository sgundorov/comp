<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/cli_categ_columns.php';
require_once __DIR__ . '/config/cli_categ_page.php';

$tp = new TablePage($conn, $cliCategPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);
$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Категории контрагентов',
    'colValues' => [
        'id' => fn($r) => (int)$r['cli_categ_id'],
    ],
    'printWidths' => cli_categ_columns_widths_print(),
]);