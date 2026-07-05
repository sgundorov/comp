<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$columns = isset($_POST['columns']) ? (array)$_POST['columns'] : [];
$defaults = invoice_columns_defaults();

$clean = [];
$order = 0;
foreach ($columns as $col) {
    $name = trim((string)($col['name'] ?? ''));
    if ($name === '') continue;
    $visible = !empty($col['visible']);
    foreach ($defaults as $d) {
        if ($d['name'] === $name) {
            $entry = $d;
            $entry['visible'] = $visible;
            $entry['order'] = $order++;
            $clean[] = $entry;
            break;
        }
    }
}

$existingNames = array_map(function ($c) { return $c['name']; }, $clean);
foreach ($defaults as $d) {
    if (!in_array($d['name'], $existingNames, true)) {
        $entry = $d;
        $entry['visible'] = true;
        $entry['order'] = $order++;
        $clean[] = $entry;
    }
}

usort($clean, function ($a, $b) {
    $ao = isset($a['order']) ? (int)$a['order'] : 999;
    $bo = isset($b['order']) ? (int)$b['order'] : 999;
    return $ao - $bo;
});

save_columns_config($conn, 'invoice', $clean);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
