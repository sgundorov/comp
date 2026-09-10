<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

header('Content-Type: application/json; charset=utf-8');

$isAjax = (
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
if (!$isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'AJAX only']); exit; }

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');

if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'Некорректный id']); exit; }

$chk = $conn->prepare("SELECT prplan_id FROM prplan WHERE prplan_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

function prplan_norm_date(string $v): string {
    return norm_date_smart($v);
}

$ALLOWED = [
    'prplan' => ['col' => 'prplan', 'type' => 's', 'max' => 80],
    'bdate'  => ['col' => 'bdate',  'type' => 'date', 'max' => 10],
    'edate'  => ['col' => 'edate',  'type' => 'date', 'max' => 10],
    'note'   => ['col' => 'note',   'type' => 's', 'max' => 100],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле не разрешено для редактирования']);
    exit;
}

/* Сезоны отключены — даты не редактируются */
$sezonFlag = (int)($GLOBALS['appSettings']['SezonFlag'] ?? 0) === 1;
if (!$sezonFlag && in_array($field, ['bdate', 'edate'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Использование сезонов тарифных планов отключено']);
    exit;
}

$cfg = $ALLOWED[$field];
$value = trim((string)($_POST['value'] ?? ''));

if ($field === 'prplan' && $value === '') {
    echo json_encode(['ok' => false, 'error' => 'Поле «Тарифный план» не может быть пустым']);
    exit;
}
if ($cfg['type'] === 'date') {
    $norm = prplan_norm_date($value);
    if ($value !== '' && $norm === '') {
        echo json_encode(['ok' => false, 'error' => 'Дата должна быть в формате дд.мм.гггг']);
        exit;
    }
    $value = $norm;
}
if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);

$stmt = $conn->prepare("UPDATE prplan SET {$cfg['col']} = ? WHERE prplan_id = ?");
bind_auto($stmt, [$value, $id]);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'field' => $field, 'value' => $value]);
