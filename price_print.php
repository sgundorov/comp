<?php
require_once __DIR__ . '/config.php';

$productId = (int)($_GET['product_id'] ?? 0);
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'])), fn($v) => $v > 0));
}

$productName = '';
if ($productId > 0) {
    $r = @$conn->query("SELECT product_name FROM product WHERE product_id = " . $productId);
    if ($r && $row = $r->fetch_assoc()) $productName = (string)$row['product_name'];
}

$rows = [];
if ($productId > 0) {
    $sql = "SELECT p.price_id, pp.prplan AS prplan_name, p.name, p.bdays, p.edays,
                   p.btime, p.etime, p.price, p.pricef, p.hprice, p.mprice, p.fixed_flag
            FROM price p
            LEFT JOIN prplan pp ON pp.prplan_id = p.prplan_id
            WHERE p.product_id = " . $productId;
    if (!empty($ids)) $sql .= " AND p.price_id IN (" . implode(',', $ids) . ")";
    $sql .= " ORDER BY p.prplan_id ASC, p.price_id ASC";
    $rs = $conn->query($sql);
    if ($rs) while ($r = $rs->fetch_assoc()) $rows[] = $r;
}

function price_prn_num($v) {
    $f = (float)$v;
    return $f == 0 ? '' : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
}
function price_prn_time($v) {
    $v = trim((string)$v);
    if ($v === '' || preg_match('/^0{1,2}:00(:00)?$/', $v)) return '';
    return substr($v, 0, 5);
}

$tp = new TablePage($conn, [
    'table'       => 'price',
    'key'         => 'price_id',
    'search_cols' => [],
    'column_visibility_tbl' => '',
    'columns'     => [
        ['name' => 'prplan_name', 'label' => 'Тарифный план'],
        ['name' => 'bdays',       'label' => 'Дней от'],
        ['name' => 'edays',       'label' => 'Дней по'],
        ['name' => 'price',       'label' => 'За день'],
        ['name' => 'pricef',      'label' => 'Выходной'],
        ['name' => 'btime',       'label' => 'Время от'],
        ['name' => 'etime',       'label' => 'Время по'],
        ['name' => 'hprice',      'label' => 'За час'],
        ['name' => 'mprice',      'label' => 'За месяц'],
        ['name' => 'fixed_flag',  'label' => 'Фикс.'],
        ['name' => 'name',        'label' => 'Название тарифа'],
    ],
]);

$tp->renderPrintPage($rows, ['totalCount' => count($rows)], [
    'title' => 'Тарифы товара: ' . ($productName !== '' ? $productName : ('#' . $productId)),
    'orientation' => 'landscape',
    'colValues' => [
        'prplan_name' => fn($r) => (string)($r['prplan_name'] ?? ''),
        'bdays'       => fn($r) => (int)$r['bdays'] == 0 ? '' : (int)$r['bdays'],
        'edays'       => fn($r) => (int)$r['edays'] == 0 ? '' : (int)$r['edays'],
        'price'       => fn($r) => price_prn_num($r['price']),
        'pricef'      => fn($r) => price_prn_num($r['pricef']),
        'btime'       => fn($r) => price_prn_time($r['btime']),
        'etime'       => fn($r) => price_prn_time($r['etime']),
        'hprice'      => fn($r) => price_prn_num($r['hprice']),
        'mprice'      => fn($r) => price_prn_num($r['mprice']),
        'fixed_flag'  => fn($r) => (int)$r['fixed_flag'] === 1 ? 'Да' : '',
        'name'        => fn($r) => (string)($r['name'] ?? ''),
    ],
]);
