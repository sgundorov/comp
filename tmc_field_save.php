<?php
require_once __DIR__ . '/config.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    die('AJAX only');
}

$id    = (int)($_POST['id'] ?? 0);
$field = (string)($_POST['field'] ?? '');
$value = (string)($_POST['value'] ?? '');

if ($field === '_residue_recalc') {
    if ($id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid product id'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conn->query("DELETE FROM residue WHERE product_id = $id");
    $ins = $conn->prepare("INSERT INTO residue (wh_id, product_id, quant)
        SELECT d.store_id, d2.product_id,
               COALESCE(SUM(d2.quant * IF(tp.prihod_flag = 1, 1, -1)), 0)
        FROM docum2 d2
        JOIN docum d ON d.docum_id = d2.docum_id
        LEFT JOIN typeop tp ON tp.typeop_id = d.typeop
        WHERE d2.accept_flag != 0 AND d2.product_id = ?
        GROUP BY d.store_id, d2.product_id");
    if ($ins) {
        $ins->bind_param('i', $id);
        $ins->execute();
        $ins->close();
    }
    $ins2 = $conn->prepare("INSERT INTO residue (wh_id, product_id, quant)
        SELECT d.store2_id, d2.product_id,
               COALESCE(SUM(d2.quant), 0)
        FROM docum2 d2
        JOIN docum d ON d.docum_id = d2.docum_id
        WHERE d2.accept_flag != 0 AND d2.product_id = ? AND d.typeop = 100 AND d.store2_id > 0
        GROUP BY d.store2_id, d2.product_id
        ON DUPLICATE KEY UPDATE quant = quant + VALUES(quant)");
    if ($ins2) {
        $ins2->bind_param('i', $id);
        $ins2->execute();
        $ins2->close();
    }
    $conn->query("UPDATE product SET residue = (SELECT COALESCE(SUM(quant), 0) FROM residue WHERE product_id = $id), quantall = (SELECT COALESCE(SUM(quant), 0) FROM residue WHERE product_id = $id) WHERE product_id = $id");
    $rStmt = $conn->prepare("SELECT r.quant, COALESCE(st.name, '?') AS store_name FROM residue r LEFT JOIN store st ON r.wh_id = st.store_id WHERE r.product_id = ? ORDER BY st.name");
    $rows = [];
    if ($rStmt) {
        $rStmt->bind_param('i', $id);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        while ($rr = $rRes->fetch_assoc()) {
            $rows[] = ['store_name' => (string)$rr['store_name'], 'quant' => rtrim(rtrim(number_format((float)$rr['quant'], 3, '.', ''), '0'), '.')];
        }
        $rStmt->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id <= 0 || $field === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid params'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ALLOWED = [
    'name'     => ['type' => 's', 'db' => 'product_name',  'label' => 'Название'],
    'article'  => ['type' => 's', 'db' => 'article',       'label' => 'Артикул'],
    'categ'    => ['type' => 'i', 'db' => 'categ_id',      'label' => 'Категория',    'table' => 'categ', 'table_id' => 'categ_id', 'display' => 'categ'],
    'group'    => ['type' => 'i', 'db' => 'group_id',      'label' => 'Группа',       'table' => '`group`', 'table_id' => 'group_id', 'display' => 'name'],
    'sgroup'   => ['type' => 'i', 'db' => 'sgroup_id',     'label' => 'Подгруппа',    'table' => 'sgroup', 'table_id' => 'sgroup_id', 'display' => 'name'],
    'country'  => ['type' => 'i', 'db' => 'country_id',    'label' => 'Страна',       'table' => 'country', 'table_id' => 'country_id', 'display' => 'country'],
    'quant'    => ['type' => 'd', 'db' => 'residue',       'label' => 'Количество'],
    'price_in' => ['type' => 'd', 'db' => 'price_in',      'label' => 'Закупочная цена'],
    'price_out'=> ['type' => 'd', 'db' => 'price_out',     'label' => 'Розничная цена'],
    'note'     => ['type' => 's', 'db' => 'note',          'label' => 'Примечание'],
];

if (!isset($ALLOWED[$field])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Field not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$def = $ALLOWED[$field];
$dbField = $def['db'];

if ($def['type'] === 'i') {
    $val = (int)$value;
    if ($val > 0 && isset($def['table'])) {
        $stmt = @$conn->prepare("SELECT " . $def['display'] . " AS name FROM " . $def['table'] . " WHERE " . $def['table_id'] . " = ?");
        if ($stmt) {
            $stmt->bind_param('i', $val);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $r = $res->fetch_assoc()) {
                $display = (string)$r['name'];
            } else {
                $val = 0;
                $display = '';
            }
            $stmt->close();
        } else {
            $display = '';
        }
    } else {
        $val = 0;
        $display = '';
    }
    $stmt2 = @$conn->prepare("UPDATE product SET $dbField = ? WHERE product_id = ?");
    if ($stmt2) {
        bind_auto($stmt2, [$val, $id]);
        $stmt2->execute();
        $stmt2->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'field' => $field, 'value' => $val, 'display' => $display], JSON_UNESCAPED_UNICODE);
} elseif ($def['type'] === 'd') {
    $val = str_replace(',', '.', $value);
    $val = (float)$val;
    $stmt = @$conn->prepare("UPDATE product SET $dbField = ? WHERE product_id = ?");
    if ($stmt) {
        bind_auto($stmt, [$val, $id]);
        $stmt->execute();
        $stmt->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'field' => $field, 'value' => $val], JSON_UNESCAPED_UNICODE);
} else {
    $val = mb_substr($value, 0, 250);
    $stmt = @$conn->prepare("UPDATE product SET $dbField = ? WHERE product_id = ?");
    if ($stmt) {
        bind_auto($stmt, [$val, $id]);
        $stmt->execute();
        $stmt->close();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'field' => $field, 'value' => $val], JSON_UNESCAPED_UNICODE);
}
