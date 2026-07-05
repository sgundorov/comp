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

$chk = $conn->prepare("SELECT sotr_id FROM `sotr` WHERE sotr_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'last_name'   => ['col' => 'last_name',   'type' => 's', 'max' => 119],
    'first_name'  => ['col' => 'first_name',  'type' => 's', 'max' => 119],
    'second_name' => ['col' => 'second_name', 'type' => 's', 'max' => 119],
    'role'        => ['col' => 'role_id',     'type' => 'i', 'max' => 6],
    'phone'       => ['col' => 'phone',       'type' => 's', 'max' => 149],
    'sphone'      => ['col' => 'sphone',      'type' => 's', 'max' => 59],
    'email'       => ['col' => 'email',       'type' => 's', 'max' => 120],
    'address'     => ['col' => 'address',     'type' => 's', 'max' => 254],
    'login'       => ['col' => 'login',       'type' => 's', 'max' => 79],
    'passw'       => ['col' => 'passw',       'type' => 's', 'max' => 79],
    'inn'         => ['col' => 'inn',         'type' => 's', 'max' => 29],
    'title'       => ['col' => 'title',       'type' => 's', 'max' => 119],
    'user_status' => ['col' => 'user_status', 'type' => 's', 'max' => 1],
    'note'        => ['col' => 'note',        'type' => 's', 'max' => 254],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if (($field === 'last_name' || $field === 'first_name') && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле обязательно для заполнения']);
    exit;
}
if ($field === 'role') {
    $value = (int)$value;
    if ($value <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Выберите значение из списка']);
        exit;
    }
    $check = $conn->prepare("SELECT role_id FROM role WHERE role_id = ?");
    $check->bind_param('i', $value);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) { $check->close(); echo json_encode(['ok' => false, 'error' => 'Роль не найдена']); exit; }
    $check->close();
}
if ($field === 'user_status') {
    $value = $value === '1' ? '1' : '0';
} elseif (mb_strlen($value) > $cfg['max']) {
    $value = mb_substr($value, 0, $cfg['max']);
}

$stmt = $conn->prepare("UPDATE `sotr` SET {$cfg['col']} = ? WHERE sotr_id = ?");
$stmt->bind_param($cfg['type'] === 'i' ? 'ii' : 'si', $value, $id);
$stmt->execute();
$stmt->close();

if ($field === 'role') {
    $nameStmt = $conn->prepare("SELECT role FROM role WHERE role_id = ?");
    $nameStmt->bind_param('i', $value);
    $nameStmt->execute();
    $nr = $nameStmt->get_result()->fetch_assoc();
    $displayValue = $nr ? (string)$nr['role'] : (string)$value;
    $nameStmt->close();
} else {
    $displayValue = $value;
}

echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
