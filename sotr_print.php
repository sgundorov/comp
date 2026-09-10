<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sotr_columns.php';
require_once __DIR__ . '/config/sotr_page.php';

$tp = new TablePage($conn, $sotrPageConfig);

if ((string)($_GET['selected'] ?? '') === '1') {
    $tp->applySelectedFilter($conn);
}
[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Сотрудники',
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
    'printWidths' => sotr_columns_widths_print(),
]);