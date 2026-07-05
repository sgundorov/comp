<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

$raw = $_POST['columns'] ?? [];
$clean = [];
$allowed = [];
require_once __DIR__ . '/config/repmenu_columns.php';
foreach (repmenu_columns_defaults() as $c) $allowed[$c['name']] = true;
$seen = [];
if (is_array($raw)) {
    foreach ($raw as $i => $row) {
        if (!is_array($row)) continue;
        $name = (string)($row['name'] ?? '');
        if (!isset($allowed[$name]) || isset($seen[$name])) continue;
        $seen[$name] = true;
        $clean[] = ['name' => $name, 'visible' => !empty($row['visible']) ? 1 : 0, 'order' => (int)($row['order'] ?? $i)];
    }
}
foreach (repmenu_columns_defaults() as $i => $c) {
    if (!isset($seen[$c['name']])) $clean[] = ['name' => $c['name'], 'visible' => 1, 'order' => count($clean) + $i];
}
usort($clean, function ($a, $b) { return $a['order'] - $b['order']; });
$ok = save_columns_config($conn, 'repmenu', $clean);
echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
