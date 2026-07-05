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

$chk = $conn->prepare("SELECT categ_id FROM `categ` WHERE categ_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'categ'        => ['col' => 'categ',        'type' => 's', 'max' => 50],
    'noquant_flag' => ['col' => 'noquant_flag', 'type' => 's', 'max' => 1],
    'service_flag' => ['col' => 'service_flag', 'type' => 's', 'max' => 1],
    'note'         => ['col' => 'note',         'type' => 's', 'max' => 100],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if (in_array($field, ['noquant_flag', 'service_flag'], true)) {
    $value = $value === '1' ? '1' : '0';
} elseif ($field === 'categ' && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле «Категория» не может быть пустым']);
    exit;
}
if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

$stmt = $conn->prepare("UPDATE `categ` SET {$cfg['col']} = ? WHERE categ_id = ?");
bind_auto($stmt, [$value, $id]);
$stmt->execute();
$stmt->close();

$response = ['ok' => true, 'field' => $field, 'value' => $value];
if (in_array($field, ['noquant_flag', 'service_flag'], true)) {
    $response['displayValue'] = $value === '1'
        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
        : '';
}
echo json_encode($response);
