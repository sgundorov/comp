<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/cli_categ_columns.php';
require_once __DIR__ . '/config/cli_categ_page.php';

$tp = new TablePage($conn, $cliCategPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Категории контрагентов',
    'colValues' => [
        'id' => fn($r) => (int)$r['cli_categ_id'],
    ],
    'printWidths' => cli_categ_columns_widths_print(),
]);