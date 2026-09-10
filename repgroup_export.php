<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repgroup_columns.php';
require_once __DIR__ . '/config/repgroup_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $repgroupPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Группы шаблонов документов',
    'colValues' => [
        'id'   => fn($r) => (int)$r['gr_id'],
        'name' => fn($r) => (string)($r['name'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
]);