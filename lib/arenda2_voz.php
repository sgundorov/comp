<?php
if (!defined('ARENDA2_VOZ_LOADED')) {
define('ARENDA2_VOZ_LOADED', true);

require_once __DIR__ . '/arenda2_totals.php';

/** Проброс флагов docum.rezerv_flag / docum.voz_flag на все товары документа */
function arenda2_items_set_flags(mysqli $conn, int $documId, int $rezerv, int $voz): void {
    if ($documId <= 0) return;
    $stmt = $conn->prepare("UPDATE docum2 SET rezerv_flag = ?, voz_flag = ? WHERE docum_id = ? AND typeop = 90");
    bind_auto($stmt, [$rezerv, $voz, $documId]);
    $stmt->execute();
    $stmt->close();
}

function arenda2_count_weekends(string $beg, string $end): int {
    $b = DateTime::createFromFormat('Y-m-d', $beg);
    $e = DateTime::createFromFormat('Y-m-d', $end);
    if (!$b || !$e) return 0;
    $n = 0;
    for ($d = clone $b; $d <= $e; $d->modify('+1 day')) {
        $w = (int)$d->format('w');
        if ($w === 0 || $w === 6) $n++;
    }
    return $n;
}

/**
 * Пересчёт стоимости товаров аренды при переключении docum.voz_flag
 * (момент сохранения формы). Для voz_flag=1 применяются правила раздела
 * «изменение docum2.voz_flag»: месячные — пропорционально days/30 (или полные,
 * если KeepDaysOnEarlyReturn), иначе — дни/часы/выходные. Обновляет
 * date_voz/time_voz на текущую дату/время и итоги документа.
 */
function arenda2_items_recalc_sums(mysqli $conn, int $documId, int $voz, array $appSettings): void {
    if ($documId <= 0) return;
    $items = [];
    $q = $conn->query("SELECT docum2_id FROM docum2 WHERE docum_id = $documId AND typeop = 90");
    while ($r = $q->fetch_assoc()) $items[] = (int)$r['docum2_id'];
    foreach ($items as $iid) {
        arenda2_apply_voz_to_item($conn, $appSettings, $documId, $iid, $voz);
    }
    arenda2_backfill_docum($conn, $documId);
}

/**
 * Применение voz_flag к одному товару аренды: пересчёт days/hours/months/date_voz/
 * time_voz по правилам 111.txt:644-683 + пересчёт sum/sum_discount.
 *  - voz_flag=1:
 *      months>0:  если KeepDaysOnEarlyReturn=0 — пропорционально фактическому сроку
 *                 (months/days = splitPeriod(today-beg)); иначе месяцы сохраняются.
 *      months==0: days = date_voz - date_beg (clamp ≥0); если (days==0 || !ShowDays) и
 *                 условия по beg/today — hours = now+shift - time_beg.
 *                 date_voz = today, time_voz = now+shift.
 *  - voz_flag=0:
 *      date_voz = date_beg + days + (hours переполнение / 1440);
 *      time_voz = time_beg + hours (mod 1440).
 * При KeepDaysOnEarlyReturn=1 — days/months остаются как есть.
 * Вызывается при изменении docum2.voz_flag (включая прозвон с docum.voz_flag).
 */
function arenda2_apply_voz_to_item(mysqli $conn, array $appSettings, int $documId, int $itemId, int $voz): void {
    if ($documId <= 0 || $itemId <= 0) return;

    $keep       = (($appSettings['KeepDaysOnEarlyReturn'] ?? '0') === '1');
    $showHours  = ((int)($appSettings['ShowHoursFlag'] ?? 0)) === 1;
    $showDays   = ((int)($appSettings['ShowDaysFlag'] ?? 0)) === 1;
    $showMonths = ((int)($appSettings['ShowMonthsFlag'] ?? 0)) === 1;
    $shift      = (string)($appSettings['TimeShift'] ?? '00:00');

    $nowTs = time();
    if ($shift !== '' && preg_match('/^(\d{1,2}):(\d{2})/', $shift, $sm)) $nowTs += ((int)$sm[1] * 60 + (int)$sm[2]) * 60;
    $today = date('Y-m-d', $nowTs);
    $nowHm = date('H:i', $nowTs);

    $it = $conn->query("SELECT docum2_id, product_id, quant, discount, days, hours, months, date_beg, time_beg, date_voz, time_voz, fixed_flag, price_day, price_hour, price_we, price_month, price_fix FROM docum2 WHERE docum2_id = $itemId AND typeop = 90")->fetch_assoc();
    if (!$it) return;

    $dateBeg = (string)$it['date_beg'];
    $timeBeg = substr((string)$it['time_beg'], 0, 5);
    $dateVoz = (string)$it['date_voz'];
    $timeVoz = substr((string)$it['time_voz'], 0, 5);
    $days    = (int)$it['days'];
    $months  = (int)$it['months'];
    $hours   = (string)$it['hours'];
    if (preg_match('/^(\d{1,2}):(\d{2})/', $hours, $hm)) {
        $hh = (int)$hm[1]; $mm = (int)$hm[2];
    } else { $hh = 0; $mm = 0; }

    if ($voz === 1) {
        if (!$keep) {
            if ($months > 0 && $showMonths) {
                $sp = arenda2_period_months_days($dateBeg, $today);
                $months = $sp['months'];
                $days   = $sp['days'];
                $hours  = '';
                $hh = 0; $mm = 0;
            } else {
                /* days = today - date_beg (фактический срок до момента возврата) */
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateBeg, $bm)) {
                    $ddiff = (int)round((mktime(0,0,0,(int)substr($today,5,2),(int)substr($today,8,2),(int)substr($today,0,4))
                                        - mktime(0,0,0,(int)$bm[2],(int)$bm[3],(int)$bm[1])) / 86400);
                    if ($ddiff < 0) $ddiff = 0;
                    $days = $ddiff;
                } else {
                    $days = 0;
                }
                /* hours = now+shift - time_beg (если сейчас позже времени выдачи) */
                if ($showHours) {
                    $bm = $timeBeg !== '' ? explode(':', $timeBeg) : ['0','0'];
                    $begMin = ((int)$bm[0]) * 60 + ((int)($bm[1] ?? 0));
                    $nowMin = ((int)substr($nowHm,0,2)) * 60 + ((int)substr($nowHm,3,2));
                    if ($dateBeg < $today || ($dateBeg === $today && $nowMin > $begMin)) {
                        $diff = $nowMin - $begMin;
                        if ($diff < 0) $diff += 1440;
                        $hh = (int)floor($diff / 60);
                        $mm = $diff % 60;
                    } else {
                        $hh = 0; $mm = 0;
                    }
                }
            }
        }
        $dateVoz = $today;
        $timeVoz = $nowHm;
    } else {
        if ($dateBeg !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateBeg, $bm)) {
            $begTs = mktime(0,0,0,(int)$bm[2],(int)$bm[3],(int)$bm[1]);
            $extraD = (int)floor(($hh * 60 + $mm) / 1440);
            $rm    = ($hh * 60 + $mm) % 1440;
            $dateVozTs = $begTs + ($days + $extraD) * 86400;
            $dateVoz = date('Y-m-d', $dateVozTs);
            $timeVoz = sprintf('%02d:%02d', (int)floor($rm / 60), $rm % 60);
        }
    }

    $hoursFmt = '';
    if ($hh > 0 || $mm > 0) {
        $hoursFmt = sprintf('%02d:%02d', $hh, $mm);
    }

    $pq = $conn->query("SELECT noquant_flag FROM product WHERE product_id = " . (int)$it['product_id'])->fetch_assoc();
    $noquant = $pq ? (int)$pq['noquant_flag'] : 0;

    $sumItem = array_merge($it, [
        'days' => $days, 'hours' => $hoursFmt, 'months' => $months,
        'date_beg' => $dateBeg, 'date_voz' => $dateVoz,
        'noquant_flag' => $noquant,
    ]);
    $cs = arenda2_calc_sum_for_item($sumItem, $appSettings);

    $upd = $conn->prepare("UPDATE docum2 SET days = ?, hours = ?, months = ?, date_voz = ?, time_voz = ?, sum = ?, sum_discount = ?, voz_flag = ? WHERE docum2_id = ?");
    bind_auto($upd, [$days, $hoursFmt, $months, $dateVoz, $timeVoz, $cs['sum'], $cs['sum_discount'], $voz, $itemId]);
    $upd->execute();
    $upd->close();
}

}
// endif ARENDA2_VOZ_LOADED