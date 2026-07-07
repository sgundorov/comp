<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/regcod_columns.php';
require_once __DIR__ . '/config/regcod_page.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$regcodPageConfig['columns'] = regcod_columns_defaults();
$regcodPageConfig['default_sort'] = ['col' => 'regcod', 'dir' => 'desc'];
$regcodPageConfig['key_expr'] = 'r.regcod_id';

$tp = new TablePage($conn, $regcodPageConfig);

$clientId = (int)($_GET['client_id'] ?? 0);
if ($clientId > 0) {
    $tp->appendWhere("r.client_id = ?", [$clientId], 'i');
}

$rows = $tp->fetchAll($conn);

$baseName = 'Регистрационные коды';
$customName = trim((string)($_GET['filename'] ?? ''));
if ($customName !== '') {
    $customName = preg_replace('/[\x00-\x1F\x7F\/\\\\<>:"|?*]+/u', '_', $customName);
    $customName = trim($customName, ". \t\n\r\0\x0B");
    $customName = mb_substr($customName, 0, 120, 'UTF-8');
    if ($customName !== '') {
        $customName = preg_replace('/\.(csv|xls)$/i', '', $customName);
        if ($customName !== '') $baseName = $customName;
    }
}

$tp->renderExport($format, $rows, [
    'baseName' => $baseName,
    'colValues' => [
        'days' => fn($r) => (int)($r['days'] ?? 0) === 0 ? '' : $r['days'],
    ],
]);