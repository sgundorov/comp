<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

function check_plat_doc_accepted(mysqli $conn, int $docId, int $docType): void {
    if (!in_array($docType, [20, 110, 120, 127], true)) return;
    $q = $conn->query("SELECT accept_flag FROM docum WHERE docum_id = $docId");
    $r = $q ? $q->fetch_assoc() : null;
    if ($r && (int)$r['accept_flag'] === 1) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Документ утверждён, редактирование запрещено']);
        exit;
    }
}

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');
$value = (string)($_POST['value'] ?? '');

if ($field === '_list') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    $listDocType = (int)($_REQUEST['doc_type'] ?? 10);
    if ($docId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([]);
        exit;
    }
    $stmt = $conn->prepare("SELECT p.plat_id, p.datetime, p.client_id, p.zat_id, p.sum, p.plat_type, p.out_flag, p.note, c.name AS client_name, z.name AS zat_name FROM plat p LEFT JOIN client c ON p.client_id = c.client_id LEFT JOIN zat z ON p.zat_id = z.zat_id WHERE p.doc_id = ? AND p.doc_type = ? ORDER BY p.plat_id DESC");
    bind_auto($stmt, [$docId, $listDocType]);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $result = array_map(function($r) {
        $dt = strtotime((string)$r['datetime']);
        return [
            'id' => (int)$r['plat_id'],
            'datetime' => $dt ? date('d.m.Y H:i', $dt) : '-',
            'client_name' => (string)($r['client_name'] ?? '-'),
            'client_id' => (int)$r['client_id'],
            'zat_name' => (string)($r['zat_name'] ?? '-'),
            'zat_id' => (int)$r['zat_id'],
            'sum' => (string)(float)$r['sum'],
            'plat_type' => (string)$r['plat_type'],
            'out_flag' => (int)$r['out_flag'] ? 'Расход' : 'Приход',
            'note' => (string)$r['note'],
        ];
    }, $rows);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($field === '_delete') {
    $delId = (int)($_POST['plat_id'] ?? $_POST['id'] ?? 0);
    if ($delId <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    $dq = $conn->query("SELECT doc_id, doc_type FROM plat WHERE plat_id = $delId");
    $docId = 0; $docType = 0;
    if ($dq && ($dr = $dq->fetch_assoc())) { $docId = (int)$dr['doc_id']; $docType = (int)$dr['doc_type']; }
    if ($docId > 0) check_plat_doc_accepted($conn, $docId, $docType);
    $stmt = $conn->prepare("DELETE FROM plat WHERE plat_id = ?");
    $stmt->bind_param('i', $delId);
    $stmt->execute();
    $stmt->close();
    $conn->query("DELETE FROM marks WHERE tbl = 'plat' AND row_id = $delId");
    $sumPlat = 0;
    if ($docId > 0 && in_array($docType, [10, 20, 110, 120, 127], true)) {
        $parentTable = $docType === 10 ? 'invoice' : 'docum';
        $parentKey = $docType === 10 ? 'invoice_id' : 'docum_id';
        $sp = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = ?");
        $sp->bind_param('ii', $docId, $docType);
        $sp->execute();
        $sumPlat = (float)$sp->get_result()->fetch_row()[0];
        $sp->close();
        $upd = $conn->prepare("UPDATE $parentTable SET sum_plat = ? WHERE $parentKey = ?");
        bind_auto($upd, [$sumPlat, $docId]);
        $upd->execute();
        $upd->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'mode' => 'delete', 'id' => $delId, 'sum_plat' => number_format($sumPlat, 2, '.', '')]);
    exit;
}

