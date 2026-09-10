<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sotr_columns.php';
require_once __DIR__ . '/config/sotr_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$tp = new TablePage($conn, $sotrPageConfig);
if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
$rows = $tp->fetchAll($conn);

$tp->renderExport($format, $rows, [
    'baseName' => 'Сотрудники',
    'colValues' => [
        'id'          => fn($r) => (int)$r['sotr_id'],
        'last_name'   => fn($r) => (string)($r['last_name'] ?? ''),
        'first_name'  => fn($r) => (string)($r['first_name'] ?? ''),
        'second_name' => fn($r) => (string)($r['second_name'] ?? ''),
        'role'        => fn($r) => (string)($r['role_name'] ?? ''),
        'sphone'      => fn($r) => (string)($r['sphone'] ?? ''),
        'address'     => fn($r) => (string)($r['address'] ?? ''),
        'title'       => fn($r) => (string)($r['title'] ?? ''),
        'user_status' => fn($r) => ((string)($r['user_status'] ?? '0') === '1') ? 'Да' : 'Нет',
        'note'        => fn($r) => (string)($r['note'] ?? ''),
    ],
]);