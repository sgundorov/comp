<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/arenda2_tariff.php';
require_once __DIR__ . '/lib/arenda2_totals.php';
require_once __DIR__ . '/lib/arenda2_voz.php';
ob_start();

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$showHoursFlag  = (int)($appSettings['ShowHoursFlag'] ?? 0) === 1;
$showDaysFlag   = (int)($appSettings['ShowDaysFlag'] ?? 0) === 1;
$showMonthsFlag = (int)($appSettings['ShowMonthsFlag'] ?? 0) === 1;
$manualTariff   = (int)($appSettings['ManualTariffFlag'] ?? 0) === 1;
$fixedFlag      = (int)($appSettings['FixedFlag'] ?? 0) === 1;
$sezonFlag      = (int)($appSettings['SezonFlag'] ?? 0) === 1;
$ndsRateJs      = (int)($appSettings['nds_rate'] ?? 0);
$noNdsJs        = (($appSettings['no_nds'] ?? '0') === '1') ? 1 : 0;
$keepDaysJs     = (($appSettings['KeepDaysOnEarlyReturn'] ?? '0') === '1') ? 1 : 0;
$noRefundJs     = (($appSettings['NoRefundOnEarlyReturn'] ?? '0') === '1') ? 1 : 0;
$timeShiftJs    = (string)($appSettings['TimeShift'] ?? '00:00');

$mode     = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id       = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$documId  = (int)($_GET['docum_id'] ?? $_POST['docum_id'] ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';
$values = [
    'product_id' => 0, 'product_name' => '', 'code' => '',
    'service_flag' => 0, 'noquant_flag' => 0, 'nocalc_flag' => 0,
    'quant' => '', 'hours' => '', 'days' => '', 'months' => '',
    'date_beg' => '', 'time_beg' => '',
    'date_voz' => '', 'time_voz' => '',
    'prplan_id' => 0, 'price_id' => 0, 'price_name' => '',
    'price_hour' => '', 'price_day' => '', 'price_we' => '', 'price_fix' => '', 'price_month' => '',
    'discount' => '', 'sum_discount' => '',
    'sum' => '', 'sum_zalog' => '',
    'rezerv_flag' => 0, 'voz_flag' => 0,
    'fixed_flag' => 0,
    'note' => '',
];

function arenda2_norm_time(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $v, $m)) {
        if ((int)$m[1] <= 23) return sprintf('%02d:%02d', $m[1], $m[2]);
    }
    if (preg_match('/^\d+$/', $v)) {
        $L = strlen($v);
        if ($L <= 2) { $h = (int)$v; if ($h <= 23) return sprintf('%02d:00', $h); }
        elseif ($L === 3) { $h = (int)$v[0]; $mi = (int)substr($v, 1); if ($h <= 23 && $mi <= 59) return sprintf('%02d:%02d', $h, $mi); }
        elseif ($L === 4) { $h = (int)substr($v, 0, 2); $mi = (int)substr($v, 2); if ($h <= 23 && $mi <= 59) return sprintf('%02d:%02d', $h, $mi); }
    }
    return '';
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = @$conn->prepare("SELECT d2.*, p.product_name AS pname FROM docum2 d2 LEFT JOIN product p ON p.product_id = d2.product_id WHERE d2.docum2_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$r) { $errors[] = 'Запись не найдена.'; }
        else {
            foreach ($values as $k => $v) { $values[$k] = isset($r[$k]) ? $r[$k] : $v; }
            $values['product_name'] = (string)($r['pname'] ?? '');
            $values['quant'] = (float)$r['quant'] == 0 ? '0' : rtrim(rtrim(number_format((float)$r['quant'], 3, '.', ''), '0'), '.');
            $values['hours'] = trim((string)$r['hours']) === '' ? '' : substr(trim((string)$r['hours']), 0, 5);
            $values['days'] = (int)$r['days'] > 0 ? (int)$r['days'] : '';
            $values['months'] = (int)$r['months'] > 0 ? (int)$r['months'] : '';
            foreach (['price_hour', 'price_day', 'price_we', 'price_fix', 'price_month'] as $kp) {
                $values[$kp] = (float)$values[$kp] == 0 ? '' : rtrim(rtrim(number_format((float)$values[$kp], 2, '.', ''), '0'), '.');
            }
            $values['discount'] = (float)$r['discount'] == 0 ? '' : rtrim(rtrim(number_format((float)$r['discount'], 1, '.', ''), '0'), '.');
            $values['sum_discount'] = (float)$r['sum_discount'] == 0 ? '' : rtrim(rtrim(number_format((float)$r['sum_discount'], 2, '.', ''), '0'), '.');
            $values['sum'] = (float)$r['sum'] == 0 ? '' : rtrim(rtrim(number_format((float)$r['sum'], 2, '.', ''), '0'), '.');
            $values['sum_zalog'] = (float)$r['sum_zalog'] == 0 ? '' : rtrim(rtrim(number_format((float)$r['sum_zalog'], 2, '.', ''), '0'), '.');
            $values['rezerv_flag'] = (int)$r['rezerv_flag'];
            $values['voz_flag'] = (int)$r['voz_flag'];
            $documId = (int)$r['docum_id'];
        }
    }
}

