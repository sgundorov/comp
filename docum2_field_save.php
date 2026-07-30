<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$docum2Id = (int)($_POST['docum2_id'] ?? $_POST['id'] ?? 0);
$field    = (string)($_POST['field'] ?? '');
$value    = (string)($_POST['value'] ?? '');
$documId  = (int)($_POST['docum_id'] ?? 0);

function check_docum_accepted(mysqli $conn, int $documId): void {
    $q = $conn->query("SELECT accept_flag FROM docum WHERE docum_id = $documId");
    $r = $q ? $q->fetch_assoc() : null;
    if ($r && (int)$r['accept_flag'] === 1) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Документ утверждён, редактирование запрещено']);
        exit;
    }
}

function _fmt_qty($v) { return fmt_num($v, 3); }
function _fmt_d2($v, $dec) { return fmt_num($v, $dec); }

function recalc_docum_totals(mysqli $conn, int $documId): array {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COALESCE(SUM(sum_nds),0), COUNT(*) FROM docum2 WHERE docum_id = ?");
    $stmt->bind_param('i', $documId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum  = (float)$row[0];
    $sd   = (float)$row[1];
    $snds = (float)$row[2];
    $pos  = (int)$row[3];
    $spStmt = $conn->prepare("SELECT d.typeop, COALESCE(tp.prihod_flag,0) AS prihod_flag FROM docum d LEFT JOIN typeop tp ON tp.typeop_id = d.typeop WHERE d.docum_id = ?");
    $spStmt->bind_param('i', $documId);
    $spStmt->execute();
    $spRow = $spStmt->get_result()->fetch_assoc();
    $spStmt->close();
    $typeop = (int)($spRow['typeop'] ?? 120);
    $prihodFlag = (int)($spRow['prihod_flag'] ?? 0);
    $ps = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = ?");
    $ps->bind_param('ii', $documId, $typeop);
    $ps->execute();
    $sumPlat = (float)$ps->get_result()->fetch_row()[0];
    $ps->close();
    $sumPlatRed = $prihodFlag ? ($sum > -$sumPlat) : ($sumPlat < $sum);
    $sumBalans = $sum - $sumPlat;
    $upd = $conn->prepare("UPDATE docum SET sum = ?, sum_discount = ?, pos = ?, sum_balans = ?, sum_plat = ? WHERE docum_id = ?");
    bind_auto($upd, [$sum, $sd, $pos, $sumBalans, $sumPlat, $documId]);
    $upd->execute();
    $upd->close();
    $sumPlatFmt = _fmt_d2($sumPlat, 2);
    return ['total_sum' => _fmt_d2($sum, 2), 'total_sum_discount' => _fmt_d2($sd, 2), 'total_sum_nds' => _fmt_d2($snds, 2), 'pos' => $pos, 'sum_plat' => $sumPlatFmt, 'sum_plat_red' => $sumPlatRed];
}

$field = $field === '' ? $action : $field;

if ($field === '_apply_discount') {
    $discount = (float)($_POST['discount'] ?? 0);
    $docId = (int)($_POST['docum_id'] ?? 0);
    if ($docId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid docum_id']);
        exit;
    }
    check_docum_accepted($conn, $docId);
    $ndsRate = (int)($appSettings['nds_rate'] ?? 22);
    $noNds   = ($appSettings['no_nds'] ?? '0') === '1';
    $stmt = $conn->prepare("UPDATE docum2 SET discount = ?, sum = quant * price * (1 - ? / 100), sum_discount = quant * price * (? / 100), sum_nds = (CASE WHEN ? = 1 THEN quant * price * (1 - ? / 100) * ? / (100 + ?) ELSE quant * price * (1 - ? / 100) * ? / 100 END) WHERE docum_id = ?");
    bind_auto($stmt, [$discount, $discount, $discount, $noNds ? 1 : 0, $discount, $ndsRate, $ndsRate, $discount, $ndsRate, $docId]);
    $stmt->execute();
    $stmt->close();
    $hStmt = $conn->prepare("UPDATE docum SET discount = ? WHERE docum_id = ?");
    $hStmt->bind_param('di', $discount, $docId);
    $hStmt->execute();
    $hStmt->close();
    $totals = recalc_docum_totals($conn, $docId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'delete') {
    $idsRaw = (string)($_POST['ids'] ?? '');
    $ids = array_filter(array_map('intval', explode(',', $idsRaw)), function($x) { return $x > 0; });
    if (empty($ids)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No ids']);
        exit;
    }
    $in = implode(',', $ids);
    $q = $conn->query("SELECT DISTINCT docum_id FROM docum2 WHERE docum2_id IN ($in)");
    $docId = 0;
    if ($q && ($r = $q->fetch_assoc())) $docId = (int)$r['docum_id'];
    if ($docId > 0) check_docum_accepted($conn, $docId);
    $conn->query("DELETE FROM docum2 WHERE docum2_id IN ($in)");
    $totals = $docId > 0 ? recalc_docum_totals($conn, $docId) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_delete') {
    if ($docum2Id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid params']);
        exit;
    }
    $q = $conn->query("SELECT docum_id FROM docum2 WHERE docum2_id = $docum2Id");
    $dId = $q && ($r = $q->fetch_assoc()) ? (int)$r['docum_id'] : 0;
    if ($dId > 0) check_docum_accepted($conn, $dId);
    $stmt = $conn->prepare("DELETE FROM docum2 WHERE docum2_id = ?");
    $stmt->bind_param('i', $docum2Id);
    $stmt->execute();
    $stmt->close();
    $totals = $dId > 0 ? recalc_docum_totals($conn, $dId) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '_list') {
    $dId = $documId > 0 ? $documId : $docum2Id;
    if ($dId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare("SELECT docum2_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note FROM docum2 WHERE docum_id = ? ORDER BY docum2_id ASC");
    $stmt->bind_param('i', $dId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $zeroFields = ['quant', 'price', 'discount', 'sum', 'sum_discount', 'sum_nds'];
    $out = array_map(function($r) use ($zeroFields) {
        $item = [
            'id' => (int)$r['docum2_id'],
            'product_id' => (int)$r['product_id'],
            'code' => (string)$r['code'],
            'product_name' => (string)$r['product_name'],
            'quant' => _fmt_qty($r['quant']),
            'price' => _fmt_d2($r['price'], 2),
            'discount' => _fmt_d2($r['discount'], 1),
            'sum' => _fmt_d2($r['sum'], 2),
            'sum_discount' => _fmt_d2($r['sum_discount'], 2),
            'sum_nds' => _fmt_d2($r['sum_nds'], 2),
            'note' => (string)$r['note'],
        ];
        foreach ($zeroFields as $f) {
            if (isset($item[$f]) && $item[$f] !== '' && ((float)str_replace(',', '.', $item[$f])) == 0) $item[$f] = '';
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
    $dId = $documId;
    if ($dId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid docum_id']);
        exit;
    }
    check_docum_accepted($conn, $dId);
    $ndsRate = (int)($appSettings['nds_rate'] ?? 22);
    $noNds   = ($appSettings['no_nds'] ?? '0') === '1';
    $discount = 0;
    $parentStoreId = 0;
    $dStmt = $conn->prepare("SELECT discount, store_id, typeop FROM docum WHERE docum_id = ?");
    $dStmt->bind_param('i', $dId);
    $dStmt->execute();
    $dRes = $dStmt->get_result();
    $parentTypeop = 120;
    if ($dRow = $dRes->fetch_assoc()) {
        $discount = (float)$dRow['discount'];
        $parentStoreId = (int)$dRow['store_id'];
        $parentTypeop = (int)$dRow['typeop'];
    }
    $dStmt->close();
    $mStmt = $conn->prepare("SELECT row_id FROM marks WHERE tbl = 'product'");
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    $ids = [];
    while ($mRow = $mRes->fetch_assoc()) $ids[] = (int)$mRow['row_id'];
    $mStmt->close();
    $priceCol = in_array($parentTypeop, [20, 110, 127], true) ? 'price_in' : 'price_out';
    $inserted = 0;
    if (!empty($ids)) {
        $pStmt = $conn->prepare("SELECT product_id, product_name, article, $priceCol AS price FROM product WHERE product_id = ?");
        $iStmt = $conn->prepare("INSERT INTO docum2 (docum_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note, typeop, store_id) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, '', $parentTypeop, ?)");
        if (!$iStmt) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'prepare failed: ' . $conn->error]);
            exit;
        }
        foreach ($ids as $pid) {
            $pStmt->bind_param('i', $pid);
            $pStmt->execute();
            $pRes = $pStmt->get_result();
            if ($pRow = $pRes->fetch_assoc()) {
                $price = (float)$pRow['price'];
                $code = (string)$pRow['article'];
                $pname = (string)$pRow['product_name'];
                $sum = $price * (1 - $discount / 100);
                $sumDisc = $price * ($discount / 100);
                $sumNds = $noNds ? ($sum * $ndsRate / (100 + $ndsRate)) : ($sum * $ndsRate / 100);
                bind_auto($iStmt, [$dId, $pid, $code, $pname, $price, $discount, $sum, $sumDisc, $sumNds, $parentStoreId]);
                if (!$iStmt->execute()) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => 'insert failed: ' . $iStmt->error, 'sql' => $conn->error]);
                    exit;
                }
                $inserted++;
            }
            $pRes->close();
        }
        $pStmt->close();
        $iStmt->close();
    }
    $conn->query("DELETE FROM marks WHERE tbl = 'product'");
    $totals = recalc_docum_totals($conn, $dId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true, 'inserted' => $inserted, 'ids' => $ids], $totals));
    exit;
}

