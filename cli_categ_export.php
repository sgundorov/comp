<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/cli_categ_columns.php';
require_once __DIR__ . '/config/cli_categ_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $cliCategPageConfig);
$rows = $tp->fetchAll($conn);
$tp->renderExport($format, $rows, ['baseName' => 'Категории контрагентов', 'colValues' => ['id' => fn($r) => (string)(int)$r['cli_categ_id']]]);