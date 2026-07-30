<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$field   = (string)($_POST['field'] ?? '');
$value   = (string)($_POST['value'] ?? '');
$groupId = (int)($_POST['group_id'] ?? 0);
$id      = (int)($_POST['id'] ?? $_POST['sgroup_id'] ?? 0);

function recalc_group_pos(mysqli $conn, int $groupId): void {
    if ($groupId <= 0) return;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sgroup WHERE group_id = ?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    $upd = $conn->prepare("UPDATE `group` SET pos = ? WHERE group_id = ?");
    bind_auto($upd, [$cnt, $groupId]);
    $upd->execute();
    $upd->close();
}

if ($field === '_list') {
    $stmt = $conn->prepare("SELECT sgroup_id AS id, name, note FROM sgroup WHERE group_id = ? AND service_flag = (SELECT service_flag FROM `group` WHERE group_id = ?) ORDER BY sgroup_id ASC");
    $stmt->bind_param('ii', $groupId, $groupId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($field === '_delete') {
    if ($id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    $q = $conn->query("SELECT group_id FROM sgroup WHERE sgroup_id = $id");
    $delGroupId = $q ? (int)$q->fetch_assoc()['group_id'] : 0;
    $stmt = $conn->prepare("DELETE FROM sgroup WHERE sgroup_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    if ($delGroupId > 0) recalc_group_pos($conn, $delGroupId);
    $freshList = [];
    if ($delGroupId > 0) {
        $lStmt = $conn->prepare("SELECT sgroup_id AS id, name, note FROM sgroup WHERE group_id = ? AND service_flag = (SELECT service_flag FROM `group` WHERE group_id = ?) ORDER BY sgroup_id ASC");
        if ($lStmt) {
            $lStmt->bind_param('ii', $delGroupId, $delGroupId);
            $lStmt->execute();
            $freshList = $lStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $lStmt->close();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, '_deleted' => true, 'id' => $id, 'group_id' => $delGroupId, 'sgroup_list' => $freshList]);
    exit;
}

$ALLOWED = [
    'name'  => ['type' => 'text'],
    'note'  => ['type' => 'text'],
];

if ($field === '' || ($id <= 0 && $groupId <= 0)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unknown field']);
    exit;
}

$val = $ALLOWED[$field]['type'] === 'text' ? trim($value) : (float)str_replace(',', '.', $value);

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE sgroup SET $field = ? WHERE sgroup_id = ?");
    $stmt->bind_param($ALLOWED[$field]['type'] === 'text' ? 'si' : 'di', $val, $id);
    $stmt->execute();
    $stmt->close();
    $q = $conn->query("SELECT * FROM sgroup WHERE sgroup_id = $id");
    $item = $q ? $q->fetch_assoc() : null;
    if ($item) {
        $item['id'] = (int)$item['sgroup_id'];
        if ($groupId > 0) recalc_group_pos($conn, $groupId);
    }
} else {
    $parentSf = '0';
    if ($groupId > 0) {
        $sfR = $conn->query("SELECT service_flag FROM `group` WHERE group_id = $groupId");
        if ($sfR && $sfRow = $sfR->fetch_assoc()) $parentSf = (string)$sfRow['service_flag'];
    }
    $stmt = $conn->prepare("INSERT INTO sgroup (group_id, service_flag, $field) VALUES (?, ?, ?)");
    $stmt->bind_param($ALLOWED[$field]['type'] === 'text' ? 'iss' : 'isd', $groupId, $parentSf, $val);
    $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();
    if ($groupId > 0) recalc_group_pos($conn, $groupId);
    $q = $conn->query("SELECT * FROM sgroup WHERE sgroup_id = $newId");
    $item = $q ? $q->fetch_assoc() : null;
    if ($item) $item['id'] = (int)$item['sgroup_id'];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
