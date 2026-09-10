<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/arenda2_totals.php';
require_once __DIR__ . '/lib/arenda2_voz.php';

if (!$isAjax) { header('HTTP/1.0 400 Bad Request'); exit; }

/** Пересчёт итогов документа аренды (как recalc_docum_totals в docum2_field_save) */
function arenda_recalc_totals(mysqli $conn, int $documId): array {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COUNT(*) FROM docum2 WHERE docum_id = ?");
    $stmt->bind_param('i', $documId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum = (float)$row[0];
    $sd  = (float)$row[1];
    $pos = (int)$row[2];
    $ps = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = 90");
    $ps->bind_param('i', $documId);
    $ps->execute();
    $sumPlat = (float)$ps->get_result()->fetch_row()[0];
    $ps->close();
    $sumBalans = $sum - $sumPlat;
    $upd = $conn->prepare("UPDATE docum SET sum = ?, sum_discount = ?, pos = ?, sum_balans = ?, sum_plat = ? WHERE docum_id = ?");
    bind_auto($upd, [$sum, $sd, $pos, $sumBalans, $sumPlat, $documId]);
    $upd->execute();
    $upd->close();
    return [
        'total_sum'           => number_format($sum, 2, '.', ''),
        'total_sum_discount'  => number_format($sd, 2, '.', ''),
        'pos'                 => $pos,
        'sum_plat'            => number_format($sumPlat, 2, '.', ''),
    ];
}

function arenda_doc_exists(mysqli $conn, int $documId): bool {
    if ($documId <= 0) return false;
    $q = $conn->query("SELECT docum_id FROM docum WHERE docum_id = $documId AND typeop = 90");
    return $q && $q->num_rows > 0;
}

$field   = (string)($_POST['field'] ?? '');
$value   = (string)($_POST['value'] ?? '');
$documId = (int)($_POST['docum_id'] ?? $_POST['id'] ?? 0);

/* ---- Применить Начало ко всем товарам документа ----
 * Устанавливает дату/время начала И возврата всем товарам из значений формы
 * (POST), затем пересчитывает период (месяцы/дни/часы), тарифы и суммы каждого
 * товара (сезон, выходные). */
if ($field === '_apply_beg') {
    if (!arenda_doc_exists($conn, $documId)) { echo json_encode(['ok' => false]); exit; }
    $begDate = trim((string)($_POST['date_beg'] ?? ''));
    $begTime = trim((string)($_POST['time_beg'] ?? ''));
    $vozDate = trim((string)($_POST['date_voz'] ?? ''));
    $vozTime = trim((string)($_POST['time_voz'] ?? ''));
    $stmt = $conn->prepare("UPDATE docum2 SET date_beg = ?, time_beg = ?, date_voz = ?, time_voz = ? WHERE docum_id = ?");
    bind_auto($stmt, [$begDate, $begTime, $vozDate, $vozTime, $documId]);
    $stmt->execute();
    $stmt->close();
    /* Сохранить те же даты в шапку docum, иначе при отмене формы они разойдутся
       с товарами. После — бэкаполь скорректирует date_voz/days/hours/months/sum. */
    $dstmt = $conn->prepare("UPDATE docum SET date_beg = ?, time_beg = ?, date_voz = ?, time_voz = ? WHERE docum_id = ?");
    bind_auto($dstmt, [$begDate, $begTime, $vozDate, $vozTime, $documId]);
    $dstmt->execute();
    $dstmt->close();
    _arenda_apply_recalc_items($conn, $appSettings, $documId);
    $totals = arenda2_backfill_docum($conn, $documId);
    header('Content-Type: application/json; charset=utf-8');
    $totals['ok'] = true;
    echo json_encode($totals);
    exit;
}

/* ---- Применить Возврат ко всем товарам документа ----
 * Устанавливает дату/время возврата всем товарам из значения формы (POST),
 * вычисляет месяцы/дни/часы каждого товара и пересчитывает тарифы и суммы. */
if ($field === '_apply_voz') {
    if (!arenda_doc_exists($conn, $documId)) { echo json_encode(['ok' => false]); exit; }
    $vozDate = trim((string)($_POST['date_voz'] ?? ''));
    $vozTime = trim((string)($_POST['time_voz'] ?? ''));
    $stmt = $conn->prepare("UPDATE docum2 SET date_voz = ?, time_voz = ? WHERE docum_id = ?");
    bind_auto($stmt, [$vozDate, $vozTime, $documId]);
    $stmt->execute();
    $stmt->close();
    /* Сохранить дату возврата в шапку docum (см. примечание к _apply_beg). */
    $dstmt = $conn->prepare("UPDATE docum SET date_voz = ?, time_voz = ? WHERE docum_id = ?");
    bind_auto($dstmt, [$vozDate, $vozTime, $documId]);
    $dstmt->execute();
    $dstmt->close();
    _arenda_apply_recalc_items($conn, $appSettings, $documId);
    $totals = arenda2_backfill_docum($conn, $documId);
    header('Content-Type: application/json; charset=utf-8');
    $totals['ok'] = true;
    echo json_encode($totals);
    exit;
}

/* ---- Мгновенный проброс docum.voz_flag / docum.rezerv_flag на все товары ----
 * Вызывается при переключении чекбоксов «Возвращено»/«Резервирование» в форме
 * документа (arenda_form.php). Сразу сохраняет: docum2-флаги у всех товаров,
 * при voz_flag пересчитывает days/hours/months и суммы каждого товара
 * (arenda2_items_recalc_sums → arenda2_apply_voz_to_item), затем бэкаполь итогов
 * docum. Возвращает итоги и актуальные флаги docum для обновления формы. */
if ($field === '_set_doc_flags') {
    if (!arenda_doc_exists($conn, $documId)) { echo json_encode(['ok' => false, 'error' => 'doc not found']); exit; }
    $rez = (int)($_POST['rezerv_flag'] ?? 0);
    $voz = (int)($_POST['voz_flag'] ?? 0);
    $changed = false;

    $cur = $conn->query("SELECT rezerv_flag, voz_flag FROM docum WHERE docum_id = $documId")->fetch_assoc();
    $oldRez = $cur ? (int)$cur['rezerv_flag'] : 0;
    $oldVoz = $cur ? (int)$cur['voz_flag'] : 0;

    if ($rez !== $oldRez || $voz !== $oldVoz) $changed = true;

    if ($changed) {
        /* Обновить флаги самого документа. */
        $upd = $conn->prepare("UPDATE docum SET rezerv_flag = ?, voz_flag = ? WHERE docum_id = ?");
        bind_auto($upd, [$rez, $voz, $documId]);
        $upd->execute();
        $upd->close();

        /* Пробросить флаги на все товары. */
        arenda2_items_set_flags($conn, $documId, $rez, $voz);

        /* При voz_flag — пересчёт периодов, сумм и прозвон правил возврата. */
        if ($voz !== $oldVoz) {
            arenda2_items_recalc_sums($conn, $documId, $voz, $appSettings);
        }
    }

    $totals = arenda2_backfill_docum($conn, $documId);

    /* Фактические флаги после пересчёта (если возвращены не все товары — voz_flag
       сбрасывается функцией синхронизации). Вернём состояние для чекбоксов. */
    $r = $conn->query("SELECT rezerv_flag, voz_flag FROM docum WHERE docum_id = $documId")->fetch_assoc();
    $docRez = $r ? (int)$r['rezerv_flag'] : 0;
    $docVoz = $r ? (int)$r['voz_flag'] : 0;

    header('Content-Type: application/json; charset=utf-8');
    $totals['ok'] = true;
    $totals['doc_rezerv_flag'] = $docRez;
    $totals['doc_voz_flag'] = $docVoz;
    $totals['changed'] = $changed;
    echo json_encode($totals);
    exit;
}

/** Пересчёт периода/тарифов/сумм всех товаров аренды документа. */
function _arenda_apply_recalc_items(mysqli $conn, array $appSettings, int $documId): void {
    $q = $conn->query("SELECT docum2_id FROM docum2 WHERE docum_id = $documId AND typeop = 90");
    if (!$q) return;
    while ($row = $q->fetch_assoc()) {
        arenda2_recalc_item_period($conn, $appSettings, $documId, (int)$row['docum2_id']);
    }
}

/* ---- Пересчёт итогов ---- */
if ($field === '_recalc_totals') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], arenda_recalc_totals($conn, $documId)));
    exit;
}

/* ---- Инлайн-редактирование полей списка ---- */
$ALLOWED = [
    'note'      => ['col' => 'note',      'type' => 'text'],
    'plat_type' => ['col' => 'plat_type', 'type' => 'plat'],
];

if ($field === '' || !arenda_doc_exists($conn, $documId)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}
if (!isset($ALLOWED[$field])) {
    echo json_encode(['ok' => false, 'error' => 'Unknown field']);
    exit;
}

$value = trim($value);
if ($ALLOWED[$field]['type'] === 'plat') {
    $valid = ['Наличные', 'Безнал.', 'Карта', 'Прочее'];
    if ($value !== '' && !in_array($value, $valid, true)) {
        echo json_encode(['ok' => false, 'error' => 'Недопустимый вид оплаты']);
        exit;
    }
}

$stmt = $conn->prepare("UPDATE docum SET {$ALLOWED[$field]['col']} = ? WHERE docum_id = ?");
$stmt->bind_param('si', $value, $documId);
$stmt->execute();
$stmt->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'field' => $field, 'value' => $value]);
