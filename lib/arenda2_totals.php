<?php
if (!defined('ARENDA2_TOTALS_LOADED')) {
define('ARENDA2_TOTALS_LOADED', true);

require_once __DIR__ . '/arenda2_tariff.php';

/**
 * Число полных месяцев и избыток дней за период (date_beg..date_voz),
 * по алгоритму splitPeriod из arenda2_form.js.
 */
function arenda2_period_months_days(string $begYmd, string $endYmd): array {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $begYmd, $b) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $endYmd, $v)) {
        return ['months' => 0, 'days' => 0];
    }
    $begD = mktime(0, 0, 0, (int)$b[2], (int)$b[3], (int)$b[1]);
    $endD = mktime(0, 0, 0, (int)$v[2], (int)$v[3], (int)$v[1]);
    if ($endD < $begD) return ['months' => 0, 'days' => 0];
    $months = ((int)$v[1] - (int)$b[1]) * 12 + ((int)$v[2] - (int)$b[2]);
    $anchor = mktime(0, 0, 0, (int)$b[2] + $months, (int)$b[3], (int)$b[1]);
    if ($anchor > $endD) { $months--; $anchor = mktime(0, 0, 0, (int)$b[2] + $months, (int)$b[3], (int)$b[1]); }
    if ($months < 0) $months = 0;
    $days = (int)round(($endD - $anchor) / 86400);
    if ($days < 0) $days = 0;
    return ['months' => $months, 'days' => $days];
}

/** Разница времени возврата и начала в часах (12:00→14:00 = 2; при <0 +24 часа). */
function arenda2_hours_diff(string $timeBeg, string $timeVoz): float {
    $toMin = function (string $t): int {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) return (int)$m[1] * 60 + (int)$m[2];
        return 0;
    };
    $diff = $toMin($timeVoz) - $toMin($timeBeg);
    if ($diff < 0) $diff += 1440;
    return round($diff / 60, 4);
}

/** Число выходных (Сб/Вс) среди последних $nDays дней, заканчивающихся на $endYmd. */
function arenda2_count_weekends_end(string $endYmd, int $nDays): int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $endYmd, $m) || $nDays <= 0) return 0;
    $end = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    $n = 0;
    for ($i = 0; $i < $nDays; $i++) {
        $w = (int)date('w', $end - $i * 86400);
        if ($w === 0 || $w === 6) $n++;
    }
    return $n;
}

/** Число выходных между двумя датами включительно. */
function arenda2_count_weekends_range(string $beg, string $end): int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $beg, $bm) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $end, $em)) return 0;
    $b = mktime(0, 0, 0, (int)$bm[2], (int)$bm[3], (int)$bm[1]);
    $e = mktime(0, 0, 0, (int)$em[2], (int)$em[3], (int)$em[1]);
    if ($e < $b) return 0;
    $n = 0;
    for ($d = $b; $d <= $e; $d += 86400) {
        $w = (int)date('w', $d);
        if ($w === 0 || $w === 6) $n++;
    }
    return $n;
}

/**
 * Пересчёт периода и суммы одного товара аренды после изменения дат Начала/Возврата.
 * По алгоритму arenda2_form.js (applyPeriodEnd + get_price + calc_sum):
 *  - months/days из (date_beg..date_voz), hours = time_voz - time_beg;
 *  - подбор тарифа (новый сезон/prplan/цены);
 *  - пересчёт sum/sum_discount (фикс./месяцы/дни+часы+выходные, quant, НДС).
 * Обновляет запись docum2 и возвращает ключи цены/периода для интерфейса.
 */
