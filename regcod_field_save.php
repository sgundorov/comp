<?php
require_once __DIR__ . '/config.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    die('AJAX only');
}

$regcodId = (int)($_POST['regcod_id'] ?? $_POST['id'] ?? 0);
$field    = (string)($_POST['field'] ?? '');
$value    = (string)($_POST['value'] ?? '');

if ($field === '_list') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $rows = [];
    if ($clientId > 0) {
        $rs = $conn->query("SELECT regcod_id AS id, regcod, product, quant, date, city, signat, days, block_flag, note FROM regcod WHERE client_id = $clientId ORDER BY regcod_id DESC");
        if ($rs) while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

$ALLOWED = [
    'product_id'  => ['type' => 'lookup_int'],
    'product'     => ['type' => 'text'],
    'regcod'      => ['type' => 'text'],
    'quant'       => ['type' => 'int'],
    'date'        => ['type' => 'text'],
    'city'        => ['type' => 'text'],
    'signat'      => ['type' => 'text'],
    'days'        => ['type' => 'int'],
    'block_flag'  => ['type' => 'int'],
    'note'        => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

$displayValue = null;

if ($regcodId > 0) {
    switch ($ALLOWED[$field]['type']) {
        case 'text':
            $value = trim($value);
            $stmt = $conn->prepare("UPDATE regcod SET $field = ? WHERE regcod_id = ?");
            bind_auto($stmt, [$value, $regcodId]);
            $stmt->execute();
            $stmt->close();
            $displayValue = h($value);
            break;
        case 'int':
            $value = (int)$value;
            $stmt = $conn->prepare("UPDATE regcod SET $field = ? WHERE regcod_id = ?");
            bind_auto($stmt, [$value, $regcodId]);
            $stmt->execute();
            $stmt->close();
            $displayValue = (string)$value;
            break;
        case 'lookup_int':
            $value = (int)$value;
            $stmt = $conn->prepare("UPDATE regcod SET $field = ? WHERE regcod_id = ?");
            bind_auto($stmt, [$value, $regcodId]);
            $stmt->execute();
            $stmt->close();
            $displayValue = (string)$value;
            break;
    }
    $itemData = null;
    $q = $conn->query("SELECT * FROM regcod WHERE regcod_id = $regcodId");
    if ($q) $itemData = $q->fetch_assoc();
} else {
    $conn->query("INSERT INTO regcod (product_id) VALUES (0)");
    $regcodId = $conn->insert_id;
    $stmt = $conn->prepare("UPDATE regcod SET $field = ? WHERE regcod_id = ?");
    $val = ($ALLOWED[$field]['type'] === 'text') ? $value : (int)$value;
    $stmt->bind_param(($ALLOWED[$field]['type'] === 'text') ? 'si' : 'ii', $val, $regcodId);
    $stmt->execute();
    $stmt->close();
    $q = $conn->query("SELECT * FROM regcod WHERE regcod_id = $regcodId");
    $itemData = $q ? $q->fetch_assoc() : null;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue, 'id' => $regcodId, 'item' => $itemData], JSON_UNESCAPED_UNICODE);
