<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$objectId = max(0, (int)($_POST['object_id'] ?? 0));
$roleId   = max(0, (int)($_POST['role_id'] ?? 0));
$field    = (string)($_POST['field'] ?? '');

$allowedFields = ['dostup_flag', 'insert_flag', 'change_flag', 'delete_flag', 'save_flag', 'print_flag'];

if ($objectId <= 0 || $roleId <= 0 || !in_array($field, $allowedFields, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

// Check admin restrictions
$adminRole = $conn->query("SELECT role_id FROM role WHERE role = 'Администратор' LIMIT 1");
$adminRoleId = $adminRole && ($ar = $adminRole->fetch_assoc()) ? (int)$ar['role_id'] : 0;
if ($adminRoleId > 0 && $roleId === $adminRoleId && $field !== 'print_flag') {
    $or = $conn->query("SELECT object FROM object WHERE object_id = $objectId LIMIT 1");
    $objCode = $or && ($orow = $or->fetch_assoc()) ? (string)$orow['object'] : '';
    if (in_array($objCode, ['Sotr', 'Role', 'Object', 'Setup'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Нельзя включить этот запрет для Администратора']);
        exit;
    }
}

// read current flag value
$current = 0;
$stmt = $conn->prepare("SELECT $field FROM dostup WHERE object_id = ? AND catsotr_id = ?");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('ii', $objectId, $roleId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && ($r = $res->fetch_assoc())) {
    $current = (int)$r[$field];
}
$stmt->close();

$newValue = $current ? 0 : 1;

// UPSERT — unique key on (object_id, catsotr_id) ensures update on duplicate
$sql = "INSERT INTO dostup (object_id, catsotr_id, $field) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE $field = VALUES($field)";
$upsert = $conn->prepare($sql);
if (!$upsert) {
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}
$upsert->bind_param('iii', $objectId, $roleId, $newValue);
if (!$upsert->execute()) {
    echo json_encode(['ok' => false, 'error' => $upsert->error]);
    $upsert->close();
    exit;
}
$upsert->close();

$html = $newValue
    ? '<svg class="flag-x" viewBox="0 0 24 24" width="20" height="20"><line x1="5" y1="5" x2="19" y2="19" stroke="#ff6b6b" stroke-width="3.2" stroke-linecap="round"/><line x1="19" y1="5" x2="5" y2="19" stroke="#ff6b6b" stroke-width="3.2" stroke-linecap="round"/></svg>'
    : '';

echo json_encode(['ok' => true, 'flag' => $newValue, 'html' => $html]);
