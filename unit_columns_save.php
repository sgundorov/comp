<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/unit_columns.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$isAjax = ((string)($_GET['ajax'] ?? '') === '1')
       || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
if (!$isAjax) {
    echo json_encode(['ok' => false, 'error' => 'ajax required']);
    exit;
}

$allowedCols = [];
foreach (unit_columns_defaults() as $c) $allowedCols[$c['name']] = $c['label'];

$raw = $_POST['columns'] ?? [];
if (!is_array($raw)) {
    echo json_encode(['ok' => false, 'error' => 'bad payload']);
    exit;
}

$clean = [];
$seen = [];
foreach ($raw as $i => $row) {
    if (!is_array($row)) continue;
    $name = (string)($row['name'] ?? '');
    if (!isset($allowedCols[$name])) continue;
    if (isset($seen[$name])) continue;
    $seen[$name] = true;
    $clean[] = [
        'name'    => $name,
        'visible' => !empty($row['visible']) ? 1 : 0,
        'order'   => (int)($row['order'] ?? $i),
    ];
}
foreach (unit_columns_defaults() as $i => $c) {
    if (!isset($seen[$c['name']])) {
        $clean[] = ['name' => $c['name'], 'visible' => 1, 'order' => count($clean) + $i];
    }
}

usort($clean, function ($a, $b) { return $a['order'] - $b['order']; });

$ok = save_columns_config($conn, 'unit', $clean);
if (!$ok) {
    echo json_encode(['ok' => false, 'error' => 'save failed']);
    exit;
}
echo json_encode(['ok' => true]);
