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

$chk = $conn->prepare("SELECT zat_id FROM `zat` WHERE zat_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'name'    => ['col' => 'name',    'type' => 's', 'max' => 200],
    'out_flag' => ['col' => 'out_flag', 'type' => 'i', 'max' => 1],
    'note'    => ['col' => 'note',    'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if ($field === 'name' && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле «Операция» не может быть пустым']);
    exit;
}
if ($field === 'out_flag') {
    $value = (int)(!empty($value) ? 1 : 0);
}
if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

$stmt = $conn->prepare("UPDATE `zat` SET {$cfg['col']} = ? WHERE zat_id = ?");
bind_auto($stmt, [$value, $id]);
$stmt->execute();
$stmt->close();

if ($field === 'out_flag') {
    $displayValue = $value ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : '';
} else {
    $displayValue = $value;
}

echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