function arenda2_recalc_item_period(mysqli $conn, array $appSettings, int $documId, int $itemId): array {
    $fixedFlagSetting = ((int)($appSettings['FixedFlag'] ?? 0)) === 1;
    $showMonths       = ((int)($appSettings['ShowMonthsFlag'] ?? 0)) === 1;
    $noNds            = (($appSettings['no_nds'] ?? '0') === '1');
    $nds              = (int)($appSettings['nds_rate'] ?? 0);

    $q = $conn->query("SELECT docum2_id, product_id, prplan_id, quant, discount, date_beg, time_beg, date_voz, time_voz, fixed_flag FROM docum2 WHERE docum2_id = $itemId AND typeop = 90");
    $it = $q ? $q->fetch_assoc() : null;
    if (!$it) return [];
    $iid = (int)$it['docum2_id'];

    $dateBeg = (string)$it['date_beg'];
    $dateVoz = (string)$it['date_voz'];
    $timeBeg = (string)$it['time_beg'];
    $timeVoz = (string)$it['time_voz'];

    /* «Месячность» определяется по месячной цене товара (как в форме:
       hasMonthly = ShowMonthsFlag && у товара есть price_month). */
    $prodP = $conn->query("SELECT price_month, noquant_flag FROM product WHERE product_id = " . (int)$it['product_id'])->fetch_assoc();
    $prodPMonth = $prodP ? (float)$prodP['price_month'] : 0;
    $hasMonthly = $showMonths && $prodPMonth > 0;

    /* Период: месяцы/дни/часы */
    if ($hasMonthly) {
        $pmd = arenda2_period_months_days($dateBeg, $dateVoz);
        $months = $pmd['months'];
        $days   = $pmd['days'];
    } else {
        $months = 0;
        $days = 0;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateBeg, $bm) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateVoz, $vm)) {
            $days = (int)round((mktime(0,0,0,(int)$vm[2],(int)$vm[3],(int)$vm[1]) - mktime(0,0,0,(int)$bm[2],(int)$bm[3],(int)$bm[1])) / 86400);
            if ($days < 0) $days = 0;
        }
    }
    $hours = arenda2_hours_diff($timeBeg, $timeVoz);
    $hoursStr = '';
    if ($hours > 0) {
        $hh = (int)floor($hours);
        $mm = (int)round(($hours - $hh) * 60);
        if ($mm === 60) { $hh++; $mm = 0; }
        $hoursStr = str_pad((string)$hh, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$mm, 2, '0', STR_PAD_LEFT);
    }

    /* Подбор тарифа по вычисленному периоду (корректные дни/месяцы/часы). */
    $tt = arenda2_select_tariff($conn, $appSettings, (int)$it['product_id'], (int)$it['prplan_id'], $dateBeg, $hoursStr, $days, $months, $documId);
    $pDay    = (float)($tt['price_day'] ?? 0);
    $pHour   = (float)($tt['price_hour'] ?? 0);
    $pWe     = (float)($tt['price_we'] ?? 0); if ($pWe == 0) $pWe = $pDay;
    $pMonth  = (float)($tt['price_month'] ?? 0);
    $pFix    = (float)($tt['price_fix'] ?? 0);
    $prplan  = (int)($tt['prplan_id'] ?? 1);
    $sumZalog = (float)($tt['sum_zalog'] ?? 0);

    /* Сумма (calc_sum) */
    $disc   = (float)$it['discount'];
    $quant  = (float)$it['quant'];
    $noquant = $prodP ? (int)$prodP['noquant_flag'] : 0;

    $fixedMode = ((int)$it['fixed_flag'] === 1);
    $sum = 0.0; $sd = 0.0;
    if ($fixedFlagSetting || $fixedMode) {
        $fix = $pFix ? $pFix : $pDay;
        $sum = $fix * (1 - $disc / 100);
        $sd  = $fix * ($disc / 100);
    } else {
        $monthsActive = $months > 0 && $pMonth > 0;
        if ($monthsActive) {
            $we = arenda2_count_weekends_end($dateVoz, $days);
            $wd = $days - $we; if ($wd < 0) $wd = 0;
        } else {
            $we = arenda2_count_weekends_range($dateBeg, $dateVoz);
            $wd = $days - $we; if ($wd < 0) $wd = 0;
        }
        $daysPart  = $pDay * $wd + $pWe * $we;
        $hoursPart = $pHour * $hours;
        $monthsPart = $monthsActive ? $pMonth * $months : 0;
        $gross = $daysPart + $hoursPart + $monthsPart;
        $sum = $gross * (1 - $disc / 100);
        $sd  = $gross * ($disc / 100);
        if ($sum < 0) $sum = 0; if ($sd < 0) $sd = 0;
    }
    if (!$noquant && $quant > 0 && $quant != 1) { $sum *= $quant; $sd *= $quant; }
    if ($nds > 0 && !$noNds) $sum *= (1 + $nds / 100);

    $sum = round($sum, 2); $sd = round($sd, 2);
    $upd = $conn->prepare("UPDATE docum2 SET date_beg = ?, time_beg = ?, date_voz = ?, time_voz = ?, days = ?, hours = ?, months = ?, price_day = ?, price_hour = ?, price_we = ?, price_month = ?, price_fix = ?, prplan_id = ?, price_id = ?, sum_zalog = ?, sum = ?, sum_discount = ? WHERE docum2_id = ?");
    bind_auto($upd, [$dateBeg, $timeBeg, $dateVoz, $timeVoz, $days, $hoursStr, $months, $pDay, $pHour, $pWe, $pMonth, $pFix, $prplan, (int)($tt['tariff_id'] ?? 0), $sumZalog, $sum, $sd, $iid]);
    $upd->execute();
    $upd->close();

    return [
        'id' => $iid,
        'days' => $days,
        'hours' => $hoursStr,
        'months' => $months,
        'date_beg' => $dateBeg,
        'time_beg' => substr($timeBeg, 0, 5),
        'date_voz' => $dateVoz,
        'time_voz' => substr($timeVoz, 0, 5),
        'price_day' => $pDay,
        'price_hour' => $pHour,
        'price_month' => $pMonth,
        'price_fix' => $pFix,
        'prplan_id' => $prplan,
        'price_id' => (int)($tt['tariff_id'] ?? 0),
        'sum_zalog' => $sumZalog,
        'sum' => $sum,
        'sum_discount' => $sd,
    ];
}

