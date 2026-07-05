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

$chk = $conn->prepare("SELECT firm_id FROM firm WHERE firm_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'name'     => ['col' => 'name',     'type' => 's', 'max' => 200],
    'address'  => ['col' => 'address',  'type' => 's', 'max' => 500],
    'phone'    => ['col' => 'phone',    'type' => 's', 'max' => 100],
    'email'    => ['col' => 'email',    'type' => 's', 'max' => 200],
    'city'     => ['col' => 'city_id',  'type' => 'i', 'max' => 0],
    'director' => ['col' => 'director', 'type' => 's', 'max' => 200],
    'note'     => ['col' => 'note',     'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$rawValue = $_POST['value'] ?? '';

if ($field === 'name') {
    $value = trim((string)$rawValue);
    if ($value === '') { echo json_encode(['ok' => false, 'error' => 'Поле «Название» не может быть пустым']); exit; }
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
} elseif ($field === 'city') {
    $value = (int)$rawValue;
} else {
    $value = trim((string)$rawValue);
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
}

if ($cfg['type'] === 'i') {
    $stmt = $conn->prepare("UPDATE firm SET {$cfg['col']} = ? WHERE firm_id = ?");
    bind_auto($stmt, [$value, $id]);
} else {
    $stmt = $conn->prepare("UPDATE firm SET {$cfg['col']} = ? WHERE firm_id = ?");
    bind_auto($stmt, [$value, $id]);
}
$stmt->execute();
$stmt->close();

if ($field === 'city') {
    $nameRes = $conn->prepare("SELECT city FROM city WHERE city_id = ?");
    $nameRes->bind_param('i', $value);
    $nameRes->execute();
    $nr = $nameRes->get_result()->fetch_assoc();
    $displayValue = $nr ? (string)$nr['city'] : '';
    $nameRes->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} else {
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value]);
}
