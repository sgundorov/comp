<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

$field = (string)($_POST['field'] ?? '');
$id    = (int)($_POST['gr_id'] ?? $_POST['id'] ?? 0);
$value = $_POST['value'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if ($id <= 0 || $field === '' || $field === '_list' || $field === '_delete') {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['name', 'note'];
if (!in_array($field, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Unknown field'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("UPDATE repgroup SET `$field` = ? WHERE gr_id = ?");
$stmt->bind_param('si', $value, $id);
$stmt->execute();
$stmt->close();

$resp = ['ok' => true, 'field' => $field, 'value' => $value];
if ($field === 'name') $resp['name'] = $value;

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
