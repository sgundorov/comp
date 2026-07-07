<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/role_columns.php';
require_once __DIR__ . '/config/role_page.php';

$tp = new TablePage($conn, $rolePageConfig);

[$rows, $pagination] = $tp->fetchPage($conn);

$tp->renderPrintPage($rows, $pagination, [
    'title' => 'Роли сотрудников',
    'colValues' => [
        'id'   => fn($r) => (int)$r['role_id'],
        'role' => fn($r) => (string)($r['role'] ?? ''),
        'note' => fn($r) => (string)($r['note'] ?? ''),
    ],
    'printWidths' => role_columns_widths_print(),
]);