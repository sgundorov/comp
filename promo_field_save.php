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

$chk = $conn->prepare("SELECT promo_id FROM promo WHERE promo_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'promo'   => ['col' => 'promo',   'type' => 's', 'max' => 200],
    'bdate'   => ['col' => 'bdate',   'type' => 's', 'max' => 10],
    'edate'   => ['col' => 'edate',   'type' => 's', 'max' => 10],
    'count'   => ['col' => 'count',   'type' => 'i', 'max' => 11],
    'procent' => ['col' => 'procent', 'type' => 'd', 'max' => 0],
    'note'    => ['col' => 'note',    'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if ($field === 'promo' && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле «Вид рекламы» не может быть пустым']);
    exit;
}
if ($field === 'count' && $value !== '' && (!ctype_digit($value) || (int)$value < 0)) {
    echo json_encode(['ok' => false, 'error' => 'Количество клиентов должно быть целым неотрицательным числом']);
    exit;
}
if ($field === 'procent' && $value !== '' && (!is_numeric($value) || (float)$value < 0 || (float)$value > 100)) {
    echo json_encode(['ok' => false, 'error' => 'Процент должен быть числом от 0 до 100']);
    exit;
}
if ($cfg['type'] === 's' && mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

$stmt = $conn->prepare("UPDATE promo SET {$cfg['col']} = ? WHERE promo_id = ?");
if ($cfg['type'] === 'd') {
    $val = $value !== '' ? (float)$value : null;
    bind_auto($stmt, [$val, $id]);
} elseif ($cfg['type'] === 'i') {
    $val = $value !== '' ? (int)$value : null;
    bind_auto($stmt, [$val, $id]);
} else {
    bind_auto($stmt, [$value, $id]);
}
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'field' => $field, 'value' => $value]);
