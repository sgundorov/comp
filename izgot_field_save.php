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

$chk = $conn->prepare("SELECT izgot_id FROM izgot WHERE izgot_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'izgot'   => ['col' => 'izgot',     'type' => 's', 'max' => 200],
    'country' => ['col' => 'country_id', 'type' => 'i', 'max' => 0],
    'note'    => ['col' => 'note',      'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$rawValue = $_POST['value'] ?? '';

if ($field === 'izgot') {
    $value = trim((string)$rawValue);
    if ($value === '') { echo json_encode(['ok' => false, 'error' => 'Поле «Производитель» не может быть пустым']); exit; }
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
} elseif ($field === 'country') {
    $value = (int)$rawValue;
} else {
    $value = trim((string)$rawValue);
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
}

if ($cfg['type'] === 'i') {
    $stmt = $conn->prepare("UPDATE izgot SET {$cfg['col']} = ? WHERE izgot_id = ?");
    bind_auto($stmt, [$value, $id]);
} else {
    $stmt = $conn->prepare("UPDATE izgot SET {$cfg['col']} = ? WHERE izgot_id = ?");
    bind_auto($stmt, [$value, $id]);
}
$stmt->execute();
$stmt->close();

if ($field === 'country') {
    $nameRes = $conn->prepare("SELECT country FROM country WHERE country_id = ?");
    $nameRes->bind_param('i', $value);
    $nameRes->execute();
    $nr = $nameRes->get_result()->fetch_assoc();
    $displayValue = $nr ? (string)$nr['country'] : '';
    $nameRes->close();
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value, 'displayValue' => $displayValue]);
} else {
    echo json_encode(['ok' => true, 'field' => $field, 'value' => (string)$value]);
}
