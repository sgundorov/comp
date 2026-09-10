<?php
require_once __DIR__ . '/config.php';
if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }
require_once __DIR__ . '/lib/arenda2_totals.php';

/**
 * Быстрое создание платёжной записи по аренде без открытия формы оплаты.
 * kind: oplata  -> «За аренду» (sum_in = остаток sum-sum_plat)  sum_out=0
 *       zalog   -> «Оплата залога» (sum_in = docum.sum_zalog)   sum_out=0
 *       vozvrat -> «Возврат залога» (sum_out = docum.sum_zalog) sum_in=0
 */

$documId = (int)($_POST['docum_id'] ?? 0);
$kind    = (string)($_POST['kind'] ?? 'oplata');

if ($documId <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Не указан документ.']);
    exit;
}

$q = $conn->query("SELECT number, client_id, sotr_id, store_id, sum, sum_plat, sum_zalog, plat_type FROM docum WHERE docum_id = $documId AND typeop = 90");
$doc = $q ? $q->fetch_assoc() : null;
if (!$doc) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Документ аренды не найден.']);
    exit;
}

$getZat = function (string $name, int $fallback = 0) use ($conn): int {
    $r = $conn->query("SELECT zat_id FROM zat WHERE name = '" . $conn->real_escape_string($name) . "' LIMIT 1");
    return $r && ($row = $r->fetch_row()) ? (int)$row[0] : $fallback;
};

switch ($kind) {
    case 'oplata':
        $zatId  = $getZat('За аренду', 3);
        $sumIn  = (float)$doc['sum'] - (float)$doc['sum_plat']; if ($sumIn < 0) $sumIn = 0;
        $sumOut = 0.0;
        $outFlag = 0;
        $note = 'Оплата аренды';
        break;
    case 'zalog':
        $zatId  = $getZat('Оплата залога', 0);
        $sumIn  = (float)$doc['sum_zalog'];
        $sumOut = 0.0;
        $outFlag = 0;
        $note = 'Оплата залога';
        break;
    case 'vozvrat':
        $zatId  = $getZat('Возврат залога', 0);
        $sumIn  = 0.0;
        $sumOut = (float)$doc['sum_zalog'];
        $outFlag = 1;
        $note = 'Возврат залога';
        break;
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Неизвестный вид операции.']);
        exit;
}

if ($sumIn <= 0 && $sumOut <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Нет суммы для записи (' . $note . ').']);
    exit;
}

$sum = $sumIn - $sumOut;
$d = date('Y-m-d'); $t = date('H:i:s');
$dateTime = $d . ' ' . $t;
$clientId = (int)$doc['client_id'];
$sotrId   = (int)($doc['sotr_id'] ?: $CurSotrID);
$docNum   = (string)(int)$doc['number'];
$platType = (string)($doc['plat_type'] ?: 'Наличные');

$stmt = $conn->prepare("INSERT INTO plat (datetime, date, time, client_id, zat_id, sum_in, sum_out, sum, out_flag, plat_type, doc_id, doc_number, doc_type, sotr_id, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 90, ?, ?)");
bind_auto($stmt, [$dateTime, $d, $t, $clientId, $zatId, $sumIn, $sumOut, $sum, $outFlag, $platType, $documId, $docNum, $sotrId, $note]);
$stmt->execute();
$platId = (int)$conn->insert_id;
$stmt->close();

$bk = arenda2_backfill_docum($conn, $documId);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_merge(['ok' => true, 'id' => $platId, 'kind' => $kind], $bk));
exit;
