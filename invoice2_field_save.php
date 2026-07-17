<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$invoice2Id = (int)($_POST['invoice2_id'] ?? $_POST['id'] ?? 0);
$field      = (string)($_POST['field'] ?? '');
$value      = (string)($_POST['value'] ?? '');
$invoiceId  = (int)($_POST['invoice_id'] ?? 0);

function recalc_invoice_totals(mysqli $conn, int $invoiceId): array {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COALESCE(SUM(sum_nds),0), COUNT(*) FROM invoice2 WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum   = (float)$row[0];
    $sd    = (float)$row[1];
    $snds  = (float)$row[2];
    $pos   = (int)$row[3];
    $ps = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = (SELECT doctype_id FROM invoice WHERE invoice_id = ?)");
    $ps->bind_param('ii', $invoiceId, $invoiceId);
    $ps->execute();
    $sumPlat = (float)$ps->get_result()->fetch_row()[0];
    $ps->close();
    $upd = $conn->prepare("UPDATE invoice SET sum = ?, sum_discount = ?, sum_nds = ?, pos = ?, sum_plat = ? WHERE invoice_id = ?");
    bind_auto($upd, [$sum, $sd, $snds, $pos, $sumPlat, $invoiceId]);
    $upd->execute();
    $upd->close();
    return ['sum' => number_format($sum, 2, '.', ''), 'sum_discount' => number_format($sd, 2, '.', ''), 'sum_nds' => number_format($snds, 2, '.', ''), 'pos' => $pos, 'sum_plat' => number_format($sumPlat, 2, '.', '')];
}

if ($field === '_delete') {
    if ($invoice2Id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid params']);
        exit;
    }
    $q = $conn->query("SELECT invoice_id FROM invoice2 WHERE invoice2_id = $invoice2Id");
    $invId = $q && ($r = $q->fetch_assoc()) ? (int)$r['invoice_id'] : 0;
    $stmt = $conn->prepare("DELETE FROM invoice2 WHERE invoice2_id = ?");
    $stmt->bind_param('i', $invoice2Id);
    $stmt->execute();
    $stmt->close();
    $totals = $invId > 0 ? recalc_invoice_totals($conn, $invId) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_list') {
    $invId = $invoiceId > 0 ? $invoiceId : $invoice2Id;
    if ($invId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare("SELECT invoice2_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id ASC");
    $stmt->bind_param('i', $invId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $zeroFields = ['quant', 'price', 'discount', 'sum', 'sum_discount', 'sum_nds'];
    $out = array_map(function($r) use ($zeroFields) {
        $item = [
            'id' => (int)$r['invoice2_id'],
            'product_id' => (int)$r['product_id'],
            'code' => (string)$r['code'],
            'product_name' => (string)$r['product_name'],
            'quant' => (string)$r['quant'],
            'price' => (string)$r['price'],
            'discount' => (string)$r['discount'],
            'sum' => (string)$r['sum'],
            'sum_discount' => (string)$r['sum_discount'],
            'sum_nds' => (string)$r['sum_nds'],
            'note' => (string)$r['note'],
        ];
        foreach ($zeroFields as $f) {
            if (isset($item[$f]) && ((float)$item[$f]) == 0) $item[$f] = '';
        }
        foreach ($zeroFields as $f) {
            if (isset($item[$f]) && $item[$f] !== '') {
                $item[$f] = rtrim(rtrim($item[$f], '0'), '.');
            }
        }
        return $item;
    }, $rows);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}

if ($field === '_import_marked_count') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM marks WHERE tbl = 'product'");
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => (int)($countRow['cnt'] ?? 0)]);
    exit;
}

if ($field === '_import_marked') {
    $invId = $invoiceId;
    if ($invId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid invoice_id']);
        exit;
    }
    $discount = 0;
    $dStmt = $conn->prepare("SELECT discount FROM invoice WHERE invoice_id = ?");
    $dStmt->bind_param('i', $invId);
    $dStmt->execute();
    $dRes = $dStmt->get_result();
    if ($dRow = $dRes->fetch_assoc()) $discount = (float)$dRow['discount'];
    $dStmt->close();
    $mStmt = $conn->prepare("SELECT row_id FROM marks WHERE tbl = 'product'");
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    $ids = [];
    while ($mRow = $mRes->fetch_assoc()) $ids[] = (int)$mRow['row_id'];
    $mStmt->close();
    $inserted = 0;
    if (!empty($ids)) {
        $pStmt = $conn->prepare("SELECT product_id, product_name, article, price_out FROM product WHERE product_id = ?");
        $iStmt = $conn->prepare("INSERT INTO invoice2 (invoice_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note, guarantee, guarant_unit) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, 0, '', 0, '')");
        foreach ($ids as $pid) {
            $pStmt->bind_param('i', $pid);
            $pStmt->execute();
            $pRes = $pStmt->get_result();
            if ($pRow = $pRes->fetch_assoc()) {
                $price = (float)$pRow['price_out'];
                $code = (string)$pRow['article'];
                $pname = (string)$pRow['product_name'];
                $sum = $price * (1 - $discount / 100);
                $sumDisc = $price * ($discount / 100);
                bind_auto($iStmt, [$invId, $pid, $code, $pname, $price, $discount, $sum, $sumDisc]);
                $iStmt->execute();
                $inserted++;
            }
            $pRes->close();
        }
        $pStmt->close();
        $iStmt->close();
    }
    $conn->query("DELETE FROM marks WHERE tbl = 'product'");
    $totals = recalc_invoice_totals($conn, $invId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true, 'inserted' => $inserted], $totals));
    exit;
}

