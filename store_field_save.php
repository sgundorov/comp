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

$chk = $conn->prepare("SELECT store_id FROM store WHERE store_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'name'    => ['col' => 'name',    'type' => 's', 'max' => 200],
    'address' => ['col' => 'address', 'type' => 's', 'max' => 500],
    'note'    => ['col' => 'note',    'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if ($field === 'name' && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле «Участок» не может быть пустым']);
    exit;
}
if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

$stmt = $conn->prepare("UPDATE store SET {$cfg['col']} = ? WHERE store_id = ?");
bind_auto($stmt, [$value, $id]);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'field' => $field, 'value' => $value]);