/**
 * Пересчёт итогов документа аренды и «бэкаполь» минимальной даты возврата.
 * При сохранении/удалении товара аренды (docum2, typeop=90):
 *  - docum.date_voz = MIN(docum2.date_voz) среди позиций с voz_flag==False;
 *  - docum.time_voz = MIN(docum2.time_voz) среди позиций с (date_voz == MIN);
 *  - docum.days/hours — из позиции с минимальной датой;
 *  - docum.sum/sum_zalog/sum_discount/pos/sum_plat/sum_balans — пересчёт.
 * Возвращает итоги для обновления интерфейса формы.
 */
function arenda2_backfill_docum(mysqli $conn, int $documId): array {
    $row = ['s' => 0, 'sz' => 0, 'sd' => 0, 'pos' => 0];
    $r = $conn->query("SELECT COALESCE(SUM(sum),0) s, COALESCE(SUM(sum_zalog),0) sz, COALESCE(SUM(sum_discount),0) sd, COUNT(*) pos FROM docum2 WHERE docum_id = $documId");
    if ($r) $row = $r->fetch_assoc();

    $dateVoz = ''; $timeVoz = ''; $days = 0; $hours = ''; $months = 0;
    $mv = $conn->query("SELECT date_voz, time_voz, days, hours, months FROM docum2 WHERE docum_id = $documId AND date_voz <> '' ORDER BY date_voz ASC, time_voz ASC LIMIT 1");
    if ($mv && ($m = $mv->fetch_assoc())) {
        $dateVoz = (string)$m['date_voz'];
        $timeVoz = (string)$m['time_voz'];
        $days = (int)$m['days'];
        $hours = (string)$m['hours'];
        $months = (int)$m['months'];
    }

    $sum     = round((float)$row['s'], 2);
    $sumZalog = round((float)$row['sz'], 2);
    $sumDisc = round((float)$row['sd'], 2);
    $pos     = (int)$row['pos'];
    $sumPlat = (float)$conn->query("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = $documId AND doc_type = 90")->fetch_row()[0];
    $sumBalans = round($sum - $sumPlat, 2);

    $upd = $conn->prepare("UPDATE docum SET sum = ?, sum_zalog = ?, sum_discount = ?, pos = ?, date_voz = ?, time_voz = ?, days = ?, hours = ?, months = ?, sum_balans = ?, sum_plat = ? WHERE docum_id = ?");
    bind_auto($upd, [$sum, $sumZalog, $sumDisc, $pos, $dateVoz, $timeVoz, $days, $hours, $months, $sumBalans, round($sumPlat, 2), $documId]);
    if ($upd) { $upd->execute(); $upd->close(); }

    return [
        'ok'                => true,
        'total_sum'         => $sum,
        'total_sum_discount'=> $sumDisc,
        'total_sum_zalog'   => $sumZalog,
        'pos'               => $pos,
        'sum_plat'          => round($sumPlat, 2),
        'date_voz'          => $dateVoz,
        'time_voz'          => $timeVoz,
        'days'              => $days,
        'hours'             => $hours,
        'months'            => $months,
    ];
}

