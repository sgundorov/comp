<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/invo_columns.php';
require_once __DIR__ . '/../config/invo_page.php';

// Simulate GET request for edit mode
$_GET['mode'] = 'edit';
$_GET['id'] = '1';

// Test items loading
$id = 1;
$itemsList = [];
$stmt = $conn->prepare("SELECT invoice2_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note, guarantee, guarant_unit FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$itemsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
echo "Items count: " . count($itemsList) . PHP_EOL;
if (count($itemsList) > 0) {
    echo "First item: " . json_encode($itemsList[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

// Test product lookup
$productList = [];
$q = $conn->query("SELECT product_id, product_name, code, price_out FROM product ORDER BY product_name");
while ($r = $q->fetch_assoc()) {
    $productList[] = [
        'id' => (int)$r['product_id'],
        'name' => $r['product_name'],
        'code' => (string)$r['code'],
        'price_out' => (string)($r['price_out'] ?? '0'),
    ];
}
echo "Products count: " . count($productList) . PHP_EOL;
