<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$ainv2Id = (int)($_POST['id'] ?? 0);
$field   = (string)($_POST['field'] ?? '');
$value   = (string)($_POST['value'] ?? '');
$number  = (int)($_POST['number'] ?? 0);

function _fmt_qty($v) { return fmt_num($v, 3); }
function _fmt_d2($v) { return fmt_num($v, 2); }

function ainv2_recalc_totals(mysqli $conn, int $number): array {
    if ($number <= 0) return ['sum' => '', 'pos' => ''];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COUNT(*) FROM ainv2 WHERE number = ?");
    $stmt->bind_param('i', $number);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum = (float)$row[0];
    $pos = (int)$row[1];
    $upd = $conn->prepare("UPDATE ainv SET sum = ?, poz = ? WHERE number = ?");
    bind_auto($upd, [$sum, $pos, $number]);
    $upd->execute();
    $upd->close();
    return ['sum' => number_format($sum, 2, '.', ''), 'pos' => $pos];
}

function ainv2_get_number(mysqli $conn, int $id): int {
    $q = $conn->query("SELECT number FROM ainv2 WHERE id = $id");
    return $q && ($r = $q->fetch_assoc()) ? (int)$r['number'] : 0;
}

function ainv2_check_accepted(mysqli $conn, int $number): void {
    if ($number <= 0) return;
    $q = $conn->prepare("SELECT accept_flag FROM ainv WHERE number = ?");
    $q->bind_param('i', $number);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    if ($r && (int)$r['accept_flag'] === 1) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Акт утверждён, редактирование запрещено']);
        exit;
    }
}

function ainv2_fmt_item(array $r): array {
    return [
        'id'           => (int)$r['id'],
        'product_id'   => (int)$r['product_id'],
        'code'         => (string)$r['code'],
        'product_name' => (string)$r['product_name'],
        'quant_old'    => _fmt_qty($r['quant_old']),
        'quant'        => _fmt_qty($r['quant']),
        'dif'          => _fmt_qty($r['dif']),
        'price'        => _fmt_d2($r['price']),
        'sum'          => _fmt_d2($r['sum']),
        'state'        => (int)$r['state'] > 0 ? (string)(int)$r['state'] : '',
        'note'         => (string)$r['note'],
    ];
}

function ainv2_recalc_item(mysqli $conn, int $id): void {
    $q = $conn->query("SELECT quant_old, quant, price FROM ainv2 WHERE id = $id");
    if (!$q || !($row = $q->fetch_assoc())) return;
    $quantOld = (float)$row['quant_old'];
    $quant    = (float)$row['quant'];
    $price    = (float)$row['price'];
    $dif = $quant - $quantOld;
    $sum = $price * $dif;
    $upd = $conn->prepare("UPDATE ainv2 SET dif = ?, sum = ? WHERE id = ?");
    bind_auto($upd, [$dif, $sum, $id]);
    $upd->execute();
    $upd->close();
}

