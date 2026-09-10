<?php
require_once __DIR__ . '/config.php';
if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

/**
 * Остаток товара на заданную дату (для_Product с количеством).
 * Суммирует docum2.quant по всем документам движения,
 * исключая текущий docum_id.
 */
function quant_date(mysqli $conn, int $productId, int $excludeDocumId, string $date): float {
    /* Участок документа аренды — остаток считаем только на этом участке. */
    $storeId = 0;
    if ($excludeDocumId > 0) {
        $q = $conn->query("SELECT store_id FROM docum WHERE docum_id = $excludeDocumId");
        $dr = $q ? $q->fetch_assoc() : null;
        if ($dr) $storeId = (int)$dr['store_id'];
    }

    $sql = "SELECT d2.typeop, d2.quant, d2.voz_flag, d2.rezerv_flag, d2.accept_flag, d2.date_beg, d2.date_voz,
                   d.store_id, d.store2_id
            FROM docum2 d2
            JOIN docum d ON d.docum_id = d2.docum_id
            WHERE d2.product_id = ? AND d2.docum_id != ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $productId, $excludeDocumId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total = 0.0;
    foreach ($rows as $r) {
        $typeop     = (int)$r['typeop'];
        $quant      = (float)$r['quant'];
        $vozFlag    = (int)$r['voz_flag'];
        $rezervFlag = (int)$r['rezerv_flag'];
        $acceptFlag = (int)$r['accept_flag'];
        $dateBeg    = (string)$r['date_beg'];
        $dateVoz    = (string)$r['date_voz'];
        $rowStore   = (int)$r['store_id'];
        $rowStore2  = (int)$r['store2_id'];

        if ($quant == 0) continue;

        switch ($typeop) {
            case 20:  // Приход
            case 40:  // Возврат от покупателя
                if ($acceptFlag && $rowStore == $storeId) $total += $quant;
                break;

            case 120: // Продажа
            case 127: // Списание
            case 110: // Возврат поставщику
                if ($acceptFlag && $rowStore == $storeId) $total -= $quant;
                break;

            case 100: // Внутреннее перемещение
                if ($acceptFlag) {
                    if ($rowStore == $storeId) $total -= $quant;   // уходит с участка
                    if ($rowStore2 == $storeId) $total += $quant;  // приходит на участок
                }
                break;

            case 130: // Ремонт
                if (!$vozFlag && $rowStore == $storeId && $dateBeg <= $date && $dateVoz >= $date) {
                    $total -= $quant;
                }
                break;

            case 90:  // Аренда
                if (($vozFlag == 0 || $rezervFlag) && $rowStore == $storeId && $dateBeg <= $date && $dateVoz >= $date) {
                    $total -= $quant;
                }
                break;
        }
    }
    return $total;
}

$action = (string)($_POST['action'] ?? 'check_bronir');

if ($action === 'quant_date') {
    $productId     = (int)($_POST['product_id'] ?? 0);
    $excludeDocId  = (int)($_POST['docum_id'] ?? 0);
    $date          = (string)($_POST['date'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'qty' => quant_date($conn, $productId, $excludeDocId, $date)]);
    exit;
}

/* ---- Подбор тарифа и залога товара аренды (get_price) ----
 * По спецификации: базовые цены по period_aren, выбор сезона (prplan),
 * тарифы из price по диапазонам часов/дней, залог с учётом nozalog_flag. */
