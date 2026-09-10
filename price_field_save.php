<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$field     = (string)($_POST['field'] ?? '');
$value     = (string)($_POST['value'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);
$prplanId  = (int)($_REQUEST['prplan_id'] ?? 0);
$id        = (int)($_POST['id'] ?? $_POST['price_id'] ?? 0);

function fetch_price_list(mysqli $conn, int $productId, int $prplanId): array {
    if ($productId <= 0 || $prplanId <= 0) return [];
    $stmt = $conn->prepare("SELECT price_id AS id, name, bdays, edays, btime, etime, price, pricef, hprice, mprice, fixed_flag
        FROM price WHERE product_id = ? AND prplan_id = ? ORDER BY price_id ASC");
    $stmt->bind_param('ii', $productId, $prplanId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function norm_time_val(string $v): string {
    return norm_time_smart($v) ?: '00:00:00';
}

if ($field === '_list') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(fetch_price_list($conn, $productId, $prplanId), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($field === '_delete') {
    if ($id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM price WHERE price_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, '_deleted' => true, 'id' => $id,
        'product_id' => $productId, 'prplan_id' => $prplanId,
        'price_list' => fetch_price_list($conn, $productId, $prplanId)], JSON_UNESCAPED_UNICODE);
    exit;
}

$ALLOWED = [
    'name'       => ['type' => 'text'],
    'bdays'      => ['type' => 'int'],
    'edays'      => ['type' => 'int'],
    'btime'      => ['type' => 'time'],
    'etime'      => ['type' => 'time'],
    'price'      => ['type' => 'dec'],
    'pricef'     => ['type' => 'dec'],
    'hprice'     => ['type' => 'dec'],
    'mprice'     => ['type' => 'dec'],
];

if ($field === '' || ($id <= 0 && ($productId <= 0 || $prplanId <= 0))) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unknown field']);
    exit;
}

switch ($ALLOWED[$field]['type']) {
    case 'text': $val = trim($value); break;
    case 'int':  $val = (int)$value; break;
    case 'time': $val = norm_time_val($value); break;
    default:     $val = (float)str_replace(',', '.', $value); break;
}

$bindType = ['text' => 's', 'int' => 'i', 'time' => 's', 'dec' => 'd'][$ALLOWED[$field]['type']];

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE price SET $field = ? WHERE price_id = ?");
    $stmt->bind_param($bindType . 'i', $val, $id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO price (product_id, prplan_id, $field) VALUES (?, ?, ?)");
    $stmt->bind_param('ii' . $bindType, $productId, $prplanId, $val);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
}

$item = null;
$q = $conn->query("SELECT * FROM price WHERE price_id = " . (int)$id);
if ($q) {
    $item = $q->fetch_assoc();
    if ($item) $item['id'] = (int)$item['price_id'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