if ($field === '_list') {
    $num = $number;
    if ($num <= 0 && $ainv2Id > 0) $num = ainv2_get_number($conn, $ainv2Id);
    if ($num <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, number, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE number = ? ORDER BY id ASC");
    $stmt->bind_param('i', $num);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = array_map(function ($r) { return ainv2_fmt_item($r); }, $rows);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

if ($field === '_delete') {
    if ($ainv2Id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid params']);
        exit;
    }
    $num = $number > 0 ? $number : ainv2_get_number($conn, $ainv2Id);
    ainv2_check_accepted($conn, $num);
    $stmt = $conn->prepare("DELETE FROM ainv2 WHERE id = ?");
    $stmt->bind_param('i', $ainv2Id);
    $stmt->execute();
    $stmt->close();
    $totals = ainv2_recalc_totals($conn, $num);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_recalc_totals') {
    $totals = $number > 0 ? ainv2_recalc_totals($conn, $number) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_barcode_add') {
    $num = $number;
    if ($num <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid params']);
        exit;
    }
    ainv2_check_accepted($conn, $num);
    $code = trim((string)($_POST['value'] ?? ''));
    if ($code === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Введите штрихкод']);
        exit;
    }
    $stmt = $conn->prepare("SELECT product_id, product_name, code, residue, price_in FROM product WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$prod) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Товар со штрихкодом «' . $code . '» не найден']);
        exit;
    }
    $pStmt = $conn->prepare("SELECT number, date, time, firm_id, mesto_id FROM ainv WHERE number = ?");
    $pStmt->bind_param('i', $num);
    $pStmt->execute();
    $parent = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();

    $productId   = (int)$prod['product_id'];
    $productName = (string)$prod['product_name'];
    $prodCode    = (string)$prod['code'];
    $quantOld    = (float)($prod['residue'] ?: 0);
    $price       = (float)($prod['price_in'] ?: 0);
    $quant       = 0.0;
    $dif         = $quant - $quantOld;
    $sum         = $price * $dif;

    $stmt = $conn->prepare("INSERT INTO ainv2 (number, date, time, firm_id, mesto_id, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, '')");
    bind_auto($stmt, [
        $num,
        $parent ? (string)$parent['date'] : date('Y-m-d'),
        $parent ? (string)$parent['time'] : date('H:i:s'),
        $parent ? (int)$parent['firm_id'] : 0,
        $parent ? (int)$parent['mesto_id'] : 0,
        $productId, $prodCode, $productName, $quantOld, $quant, $dif, $price, $sum,
    ]);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    $totals = ainv2_recalc_totals($conn, $num);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true, 'id' => $newId], $totals));
    exit;
}

if ($field === '' || $ainv2Id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

$ALLOWED = [
    'product_id' => ['type' => 'int'],
    'quant'      => ['type' => 'decimal', 'decimals' => 3],
    'state'      => ['type' => 'int'],
    'note'       => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

$num = $number > 0 ? $number : ainv2_get_number($conn, $ainv2Id);
ainv2_check_accepted($conn, $num);
$displayValue = null;

switch ($ALLOWED[$field]['type']) {
    case 'text':
        $value = trim($value);
        $stmt = $conn->prepare("UPDATE ainv2 SET $field = ? WHERE id = ?");
        bind_auto($stmt, [$value, $ainv2Id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value !== '' ? h($value) : '';
        break;

    case 'int':
        $value = (int)$value;
        if ($field === 'state' && ($value < 0 || $value > 10)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Состояние должно быть от 0 до 10']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE ainv2 SET $field = ? WHERE id = ?");
        bind_auto($stmt, [$value, $ainv2Id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value > 0 ? (string)$value : '';
        break;

    case 'decimal':
        $value = str_replace(',', '.', $value);
        $valFloat = (float)$value;
        $stmt = $conn->prepare("UPDATE ainv2 SET $field = ? WHERE id = ?");
        bind_auto($stmt, [$valFloat, $ainv2Id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $valFloat ? _fmt_qty($valFloat) : '';
        break;
}

if ($field === 'product_id') {
    $q = $conn->query("SELECT product_name, code, residue, price_in FROM product WHERE product_id = " . (int)$value);
    if ($q && ($r = $q->fetch_assoc())) {
        $stmt = $conn->prepare("UPDATE ainv2 SET product_name = ?, code = ?, quant_old = ?, price = ? WHERE id = ?");
        bind_auto($stmt, [$r['product_name'], $r['code'], $r['residue'] ?: 0, $r['price_in'] ?: 0, $ainv2Id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $r['product_name'];
    }
}

if ($field === 'product_id' || $field === 'quant') {
    ainv2_recalc_item($conn, $ainv2Id);
}

$totals = $num > 0 ? ainv2_recalc_totals($conn, $num) : [];

$q = $conn->query("SELECT id, number, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE id = $ainv2Id");
$itemData = $q ? ainv2_fmt_item($q->fetch_assoc()) : null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_merge(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue, 'id' => $ainv2Id, 'item' => $itemData], $totals));
