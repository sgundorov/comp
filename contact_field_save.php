<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$isAjax = (
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
if (!$isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'AJAX only']); exit; }

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');

if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'Некорректный id']); exit; }

$chk = $conn->prepare("SELECT contact_id FROM contact WHERE contact_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'client'  => ['col' => 'client_id',  'type' => 'i', 'max' => 0],
    'datetime'=> ['col' => 'datetime',   'type' => 's', 'max' => 20],
    'impotant_flag' => ['col' => 'impotant_flag', 'type' => 's', 'max' => 1],
    'contype' => ['col' => 'contype_id', 'type' => 'i', 'max' => 0],
    'sotr'    => ['col' => 'sotr_id',    'type' => 'i', 'max' => 0],
    'note'    => ['col' => 'note',       'type' => 's', 'max' => 5000],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$rawValue = $_POST['value'] ?? '';

if ($field === 'client') {
    $value = (int)$rawValue;
    $stmt = $conn->prepare("UPDATE contact SET client_id = ? WHERE contact_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    // Update cli_name from client table
    $nr = $conn->prepare("SELECT name FROM client WHERE client_id = ?");
    $nr->bind_param('i', $value);
    $nr->execute();
    $nrd = $nr->get_result()->fetch_assoc();
    $cliName = $nrd ? (string)$nrd['name'] : '';
    $nr->close();
    $upd = $conn->prepare("UPDATE contact SET cli_name = ? WHERE contact_id = ?");
    bind_auto($upd, [$cliName, $id]);
    $upd->execute();
    $upd->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $cliName]);
} elseif ($field === 'contype') {
    $value = (int)$rawValue;
    $stmt = $conn->prepare("UPDATE contact SET contype_id = ? WHERE contact_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    // Update denormalized contype name
    $nr = $conn->prepare("SELECT contype FROM contype WHERE contype_id = ?");
    $nr->bind_param('i', $value);
    $nr->execute();
    $nrd = $nr->get_result()->fetch_assoc();
    $contypeName = $nrd ? (string)$nrd['contype'] : '';
    $nr->close();
    $upd = $conn->prepare("UPDATE contact SET contype = ? WHERE contact_id = ?");
    bind_auto($upd, [$contypeName, $id]);
    $upd->execute();
    $upd->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $contypeName]);
} elseif ($field === 'sotr') {
    $value = (int)$rawValue;
    $stmt = $conn->prepare("UPDATE contact SET sotr_id = ? WHERE contact_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    // Return display name
    $nr = $conn->prepare("SELECT TRIM(CONCAT_WS(' ', last_name, first_name)) AS name FROM sotr WHERE sotr_id = ?");
    $nr->bind_param('i', $value);
    $nr->execute();
    $nrd = $nr->get_result()->fetch_assoc();
    $displayValue = $nrd ? (string)$nrd['name'] : '';
    $nr->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} elseif ($field === 'impotant_flag') {
    $value = $rawValue === '1' ? '1' : '0';
    $displayValue = $value === '1'
        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
        : '';
    $stmt = $conn->prepare("UPDATE contact SET impotant_flag = ? WHERE contact_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} elseif ($field === 'datetime') {
    $value = trim((string)$rawValue);
    if ($value !== '') {
        $dt = strtotime($value);
        if ($dt === false) {
            echo json_encode(['ok' => false, 'error' => 'Некорректный формат даты/времени']);
            exit;
        }
        $value = date('Y-m-d H:i:s', $dt);
    }
    $stmt = $conn->prepare("UPDATE contact SET datetime = ? WHERE contact_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $displayValue = $value !== '' ? date('d.m.Y H:i', strtotime($value)) : '';
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} else {
    $value = trim((string)$rawValue);
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
    if ($cfg['type'] === 'i') {
        $stmt = $conn->prepare("UPDATE contact SET {$cfg['col']} = ? WHERE contact_id = ?");
        bind_auto($stmt, [$value, $id]);
    } else {
        $stmt = $conn->prepare("UPDATE contact SET {$cfg['col']} = ? WHERE contact_id = ?");
        bind_auto($stmt, [$value, $id]);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value]);
}