/**
 * Расчёт sum/sum_discount товара аренды по сохранённым полям (calc_sum из
 * arenda2_form.js): фикс. / месяцы / дни+часы+выходные, quant, НДС.
 * Используется при инлайн-правке товара аренды (docum2_field_save.php),
 * чтобы не затирать тарифную сумму формулой quant*price.
 */
function arenda2_calc_sum_for_item(array $it, array $appSettings): array {
    $fixedFlag = ((int)($appSettings['FixedFlag'] ?? 0)) === 1;
    $noNds     = (($appSettings['no_nds'] ?? '0') === '1');
    $nds       = (int)($appSettings['nds_rate'] ?? 0);

    $disc      = (float)($it['discount'] ?? 0);
    $quant     = (float)($it['quant'] ?? 0);
    $days      = (int)($it['days'] ?? 0);
    $hours     = (string)($it['hours'] ?? '');
    $months    = (int)($it['months'] ?? 0);
    $pDay      = (float)($it['price_day'] ?? 0);
    $pHour     = (float)($it['price_hour'] ?? 0);
    $pWe       = (float)($it['price_we'] ?? 0); if ($pWe == 0) $pWe = $pDay;
    $pMonth    = (float)($it['price_month'] ?? 0);
    $pFix      = (float)($it['price_fix'] ?? 0);
    $fixedMode = ((int)($it['fixed_flag'] ?? 0)) === 1;
    $noquant   = (int)($it['noquant_flag'] ?? 0);
    $dateBeg   = (string)($it['date_beg'] ?? '');
    $dateVoz   = (string)($it['date_voz'] ?? '');

    $sum = 0.0; $sd = 0.0;
    if ($fixedFlag || $fixedMode) {
        $fix = $pFix ? $pFix : $pDay;
        $sum = $fix * (1 - $disc / 100);
        $sd  = $fix * ($disc / 100);
    } else {
        $monthsActive = $months > 0 && $pMonth > 0;
        if ($monthsActive) {
            $we = arenda2_count_weekends_end($dateVoz, $days);
            $wd = $days - $we; if ($wd < 0) $wd = 0;
        } else {
            $we = arenda2_count_weekends_range($dateBeg, $dateVoz);
            $wd = $days - $we; if ($wd < 0) $wd = 0;
        }
        $hrsMin = 0;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hours, $hm)) $hrsMin = (int)$hm[1] * 60 + (int)$hm[2];
        $hrs = $hrsMin / 60;
        $daysPart   = $pDay * $wd + $pWe * $we;
        $hoursPart  = $pHour * $hrs;
        $monthsPart = $monthsActive ? $pMonth * $months : 0;
        $gross = $daysPart + $hoursPart + $monthsPart;
        $sum = $gross * (1 - $disc / 100);
        $sd  = $gross * ($disc / 100);
        if ($sum < 0) $sum = 0; if ($sd < 0) $sd = 0;
    }
    if (!$noquant && $quant > 0 && $quant != 1) { $sum *= $quant; $sd *= $quant; }
    if ($nds > 0 && !$noNds) $sum *= (1 + $nds / 100);
    return ['sum' => round($sum, 2), 'sum_discount' => round($sd, 2)];
}

/**
 * Применение скидки ко всем товарам аренды документа.
 * Сумма товара аренды считается по тарифам (price_day/price_hour/price_we/
 * price_month/price_fix и периодам days/hours/months), а НЕ по quant*price —
 * поэтому здесь нельзя использовать общую формулу "Установить скидку".
 * Обновляет discount у всех товаров, пересчитывает sum/sum_discount каждого
 * товара (без изменения voz_flag/дат) и итоги документа.
 */
