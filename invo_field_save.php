<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/invo_columns.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$field   = (string)($_POST['field'] ?? '');
$value   = (string)($_POST['value'] ?? '');
$id      = (int)($_POST['id'] ?? $_POST['invoice_id'] ?? 0);

$ALLOWED = [
    'number'    => ['type' => 'int'],
    'date'      => ['type' => 'text'],
    'client_id' => ['type' => 'int'],
    'state'     => ['type' => 'text'],
    'store_id'  => ['type' => 'int'],
    'discount'  => ['type' => 'float'],
    'sum'       => ['type' => 'float'],
    'sotr_id'   => ['type' => 'int'],
    'note'      => ['type' => 'text'],
];

if ($field === '_list') {
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $stmt = $conn->prepare("SELECT invoice2_id AS id, product_name, quant, price, discount, sum, note FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id ASC");
    $stmt->bind_param('i', $invoiceId);
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
    $q = $conn->query("SELECT invoice_id FROM invoice2 WHERE invoice2_id = $id");
    $delInvoiceId = $q ? (int)$q->fetch_assoc()['invoice_id'] : 0;
    $stmt = $conn->prepare("DELETE FROM invoice2 WHERE invoice2_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    recalc_invoice_totals($conn, $delInvoiceId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, '_deleted' => true, 'id' => $id]);
    exit;
}

if ($field === '_recalc_totals') {
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    if ($invoiceId > 0) recalc_invoice_totals($conn, $invoiceId);
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

$val = $ALLOWED[$field]['type'] === 'text' ? trim($value) : (float)str_replace(',', '.', $value);

$stmt = $conn->prepare("UPDATE invoice SET $field = ? WHERE invoice_id = ?");
$stmt->bind_param($ALLOWED[$field]['type'] === 'text' ? 'si' : 'di', $val, $id);
$stmt->execute();
$stmt->close();

if ($field === 'discount') {
    $discountPct = (float)$val;
    $upd2 = $conn->prepare("UPDATE invoice2 SET sum = ROUND(quant * price * (1 - ? / 100), 2), sum_discount = ROUND(quant * price * ? / 100, 2) WHERE invoice_id = ?");
    bind_auto($upd2, [$discountPct, $discountPct, $id]);
    $upd2->execute();
    $upd2->close();
    recalc_invoice_totals($conn, $id);
}

$q = $conn->query("SELECT * FROM invoice WHERE invoice_id = $id");
$item = $q ? $q->fetch_assoc() : null;
if ($item) $item['id'] = (int)$item['invoice_id'];

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);

function recalc_invoice_totals(mysqli $conn, int $invoiceId): void {
    if ($invoiceId <= 0) return;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COALESCE(SUM(sum_nds),0), COUNT(*) FROM invoice2 WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum = (float)$row[0];
    $sd  = (float)$row[1];
    $snds = (float)$row[2];
    $pos = (int)$row[3];
    $upd = $conn->prepare("UPDATE invoice SET sum = ?, sum_discount = ?, sum_nds = ?, pos = ? WHERE invoice_id = ?");
    bind_auto($upd, [$sum, $sd, $snds, $pos, $invoiceId]);
    $upd->execute();
    $upd->close();
}
