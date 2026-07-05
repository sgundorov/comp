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

$chk = $conn->prepare("SELECT cli_categ_id FROM cli_categ WHERE cli_categ_id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) { $chk->close(); echo json_encode(['ok' => false, 'error' => 'Запись не найдена']); exit; }
$chk->close();

$ALLOWED = [
    'categ'          => ['col' => 'categ',          'type' => 's', 'max' => 200],
    'supplier_flag'  => ['col' => 'supplier_flag',  'type' => 's', 'max' => 1],
    'problem_flag'   => ['col' => 'problem_flag',   'type' => 's', 'max' => 1],
    'color'          => ['col' => 'color',          'type' => 'i'],
    'note'           => ['col' => 'note',           'type' => 's', 'max' => 500],
];

if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Поле недоступно для редактирования']);
    exit;
}

$cfg = $ALLOWED[$field];
$rawValue = $_POST['value'] ?? '';
$response = ['ok' => true, 'field' => $field];

if (in_array($field, ['supplier_flag', 'problem_flag'], true)) {
    $value = $rawValue === '1' ? '1' : '0';
    $response['displayValue'] = $value === '1'
        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
        : '';
} elseif ($field === 'color') {
    $hex = trim((string)$rawValue);
    if ($hex !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        echo json_encode(['ok' => false, 'error' => 'Некорректный формат цвета']);
        exit;
    }
    $value = $hex ? (int)hexdec(ltrim($hex, '#')) : 0;
    $response['displayValue'] = '';
} else {
    $value = trim((string)$rawValue);
    if ($field === 'categ' && $value === '') {
        echo json_encode(['ok' => false, 'error' => 'Поле «Категория» не может быть пустым']);
        exit;
    }
    if (mb_strlen($value) > $cfg['max']) $value = mb_substr($value, 0, $cfg['max']);
}

$stmt = $conn->prepare("UPDATE cli_categ SET {$cfg['col']} = ? WHERE cli_categ_id = ?");
if ($field === 'color') {
    bind_auto($stmt, [$value, $id]);
} else {
    bind_auto($stmt, [$value, $id]);
}
$stmt->execute();
$stmt->close();

$response['value'] = (string)$value;
echo json_encode($response);
