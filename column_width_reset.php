<?php
require_once __DIR__ . '/config.php';

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

$tbl = (string)($_POST['tbl'] ?? '');
if ($tbl === '') {
    echo json_encode(['ok' => false, 'error' => 'empty table']);
    exit;
}

ensure_columns_config_table($conn);
$stmt = @mysqli_prepare($conn, "UPDATE column_visibility SET width = NULL WHERE tbl = ?");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'prepare failed']);
    exit;
}
$stmt->bind_param('s', $tbl);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['ok' => $ok]);