if ($field === '_recalc_totals') {
    $totals = $documId > 0 ? recalc_docum_totals($conn, $documId) : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $totals));
    exit;
}

if ($field === '' || ($docum2Id <= 0 && $documId <= 0)) {
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
    'note'         => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

$displayValue = null;
$actualDocumId = $documId;
$newId = 0;
$itemData = null;

if ($docum2Id > 0) {
    switch ($ALLOWED[$field]['type']) {
        case 'text':
            $value = trim($value);
            $stmt = $conn->prepare("UPDATE docum2 SET $field = ? WHERE docum2_id = ?");
            bind_auto($stmt, [$value, $docum2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $value !== '' ? h($value) : '';
            break;

        case 'int':
            $value = (int)$value;
            $stmt = $conn->prepare("UPDATE docum2 SET $field = ? WHERE docum2_id = ?");
            bind_auto($stmt, [$value, $docum2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $value > 0 ? (string)$value : '';
            break;

        case 'decimal':
            $value = str_replace(',', '.', $value);
            $valFloat = (float)$value;
            if ($field === 'quant' && !empty($appSettings['neg_ost_flag']) && $appSettings['neg_ost_flag'] === '1') {
                $d2 = $conn->query("SELECT docum_id, product_id FROM docum2 WHERE docum2_id = $docum2Id")->fetch_assoc();
                $dId = $d2 ? (int)$d2['docum_id'] : 0;
                $pid = $d2 ? (int)$d2['product_id'] : 0;
                if ($dId > 0 && $pid > 0) {
                    $nq = $conn->query("SELECT noquant_flag FROM product WHERE product_id = $pid")->fetch_assoc();
                    if (!$nq || !(int)$nq['noquant_flag']) {
                        $pf = $conn->query("SELECT COALESCE(tp.prihod_flag,0) AS pf, d.store_id FROM docum d LEFT JOIN typeop tp ON tp.typeop_id = d.typeop WHERE d.docum_id = $dId")->fetch_assoc();
                        if ($pf && !(int)$pf['pf'] && (int)$pf['store_id'] > 0) {
                            $rr = $conn->query("SELECT COALESCE(quant,0) AS q FROM residue WHERE wh_id = {$pf['store_id']} AND product_id = $pid")->fetch_assoc();
                            $residueQ = $rr ? (float)$rr['q'] : 0;
                            if ($valFloat > $residueQ) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['ok' => false, 'error' => 'Недостаточно остатка товара на участке. Текущий остаток: ' . _fmt_qty($residueQ)]);
                                exit;
                            }
                        }
                    }
                }
            }
            $stmt = $conn->prepare("UPDATE docum2 SET $field = ? WHERE docum2_id = ?");
            bind_auto($stmt, [$valFloat, $docum2Id]);
            $stmt->execute();
            $stmt->close();
            if (in_array($field, ['price', 'sum', 'sum_discount'])) {
                $displayValue = _fmt_d2($value, 2);
            } elseif ($field === 'discount') {
                $displayValue = _fmt_d2($value, 1);
            } else {
                $displayValue = _fmt_qty($value);
            }
            break;
    }
    $newId = $docum2Id;
    if ($actualDocumId <= 0) {
        $q = $conn->query("SELECT docum_id FROM docum2 WHERE docum2_id = $docum2Id");
        $actualDocumId = $q && ($r = $q->fetch_assoc()) ? (int)$r['docum_id'] : 0;
    }
    if ($actualDocumId > 0) check_docum_accepted($conn, $actualDocumId);
    if ($field === 'product_id') {
        $priceCol = 'price_out';
        if ($actualDocumId > 0) {
            $pt = $conn->query("SELECT typeop FROM docum WHERE docum_id = $actualDocumId");
            $ptRow = $pt ? $pt->fetch_assoc() : null;
            if ($ptRow) $priceCol = in_array((int)$ptRow['typeop'], [20, 110, 127], true) ? 'price_in' : 'price_out';
        }
        $q = $conn->query("SELECT product_name, article AS code, $priceCol AS price FROM product WHERE product_id = " . (int)$value);
        if ($q && ($r = $q->fetch_assoc())) {
            $stmt = $conn->prepare("UPDATE docum2 SET product_name = ?, code = ?, price = ? WHERE docum2_id = ?");
            bind_auto($stmt, [$r['product_name'], $r['code'], $r['price'], $docum2Id]);
            $stmt->execute();
            $stmt->close();
            $displayValue = $r['product_name'];
            $value = (int)$value;
        }
    }
    $r = $conn->query("SELECT quant, price, discount FROM docum2 WHERE docum2_id = $docum2Id");
    if ($r && ($row = $r->fetch_assoc())) {
        $quant = (float)$row['quant'];
        $price = (float)$row['price'];
        $discount = (float)$row['discount'];
        $sum = $quant * $price * (1 - $discount / 100);
        $sum_discount = $quant * $price * ($discount / 100);
        $ndsRate = (int)($appSettings['nds_rate'] ?? 22);
        $noNds   = ($appSettings['no_nds'] ?? '0') === '1';
        $sum_nds = $noNds ? ($sum * $ndsRate / (100 + $ndsRate)) : ($sum * $ndsRate / 100);
        $uStmt = $conn->prepare("UPDATE docum2 SET sum = ?, sum_discount = ?, sum_nds = ? WHERE docum2_id = ?");
        bind_auto($uStmt, [$sum, $sum_discount, $sum_nds, $docum2Id]);
        $uStmt->execute();
        $uStmt->close();
    }
    $q2 = $conn->query("SELECT docum2_id AS id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note FROM docum2 WHERE docum2_id = $docum2Id");
    $itemData = $q2 ? $q2->fetch_assoc() : null;
} else {
    if ($documId > 0) check_docum_accepted($conn, $documId);
    $stmt = $conn->prepare("INSERT INTO docum2 (docum_id, $field) VALUES (?, ?)");
    $val = ($ALLOWED[$field]['type'] === 'text') ? $value : (float)str_replace(',', '.', $value);
    if ($ALLOWED[$field]['type'] === 'int') $val = (int)$value;
    $stmt->bind_param(($ALLOWED[$field]['type'] === 'text') ? 'is' : 'id', $documId, $val);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();
    if ($documId > 0 && $newId > 0) {
        $pq = $conn->query("SELECT store_id, typeop FROM docum WHERE docum_id = $documId")->fetch_assoc();
        if ($pq) {
            $upd = $conn->prepare("UPDATE docum2 SET typeop = ?, store_id = ? WHERE docum2_id = ?");
            $upd->bind_param('iii', (int)$pq['typeop'], (int)$pq['store_id'], $newId);
            $upd->execute();
            $upd->close();
        }
    }
}

$totals = $actualDocumId > 0 ? recalc_docum_totals($conn, $actualDocumId) : [];

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_merge(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue, 'id' => $newId, 'item' => $itemData], $totals));
