<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repmenu_columns.php';
require_once __DIR__ . '/config/repmenu_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $repmenuPageConfig);

$tp->applyFilterWithLabel($conn, 'gr_id', 'm.gr_id', 'Группа', 'repgroup', 'gr_id', 'name');

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Настройка документов',
    'colValues' => [
        'id'    => fn($r) => (int)$r['number'],
        'group' => fn($r) => (string)($r['group_name'] ?? ''),
        'name'  => fn($r) => (string)($r['name'] ?? ''),
        'fname' => fn($r) => (string)($r['fname'] ?? ''),
        'note'  => fn($r) => (string)($r['note'] ?? ''),
    ],
]);