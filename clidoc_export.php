<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

$format = strtolower((string)($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'xls'], true)) {
    http_response_code(400);
    echo 'Unknown format';
    exit;
}

$clientId = (int)($_GET['client_id'] ?? 0);
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'])), fn($v) => $v > 0));
}

$rows = [];
if ($clientId > 0) {
    $sql = "SELECT id, number, name, filename, note FROM clidoc WHERE client_id = " . (int)$clientId;
    if (!empty($ids)) $sql .= " AND id IN (" . implode(',', $ids) . ")";
    $sql .= " ORDER BY number ASC";
    $rs = $conn->query($sql);
    if ($rs) while ($r = $rs->fetch_assoc()) $rows[] = $r;
}

$baseName = 'Документы контрагента';
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

$tp = new TablePage($conn, [
    'table'       => 'clidoc',
    'key'         => 'id',
    'search_cols' => [],
    'column_visibility_tbl' => '',
    'columns'     => [
        ['name' => 'number',   'label' => '№'],
        ['name' => 'name',     'label' => 'Документ'],
        ['name' => 'filename', 'label' => 'Файл'],
        ['name' => 'note',     'label' => 'Примечание'],
    ],
]);
$tp->renderExport($format, $rows);
