<?php
require_once __DIR__ . '/config.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');
$value = (string)($_POST['value'] ?? '');

if ($id <= 0 || $field === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

$check = $conn->query("SELECT invoice_id FROM invoice WHERE invoice_id = $id");
if (!$check || $check->num_rows === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Record not found']);
    exit;
}

$ALLOWED = [
    'number'       => ['type' => 'text'],
    'date'         => ['type' => 'text'],
    'time'         => ['type' => 'text'],
    'client_id'    => ['type' => 'lookup_int'],
    'state'        => ['type' => 'text'],
    'store_id'     => ['type' => 'lookup_int'],
    'discount'     => ['type' => 'decimal'],
    'sum_discount' => ['type' => 'decimal'],
    'sum'          => ['type' => 'decimal'],
    'sum_nds'      => ['type' => 'decimal'],
    'sum_plat'     => ['type' => 'decimal'],
    'date_plat'    => ['type' => 'text'],
    'sotr_id'      => ['type' => 'lookup_int'],
    'pos'          => ['type' => 'int'],
    'note'         => ['type' => 'text'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed']);
    exit;
}

$displayValue = null;

switch ($ALLOWED[$field]['type']) {
    case 'text':
        $value = trim($value);
        $stmt = $conn->prepare("UPDATE invoice SET $field = ? WHERE invoice_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value !== '' ? h($value) : '';
        break;

    case 'int':
        $value = (int)$value;
        $stmt = $conn->prepare("UPDATE invoice SET $field = ? WHERE invoice_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = $value > 0 ? (string)$value : '';
        break;

    case 'decimal':
        $value = str_replace(',', '.', $value);
        $valFloat = (float)$value;
        $stmt = $conn->prepare("UPDATE invoice SET $field = ? WHERE invoice_id = ?");
        bind_auto($stmt, [$valFloat, $id]);
        $stmt->execute();
        $stmt->close();
        $displayValue = number_format($valFloat, 2, ',', ' ');
        break;

    case 'lookup_int':
        $value = (int)$value;
        $stmt = $conn->prepare("UPDATE invoice SET $field = ? WHERE invoice_id = ?");
        bind_auto($stmt, [$value, $id]);
        $stmt->execute();
        $stmt->close();
        if ($value > 0) {
            $tableMap = ['client_id' => ['table' => 'client', 'id_field' => 'client_id', 'name_field' => 'name'],
                         'store_id'  => ['table' => 'store',  'id_field' => 'store_id',  'name_field' => 'name'],
                         'sotr_id'   => ['table' => 'sotr',   'id_field' => 'sotr_id',   'name_field' => 'doc_name']];
            if (isset($tableMap[$field])) {
                $m = $tableMap[$field];
                $qr = $conn->query("SELECT {$m['name_field']} FROM {$m['table']} WHERE {$m['id_field']} = $value");
                if ($qr && ($rw = $qr->fetch_assoc())) $displayValue = h($rw[$m['name_field']]);
            }
        }
        break;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'field' => $field, 'value' => $value, 'displayValue' => $displayValue]);
