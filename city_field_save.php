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

$chk = $conn->prepare("SELECT city_id FROM city WHERE city_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'city' => ['col' => 'city',       'type' => 's', 'max' => 200],
    'note' => ['col' => 'note',       'type' => 's', 'max' => 500],
    'country' => ['col' => 'country_id', 'type' => 'i'],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$displayValue = '';

if ($cfg['type'] === 's') {
    $value = trim((string)($_POST['value'] ?? ''));
    if ($field === 'city' && $value === '') {
        echo json_encode(['ok' => false, 'error' => 'Поле «Город» не может быть пустым']);
        exit;
    }
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

    $stmt = $conn->prepare("UPDATE city SET {$cfg['col']} = ? WHERE city_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $displayValue = $value;
} else {
    $value = (int)($_POST['value'] ?? 0);
    if ($value <= 0) { echo json_encode(['ok' => false, 'error' => 'Выберите страну']); exit; }

    $c = $conn->prepare("SELECT country FROM country WHERE country_id = ?");
    $c->bind_param('i', $value);
    $c->execute();
    $cr = $c->get_result()->fetch_assoc();
    $c->close();
    if (!$cr) { echo json_encode(['ok' => false, 'error' => 'Страна не найдена']); exit; }

    $stmt = $conn->prepare("UPDATE city SET {$cfg['col']} = ? WHERE city_id = ?");
    bind_auto($stmt, [$value, $id]);
    $stmt->execute();
    $stmt->close();
    $displayValue = (string)$cr['country'];
}

echo json_encode(['ok' => true, 'field' => $field, 'value' => $displayValue]);