$isReadonly = ($mode === 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['product_id'] = (int)($_POST['product_id'] ?? 0);
    $values['quant']      = str_replace(',', '.', trim((string)($_POST['quant'] ?? '')));
    $values['hours']      = arenda2_norm_time((string)($_POST['hours'] ?? ''));
    $values['days']       = (int)($_POST['days'] ?? 0);
    $values['months']     = (int)($_POST['months'] ?? 0);
    $values['date_beg']   = trim((string)($_POST['date_beg'] ?? ''));
    $values['time_beg']   = arenda2_norm_time((string)($_POST['time_beg'] ?? ''));
    $values['date_voz']   = trim((string)($_POST['date_voz'] ?? ''));
    $values['time_voz']   = trim((string)($_POST['time_voz'] ?? ''));
    $values['price_hour'] = str_replace(',', '.', trim((string)($_POST['price_hour'] ?? '0')));
    $values['price_day']  = str_replace(',', '.', trim((string)($_POST['price_day'] ?? '0')));
    $values['price_we']   = str_replace(',', '.', trim((string)($_POST['price_we'] ?? '0')));
    $values['price_fix']  = str_replace(',', '.', trim((string)($_POST['price_fix'] ?? '0')));
    $values['price_month']= str_replace(',', '.', trim((string)($_POST['price_month'] ?? '0')));
    /* Ручной режим тарифов: выходной тариф = дневному (нет отдельного поля price_we) */
    if ($manualTariff) $values['price_we'] = $values['price_day'];
    $values['price_id']   = (int)($_POST['price_id'] ?? 0);
    $values['prplan_id']  = (int)($_POST['prplan_id'] ?? 1);
    $values['discount']   = str_replace(',', '.', trim((string)($_POST['discount'] ?? '0')));
    $values['sum_discount'] = str_replace(',', '.', trim((string)($_POST['sum_discount'] ?? '0')));
    $values['sum']        = str_replace(',', '.', trim((string)($_POST['sum'] ?? '0')));
    $values['sum_zalog']  = str_replace(',', '.', trim((string)($_POST['sum_zalog'] ?? '0')));
    $values['rezerv_flag']= (int)(!empty($_POST['rezerv_flag']));
    $values['voz_flag']   = (int)(!empty($_POST['voz_flag']));
    $values['fixed_flag'] = (int)(!empty($_POST['fixed_flag']));
    $values['note']       = trim((string)($_POST['note'] ?? ''));

    $parent = null;
    if ($documId > 0) {
        $q = $conn->query("SELECT * FROM docum WHERE docum_id = $documId AND typeop = 90");
        $parent = $q ? $q->fetch_assoc() : null;
    }

    if ($mode !== 'delete') {
        if ($values['product_id'] <= 0) {
            $errors[] = 'Выберите товар.';
            $focusField = 'arenda2-product';
        }
    }

    if (empty($errors) && $parent) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM docum2 WHERE docum2_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $bk = arenda2_backfill_docum($conn, $documId);
                $bk = array_merge($bk, arenda2_sync_docum_flags($conn, $documId));
                echo json_encode(array_merge(['ok' => true, 'mode' => $mode, 'id' => $id], $bk));
                exit;
            }
            arenda2_backfill_docum($conn, $documId);
            arenda2_sync_docum_flags($conn, $documId);
            header('Location: arenda.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $pq2 = $conn->query("SELECT product_name, code FROM product WHERE product_id = " . (int)$values['product_id']);
            $prodInfo = $pq2 ? $pq2->fetch_assoc() : null;
            $pName = $prodInfo ? (string)$prodInfo['product_name'] : '';
            $pCode = $prodInfo ? (string)$prodInfo['code'] : '';
$stmt = $conn->prepare("INSERT INTO docum2 (docum_id, typeop, number, date, time, date_beg, time_beg, store_id, prplan_id, product_id, product_name, code, quant, price_hour, price_day, price_we, price_fix, price_month, price_id, discount, sum_discount, sum, sum_zalog, days, hours, months, date_voz, time_voz, rezerv_flag, voz_flag, fixed_flag, note)
                VALUES (?, 90, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$documId, (int)($parent['number'] ?? 0), (string)($parent['date'] ?? ''), (string)($parent['time'] ?? ''),
                $values['date_beg'], $values['time_beg'] ?: '00:00', (int)($parent['store_id'] ?? 0),
                $values['prplan_id'], $values['product_id'], $pName, $pCode,
                (float)$values['quant'],
                (float)$values['price_hour'], (float)$values['price_day'], (float)$values['price_we'], (float)$values['price_fix'], (float)$values['price_month'], (int)$values['price_id'],
                (float)($parent['discount'] ?? 0), (float)$values['sum_discount'], (float)$values['sum'], (float)$values['sum_zalog'],
                (int)$values['days'], (string)$values['hours'], (int)$values['months'],
                $values['date_voz'], $values['time_voz'] ? $values['time_voz'] . ':00' : '',
                $values['rezerv_flag'], $values['voz_flag'], $values['fixed_flag'], $values['note']]);
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $bk = arenda2_backfill_docum($conn, $documId);
                $bk = array_merge($bk, arenda2_sync_docum_flags($conn, $documId));
                echo json_encode(array_merge(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['note']], $bk));
                exit;
            }
            arenda2_backfill_docum($conn, $documId);
            arenda2_sync_docum_flags($conn, $documId);
            header('Location: arenda.php');
            exit;
        }
        if ($mode === 'edit') {
            $pq2 = $conn->query("SELECT product_name, code FROM product WHERE product_id = " . (int)$values['product_id']);
            $prodInfo = $pq2 ? $pq2->fetch_assoc() : null;
            $pName = $prodInfo ? (string)$prodInfo['product_name'] : '';
            $pCode = $prodInfo ? (string)$prodInfo['code'] : '';
            $stmt = $conn->prepare("UPDATE docum2 SET product_id=?, product_name=?, code=?, date_beg=?, time_beg=?, date_voz=?, time_voz=?, quant=?, hours=?, days=?, months=?,
                price_hour=?, price_day=?, price_we=?, price_fix=?, price_month=?, price_id=?, prplan_id=?, discount=?, sum_discount=?, sum=?, sum_zalog=?, rezerv_flag=?, voz_flag=?, fixed_flag=?, note=?
                WHERE docum2_id = ?");
            bind_auto($stmt, [$values['product_id'], $pName, $pCode,
                $values['date_beg'], $values['time_beg'] ?: '00:00',
                $values['date_voz'], $values['time_voz'] ? $values['time_voz'] . ':00' : '',
                (float)($values['quant'] ?: 0), (string)$values['hours'], (int)$values['days'], (int)$values['months'],
                (float)($values['price_hour'] ?: 0), (float)($values['price_day'] ?: 0), (float)($values['price_we'] ?: 0), (float)($values['price_fix'] ?: 0), (float)($values['price_month'] ?: 0), (int)$values['price_id'],
                $values['prplan_id'],
                (float)($values['discount'] ?: 0), (float)($values['sum_discount'] ?: 0),
                (float)($values['sum'] ?: 0), (float)($values['sum_zalog'] ?: 0),
                $values['rezerv_flag'], $values['voz_flag'], $values['fixed_flag'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            /* При изменении voz_flag товара — пересчёт days/hours/months/date_voz/time_voz
               и сумм (правила 111.txt:644-683). */
            arenda2_apply_voz_to_item($conn, $appSettings, $documId, $id, (int)$values['voz_flag']);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $bk = arenda2_backfill_docum($conn, $documId);
                $bk = array_merge($bk, arenda2_sync_docum_flags($conn, $documId));
                echo json_encode(array_merge(['ok' => true, 'mode' => $mode, 'id' => $id], $bk));
                exit;
            }
            arenda2_backfill_docum($conn, $documId);
            arenda2_sync_docum_flags($conn, $documId);
            header('Location: arenda.php');
            exit;
        }
    }
}

$titleNum = $id > 0 ? '#' . $id : '(новый)';
$pageTitle = 'Товар аренды ' . $titleNum;
$isReadonly = ($mode === 'delete');

/* Инициализация новой записи — по спецификации (только при GET, до рендеринга) */
if ($mode === 'new' && $documId > 0) {
    if ($values['quant'] === '') $values['quant'] = '1';
    $qParent = $conn->query("SELECT date_beg, time_beg, days, hours, months FROM docum WHERE docum_id = $documId AND typeop = 90");
    $parent = $qParent ? $qParent->fetch_assoc() : null;
    if ($parent) {
        if (empty($values['date_beg'])) $values['date_beg'] = (string)$parent['date_beg'];
        if (empty($values['time_beg'])) $values['time_beg'] = substr((string)$parent['time_beg'], 0, 5);
        // Период из товара
        if ($values['product_id'] > 0) {
            $qProd = $conn->query("SELECT period_aren FROM product WHERE product_id = " . (int)$values['product_id']);
            $prodRow = $qProd ? $qProd->fetch_assoc() : null;
            $period = trim((string)($prodRow['period_aren'] ?? ''));
            if ($period === 'h' && empty($values['hours'])) $values['hours'] = '1:00';
            elseif ($period === 'd' && empty($values['days'])) $values['days'] = 1;
            elseif ($period === 'm' && empty($values['months'])) $values['months'] = 1;
        }
        // Из документа
        if ((float)$parent['days'] != 0 && empty($values['days'])) $values['days'] = (int)$parent['days'];
        if ((float)$parent['months'] != 0 && empty($values['months'])) $values['months'] = (int)$parent['months'];
        if (!empty($parent['hours']) && empty($values['hours'])) $values['hours'] = substr((string)$parent['hours'], 0, 5);
    }
}

/* Данные для лукапов */
$productList = [];
$hideFilter = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$prs = @$conn->query("SELECT product_id, product_name, code, service_flag, noquant_flag, nocalc_flag, price_hour, price_day, price_month, zalog, period_aren FROM product" . $hideFilter . " ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) $productList[] = [
    'id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name'],
    'code' => (string)$pr['code'], 'service_flag' => (int)$pr['service_flag'],
    'noquant_flag' => (int)$pr['noquant_flag'], 'nocalc_flag' => (int)$pr['nocalc_flag'],
    'price_hour' => (string)$pr['price_hour'], 'price_day' => (string)$pr['price_day'], 'price_month' => (string)$pr['price_month'],
    'zalog' => (string)$pr['zalog'], 'period_aren' => (string)$pr['period_aren'],
];

$prplanList = [];
$pq = @$conn->query("SELECT prplan_id, prplan AS name FROM prplan ORDER BY prplan_id ASC");
if ($pq) while ($prow = $pq->fetch_assoc()) $prplanList[] = ['id' => (int)$prow['prplan_id'], 'name' => (string)$prow['name']];
$defaultPrplanId = !empty($prplanList) ? $prplanList[0]['id'] : 1;
if ($values['prplan_id'] <= 0) $values['prplan_id'] = $defaultPrplanId;

$priceList = [];
if ($values['prplan_id'] > 0 && $values['product_id'] > 0) {
    $stmtP = @$conn->prepare("SELECT price_id, name, bdays, edays, bmonths, emonths, btime, etime, price, pricef, hprice, mprice, fixed_flag FROM price WHERE prplan_id = ? AND product_id = ? ORDER BY price_id ASC");
    if ($stmtP) {
        $stmtP->bind_param('ii', $values['prplan_id'], $values['product_id']);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        if ($resP) while ($prow = $resP->fetch_assoc()) {
            $priceList[] = [
                'id' => (int)$prow['price_id'], 'name' => (string)$prow['name'],
                'bdays' => (int)$prow['bdays'], 'edays' => (int)$prow['edays'],
                'bmonths' => (int)$prow['bmonths'], 'emonths' => (int)$prow['emonths'],
                'btime' => (string)$prow['btime'], 'etime' => (string)$prow['etime'],
                'price' => (float)$prow['price'], 'pricef' => (float)$prow['pricef'],
                'hprice' => (float)$prow['hprice'], 'mprice' => (float)$prow['mprice'],
                'fixed_flag' => (int)$prow['fixed_flag'],
            ];
        }
        $stmtP->close();
    }
}