if ($field === '_recalc_totals') {
    $totals = $invoiceId > 0 ? recalc_invoice_totals($conn, $invoiceId) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_apply_discount') {
    $discount = (float)($_POST['discount'] ?? 0);
    $invId = (int)($_POST['invoice_id'] ?? 0);
    if ($invId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid invoice_id']);
        exit;
    }
    $discPct = $discount / 100;
    $stmt = $conn->prepare("UPDATE invoice2 SET discount = ?, sum = price * (1 - ?), sum_discount = price * ?, sum_nds = 0 WHERE invoice_id = ?");
    bind_auto($stmt, [$discount, $discPct, $discPct, $invId]);
    $stmt->execute();
    $stmt->close();
    $totals = recalc_invoice_totals($conn, $invId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '' || ($invoice2Id <= 0 && $invoiceId <= 0)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

$ALLOWED = [
    'product_id'   => ['type' => 'int'],
    'product_name' => ['type' => 'text'],
    'code'         => ['type' => 'text'],
    'quant'        => ['type' => 'decimal', 'decimals' => 3],
    'price'        => ['type' => 'decimal', 'decimals' => 2],
    'discount'     => ['type' => 'decimal', 'decimals' => 1],
    'sum'          => ['type' => 'decimal', 'decimals' => 2],
    'sum_discount' => ['type' => 'decimal', 'decimals' => 2],
    'sum_nds'      => ['type' => 'decimal', 'decimals' => 2],
    'guarantee'    => ['type' => 'int'],
    'guarant_unit' => ['type' => 'text'],
    'note'         => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

    $displayValue = null;
    $itemData = null;
    $actualInvId = $invoiceId;

if ($invoice2Id > 0) {
    switch ($ALLOWED[$field]['type']) {
        case 'text':
            $value = trim($value);
            $stmt = $conn->prepare("UPDATE invoice2 SET $field = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$value, $invoice2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $value !== '' ? h($value) : '';
            break;

        case 'int':
            $value = (int)$value;
            $stmt = $conn->prepare("UPDATE invoice2 SET $field = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$value, $invoice2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $value > 0 ? (string)$value : '';
            break;

        case 'decimal':
            $value = str_replace(',', '.', $value);
            $valFloat = (float)$value;
            $dec = $ALLOWED[$field]['decimals'] ?? 2;
            $stmt = $conn->prepare("UPDATE invoice2 SET $field = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$valFloat, $invoice2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $valFloat ? rtrim(rtrim(number_format($valFloat, $dec, ',', ' '), '0'), ',') : '';
            break;
    }
    $newId = $invoice2Id;
    if ($actualInvId <= 0) {
        $q = $conn->query("SELECT invoice_id FROM invoice2 WHERE invoice2_id = $invoice2Id");
        $actualInvId = $q && ($r = $q->fetch_assoc()) ? (int)$r['invoice_id'] : 0;
    }
    if ($field === 'product_id') {
        $q = $conn->query("SELECT product_name, code, price_out FROM product WHERE product_id = " . (int)$value);
        if ($q && ($r = $q->fetch_assoc())) {
            $stmt = $conn->prepare("UPDATE invoice2 SET product_name = ?, code = ?, price = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$r['product_name'], $r['code'], $r['price_out'], $invoice2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $r['product_name'];
            $value = (int)$value;
        }
    }
    $r = $conn->query("SELECT quant, price, discount FROM invoice2 WHERE invoice2_id = $invoice2Id");
    if ($r && ($row = $r->fetch_assoc())) {
        $quant = (float)$row['quant'];
        $price = (float)$row['price'];
        $discount = (float)$row['discount'];
        $sum = $quant * $price * (1 - $discount / 100);
        $sum_discount = $quant * $price * ($discount / 100);
        $sum_nds = 0;
        $uStmt = $conn->prepare("UPDATE invoice2 SET sum = ?, sum_discount = ?, sum_nds = ? WHERE invoice2_id = ?");
        bind_auto($uStmt, [$sum, $sum_discount, $sum_nds, $invoice2Id]);
        $uStmt->execute();
        $uStmt->close();
    }
    $q2 = $conn->query("SELECT invoice2_id as id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note FROM invoice2 WHERE invoice2_id = $invoice2Id");
    $itemData = $q2 ? $q2->fetch_assoc() : null;
    if ($itemData) {
        $zeroFields = ['quant', 'price', 'discount', 'sum', 'sum_discount', 'sum_nds'];
        foreach ($zeroFields as $f) {
            if (isset($itemData[$f]) && ((float)$itemData[$f]) == 0) $itemData[$f] = '';
        }
        foreach ($zeroFields as $f) {
            if (isset($itemData[$f]) && $itemData[$f] !== '') {
                $itemData[$f] = rtrim(rtrim($itemData[$f], '0'), '.');
            }
        }
    }
} else {
    $stmt = $conn->prepare("INSERT INTO invoice2 (invoice_id, $field) VALUES (?, ?)");
    $val = ($ALLOWED[$field]['type'] === 'text') ? $value : (float)str_replace(',', '.', $value);
    if ($ALLOWED[$field]['type'] === 'int') $val = (int)$value;
    $stmt->bind_param(($ALLOWED[$field]['type'] === 'text') ? 'is' : 'id', $invoiceId, $val);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();
    if ($field === 'product_id' && $newId > 0) {
        $q = $conn->query("SELECT product_name, code, price_out FROM product WHERE product_id = " . (int)$val);
        if ($q && ($r = $q->fetch_assoc())) {
            $stmt = $conn->prepare("UPDATE invoice2 SET product_name = ?, code = ?, price = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$r['product_name'], $r['code'], $r['price_out'], $newId]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $r['product_name'];
        }
    }
}

$totals = $actualInvId > 0 ? recalc_invoice_totals($conn, $actualInvId) : [];

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_merge(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue, 'id' => $newId, 'item' => $itemData], $totals));
