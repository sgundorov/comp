<?php

/* Доступный остаток товара на участке (store) на текущую дату.
 * Учитывает документы с accept_flag (приход/расход), внутренние перемещения
 * (typeop 100: store_id уходит, store2_id приходит), Аренду (typeop 90) и
 * Ремонт (typeop 130). Аренда/Ремонт уменьшают остаток независимо от accept_flag:
 *  - Аренда: если товар не возвращён ИЛИ зарезервирован и today внутри [date_beg..date_voz]
 *  - Ремонт: если не возвращён и today внутри [date_beg..date_voz] (без rezerv_flag)
 */
function residue_available_product(mysqli $conn, int $productId, int $storeId): float {
    if ($productId <= 0 || $storeId <= 0) return 0.0;
    $today = date('Y-m-d');
    $q = $conn->prepare("
        SELECT d.typeop, d2.quant, d2.voz_flag, d2.rezerv_flag, d2.accept_flag,
               ti.prihod_flag, d.store_id, d.store2_id, d2.date_beg, d2.date_voz
        FROM docum2 d2
        JOIN docum d ON d.docum_id = d2.docum_id
        LEFT JOIN typeop ti ON ti.typeop_id = d.typeop
        WHERE d2.product_id = ?");
    if (!$q) return 0.0;
    $q->bind_param('i', $productId);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();

    $total = 0.0;
    foreach ($rows as $r) {
        $typeop = (int)$r['typeop'];
        $quant  = (float)$r['quant'];
        if ($quant == 0) continue;
        $store  = (int)$r['store_id'];
        $store2 = (int)$r['store2_id'];

        if ($typeop === 90) { // Аренда
            if ($store === $storeId
                && ((int)$r['voz_flag'] === 0 || (int)$r['rezerv_flag'] === 1)
                && (string)$r['date_beg'] !== '' && (string)$r['date_voz'] !== ''
                && $r['date_beg'] <= $today && $r['date_voz'] >= $today) {
                $total -= $quant;
            }
        } elseif ($typeop === 130) { // Ремонт
            if ($store === $storeId && (int)$r['voz_flag'] === 0
                && (string)$r['date_beg'] !== '' && (string)$r['date_voz'] !== ''
                && $r['date_beg'] <= $today && $r['date_voz'] >= $today) {
                $total -= $quant;
            }
        } elseif ($typeop === 100) { // Внутреннее перемещение
            if ((int)$r['accept_flag']) {
                if ($store === $storeId) $total -= $quant;
                if ($store2 === $storeId) $total += $quant;
            }
        } else { // Приход/расход по prihod_flag
            if ((int)$r['accept_flag'] && $store === $storeId) {
                $total += ((int)$r['prihod_flag'] === 1) ? $quant : -$quant;
            }
        }
    }
    return $total;
}