if ($id <= 0 || $field === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

$check = $conn->query("SELECT plat_id FROM plat WHERE plat_id = $id");
if (!$check || $check->num_rows === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Record not found']);
    exit;
}

$ALLOWED = [
    'datetime'  => ['type' => 'text'],
    'client_id' => ['type' => 'lookup_int'],
    'zat_id'    => ['type' => 'lookup_int'],
    'sum'       => ['type' => 'decimal'],
    'plat_type' => ['type' => 'text'],
    'doc_id'    => ['type' => 'int'],
    'out_flag'  => ['type' => 'out_flag'],
    'sotr_id'   => ['type' => 'lookup_int'],
    'note'      => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

$displayValue = null;

$docQ = $conn->query("SELECT doc_id, doc_type FROM plat WHERE plat_id = $id");
if ($docQ && ($docR = $docQ->fetch_assoc()) && (int)$docR['doc_id'] > 0) {
    check_plat_doc_accepted($conn, (int)$docR['doc_id'], (int)$docR['doc_type']);
}

switch ($ALLOWED[$field]['type']) {
    case 'text':
        $value = trim($value);
        $stmt = $conn->prepare("UPDATE plat SET $field = ? WHERE plat_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value !== '' ? h($value) : '';
        break;

    case 'int':
        $value = (int)$value;
        $stmt = $conn->prepare("UPDATE plat SET $field = ? WHERE plat_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value > 0 ? (string)$value : '';
        break;

    case 'decimal':
        $value = str_replace(',', '.', $value);
        $valFloat = (float)$value;
        $stmt = $conn->prepare("UPDATE plat SET $field = ? WHERE plat_id = ?");
        bind_auto($stmt, [$valFloat, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = number_format($valFloat, 2, ',', ' ');
        break;

    case 'out_flag':
        $value = (int)(!empty($value) ? 1 : 0);
        $stmt = $conn->prepare("UPDATE plat SET $field = ? WHERE plat_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value ? 'Расход' : 'Приход';
        break;

    case 'lookup_int':
        $value = (int)$value;
        $stmt = $conn->prepare("UPDATE plat SET $field = ? WHERE plat_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        if ($field === 'zat_id' && $value > 0) {
            $zr = $conn->query("SELECT out_flag FROM zat WHERE zat_id = $value");
            if ($zr && ($zrow = $zr->fetch_assoc())) {
                $of = (int)$zrow['out_flag'];
                $conn->query("UPDATE plat SET out_flag = $of WHERE plat_id = $id");
            }
        }
        if ($value > 0) {
            $tableMap = ['client_id' => ['table' => 'client', 'id_field' => 'client_id', 'name_field' => 'name'],
                         'zat_id'    => ['table' => 'zat',    'id_field' => 'zat_id',    'name_field' => 'name'],
                         'sotr_id'   => ['table' => 'sotr',   'id_field' => 'sotr_id',   'name_field' => 'name']];
            if (isset($tableMap[$field])) {
                $m = $tableMap[$field];
                $qr = $conn->query("SELECT {$m['name_field']} FROM {$m['table']} WHERE {$m['id_field']} = $value");
                if ($qr && ($rw = $qr->fetch_assoc())) $displayValue = h($rw[$m['name_field']]);
            }
        }
        break;
}

$sumPlat = 0;
$docQ = $conn->query("SELECT doc_id, doc_type FROM plat WHERE plat_id = $id");
if ($docQ && ($docR = $docQ->fetch_assoc()) && in_array((int)$docR['doc_type'], [10, 20, 110, 120, 127], true) && (int)$docR['doc_id'] > 0) {
    $docType = (int)$docR['doc_type'];
    $parentTable = $docType === 10 ? 'invoice' : 'docum';
    $parentKey = $docType === 10 ? 'invoice_id' : 'docum_id';
    $sp = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = ?");
    $sp->bind_param('ii', $docR['doc_id'], $docType);
    $sp->execute();
    $sumPlat = (float)$sp->get_result()->fetch_row()[0];
    $sp->close();
    $upd = $conn->prepare("UPDATE $parentTable SET sum_plat = ? WHERE $parentKey = ?");
    bind_auto($upd, [$sumPlat, $docR['doc_id']]);
    $upd->execute();
    $upd->close();
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue, 'sum_plat' => number_format($sumPlat, 2, '.', '')]);