if ($action === 'get_price') {
    header('Content-Type: application/json; charset=utf-8');

    $documId   = (int)($_POST['docum_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $prplanId  = (int)($_POST['prplan_id'] ?? 0);
    $hours     = trim((string)($_POST['hours'] ?? ''));
    $days      = (int)($_POST['days'] ?? 0);
    $months    = (int)($_POST['months'] ?? 0);
    $dateBeg   = trim((string)($_POST['date_beg'] ?? ''));
    $quant     = (float)($_POST['quant'] ?? 1);
    $discount  = (float)str_replace(',', '.', (string)($_POST['discount'] ?? '0'));
    $prplanManual = (($_POST['prplan_manual'] ?? '') === '1');

    $fixedFlag  = ((int)($appSettings['FixedFlag'] ?? 0)) === 1;
    $manualFlag = ((int)($appSettings['ManualTariffFlag'] ?? 0)) === 1;
    $sezonFlag  = ((int)($appSettings['SezonFlag'] ?? 0)) === 1;

    if ($productId <= 0) { echo json_encode(['ok' => false]); exit; }

    $qq = $conn->query("SELECT product_id, zalog, period_aren, price_hour, price_day, price_month, noquant_flag, nocalc_flag FROM product WHERE product_id = $productId");
    $prod = $qq ? $qq->fetch_assoc() : null;
    if (!$prod) { echo json_encode(['ok' => false]); exit; }

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
        if ($cl && (int)$cl['nozalog_flag'] === 1) $sumZalog = 0;
    }

    /* Базовая цена по периоду аренды товара */
    $price = 0.0;
    $period = (string)($prod['period_aren'] ?? '');
    if ($period === 'm')      $price = (float)$prod['price_month'];
    elseif ($period === 'd')  $price = (float)$prod['price_day'];
    elseif ($period === 'h')  $price = (float)$prod['price_hour'];

    /* Сезон: prplan.ddate..prplan.edate содержит date_beg ('d.m.Y').
       Не применяется при ручном выборе плана пользователем (prplan_manual). */
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

    /* Все тарифы — для выпадающего списка «Название тарифа»:
     * фиксированные выбираемы, остальные отображаются (disabled). */
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

    echo json_encode([
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
        'manual_flag'  => $manualFlag ? 1 : 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'check_bronir') {
    $documId    = (int)($_POST['docum_id'] ?? 0);
    $productId  = (int)($_POST['product_id'] ?? 0);
    $dateBeg    = trim((string)($_POST['date_beg'] ?? ''));
    $timeBeg    = trim((string)($_POST['time_beg'] ?? ''));
    $dateVoz    = trim((string)($_POST['date_voz'] ?? ''));
    $timeVoz    = trim((string)($_POST['time_voz'] ?? ''));
    $quant      = (float)($_POST['quant'] ?? 0);
    $vozFlag    = (int)($_POST['voz_flag'] ?? 0);
    $noquantFlag = (int)($_POST['noquant_flag'] ?? 0);
    $nocalcFlag  = (int)($_POST['nocalc_flag'] ?? 0);

    header('Content-Type: application/json; charset=utf-8');

    if ($vozFlag) {
        echo json_encode(['ok' => true, 'blocked' => false]);
        exit;
    }

    if ($productId <= 0) {
        echo json_encode(['ok' => true, 'blocked' => false]);
        exit;
    }

    $pq = $conn->query("SELECT product_name FROM product WHERE product_id = $productId");
    $product = $pq ? $pq->fetch_assoc() : null;
    $productName = $product ? (string)$product['product_name'] : '';

    if ($nocalcFlag) {
        echo json_encode(['ok' => true, 'blocked' => false]);
        exit;
    }

    if ($noquantFlag) {
        $dtBeg = $dateBeg . ($timeBeg ? ' ' . $timeBeg : '');
        $dtVoz = $dateVoz . ($timeVoz ? ' ' . $timeVoz : '');
        $sql = "SELECT d.docum_id FROM docum d
                JOIN docum2 d2 ON d2.docum_id = d.docum_id
                WHERE d2.product_id = ? AND d2.voz_flag = 0
                  AND d.typeop = 90
                  AND d.docum_id != ?
                  AND d2.date_beg <= ? AND d2.date_voz >= ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('iiss', $productId, $documId, $dtVoz, $dtBeg);
            $stmt->execute();
            $res = $stmt->get_result();
            $found = $res->num_rows > 0;
            $stmt->close();
        } else {
            $found = false;
        }

        if ($found) {
            echo json_encode([
                'ok' => true, 'blocked' => true,
                'message' => $productName . ' занят на это время!',
                'clear_product' => true,
            ]);
        } else {
            echo json_encode(['ok' => true, 'blocked' => false]);
        }
        exit;
    }

    // Товар с количеством
    if ((int)($_POST['days'] ?? 0) > 0) {
        $minQuant = PHP_FLOAT_MAX;
        $d = new DateTime($dateBeg);
        $dEnd = new DateTime($dateVoz);
        $dEnd = $dEnd->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($d, $interval, $dEnd);

        foreach ($period as $dt) {
            $dayDate = $dt->format('Y-m-d');
            $q = quant_date($conn, $productId, $documId, $dayDate);
            if ($q < $minQuant) $minQuant = $q;
        }
    } else {
        $minQuant = quant_date($conn, $productId, $documId, $dateBeg);
    }

    if ($quant > $minQuant) {
        $msg = 'Товар ' . $productName . '. Доступно: ' . $minQuant;
        if ($minQuant <= 0) {
            echo json_encode([
                'ok' => true, 'blocked' => true,
                'message' => $msg,
                'quant' => $minQuant,
                'clear_product' => true,
            ]);
        } else {
            echo json_encode([
                'ok' => true, 'blocked' => true,
                'message' => $msg,
                'quant' => $minQuant,
                'clear_product' => false,
            ]);
        }
    } else {
        echo json_encode(['ok' => true, 'blocked' => false]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);