/* Серверный подбор тарифа при рендере: название/цены должны быть корректны
 * даже если price_id в БД = 0 (старые записи) или рендер устарел.
 * Не выполняется в ручном режиме тарифов (ManualTariffFlag=1) — там тарифы
 * вводятся вручную и берутся из product при выборе товара. */
if (!$manualTariff && (int)($values['product_id'] ?? 0) > 0 && (int)($values['fixed_flag'] ?? 0) !== 1) {
    $tt = arenda2_select_tariff($conn, $appSettings, (int)$values['product_id'], (int)$values['prplan_id'],
        (string)($values['date_beg'] ?? ''), (string)($values['hours'] ?? ''), (int)($values['days'] ?? 0), (int)($values['months'] ?? 0), $documId);
    if (!empty($tt['ok'])) {
        if ((int)$values['price_id'] <= 0) $values['price_id'] = (int)$tt['tariff_id'];
        foreach (['price_hour', 'price_day', 'price_we', 'price_fix', 'price_month'] as $k) {
            if ((float)($values[$k] ?? 0) == 0) $values[$k] = (string)$tt[$k];
        }
        if ((float)($values['sum_zalog'] ?? 0) == 0) $values['sum_zalog'] = (string)$tt['sum_zalog'];
    }
}
$currentPrplanName = '';
foreach ($prplanList as $pp) { if ($pp['id'] === (int)$values['prplan_id']) { $currentPrplanName = $pp['name']; break; } }
$currentPriceName = '';
foreach ($priceList as $pl) { if ($pl['id'] === (int)$values['price_id']) { $currentPriceName = $pl['name']; break; } }
$currentProductName = '';
foreach ($productList as $pp2) { if ($pp2['id'] === (int)$values['product_id']) { $currentProductName = $pp2['name']; break; } }
if ($currentProductName === '' && !empty($values['product_name'])) $currentProductName = (string)$values['product_name'];

function dv2($v) { return $v; }

?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/price.png" alt="" /> <?= h($pageTitle) ?></h2>
<style>
    .form-table { width: 100%; border-collapse: collapse; }
    .form-table td { vertical-align: top; padding-top: 0; padding-bottom: 6px; padding-right: 8px; width: 33.33%; }
    .form-table td:last-child { padding-right: 0; }
    .form-table td.form-label { font-size: 12px; color: var(--muted); padding-top: 0; padding-bottom: 2px; }
    input.num { text-align: right; }
    .spin-btn { width:25px; height:24px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--line); background:var(--btn); color:#fff; border-radius:2px; cursor:pointer; font-size:12px; line-height:1; user-select:none; flex:0 0 auto; }
    .spin-btn:hover { background:var(--btn-hover); }
    .spin-btn-ph { display:inline-block; width:25px; visibility:hidden; }
    .arenda2-tariff-label { font-size: 11px; color: var(--muted); line-height: 1; white-space: nowrap; }
    input.full, textarea.full { width: 100%; box-sizing: border-box; }
    .lookup-wrap { position: relative; max-width: 100%; }
    .form-modal { max-width: 720px; }
