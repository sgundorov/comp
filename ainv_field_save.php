<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$field   = (string)($_POST['field'] ?? '');
$value   = (string)($_POST['value'] ?? '');
$id      = (int)($_POST['id'] ?? $_POST['number'] ?? 0);

$ALLOWED = [
    'date'         => ['type' => 'text'],
    'time'         => ['type' => 'text'],
    'mesto_id'     => ['type' => 'int'],
    'firm_id'      => ['type' => 'int'],
    'sotr_id'      => ['type' => 'int'],
    'first_card'   => ['type' => 'int'],
    'last_card'    => ['type' => 'int'],
    'type'         => ['type' => 'int'],
    'accept_flag'  => ['type' => 'int'],
    'note'         => ['type' => 'text'],
];

if ($field === '_list') {
    $num = (int)($_POST['number'] ?? $id);
    $stmt = $conn->prepare("SELECT id, number, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE number = ? ORDER BY id ASC");
    $stmt->bind_param('i', $num);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($field === '_delete') {
    if ($id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    $q = $conn->query("SELECT number FROM ainv2 WHERE id = $id");
    $delNumber = $q ? (int)$q->fetch_assoc()['number'] : 0;
    if ($delNumber > 0) {
        $achk = $conn->prepare("SELECT accept_flag FROM ainv WHERE number = ?");
        $achk->bind_param('i', $delNumber);
        $achk->execute();
        $arow = $achk->get_result()->fetch_assoc();
        $achk->close();
        if ($arow && (int)$arow['accept_flag'] === 1) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Акт утверждён, редактирование запрещено']);
            exit;
        }
    }
    $stmt = $conn->prepare("DELETE FROM ainv2 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    if ($delNumber > 0) {
        $recalc = $conn->prepare("SELECT COALESCE(SUM(sum),0), COUNT(*) FROM ainv2 WHERE number = ?");
        $recalc->bind_param('i', $delNumber);
        $recalc->execute();
        $row = $recalc->get_result()->fetch_row();
        $recalc->close();
        $upd = $conn->prepare("UPDATE ainv SET sum = ?, poz = ? WHERE number = ?");
        bind_auto($upd, [(float)$row[0], (int)$row[1], $delNumber]);
        $upd->execute();
        $upd->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, '_deleted' => true, 'id' => $id]);
    exit;
}

if ($field === '_recalc_totals') {
    $num = (int)($_POST['number'] ?? $id);
    if ($num > 0) {
        $recalc = $conn->prepare("SELECT COALESCE(SUM(sum),0), COUNT(*) FROM ainv2 WHERE number = ?");
        $recalc->bind_param('i', $num);
        $recalc->execute();
        $row = $recalc->get_result()->fetch_row();
        $recalc->close();
        $upd = $conn->prepare("UPDATE ainv SET sum = ?, poz = ? WHERE number = ?");
        bind_auto($upd, [(float)$row[0], (int)$row[1], $num]);
        $upd->execute();
        $upd->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'sum' => number_format((float)$row[0], 2, '.', ''), 'pos' => (int)$row[1]]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($field === '' || $id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unknown field']);
    exit;
}

$achk = $conn->prepare("SELECT accept_flag FROM ainv WHERE number = ?");
$achk->bind_param('i', $id);
$achk->execute();
$arow = $achk->get_result()->fetch_assoc();
$achk->close();
if ($arow && (int)$arow['accept_flag'] === 1 && $field !== 'note') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Акт утверждён, редактирование запрещено']);
    exit;
}

$val = $ALLOWED[$field]['type'] === 'text' ? trim($value) : (float)str_replace(',', '.', $value);
if ($ALLOWED[$field]['type'] === 'int') $val = (int)$val;

$stmt = $conn->prepare("UPDATE ainv SET $field = ? WHERE number = ?");
bind_auto($stmt, [$val, $id]);
$stmt->execute();
$stmt->close();

$q = $conn->query("SELECT number AS id, date, time, mesto_id, firm_id, sotr_id, first_card, last_card, sum, poz AS pos, type, accept_flag, note FROM ainv WHERE number = $id");
$item = $q ? $q->fetch_assoc() : null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
