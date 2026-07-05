<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

$isAjax = (string)($_POST['ajax'] ?? '1') === '1' || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
$response = ['ok' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
if ($invoiceId <= 0) {
    $response['error'] = 'Invalid invoice ID';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT client_id, discount, store_id, note, number FROM invoice WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) {
        throw new Exception('Счёт не найден');
    }

    $stmt = $conn->prepare("SELECT product_id, product_name, code, quant, price, discount, sum, sum_discount, note FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $typeop = 120;
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM docum WHERE typeop = $typeop");
    $nextNum = 1;
    if ($nr && ($nrow = $nr->fetch_assoc())) $nextNum = (int)$nrow['next_num'];

    $totalSum = 0;
    $totalSumDiscount = 0;
    $pos = 0;
    foreach ($items as $item) {
        $totalSum += (float)($item['sum'] ?? 0);
        $totalSumDiscount += (float)($item['sum_discount'] ?? 0);
        $pos++;
    }

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $discount = (float)($inv['discount'] ?? 0);
    $clientId = (int)($inv['client_id'] ?? 0);
    $storeId = (int)($inv['store_id'] ?? 0);
    $sotrId = (int)($CurSotrID ?? 1);
    $note = (string)($inv['note'] ?? '');
    $zakazNum = (string)($inv['number'] ?? '');
    $zakazId = $invoiceId;
    $zakazType = 1;

    $stmt = $conn->prepare("INSERT INTO docum (typeop, number, date, time, client_id, store_id, store2_id, discount, sum_discount, sum, sum_plat, sum_balans, pos, date_plat, zakaz_num, zakaz_id, zakaz_type, sotr_id, sotr2_id, note, accept_flag, voz_flag) VALUES ($typeop, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, 0, ?, '', ?, ?, ?, ?, 0, ?, 0, 0)");
    bind_auto($stmt, [$nextNum, $date, $time, $clientId, $storeId, $discount, $totalSumDiscount, $totalSum, $pos, $zakazNum, $zakazId, $zakazType, $sotrId, $note]);
    $stmt->execute();
    $newSaleId = $stmt->insert_id;
    $stmt->close();

    if (!empty($items)) {
        $stmt = $conn->prepare("INSERT INTO docum2 (docum_id, product_id, product_name, code, quant, price, discount, sum, sum_discount, note, typeop, store_id, accept_flag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, $typeop, ?, 0)");
        foreach ($items as $item) {
            bind_auto($stmt, [
                $newSaleId,
                (int)($item['product_id'] ?? 0),
                (string)($item['product_name'] ?? ''),
                (string)($item['code'] ?? ''),
                (float)($item['quant'] ?? 0),
                (float)($item['price'] ?? 0),
                (float)($item['discount'] ?? 0),
                (float)($item['sum'] ?? 0),
                (float)($item['sum_discount'] ?? 0),
                (string)($item['note'] ?? ''),
                $storeId,
            ]);
            $stmt->execute();
        }
        $stmt->close();
    }

    $conn->commit();

    $response['ok'] = true;
    $response['id'] = $newSaleId;
    $response['name'] = '#' . $nextNum;

} catch (Exception $e) {
    $conn->rollback();
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
