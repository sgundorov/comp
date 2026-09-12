<?php
if (!defined('ARENDA2_TARIFF_LOADED')) {
define('ARENDA2_TARIFF_LOADED', true);

/**
 * Подбор тарифа товара аренды (по спецификации 111.txt, get_price).
 * Используется action `get_price` в arenda2_check.php и при рендере
 * arenda2_form.php (чтобы название тарифа всегда отображалось корректно).
 */
function arenda2_select_tariff(mysqli $conn, array $appSettings, int $productId, int $prplanId, string $dateBeg, string $hours, int $days, int $months, int $documId = 0, bool $prplanManual = false): array {
    $fixedFlag  = ((int)($appSettings['FixedFlag'] ?? 0)) === 1;
    $sezonFlag  = ((int)($appSettings['SezonFlag'] ?? 0)) === 1;

    if ($productId <= 0) return ['ok' => false];

    $qq = $conn->query("SELECT product_id, zalog, period_aren, price_hour, price_day, price_month, noquant_flag, nocalc_flag FROM product WHERE product_id = $productId");
    $prod = $qq ? $qq->fetch_assoc() : null;
    if (!$prod) return ['ok' => false];

    /* Залог: product.zalog; без залога (client.nozalog_flag) → 0 */
    $sumZalog = (float)$prod['zalog'];
    $clientId = 0;
    if ($documId > 0) {
        $doc = $conn->query("SELECT client_id, date_beg FROM docum WHERE docum_id = $documId")->fetch_assoc();
        if ($doc) {
            $clientId = (int)$doc['client_id'];
            if ($dateBeg === '' && !empty($doc['date_beg'])) $dateBeg = (string)$doc['date_beg'];
        }
    }
    if ($clientId > 0) {
        $cl = $conn->query("SELECT nozalog_flag FROM client WHERE client_id = $clientId")->fetch_assoc();
        if ($cl && (int)$cl['nozalog_flag'] === 1) $sumZalog = 0.0;
    }

    /* Базовая цена по периоду аренды товара */
    $price = 0.0;
    $period = (string)($prod['period_aren'] ?? '');
    if ($period === 'm')      $price = (float)$prod['price_month'];
    elseif ($period === 'd')  $price = (float)$prod['price_day'];
    elseif ($period === 'h')  $price = (float)$prod['price_hour'];

    /* Сезон: prplan.bdate..prplan.edate содержит date_beg.
       Не применяется при ручном выборе плана пользователем (prplanManual). */
    if ($sezonFlag && !$prplanManual && $dateBeg !== '') {
        $dBeg = date('Y-m-d', strtotime($dateBeg));
        $pr = $conn->query("SELECT prplan_id, bdate, edate FROM prplan ORDER BY prplan_id ASC");
        if ($pr) {
            while ($pp = $pr->fetch_assoc()) {
                $b = date('Y-m-d', strtotime(str_replace('.', '-', (string)$pp['bdate'])));
                $e = date('Y-m-d', strtotime(str_replace('.', '-', (string)$pp['edate'])));
                if ($dBeg !== false && $b !== false && $e !== false && $dBeg >= $b && $dBeg <= $e) {
                    $prplanId = (int)$pp['prplan_id'];
                    break;
                }
            }
        }
    }
    if ($prplanId <= 0) $prplanId = 1;

    /* Тарифы из price: план + товар, fixed_flag по настройке */
    $hPrice = 0.0; $dPrice = 0.0; $wePrice = 0.0; $fixPrice = 0.0;
    $mPrice = 0.0;
    $hourId = 0; $hourName = ''; $dayId = 0; $dayName = ''; $monthId = 0; $monthName = '';
    $pres = $conn->query("SELECT * FROM price WHERE product_id = $productId AND prplan_id = $prplanId AND fixed_flag = " . ($fixedFlag ? 1 : 0) . " ORDER BY price_id ASC");
    if ($pres) {
        $hoursVal = null;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hours, $hm)) $hoursVal = (int)$hm[1] * 60 + (int)$hm[2];
        while ($prow = $pres->fetch_assoc()) {
            /* Тариф за час: есть hprice и etime; hours внутри [btime..etime] */
            $btime = (string)($prow['btime'] ?? '');
            $etime = (string)($prow['etime'] ?? '');
            if ((float)$prow['hprice'] != 0 && $etime !== '' && $etime !== '00:00:00' && $hoursVal !== null) {
                $bmin = $btime !== '' ? ((int)substr($btime, 0, 2) * 60 + (int)substr($btime, 3, 2)) : 0;
                $emin = (int)substr($etime, 0, 2) * 60 + (int)substr($etime, 3, 2);
                if ($hoursVal >= $bmin && $hoursVal <= $emin) {
                    $hPrice = (float)$prow['hprice'];
                    $hourId = (int)$prow['price_id'];
                    $hourName = (string)$prow['name'];
                    if ($fixedFlag) $fixPrice = $hPrice;
                }
            }
            /* Тариф за день: есть price и edays; days внутри [bdays..edays] */
            if ((float)$prow['price'] != 0 && (int)$prow['edays'] > 0) {
                if ((int)$prow['bdays'] <= $days && $days <= (int)$prow['edays']) {
                    $dPrice  = (float)$prow['price'];
                    $wePrice = (float)$prow['pricef'];
                    $dayId = (int)$prow['price_id'];
                    $dayName = (string)$prow['name'];
                    if ($fixedFlag) $fixPrice = $dPrice;
                }
            }
            /* Тариф за месяц: есть mprice и emonths; months внутри [bmonths..emonths].
               Только при заданных месяцах (months > 0). */
            if ((float)$prow['mprice'] != 0 && $months > 0 && (int)$prow['emonths'] > 0) {
                if ((int)$prow['bmonths'] <= $months && $months <= (int)$prow['emonths']) {
                    $mPrice = (float)$prow['mprice'];
                    $monthId = (int)$prow['price_id'];
                    $monthName = (string)$prow['name'];
                    if ($fixedFlag) $fixPrice = $mPrice;
                }
            }
        }
    }
    if ($hPrice == 0)  $hPrice  = (float)$prod['price_hour'];
    if ($dPrice == 0)  $dPrice  = (float)$prod['price_day'];
    if ($wePrice == 0) $wePrice = $dPrice;
    $monthPrice = ($mPrice != 0) ? $mPrice : (float)$prod['price_month'];

    /* Название выбранного тарифа. Автоподбор в последовательности:
     * сначала по часам, затем по дням, затем по месяцам — выводится последний
     * выбранный (месячный, если months != 0 и найден месячный тариф).
     * Если период не задан (hours/days/months == 0) — имя тарифа пустое («—»). */
    if ($monthId && $months > 0) {
        $tariffId   = $monthId;
        $tariffName = $monthName;
    } elseif ($dayId && $days > 0) {
        $tariffId   = $dayId;
        $tariffName = $dayName;
    } elseif ($hourId && $hoursVal > 0) {
        $tariffId   = $hourId;
        $tariffName = $hourName;
    } else {
        $tariffId   = 0;
        $tariffName = '';
    }

    $pricesAll = [];
    $fq = $conn->query("SELECT price_id, name, bdays, edays, bmonths, emonths, btime, etime, price, hprice, mprice, fixed_flag FROM price WHERE product_id = $productId AND prplan_id = $prplanId ORDER BY price_id ASC");
    if ($fq) while ($f = $fq->fetch_assoc()) {
        $pricesAll[] = [
            'id'        => (int)$f['price_id'],
            'name'      => (string)$f['name'],
            'bdays'     => (int)$f['bdays'],
            'edays'     => (int)$f['edays'],
            'bmonths'   => (int)$f['bmonths'],
            'emonths'   => (int)$f['emonths'],
            'btime'     => substr((string)$f['btime'], 0, 5),
            'etime'     => substr((string)$f['etime'], 0, 5),
            'price'     => (float)$f['price'],
            'hprice'    => (float)$f['hprice'],
            'mprice'    => (float)$f['mprice'],
            'fixed_flag'=> (int)$f['fixed_flag'],
        ];
    }

    return [
        'ok'           => true,
        'price'        => $price,
        'price_hour'   => $hPrice,
        'price_day'    => $dPrice,
        'price_we'     => $wePrice,
        'price_fix'    => $fixPrice,
        'price_month'  => $monthPrice,
        'sum_zalog'    => $sumZalog,
        'prplan_id'    => $prplanId,
        'tariff_id'    => $tariffId,
        'tariff_name'  => $tariffName,
        'prices'       => $pricesAll,
        'period_aren'  => $period,
        'noquant_flag' => (int)$prod['noquant_flag'],
        'nocalc_flag'  => (int)$prod['nocalc_flag'],
    ];
}

} // endif ARENDA2_TARIFF_LOADED