</style>
<form class="form" method="post" action="arenda2_form.php?ajax=1" autocomplete="off" data-form-modal data-docum-id="<?= (int)$documId ?>">
<input type="hidden" name="mode" value="<?= h($mode) ?>" />
<input type="hidden" name="id" value="<?= (int)$id ?>" />
<input type="hidden" name="docum_id" value="<?= (int)$documId ?>" id="arenda2-docum-id" />
<input type="hidden" name="cli_name" value="" id="arenda2-cli-name" />
<input type="hidden" name="code" value="<?= h($values['code'] ?? '') ?>" id="arenda2-code" />
<input type="hidden" name="service_flag" value="<?= (int)($values['service_flag'] ?? 0) ?>" id="arenda2-service-flag" />
<input type="hidden" name="nocalc_flag" value="<?= (int)($values['nocalc_flag'] ?? 0) ?>" id="arenda2-nocalc-flag" />
<input type="hidden" name="price_we" value="<?= h($values['price_we'] ?? '') ?>" id="arenda2-price-we" />
<input type="hidden" name="price_fix" value="<?= h($values['price_fix'] ?? '') ?>" id="arenda2-price-fix" />
<input type="hidden" name="used_days" value="0" id="arenda2-used-days" />
<input type="hidden" name="fixed_flag" value="<?= (int)($values['fixed_flag'] ?? 0) ?>" id="arenda2-fixed-flag" />

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<table class="form-table">
<?php $roAttr = function() use ($isReadonly) { return $isReadonly ? ['readonly' => true] : []; }; ?>
<?php $priceRoAttr = function() use ($isReadonly, $manualTariff) { return ($isReadonly || !$manualTariff) ? ['readonly' => true] : []; }; ?>

  <!-- Строка 1: Товар (лукап) | Возвращён -->
  <tr><td class="form-label" colspan="2">Товар *</td><td class="form-label">&nbsp;</td></tr>
  <tr>
    <td colspan="2"><?= render_lookup('product', 'product_id', (int)$values['product_id'],
            h($currentProductName),
            h(json_encode($productList, JSON_UNESCAPED_UNICODE)),
            'tmc_form.php?mode=new', $isReadonly, ['id' => 'arenda2-product', 'data-name-input' => 'arenda2-product-name']) ?></td>
    <td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="voz_flag" id="arenda2-voz-flag" value="1"<?= $values['voz_flag'] ? ' checked' : '' ?><?= $isReadonly ? ' disabled' : '' ?> /> Возвращён</label></td>
  </tr>

  <!-- Строка 2: Количество | Начало (ro) | Возврат -->
  <tr>
    <td class="form-label"><?php if ($isReadonly): ?>Количество<?php else: ?><span style="display:inline-flex;gap:2px;align-items:center"><span class="spin-btn-ph">▼</span>Количество<span class="spin-btn-ph">▲</span></span><?php endif; ?></td>
    <td class="form-label">Начало</td>
    <td class="form-label">Возврат</td>
  </tr>
  <tr>
    <td><div style="display:flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="down" data-target="arenda2-quant" data-step="1" title="Уменьшить на 1">▼</button><?php endif; ?><?= render_input('text', 'quant', $values['quant'], array_merge(['id' => 'arenda2-quant', 'style' => 'width:48px;text-align:center'], $roAttr())) ?><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="up" data-target="arenda2-quant" data-step="1" title="Увеличить на 1">▲</button><?php endif; ?></div></td>
    <td><div style="display:flex;gap:8px"><?= render_input('date', 'date_beg', $values['date_beg'], ['id' => 'arenda2-date-beg', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px']) ?><?= render_input('text', 'time_beg', substr((string)$values['time_beg'], 0, 5), ['id' => 'arenda2-time-beg', 'readonly' => true, 'tabindex' => '-1', 'style' => 'width:60px', 'placeholder' => 'чч:мм']) ?></div></td>
    <td><div style="display:flex;gap:8px"><?= render_input('date', 'date_voz', $values['date_voz'], array_merge(['id' => 'arenda2-date-voz', 'style' => 'max-width:120px'], $roAttr())) ?><?= render_input('text', 'time_voz', substr((string)$values['time_voz'], 0, 5), array_merge(['id' => 'arenda2-time-voz', 'style' => 'width:60px', 'placeholder' => 'чч:мм'], $roAttr())) ?></div></td>
  </tr>

  <!-- Строка 3: Часов | Дней | Месяцев (кнопки +/- ; поле «Дней» — обычное текстовое) -->
  <tr class="arenda2-period-row">
    <td class="form-label"><?php if ($showHoursFlag): ?><span style="display:inline-flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><span class="spin-btn-ph">▼</span><?php endif; ?>Часов<?php if (!$isReadonly): ?><span class="spin-btn-ph">▲</span><?php endif; ?><span class="arenda2-tariff-label" id="arenda2-price-hour-label" style="display:none;margin-left:16px">За час</span></span><?php else: ?>&nbsp;<?php endif; ?></td>
    <td class="form-label"><?php if ($showDaysFlag): ?><span style="display:inline-flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><span class="spin-btn-ph">▼</span><?php endif; ?>Дней<?php if (!$isReadonly): ?><span class="spin-btn-ph">▲</span><?php endif; ?><span class="arenda2-tariff-label" id="arenda2-price-day-label" style="display:none;margin-left:19px">За день</span></span><?php else: ?>&nbsp;<?php endif; ?></td>
    <td class="form-label"><?php if ($showMonthsFlag): ?><span style="display:inline-flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><span class="spin-btn-ph">▼</span><?php endif; ?>Месяцев<?php if (!$isReadonly): ?><span class="spin-btn-ph">▲</span><?php endif; ?><span class="arenda2-tariff-label" id="arenda2-price-month-label" style="display:none;margin-left:4px">За месяц</span></span><?php else: ?>&nbsp;<?php endif; ?></td>
  </tr>
  <tr class="arenda2-period-row">
    <td><?php if ($showHoursFlag): ?><div style="display:flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="down" data-target="arenda2-hours" data-step="60" data-is-hour="1" title="Минус 1 час">▼</button><?php endif; ?><?= render_input('text', 'hours', $values['hours'], array_merge(['id' => 'arenda2-hours', 'placeholder' => 'чч:мм', 'style' => 'width:48px;text-align:center'], $roAttr())) ?><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="up" data-target="arenda2-hours" data-step="60" data-is-hour="1" title="Плюс 1 час">▲</button><?php endif; ?><?= render_input('text', 'price_hour', $values['price_hour'], array_merge(['id' => 'arenda2-price-hour', 'style' => 'display:none;width:70px;text-align:right;margin-left:4px'], $priceRoAttr())) ?></div><?php else: ?>&nbsp;<?php endif; ?></td>
    <td><?php if ($showDaysFlag): ?><div style="display:flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="down" data-target="arenda2-days" data-step="1" title="Минус 1">▼</button><?php endif; ?><?= render_input('text', 'days', $values['days'] !== '' ? (string)$values['days'] : '', array_merge(['id' => 'arenda2-days', 'style' => 'width:48px;text-align:center'], $roAttr())) ?><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="up" data-target="arenda2-days" data-step="1" title="Плюс 1">▲</button><?php endif; ?><?= render_input('text', 'price_day', $values['price_day'], array_merge(['id' => 'arenda2-price-day', 'style' => 'display:none;width:70px;text-align:right;margin-left:4px'], $priceRoAttr())) ?></div><?php else: ?>&nbsp;<?php endif; ?></td>
    <td><?php if ($showMonthsFlag): ?><div style="display:flex;gap:2px;align-items:center"><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="down" data-target="arenda2-months" data-step="1" title="Минус 1">▼</button><?php endif; ?><?= render_input('text', 'months', $values['months'] !== '' ? (string)$values['months'] : '', array_merge(['id' => 'arenda2-months', 'style' => 'width:48px;text-align:center'], $roAttr())) ?><?php if (!$isReadonly): ?><button type="button" class="spin-btn" data-spin="up" data-target="arenda2-months" data-step="1" title="Плюс 1">▲</button><?php endif; ?><?= render_input('text', 'price_month', $values['price_month'], array_merge(['id' => 'arenda2-price-month', 'style' => 'display:none;width:70px;text-align:right;margin-left:4px'], $priceRoAttr())) ?></div><?php else: ?>&nbsp;<?php endif; ?></td>
  </tr>

<?php if (!$manualTariff): ?>
  <!-- Строка 5: Тарифный план | Название тарифа (скрыто в ручном режиме) -->
  <tr>
    <td class="form-label">Тарифный план</td>
    <td class="form-label" colspan="2">Название тарифа</td>
  </tr>
  <tr>
    <td><select name="prplan_id" id="arenda2-prplan" class="field-input"<?php if ($isReadonly): ?> disabled<?php endif; ?>>
<?php foreach ($prplanList as $pp): ?>
        <option value="<?= (int)$pp['id'] ?>"<?= (int)$pp['id'] === (int)$values['prplan_id'] ? ' selected' : '' ?>><?= h($pp['name']) ?></option>
<?php endforeach; ?>
    </select></td>
    <td colspan="2"><select name="price_id" id="arenda2-price-id" class="field-input"<?php if ($isReadonly): ?> disabled<?php endif; ?>>
<?php
        $selPriceId = (int)$values['price_id'];
        $inPriceList = false;
        foreach ($priceList as $pl) { if ((int)$pl['id'] === $selPriceId) { $inPriceList = true; break; } }
        ?><option value="0">—</option><?php
        foreach ($priceList as $pl):
            $fixed = (int)$pl['fixed_flag'];
            if ($fixed):
            $bt = substr((string)$pl['btime'], 0, 5);
            $et = substr((string)$pl['etime'], 0, 5);
        ?>
        <option value="<?= (int)$pl['id'] ?>"
            data-fixed="1"
            data-bdays="<?= (int)$pl['bdays'] ?>"
            data-edays="<?= (int)$pl['edays'] ?>"
            data-bmonths="<?= (int)($pl['bmonths'] ?? 0) ?>"
            data-emonths="<?= (int)($pl['emonths'] ?? 0) ?>"
            data-btime="<?= h($bt) ?>"
            data-etime="<?= h($et) ?>"
            data-price="<?= (float)$pl['price'] ?>"
            data-hprice="<?= (float)$pl['hprice'] ?>"
            data-mprice="<?= (float)($pl['mprice'] ?? 0) ?>"<?= (int)$pl['id'] === $selPriceId ? ' selected' : '' ?>><?= h($pl['name']) ?></option>
<?php
            else: ?>
        <option value="<?= (int)$pl['id'] ?>" disabled<?= (int)$pl['id'] === $selPriceId ? ' selected' : '' ?>><?= h($pl['name']) ?></option>
<?php endif;
        endforeach;
        if ($selPriceId > 0 && !$inPriceList):
            $extraName = '';
            $qr = $conn->query("SELECT name FROM price WHERE price_id = $selPriceId");
            if ($qr && ($pw = $qr->fetch_assoc())) $extraName = (string)$pw['name'];
            if ($extraName !== ''): ?>
        <option value="<?= $selPriceId ?>" selected><?= h($extraName) ?></option>
<?php endif; endif; ?>
    </select></td>
  </tr>
<?php endif; ?>

  <!-- Строка 6: Скидка % | Сумма скидки -->
  <tr>
    <td class="form-label">Скидка %</td>
    <td class="form-label">Сумма скидки</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('text', 'discount', $values['discount'], array_merge(['id' => 'arenda2-discount', 'class' => 'num', 'style' => 'max-width:120px;text-align:right'], $roAttr())) ?></td>
    <td><?= render_input('text', 'sum_discount', $values['sum_discount'], array_merge(['id' => 'arenda2-sum-discount', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;text-align:right'], ['readonly' => true])) ?></td>
    <td>&nbsp;</td>
  </tr>

  <!-- Строка 7: Сумма | Залог -->
  <tr><td class="form-label">Сумма</td><td class="form-label">Залог</td><td class="form-label">&nbsp;</td></tr>
  <tr>
    <td><?= render_input('text', 'sum', $values['sum'], array_merge(['id' => 'arenda2-sum', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;font-weight:bold;text-align:right'])) ?></td>
    <td><?= render_input('text', 'sum_zalog', $values['sum_zalog'], array_merge(['id' => 'arenda2-sum-zalog', 'readonly' => true, 'tabindex' => '-1', 'class' => 'num', 'style' => 'max-width:120px;text-align:right'])) ?></td>
    <td>&nbsp;</td>
  </tr>

  <!-- Строка 8: Примечание -->
  <tr><td class="form-label" colspan="3">Примечание</td></tr>
  <tr><td colspan="3"><?= render_input('text', 'note', $values['note'], array_merge(['id' => 'arenda2-note', 'class' => 'full'], $roAttr())) ?></td></tr>
</table>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
) ?>
</form>

<script>
(function() {
  var productList = <?= json_encode($productList, JSON_UNESCAPED_UNICODE) ?>;
  var map = {};
  productList.forEach(function(p) { map[p.id] = p; });

  var FIXED_FLAG   = <?= $fixedFlag ? 1 : 0 ?>;
  var MANUAL_FLAG  = <?= $manualTariff ? 1 : 0 ?>;
  var SHOW_HOURS   = <?= $showHoursFlag ? 1 : 0 ?>;
  var SHOW_DAYS    = <?= $showDaysFlag ? 1 : 0 ?>;
  var SHOW_MONTHS  = <?= $showMonthsFlag ? 1 : 0 ?>;
  var NDS_RATE     = <?= (int)$ndsRateJs ?>;
  var NO_NDS       = <?= (int)$noNdsJs ?>;
  var KEEP_DAYS_ON_EARLY_RETURN = <?= (int)$keepDaysJs ?>;
  var NO_REFUND = <?= (int)$noRefundJs ?>;
  var TIME_SHIFT = '<?= h($timeShiftJs) ?>';

  /* Залог: храним базу (product.zalog) и показываем с учётом количества (111.txt:854) */
  var sumZalogBase = 0;
  function applySumZalog() {
    var p = currentProduct();
    var quant = parseNum(getVal('arenda2-quant')); if (String(getVal('arenda2-quant')).trim() === '') quant = 1;
    var z = sumZalogBase;
    if (!p || !p.noquant_flag) z *= quant;
    setVal('arenda2-sum-zalog', fmtNum(z));
    cleanSumFields();
  }

  function getVal(id) { var el = document.getElementById(id); return el ? el.value : ''; }
  function setVal(id, v) { var el = document.getElementById(id); if (el) el.value = v; }
  function parseNum(s) { s = String(s == null ? '' : s).trim().replace(',', '.'); var n = parseFloat(s); return isNaN(n) ? 0 : n; }
  function fmtNum(v) { v = parseFloat(v) || 0; var s = v.toFixed(2).replace(/\.?0+$/, ''); return s; }
  function normHhmm(s) { s = String(s == null ? '' : s).trim(); var m = s.match(/^(\d{1,2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }
  function hhmmToNum(s) { var m = normHhmm(s); if (!m) return 0; var p = m.split(':'); return (+p[0]) * 60 + (+p[1]); }
  function weekendCount(b, v) {
    if (!b || !v) return 0;
    var t = new Date(String(b).replace(/(\d{4})-(\d{2})-(\d{2}).*/, '$1/$2/$3'));
    var e = new Date(String(v).replace(/(\d{4})-(\d{2})-(\d{2}).*/, '$1/$2/$3'));
    if (isNaN(t.getTime()) || isNaN(e.getTime()) || e < t) return 0;
    var n = 0;
    for (var d = new Date(t); d <= e; d.setDate(d.getDate() + 1)) { var w = d.getDay(); if (w === 0 || w === 6) n++; }
    return n;
  }
  /* Число выходных среди последних nDays дней, заканчивающихся на endYmd.
     Используется для «дней сверх месяцев» (избыток): какие из них выходные. */
  function weekendCountEnd(endYmd, nDays) {
    var m = String(endYmd || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    if (!m || !nDays) return 0;
    var end = new Date(+m[1], +m[2] - 1, +m[3]);
    if (isNaN(end.getTime())) return 0;
    var n = 0;
    for (var i = 0; i < nDays; i++) { var d = new Date(end); d.setDate(end.getDate() - i); var w = d.getDay(); if (w === 0 || w === 6) n++; }
    return n;
  }
  function currentProduct() { var pid = parseInt(getVal('arenda2-product'), 10); return map[pid] || null; }

  /* Умный разбор времени — как norm_time_smart в price_form.php:
   *   5 -> 05:00 | 930 -> 09:30 | 1230 -> 12:30 | 9:15 -> 09:15 */
  function normTimeSmart(v) {
    v = String(v == null ? '' : v).trim();
    if (v === '') return '';
    var m = v.match(/^(\d{1,2}):([0-5]\d)(?::([0-5]\d))?/);
    if (m) { var h0 = +m[1]; if (h0 > 23) return ''; return (h0 < 10 ? '0' : '') + h0 + ':' + m[2]; }
    if (!/^\d+$/.test(v)) return '';
    var h = 0, mi = 0, L = v.length;
    if (L <= 2)      { h = +v; }
    else if (L === 3){ h = +v[0]; mi = +v.substr(1); }
    else             { h = +v.substr(0, 2); mi = +v.substr(2); }
    if (h > 23 || mi > 59) return '';
    return (h < 10 ? '0' : '') + h + ':' + (mi < 10 ? '0' : '') + mi;
  }
  function dispTime(v) {
    var t = String(v == null ? '' : v).trim();
    var m = t.match(/^0?(\d{1,2}):(\d{2})/);
    return m ? (+m[1]) + ':' + m[2] : t;
  }

  /* ===== Функции проверки и расчёта ===== */
  function check_bronir(callback) {
    var pid = parseInt(getVal('arenda2-product'), 10);
    if (!pid) { if (callback) callback(false); return; }
    var vozFlag = document.getElementById('arenda2-voz-flag') && document.getElementById('arenda2-voz-flag').checked ? 1 : 0;
    var noquantFlag = parseInt(getVal('arenda2-service-flag'), 10) ? 0 : 0;
    var nocalcFlag = 0;
    var p = map[pid];
    if (p) { noquantFlag = p.noquant_flag || 0; nocalcFlag = p.nocalc_flag || 0; }

    var fd = new FormData();
    fd.set('action', 'check_bronir');
    fd.set('docum_id', getVal('arenda2-docum-id') || '0');
    fd.set('product_id', pid);
    fd.set('date_beg', getVal('arenda2-date-beg'));
    fd.set('time_beg', getVal('arenda2-time-beg'));
    fd.set('date_voz', getVal('arenda2-date-voz'));
    fd.set('time_voz', getVal('arenda2-time-voz'));
    fd.set('quant', getVal('arenda2-quant') || '0');
    fd.set('voz_flag', vozFlag);
    fd.set('noquant_flag', noquantFlag);
    fd.set('nocalc_flag', nocalcFlag);
    fd.set('days', getVal('arenda2-days') || '0');

    fetch('arenda2_check.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.blocked) {
          alert(data.message || 'Товар недоступен');
          if (data.clear_product) {
            setVal('arenda2-product', '0');
            var nameEl = document.getElementById('arenda2-product-label');
            if (nameEl) nameEl.value = '';
            setVal('arenda2-code', '');
            setVal('arenda2-price-id', '0');
          }
          if (data.quant !== undefined) {
            setVal('arenda2-quant', data.quant);
          }
        }
        if (callback) callback(!!data.blocked);
      })
      .catch(function() { if (callback) callback(false); });
  }

  /* Пересчёт сумм: по спецификации (111.txt, calc_sum) */
  var fixedMode = getVal('arenda2-fixed-flag') === '1';   /* выбран фиксированный тариф из списка */
  var manualFixed = false;
  var prplanUserOverride = false;   /* пользователь вручную выбрал «Тарифный план» — не переопределять по сезону */

  function calc_sum(callback) {
    var p = currentProduct();
    var disc   = parseNum(getVal('arenda2-discount'));
    var quant  = parseNum(getVal('arenda2-quant')); if (String(getVal('arenda2-quant')).trim() === '') quant = 1;
    var days   = parseInt(getVal('arenda2-days') || '0', 10) || 0;
    var months = parseInt(getVal('arenda2-months') || '0', 10) || 0;
    var hrs    = SHOW_HOURS ? hhmmToNum(getVal('arenda2-hours')) / 60 : 0;

    var pH    = parseNum(getVal('arenda2-price-hour'));
    var pD    = parseNum(getVal('arenda2-price-day'));
    /* В ручном режиме тарифов выходной день = дневному тарифу (нет отдельного
     * ручного поля price_we). Иначе — price_we из price/БД, с запасным = price_day. */
    var pW    = MANUAL_FLAG ? pD : (parseNum(getVal('arenda2-price-we')) || pD);
    if (MANUAL_FLAG) setVal('arenda2-price-we', pD ? fmtNum(pD) : '');
    var pFix  = parseNum(getVal('arenda2-price-fix'));
    var pM    = parseNum(getVal('arenda2-price-month'));

    var sum = 0, sd = 0;
    if (FIXED_FLAG || fixedMode) {
      var fix = pFix ? pFix : pD;
      sum = fix * (1 - disc / 100);
      sd  = fix * (disc / 100);
    } else {
      /* Сумма = сумма за часы + сумма за дни (+выходные) + сумма за месяцы */
      var monthsActive = months > 0 && pM > 0;
      var we, wd;
      if (monthsActive) {
        /* days — это избыток дней сверх полных месяцев: выходные считаем
           только среди последних «days» дней, заканчивающихся на date_voz */
        we = weekendCountEnd(getVal('arenda2-date-voz'), days);
        wd = days - we; if (wd < 0) wd = 0;
      } else {
        we = weekendCount(getVal('arenda2-date-beg'), getVal('arenda2-date-voz'));
        wd = days - we; if (wd < 0) wd = 0;
      }
      var daysPart  = pD * wd + pW * we;
      var hoursPart = pH * hrs;
      var monthsPart = 0;
      if (monthsActive) {
        /* При возврате (voz_flag=1) месяцы считаются пропорционально
           фактическому сроку: months_used = days_used/30 */
        var mUsed = months;
        var vozEl = document.getElementById('arenda2-voz-flag');
        if (vozEl && vozEl.checked) {
          var ud = parseInt(getVal('arenda2-used-days') || '0', 10) || 0;
          if (ud > 0) mUsed = ud / 30;
        }
        monthsPart = pM * mUsed;
      }
      var gross = daysPart + hoursPart + monthsPart;
      sum = gross * (1 - disc / 100);
      sd  = gross * (disc / 100);
      if (sum < 0) sum = 0; if (sd < 0) sd = 0;
    }
    if (p && !p.noquant_flag) { sum *= quant; sd *= quant; }
    /* НДС: если установлен nds_rate и он не «внутри» цены (no_nds=0) — начислить сверху */
    if (NDS_RATE > 0 && NO_NDS !== 1) { sum *= (1 + NDS_RATE / 100); }
    setVal('arenda2-sum', fmtNum(sum));
    setVal('arenda2-sum-discount', fmtNum(sd));
    cleanSumFields();
    if (callback) callback({ ok: true, sum: sum, sum_discount: sd });
  }

  /* Подбор тарифа и залога (по спецификации) */
  function get_price(callback) {
    if (typeof callback !== 'function') callback = function () {};
    var pid = parseInt(getVal('arenda2-product'), 10);
    if (!pid) { callback({ ok: false }); return; }
    if (MANUAL_FLAG) { calc_sum(); callback({ ok: true }); return; }

    var fd = new FormData();
    fd.set('action', 'get_price');
    fd.set('docum_id', getVal('arenda2-docum-id') || '0');
    fd.set('product_id', String(pid));
    fd.set('prplan_id', getVal('arenda2-prplan') || '1');
    fd.set('prplan_manual', prplanUserOverride ? '1' : '0');
    fd.set('hours', getVal('arenda2-hours'));
    fd.set('days', getVal('arenda2-days') || '0');
    fd.set('months', getVal('arenda2-months') || '0');
    fd.set('date_beg', getVal('arenda2-date-beg'));
    fd.set('quant', getVal('arenda2-quant') || '1');
    fd.set('discount', getVal('arenda2-discount') || '0');

    fetch('arenda2_check.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!(d && d.ok)) { callback(d); return; }
        setVal('arenda2-price-hour', fmtNum(d.price_hour));
        setVal('arenda2-price-day', fmtNum(d.price_day));
        setVal('arenda2-price-we', fmtNum(d.price_we));
        setVal('arenda2-price-fix', fmtNum(d.price_fix));
        setVal('arenda2-price-month', fmtNum(d.price_month));
        sumZalogBase = parseNum(d.sum_zalog);
        applySumZalog();
        if (d.prplan_id && !prplanUserOverride) { var sel = document.getElementById('arenda2-prplan'); if (sel) sel.value = String(d.prplan_id); }
        rebuildPriceSelect(d.prices || [], d.tariff_id, d.tariff_name);
        calc_sum();
        callback(d);
      })
      .catch(function (err) {
        alert('Ошибка подбора тарифа: ' + ((err && err.message) ? err.message : err) + ' (см. консоль F12)');
        if (window.console) console.error('get_price error', err);
        callback({ ok: false });
      });
  }
  /* ========================================================== */

  /* Список «Название тарифа»: все тарифы; выбираемы только фиксированные */
  function rebuildPriceSelect(prices, autoId, autoName) {
    var sel = document.getElementById('arenda2-price-id');
    if (!sel) return;
    var aid = parseInt(autoId, 10) || 0;
    sel.innerHTML = '';
    sel.add(new Option('—', '0'));
    (prices || []).forEach(function (f) {
      var o = new Option(f.name || ('#' + f.id), String(f.id));
      if (f.fixed_flag == 1) {
        o.setAttribute('data-fixed', '1');
        o.setAttribute('data-bdays', String(f.bdays || 0));
        o.setAttribute('data-edays', String(f.edays || 0));
        o.setAttribute('data-bmonths', String(f.bmonths || 0));
        o.setAttribute('data-emonths', String(f.emonths || 0));
        o.setAttribute('data-btime', f.btime || '');
        o.setAttribute('data-etime', f.etime || '');
        o.setAttribute('data-price', String(f.price || 0));
        o.setAttribute('data-hprice', String(f.hprice || 0));
        o.setAttribute('data-mprice', String(f.mprice || 0));
      } else {
        o.disabled = true;   /* видно, но выбрать нельзя */
      }
      sel.add(o);
    });
    var hasAuto = false;
    for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value == aid) { hasAuto = true; break; } }
    if (aid > 0 && !hasAuto) sel.add(new Option(autoName || ('#' + aid), String(aid)));
    if (aid > 0) {
      for (var j = 0; j < sel.options.length; j++) { if (sel.options[j].value == aid) { sel.selectedIndex = j; break; } }
    } else {
      sel.selectedIndex = 0;
    }
  }

  /* Выбор пользователем фиксированного тарифа из «Названия тарифа» */
  function applyFixedTariffFromSelect() {
    var sel = document.getElementById('arenda2-price-id');
    var opt = sel && sel.options[sel.selectedIndex];
    if (!opt) return;
    if (opt.getAttribute('data-fixed') !== '1') {
      /* «—» или авто-тариф: выйти из фиксированного режима */
      manualFixed = false;
      fixedMode = false;
      setVal('arenda2-fixed-flag', '0');
      setVal('arenda2-price-fix', '0');
      calc_sum();
      syncFixedPeriodLock();
      return;
    }
    manualFixed = true;
    fixedMode = true;
    setVal('arenda2-fixed-flag', '1');

    var bdays   = parseInt(opt.getAttribute('data-bdays') || '0', 10) || 0;
    var edays   = parseInt(opt.getAttribute('data-edays') || '0', 10) || 0;
    var emonths = parseInt(opt.getAttribute('data-emonths') || '0', 10) || 0;
    var etime   = (opt.getAttribute('data-etime') || '').trim();
    var price   = parseNum(opt.getAttribute('data-price'));
    var hprice  = parseNum(opt.getAttribute('data-hprice'));
    var mprice  = parseNum(opt.getAttribute('data-mprice'));

    if (mprice > 0 || emonths > 0) {
      /* месячный фиксированный: months из записи, date_voz = beg + months,
         days = 0 (избыток), hours не затираем */
      var mo = emonths > 0 ? emonths : 1;
      setVal('arenda2-months', String(mo));
      setVal('arenda2-days', '0');
      setVal('arenda2-price-fix', fmtNum(mprice || price));
    } else if (hprice > 0) {
      /* часовой фиксированный: hours из записи, days/months = 0 */
      var h = normTimeSmart(etime) || '01:00';
      setVal('arenda2-hours', dispTime(h));
      setVal('arenda2-days', '');
      setVal('arenda2-months', '');
      setVal('arenda2-price-fix', fmtNum(hprice));
    } else {
      /* дневной фиксированный: days из записи (конец диапазона), hours/months = 0 */
      var d = edays > 0 ? edays : (bdays > 0 ? bdays : 1);
      setVal('arenda2-days', String(d));
      setVal('arenda2-hours', '');
      setVal('arenda2-months', '');
      setVal('arenda2-price-fix', fmtNum(price));
    }
    applyPeriodEnd();
    calc_sum();
    syncFixedPeriodLock();
  }

  /* Скрыть/заблокировать поля period при выборе фиксированного тарифа */
  function syncFixedPeriodLock() {
    var fixed = getVal('arenda2-fixed-flag') === '1';
    var rows = document.querySelectorAll('.arenda2-period-row');
    rows.forEach(function (row) {
      row.style.display = fixed ? 'none' : '';
    });
    var spinBtns = document.querySelectorAll('.arenda2-form .spin-btn');
    spinBtns.forEach(function (btn) {
      btn.disabled = fixed;
    });
  }

  /* Пересчёт date_voz/time_voz:
     date_voz = date_beg + число дней за months + days ; time_voz = time_beg + hours */
  function applyPeriodEnd() {
    var bd = String(getVal('arenda2-date-beg') || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    if (!bd) return;
    var bt = String(getVal('arenda2-time-beg') || '').match(/(\d{1,2}):(\d{2})/);
    var bh = bt ? +bt[1] : 0, bm = bt ? +bt[2] : 0;
    var end = new Date(+bd[1], +bd[2] - 1, +bd[3], bh, bm, 0);
    if (isNaN(end.getTime())) return;
    var months = parseInt(getVal('arenda2-months') || '0', 10) || 0;
    var days   = parseInt(getVal('arenda2-days') || '0', 10) || 0;
    var hours  = hhmmToNum(getVal('arenda2-hours'));
    if (months > 0) end.setDate(end.getDate() + daysForMonths(getVal('arenda2-date-beg'), months));
    if (days > 0) end.setDate(end.getDate() + days);
    if (hours > 0) end.setTime(end.getTime() + hours * 60000);
    var p2 = function (n) { return (n < 10 ? '0' : '') + n; };
    setVal('arenda2-date-voz', p2(end.getFullYear()) + '-' + p2(end.getMonth() + 1) + '-' + p2(end.getDate()));
    setVal('arenda2-time-voz', p2(end.getHours()) + ':' + p2(end.getMinutes()));
  }
  /* ========================================================== */

  function showQuantRow(show) {
    var quantInput = document.getElementById('arenda2-quant');
    if (!quantInput) return;
    quantInput.style.visibility = show ? '' : 'hidden';
    var spin = document.querySelectorAll('button[data-target="arenda2-quant"]');
    for (var s = 0; s < spin.length; s++) spin[s].style.visibility = show ? '' : 'hidden';
    var rows = quantInput.closest('table').querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) {
      var cells = rows[i].querySelectorAll('td.form-label');
      for (var j = 0; j < cells.length; j++) {
        if (cells[j].textContent.indexOf('Количество') !== -1) {
          cells[j].style.visibility = show ? '' : 'hidden';
          var phs = cells[j].querySelectorAll('.spin-btn-ph');
          for (var p = 0; p < phs.length; p++) phs[p].style.visibility = show ? '' : 'hidden';
          return;
        }
      }
    }
  }

  function onProductChange() {
    var pid = parseInt(getVal('arenda2-product'), 10);
    var p = map[pid];
    if (!p) return;

    prplanUserOverride = false;   /* новый товар — автоподбор плана по сезону заново */

    manualFixed = false;
    fixedMode = false;
    setVal('arenda2-fixed-flag', '0');
    setVal('arenda2-price-fix', '');

    setVal('arenda2-code', p.code || '');
    setVal('arenda2-service-flag', p.service_flag || 0);
    setVal('arenda2-nocalc-flag', p.nocalc_flag || 0);
    setVal('arenda2-price-hour', p.price_hour || '');
    setVal('arenda2-price-day', p.price_day || '');
    setVal('arenda2-price-we', '');
    setVal('arenda2-price-month', p.price_month || '');

    showQuantRow(!p.noquant_flag);
    /* При выборе товара вычисляем дату/время возврата по заданному периоду
     * (дни/часы и месяцы), чтобы они были заполненными. */
    applyPeriodEnd();
    check_bronir(function () {
      if (getVal('arenda2-product') !== '0' && getVal('arenda2-product') !== '') get_price();
    });
  }

  /* Проверка доступности товара и затем подбор тарифа (по спецификации:
   * при изменении Часов/Дней/Месяцев сначала check_bronir, потом get_price). */
  function checkThenGetPrice() {
    if (!getVal('arenda2-product') || getVal('arenda2-product') === '0') return;
    check_bronir(function () { get_price(); });
  }

  /* Видимость поля тарифа — только если соответствующее поле периода не равно нулю */
  function syncTariffVisibility() {
    var pairs = [
      ['arenda2-hours',   'arenda2-price-hour'],
      ['arenda2-days',    'arenda2-price-day'],
      ['arenda2-months',  'arenda2-price-month']
    ];
    pairs.forEach(function (pair) {
      var perEl = document.getElementById(pair[0]);
      var priEl = document.getElementById(pair[1]);
      if (!perEl || !priEl) return;
      var show = false;
      if (pair[0] === 'arenda2-hours') {
        show = hhmmToNum(perEl.value) > 0;
      } else {
        show = (parseInt(perEl.value || '0', 10) || 0) > 0;
      }
      /* показываем/скрываем поле тарифа и его метку вместе */
      priEl.style.display = show ? '' : 'none';
      var lab = document.getElementById(pair[1] + '-label');
      if (lab) lab.style.display = show ? '' : 'none';
    });
  }

  function onHoursChange() {
    if (manualFixed) { calc_sum(); return; }   /* выбран фиксированный тариф — не переподбирать */
    applyPeriodEnd();                          /* time_voz = time_beg + hours */
    syncTariffVisibility();
    checkThenGetPrice();
  }
  function onDaysChange() {
    if (manualFixed) { calc_sum(); return; }
    applyPeriodEnd();                          /* date_voz = date_beg + days */
    syncTariffVisibility();
    checkThenGetPrice();
  }
  /* date_voz = date_beg + daysForMonths(months) + days.
     daysForMonths считается КАЛЕНДАРНО (с переходом на следующий год). */
  function fmtYmd(d) { var p = function (n) { return (n < 10 ? '0' : '') + n; }; return p(d.getFullYear()) + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()); }
  /* Число дней, приходящихся на указанное количество полных месяцев от даты beg
     (календарно, с переходом на следующий год). */
  function daysForMonths(beg, months) {
    var m = String(beg || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    if (!m || !months) return 0;
    var b = new Date(+m[1], +m[2] - 1, +m[3]);
    var e = new Date(+m[1], +m[2] - 1 + months, +m[3]);
    return Math.round((e.getTime() - b.getTime()) / 86400000);
  }
  /* Разбить период (date_beg..date_voz) на полные месяцы и избыток дней:
     months = число полных месяцев за период,
     days   = (date_voz - date_beg) - число дней в этих полных месяцах. */
  function splitPeriod(begYmd, endYmd) {
    var b = String(begYmd || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    var v = String(endYmd || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    if (!b || !v) return { months: 0, days: 0 };
    var begD = new Date(+b[1], +b[2] - 1, +b[3]).getTime();
    var endD = new Date(+v[1], +v[2] - 1, +v[3]).getTime();
    if (endD < begD) return { months: 0, days: 0 };
    var months = (+v[1] - +b[1]) * 12 + (+v[2] - +b[2]);
    var anchor = new Date(+b[1], +b[2] - 1 + months, +b[3]).getTime();
    if (anchor > endD) { months--; anchor = new Date(+b[1], +b[2] - 1 + months, +b[3]).getTime(); }
    if (months < 0) months = 0;
    var days = Math.round((endD - anchor) / 86400000);
    if (days < 0) days = 0;
    return { months: months, days: days };
  }
  /* Есть месячная цена? (в product или в выбранном тарифе price) и включено поле Месяцев */
  function hasMonthly() {
    if (SHOW_MONTHS !== 1) return false;
    var p = currentProduct();
    var pm = parseNum(p && p.price_month) || parseNum(getVal('arenda2-price-month'));
    return pm > 0;
  }


  function onMonthsChange() {
    if (manualFixed) { calc_sum(); return; }
    var m = parseInt(getVal('arenda2-months') || '0', 10) || 0;
    if (m <= 0) {
      setVal('arenda2-days', '');              /* интерактивный сброс месяцев → очистить дни/часы */
      setVal('arenda2-hours', '');
    }
    syncTariffVisibility();
    applyPeriodEnd();                          /* date_voz = beg + дни за months + days */
    checkThenGetPrice();
  }
  /* Изменение даты возврата: days = date_voz - date_beg, затем check_bronir() и get_price().
   * Настройка «Не изменять Дней при досрочном возврате» относится ТОЛЬКО к возврату
   * (включению voz_flag), а не к ручному изменению date_voz. */
  function onDateVozChange() {
    if (manualFixed) { calc_sum(); return; }
    var m = parseInt(getVal('arenda2-months') || '0', 10) || 0;
    var b = getVal('arenda2-date-beg');
    var v = getVal('arenda2-date-voz');
    if (hasMonthly() && b && v) {
      /* days = избыток дней сверх полных месяцев:
         months = полные месяцы за период, days = (voz - beg) - дни в этих месяцах */
      var sp = splitPeriod(b, v);
      setVal('arenda2-months', String(sp.months));
      setVal('arenda2-days', sp.days > 0 ? String(sp.days) : '0');
    } else if (m === 0 && b && v) {
      var bm = b.match(/(\d{4})-(\d{2})-(\d{2})/), vm = v.match(/(\d{4})-(\d{2})-(\d{2})/);
      if (bm && vm) {
        var days = Math.round((new Date(+vm[1], +vm[2] - 1, +vm[3]).getTime() - new Date(+bm[1], +bm[2] - 1, +bm[3]).getTime()) / 86400000);
        setVal('arenda2-days', days > 0 ? String(days) : '');
      }
    }
    syncTariffVisibility();
    checkThenGetPrice();
  }
  /* Изменение времени возврата: hours = time_voz - time_beg (при отрицательном
   * результате + сутки), затем check_bronir() и get_price(). */
  function onTimeVozChange() {
    if (manualFixed) { calc_sum(); return; }
    if (SHOW_HOURS) {
      var tb = getVal('arenda2-time-beg');
      var tv = getVal('arenda2-time-voz');
      if (tb && tv) {
        var diff = hhmmToNum(tv) - hhmmToNum(tb);   /* в минутах */
        if (diff < 0) diff += 1440;                 /* + сутки (24*60) */
        var hh = Math.floor(diff / 60), mm = diff % 60;
        setVal('arenda2-hours', dispTime((hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm));
      }
    }
    checkThenGetPrice();
  }
  function addDaysYmd(ymd, n) {
    var m = String(ymd || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return '';
    return fmtYmd(new Date(+m[1], +m[2] - 1, +m[3] + (n || 0)));
  }
  function minsToDisp(min) { min = ((min % 1440) + 1440) % 1440; var h = Math.floor(min / 60), mm = min % 60; return dispTime((h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm); }
  function curShifted() {
    var s = new Date(Date.now() + (hhmmToNum(TIME_SHIFT || '00:00')) * 60000);
    return { date: fmtYmd(s), min: s.getHours() * 60 + s.getMinutes() };
  }
  /* Включение/выключение «Возвращено» (voz_flag) — алгоритм 111.txt:644-683 */
  function onVozFlagChange() {
    var el = document.getElementById('arenda2-voz-flag');
    var on = !!(el && el.checked);
    var cs = curShifted();
    var begDate = getVal('arenda2-date-beg');
    var begTime = getVal('arenda2-time-beg') || '00:00';
    var bm = String(begDate || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    var bt = String(begTime).match(/(\d{1,2}):(\d{2})/);
    var issue = bm ? new Date(+bm[1], +bm[2] - 1, +bm[3], bt ? +bt[1] : 0, bt ? +bt[2] : 0, 0).getTime() : 0;

    if (on) {
      if (Date.now() > issue) {   /* только если текущее время больше времени выдачи */
        var N = NO_REFUND === 1;   /* "Не возвращать деньги при досрочном возврате" */
        var K = KEEP_DAYS_ON_EARLY_RETURN === 1;  /* "Не изменять Дней..." */
        var monthsVal = parseInt(getVal('arenda2-months') || '0', 10) || 0;
        if (monthsVal > 0) {
          /* Возврат при месячных. Если включено «Не изменять Дней при досрочном возврате» —
             месяцы не уменьшаем; иначе — пропорционально фактическому сроку (дни/30).
             days — избыток дней сверх полных месяцев (по фактическому сроку). */
          if (K !== 1) {
            var spR = splitPeriod(begDate, cs.date);
            setVal('arenda2-used-days', String(spR.months * 30 + spR.days));
            setVal('arenda2-months', String(spR.months));
            setVal('arenda2-days', String(spR.days));
          } else {
            setVal('arenda2-used-days', '0');   /* месяцы не уменьшаются */
          }
          setVal('arenda2-date-voz', cs.date);
          setVal('arenda2-time-voz', minsToDisp(cs.min));
        } else {
          /* months==0: days = today - date_beg (фактический срок до возврата),
             hours = now+shift - time_beg (если сейчас позже времени выдачи).
             date_voz/time_voz = текущие. Согласовано с серверным
             arenda2_apply_voz_to_item (KeepDaysOnEarlyReturn=0). */
          var days = (function () {
            var m1 = String(begDate || '').match(/(\d{4})-(\d{2})-(\d{2})/);
            var mc = String(cs.date).match(/(\d{4})-(\d{2})-(\d{2})/);
            if (!m1 || !mc) return 0;
            var d = Math.round((new Date(+mc[1], +mc[2] - 1, +mc[3]).getTime() - new Date(+m1[1], +m1[2] - 1, +m1[3]).getTime()) / 86400000);
            return d < 0 ? 0 : d;
          })();
          setVal('arenda2-days', String(days));
          if (SHOW_HOURS) {
            var begMin = hhmmToNum(begTime);
            if (String(begDate || '') < cs.date || (String(begDate || '') === cs.date && cs.min > begMin)) {
              var h = cs.min - begMin;
              if (h < 0) h += 1440;
              setVal('arenda2-hours', minsToDisp(h));
            }
            else setVal('arenda2-hours', '');
          }
          setVal('arenda2-date-voz', cs.date);
          setVal('arenda2-time-voz', minsToDisp(cs.min));
        }
      }
    } else {
      /* Выключение: date_voz = date_beg + days; time_voz = time_beg + hours */
      var daysI = parseInt(getVal('arenda2-days') || '0', 10) || 0;
      var hrsI = hhmmToNum(getVal('arenda2-hours'));
      var total = hhmmToNum(begTime) + hrsI;
      var extraD = Math.floor(total / 1440), rm = total % 1440;
      setVal('arenda2-date-voz', addDaysYmd(begDate, daysI + extraD));
      setVal('arenda2-time-voz', minsToDisp(rm));
    }
    syncTariffVisibility();
    if (getVal('arenda2-product') && getVal('arenda2-product') !== '0') get_price();
  }
  function onPrplanChange() {
    prplanUserOverride = true;
    manualFixed = false;
    fixedMode = false;
    setVal('arenda2-fixed-flag', '0');
    setVal('arenda2-price-fix', '0');
    if (getVal('arenda2-product') && getVal('arenda2-product') !== '0') get_price();
  }
  function onQuantChange()  { check_bronir(function () { calc_sum(); applySumZalog(); }); }

  var productInput = document.getElementById('arenda2-product');
  if (productInput) { productInput.addEventListener('change', onProductChange); productInput.addEventListener('input', onProductChange); }

  var bindInput = function (id, fn) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener('change', fn); el.addEventListener('input', fn); }
  };
  bindInput('arenda2-quant', onQuantChange);
  bindInput('arenda2-days', onDaysChange);
  bindInput('arenda2-hours', onHoursChange);
  bindInput('arenda2-months', onMonthsChange);
  bindInput('arenda2-discount', function () { calc_sum(); });
  bindInput('arenda2-date-voz', onDateVozChange);
  bindInput('arenda2-time-voz', onTimeVozChange);
  bindInput('arenda2-prplan', onPrplanChange);
  /* Ручной режим тарифов (ManualTariffFlag=1): при изменении любого тарифа — пересчёт стоимости */
  bindInput('arenda2-price-hour', function () { calc_sum(); });
  bindInput('arenda2-price-day', function () { calc_sum(); });
  bindInput('arenda2-price-month', function () { calc_sum(); });

  var priceSel = document.getElementById('arenda2-price-id');
  if (priceSel) priceSel.addEventListener('change', applyFixedTariffFromSelect);

  var vozChk = document.getElementById('arenda2-voz-flag');
  if (vozChk) vozChk.addEventListener('change', onVozFlagChange);

  /* Форматирование часов «как в price_form.php» при выходе из поля:
   * 2 -> 2:00 | 930 -> 9:30 | 1230 -> 12:30 | 9:15 -> 9:15 */
  var hoursEl = document.getElementById('arenda2-hours');
  if (hoursEl) hoursEl.addEventListener('blur', function () {
    var n = normTimeSmart(hoursEl.value);
    if (n) {
      hoursEl.value = dispTime(n);
      hoursEl.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  if (productInput && parseInt(getVal('arenda2-product'), 10) > 0) {
    var p0 = map[parseInt(getVal('arenda2-product'), 10)];
    if (p0) showQuantRow(!p0.noquant_flag);
    applyPeriodEnd();
    /* При открытии (редактирование/копия) подтягиваем тариф «вживую»:
     * это гарантирует корректное название тарифа, даже если серверный рендер
     * остался со старым значением (price_id=0 или устаревший кэш). */
    if (getVal('arenda2-fixed-flag') !== '1') get_price();
  }
  syncTariffVisibility();
  syncFixedPeriodLock();
  function cleanSumFields() {
    ['arenda2-sum', 'arenda2-sum-discount', 'arenda2-sum-zalog'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el && (el.value === '0' || el.value === '0.00' || el.value === '0,00')) el.value = '';
    });
  }
})();
/* Кнопки +/- (спин) для Количество/Часов/Дней/Месяцев — глобальное делегирование.
   Обработчик вешается на document ОДИН раз. Форма пере-выполняется через eval при
   каждом открытии модалки, поэтому без guard слушатель накапливался и кнопка
   срабатывала несколько раз (значение менялось сразу на 2–3). */
if (!window.__arenda2SpinBound) {
  window.__arenda2SpinBound = true;
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.spin-btn') : null;
    if (!b) return;
    var el = document.getElementById(b.getAttribute('data-target'));
    if (!el) return;
    var dir = b.getAttribute('data-spin') === 'up' ? 1 : -1;
    var step = parseFloat(b.getAttribute('data-step') || '1') * dir;
    if (b.getAttribute('data-is-hour') === '1') {
      var m = String(el.value || '').match(/^(\d{1,2}):(\d{2})/);
      var min = m ? (+m[1]) * 60 + (+m[2]) : 0;
      min += step; if (min < 0) min = 0;
      var hh = Math.floor(min / 60), mm = min % 60;
      el.value = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
    } else {
      var n = parseFloat(String(el.value || '0').replace(',', '.')) || 0;
      n += step; if (n < 0) n = 0;
      el.value = String(parseFloat(n.toFixed(3)));
    }
    el.dispatchEvent(new Event('change', { bubbles: true }));
  });
}
</script>

<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode]);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"/><title><?= h($pageTitle) ?></title><link rel="stylesheet" href="app.css"/></head>
<body><div class="page page--form"><?= $formHtml ?></div></body></html>