function arenda2_recalc_discount(mysqli $conn, int $documId, float $discount, array $appSettings): void {
    if ($documId <= 0) return;
    $noNds  = (($appSettings['no_nds'] ?? '0') === '1');
    $nds    = (int)($appSettings['nds_rate'] ?? 0);

    $stmt = $conn->prepare("UPDATE docum2 SET discount = ? WHERE docum_id = ? AND typeop = 90");
    $stmt->bind_param('di', $discount, $documId);
    $stmt->execute();
    $stmt->close();

    $items = [];
    $q = $conn->query("SELECT docum2_id, product_id, quant, days, hours, months, price_day, price_hour, price_we, price_month, price_fix, fixed_flag FROM docum2 WHERE docum_id = $documId AND typeop = 90");
    if ($q) while ($r = $q->fetch_assoc()) $items[] = $r;

    foreach ($items as $it) {
        $iid     = (int)$it['docum2_id'];
        $quant   = (float)$it['quant'];
        $noquant = 0;
        $pq = $conn->query("SELECT noquant_flag FROM product WHERE product_id = " . (int)$it['product_id']);
        if ($pq && ($pr = $pq->fetch_assoc())) $noquant = (int)$pr['noquant_flag'];

        $sum = 0.0; $sd = 0.0;
        if ((int)$it['fixed_flag'] === 1 && (float)$it['price_fix'] > 0) {
            $fix = (float)$it['price_fix'];
            $sum = $fix * (1 - $discount / 100);
            $sd  = $fix * ($discount / 100);
        } elseif ((int)$it['months'] > 0 && (float)$it['price_month'] > 0) {
            $mUsed = (float)$it['months'];
            $sum = (float)$it['price_month'] * $mUsed * (1 - $discount / 100);
            $sd  = (float)$it['price_month'] * $mUsed * ($discount / 100);
        } else {
            $pD = (float)$it['price_day'];
            $pH = (float)$it['price_hour'];
            $pW = (float)$it['price_we']; if ($pW == 0) $pW = $pD;
            $days = (int)$it['days'];
            $hrsMin = 0;
            if (preg_match('/^(\d{1,2}):(\d{2})/', (string)$it['hours'], $hm)) $hrsMin = (int)$hm[1] * 60 + (int)$hm[2];
            $hrs = $hrsMin / 60;
            $wd = $days; if ($wd < 0) $wd = 0;
            $sum = $pD * $wd * (1 - $discount / 100) + $pH * $hrs * (1 - $discount / 100);
            $sd  = $pD * $wd * ($discount / 100) + $pH * $hrs * ($discount / 100);
            if ($sum < 0) $sum = 0; if ($sd < 0) $sd = 0;
        }
        if (!$noquant && $quant > 0 && $quant != 1) { $sum *= $quant; $sd *= $quant; }
        if ($nds > 0 && !$noNds) $sum *= (1 + $nds / 100);

        $conn->query("UPDATE docum2 SET sum = $sum, sum_discount = $sd WHERE docum2_id = $iid");
    }

    $hStmt = $conn->prepare("UPDATE docum SET discount = ? WHERE docum_id = ?");
    $hStmt->bind_param('di', $discount, $documId);
    $hStmt->execute();
    $hStmt->close();

    arenda2_backfill_docum($conn, $documId);
}

/**
 * Синхронизация docum.rezerv_flag / voz_flag с товарами.
 * Если все товары с rezerv_flag=1 -> docum.rezerv_flag = 1
 * Если все товары с voz_flag=1 -> docum.voz_flag = 1
 * При включении voz_flag сбрасывается rezerv_flag.
 * Возвращает обновлённые значения для интерфейса.
 */
function arenda2_sync_docum_flags(mysqli $conn, int $documId): array {
    if ($documId <= 0) return [];

    $cc = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(rezerv_flag),0) r, COALESCE(SUM(voz_flag),0) v FROM docum2 WHERE docum_id = $documId AND typeop = 90")->fetch_assoc();
    if (!$cc || (int)$cc['c'] === 0) return [];

    $allRes = ((int)$cc['r'] === (int)$cc['c']);
    $allVoz = ((int)$cc['v'] === (int)$cc['c']);

    $docRes = $conn->query("SELECT rezerv_flag, voz_flag FROM docum WHERE docum_id = $documId")->fetch_assoc();
    $oldRes = $docRes ? (int)$docRes['rezerv_flag'] : 0;
    $oldVoz = $docRes ? (int)$docRes['voz_flag'] : 0;

    $newRes = $allRes ? 1 : 0;
    $newVoz = $allVoz ? 1 : 0;

    if ($newVoz === 1 && $oldRes === 1) {
        $newRes = 0;
        $conn->query("UPDATE docum2 SET rezerv_flag = 0 WHERE docum_id = $documId AND typeop = 90");
    }

    if ($newRes !== $oldRes || $newVoz !== $oldVoz) {
        $conn->query("UPDATE docum SET rezerv_flag = $newRes, voz_flag = $newVoz WHERE docum_id = $documId");
    }

    return [
        'doc_rezerv_flag' => $newRes,
        'doc_voz_flag'    => $newVoz,
    ];
}

}
// endif ARENDA2_TOTALS_LOADED