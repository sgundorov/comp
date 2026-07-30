<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';

ensure_clidoc_table($conn);

$field = (string)($_POST['field'] ?? '');
$id    = (int)($_POST['id'] ?? 0);
$value = $_POST['value'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if ($field === '_list') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $rows = [];
    if ($clientId > 0) {
        $rs = $conn->query("SELECT id, number, name, filename, note FROM clidoc WHERE client_id = $clientId ORDER BY number ASC");
        if ($rs) while ($r = $rs->fetch_assoc()) {
            $rows[] = [
                'id'       => (int)$r['id'],
                'number'   => (int)$r['number'],
                'name'     => (string)$r['name'],
                'filename' => (string)$r['filename'],
                'note'     => (string)$r['note'],
            ];
        }
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id <= 0 || $field === '') {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['name', 'note'];
if (!in_array($field, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Unknown field'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dbField = $field;
$stmt = $conn->prepare("UPDATE clidoc SET `$dbField` = ? WHERE id = ?");
$stmt->bind_param('si', $value, $id);
$stmt->execute();
$stmt->close();

$resp = ['ok' => true, 'field' => $field, 'value' => $value];

if ($field === 'name') {
    $resp['name'] = $value;
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
