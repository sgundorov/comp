<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/invoice_columns.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';
$values = [
    'number'       => '',
    'date'         => '',
    'time'         => '',
    'client_id'    => 0,
    'state'        => 'Черновик',
    'store_id'     => 0,
    'zakaz_num'    => '',
    'zakaz_id'     => 0,
    'zakaz_type'   => '',
    'payment_type' => 'Наличные',
    'discount'     => '0.000',
    'sum_discount' => '0.00',
    'sum'          => '0.00',
    'sum_nds'      => '0.00',
    'sum_plat'     => '0.00',
    'date_plat'    => '',
    'sotr_id'      => 0,
    'pos'          => 0,
    'note'         => '',
];

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT number, date, time, client_id, state, store_id, zakaz_num, zakaz_id, zakaz_type, payment_type, discount, sum_discount, sum, sum_nds, sum_plat, date_plat, sotr_id, pos, note FROM invoice WHERE invoice_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $values['number']       = (int)$r['number'] > 0 ? (string)(int)$r['number'] : '';
        $values['date']         = (string)$r['date'];
        $values['time']         = (string)$r['time'];
        $values['client_id']    = (int)$r['client_id'];
        $values['state']        = (string)$r['state'];
        $values['store_id']     = (int)$r['store_id'];
        $values['zakaz_num']    = (string)$r['zakaz_num'];
        $values['zakaz_id']     = (int)$r['zakaz_id'];
        $values['zakaz_type']   = (int)$r['zakaz_type'];
        $values['payment_type'] = (string)$r['payment_type'];
        $values['discount']     = (string)$r['discount'];
        $values['sum_discount'] = (string)$r['sum_discount'];
        $values['sum']          = (string)$r['sum'];
        $values['sum_nds']      = (string)$r['sum_nds'];
        $values['sum_plat']     = (string)$r['sum_plat'];
        $values['date_plat']    = (string)$r['date_plat'];
        $values['sotr_id']      = (int)$r['sotr_id'];
        $values['pos']          = (int)$r['pos'];
        $values['note']         = (string)$r['note'];
    }
}

if ($mode === 'new' || $mode === 'copy') {
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM invoice WHERE doctype_id = 10");
    if ($nr && ($nrow = $nr->fetch_assoc())) $values['number'] = (int)$nrow['next_num'];
}
if ($mode === 'new') {
    $values['store_id'] = $CurStoreID;
    $values['sotr_id']  = $CurSotrID;
    if (!empty($_GET['client_id'])) $values['client_id'] = (int)$_GET['client_id'];
}
if ($mode === 'copy') {
    $values['date']     = date('Y-m-d');
    $values['time']     = date('H:i:s');
    $values['sum_plat'] = '0';
    $values['state']    = 'Выставлен';
}

// --- invoice2 items (pre-load for copy POST handler) ---
$itemsList = [];
if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT invoice2_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note, guarantee, guarant_unit FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (($mode === 'edit' || $mode === 'copy') && $id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $rSum  = 0; $rSd = 0; $rNds = 0; $rPos = 0;
    foreach ($itemsList as $item) {
        $rSum  += (float)$item['sum'];
        $rSd   += (float)$item['sum_discount'];
        $rNds  += (float)$item['sum_nds'];
        $rPos  += 1;
    }
    $uStmt = $conn->prepare("UPDATE invoice SET sum = ?, sum_discount = ?, sum_nds = ?, pos = ? WHERE invoice_id = ?");
    bind_auto($uStmt, [$rSum, $rSd, $rNds, $rPos, $id]);
    $uStmt->execute();
    $uStmt->close();
    $values['sum']          = sprintf('%.2f', $rSum);
    $values['sum_discount'] = sprintf('%.2f', $rSd);
    $values['sum_nds']      = sprintf('%.2f', $rNds);
    $values['pos']          = $rPos;
}

// --- plat records for payment tab ---
$platList = [];
$zatIdForPlat = 0;
$zr = $conn->query("SELECT zat_id FROM zat WHERE name = 'Оплата счета' LIMIT 1");
if ($zr && ($zrow = $zr->fetch_assoc())) $zatIdForPlat = (int)$zrow['zat_id'];
if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $docType = 10;
    $stmtP = $conn->prepare("SELECT p.plat_id, p.datetime, p.sum_in, p.sum_out, p.sum, p.out_flag, p.plat_type, p.doc_id, p.note, c.name AS client_name, z.name AS zat_name FROM plat p LEFT JOIN client c ON c.client_id = p.client_id LEFT JOIN zat z ON z.zat_id = p.zat_id WHERE p.doc_id = ? AND p.doc_type = ? ORDER BY p.plat_id DESC");
    bind_auto($stmtP, [$id, $docType]);
    $stmtP->execute();
    $platList = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtP->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['number']       = (int)($_POST['number'] ?? 0);
    $values['date']         = trim((string)($_POST['date'] ?? ''));
    $values['time']         = trim((string)($_POST['time'] ?? ''));
    $values['client_id']    = (int)($_POST['client_id'] ?? 0);
    $values['state']        = (string)($_POST['state'] ?? 'Черновик');
    $values['store_id']     = (int)($_POST['store_id'] ?? 0);
    $values['payment_type'] = 'Наличные';
    $values['discount']     = str_replace(',', '.', trim((string)($_POST['discount'] ?? '0')));
    $values['sum_discount'] = str_replace(',', '.', trim((string)($_POST['sum_discount'] ?? '0')));
    $values['sum']          = str_replace(',', '.', trim((string)($_POST['sum'] ?? '0')));
    $values['sum_nds']      = str_replace(',', '.', trim((string)($_POST['sum_nds'] ?? '0')));
    $values['sum_plat']     = str_replace(',', '.', trim((string)($_POST['sum_plat'] ?? '0')));
    $values['date_plat']    = trim((string)($_POST['date_plat'] ?? ''));
    $values['sotr_id']      = (int)($_POST['sotr_id'] ?? 0);
    $values['pos']          = (int)($_POST['pos'] ?? 0);
    $values['note']         = trim((string)($_POST['note'] ?? ''));

    if ($values['date'] === '') {
        $errors[] = 'Поле «Дата» обязательно для заполнения.';
        $focusField = 'inv-date';
    }
    if ($values['client_id'] <= 0) {
        $errors[] = 'Поле «Контрагент» обязательно для заполнения.';
        $focusField = $focusField ?: 'client-id';
    }
    if ($values['store_id'] <= 0) {
        $errors[] = 'Поле «Участок» обязательно для заполнения.';
        $focusField = $focusField ?: 'store-id';
    }
    if ($values['sotr_id'] <= 0) {
        $errors[] = 'Поле «Сотрудник» обязательно для заполнения.';
        $focusField = $focusField ?: 'sotr-id';
    }

    $allowedStates = ['Черновик', 'Выставлен', 'Оплачен', 'Отменен'];
    if (!in_array($values['state'], $allowedStates, true)) {
        $errors[] = 'Некорректное состояние.';
    }
    if (empty($errors)) {
        $numVal = $values['number'] > 0 ? $values['number'] : 0;
        $disc = (float)$values['discount'];
        $sumDisc = (float)$values['sum_discount'];
        $sumVal = (float)$values['sum'];
        $sumNds = (float)$values['sum_nds'];
        $sumPlat = (float)$values['sum_plat'];

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM invoice WHERE invoice_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'invoice' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => '#' . $id]);
                exit;
            }
            header('Location: invoice.php');
            exit;
        }

        $maxRetries = 10;
        $saved = false;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($mode === 'new' || $mode === 'copy') {
                $stmt = $conn->prepare("INSERT INTO invoice (doctype_id, number, date, time, client_id, state, store_id, payment_type, discount, sum_discount, sum, sum_nds, sum_plat, date_plat, sotr_id, pos, note) VALUES (10, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['state'], $values['store_id'], $values['payment_type'], $disc, $sumDisc, $sumVal, $sumNds, $sumPlat, $values['date_plat'], $values['sotr_id'], $values['pos'], $values['note']]);
            } else {
                $stmt = $conn->prepare("UPDATE invoice SET number = ?, date = ?, time = ?, client_id = ?, state = ?, store_id = ?, payment_type = ?, discount = ?, sum_discount = ?, sum = ?, sum_nds = ?, sum_plat = ?, date_plat = ?, sotr_id = ?, pos = ?, note = ? WHERE invoice_id = ?");
                bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['state'], $values['store_id'], $values['payment_type'], $disc, $sumDisc, $sumVal, $sumNds, $sumPlat, $values['date_plat'], $values['sotr_id'], $values['pos'], $values['note'], $id]);
            }

            if ($stmt->execute()) {
                $saved = true;
                break;
            }

            if ($stmt->errno === 1062) {
                $stmt->close();
                $nr = $conn->query("SELECT COALESCE(MAX(number),0)+1 AS next_num FROM invoice WHERE doctype_id = 10");
                if ($nr && ($nrow = $nr->fetch_assoc())) {
                    $values['number'] = $numVal = (int)$nrow['next_num'];
                }
                continue;
            }

            $errors[] = 'Ошибка БД: ' . $stmt->error;
            $stmt->close();
            goto render;
        }

        if (!$saved) {
            $errors[] = 'Не удалось сохранить: превышено количество попыток.';
            $stmt->close();
            goto render;
        }

        $newId = ($mode === 'new' || $mode === 'copy') ? $conn->insert_id : $id;
        $stmt->close();

        if ($mode === 'copy' && $newId > 0 && !empty($itemsList)) {
            $cols = ['invoice_id', 'product_id', 'code', 'product_name', 'quant', 'price', 'discount', 'sum', 'sum_discount', 'sum_nds', 'note', 'guarantee', 'guarant_unit'];
            $ph = rtrim(str_repeat('?,', count($cols)), ',');
            $copyStmt = $conn->prepare("INSERT INTO invoice2 (" . implode(',', $cols) . ") VALUES ($ph)");
            $types = str_repeat('i', 2) . str_repeat('s', 2) . str_repeat('d', 6) . str_repeat('s', 3);
            foreach ($itemsList as $item) {
                $copyStmt->bind_param($types, $newId, $item['product_id'], $item['code'], $item['product_name'],
                    $item['quant'], $item['price'], $item['discount'],
                    $item['sum'], $item['sum_discount'], $item['sum_nds'],
                    $item['note'], $item['guarantee'], $item['guarant_unit']);
                $copyStmt->execute();
            }
            $copyStmt->close();
        }

        if ($newId > 0 && $mode !== 'delete') {
            $rStmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COALESCE(SUM(sum_nds),0), COUNT(*) FROM invoice2 WHERE invoice_id = ?");
            $rStmt->bind_param('i', $newId);
            $rStmt->execute();
            $rRow = $rStmt->get_result()->fetch_row();
            $rStmt->close();
            $rSum  = (float)$rRow[0];
            $rSd   = (float)$rRow[1];
            $rNds  = (float)$rRow[2];
            $rPos  = (int)$rRow[3];
            $uStmt = $conn->prepare("UPDATE invoice SET sum = ?, sum_discount = ?, sum_nds = ?, pos = ? WHERE invoice_id = ?");
            bind_auto($uStmt, [$rSum, $rSd, $rNds, $rPos, $newId]);
            $uStmt->execute();
            $uStmt->close();
        }

        if (!empty($_POST['auto_save'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        if ($isAjax) {
            $pageOfNew = 0;
            if (defined('PAGE_SIZE') && PAGE_SIZE > 0 && ($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'invoice', 'invoice_id', 'number', 'desc', $numVal, $newId, 'doctype_id = 10');
            }
            $resp = ['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew];
            if ($mode !== 'delete' && $newId > 0) {
                $resp['sum'] = $rSum;
                $resp['sum_discount'] = $rSd;
                $resp['sum_nds'] = $rNds;
                $resp['pos'] = $rPos;
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resp);
            exit;
        }
        header('Location: invoice.php');
        exit;
    }
}

render:
$clientList = [];
$crs = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$storeList = [];
$srs = $conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($srs) while ($sr = $srs->fetch_assoc()) $storeList[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrList = [];
$sotrs = $conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($sotrs) while ($sr = $sotrs->fetch_assoc()) $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

$currentClientName = '';
foreach ($clientList as $c) { if ($c['id'] === $values['client_id']) { $currentClientName = $c['name']; break; } }
$currentStoreName = '';
foreach ($storeList as $st) { if ($st['id'] === $values['store_id']) { $currentStoreName = $st['name']; break; } }
$currentSotrName = '';
foreach ($sotrList as $so) { if ($so['id'] === $values['sotr_id']) { $currentSotrName = $so['name']; break; } }

$titleNum = $values['number'] > 0 ? '№ ' . $values['number'] : '(новый)';
if ($mode === 'edit') {
    $pageTitle = 'Счет ' . $titleNum . ' (' . $currentClientName . ')';
} elseif ($mode === 'delete') {
    $pageTitle = 'Счет ' . $titleNum . ' (удаление)';
} elseif ($mode === 'new') {
    $pageTitle = 'Счет: новый';
} elseif ($mode === 'copy') {
    $pageTitle = 'Счет ' . $titleNum . ' (копия)';
} else {
    $pageTitle = 'Счёт';
}
$isReadonly = ($mode === 'delete');

$stateOptions = ['Черновик', 'Выставлен', 'Оплачен', 'Отменен'];

function dv($v) { return ((float)str_replace(',', '.', $v)) == 0 ? '' : (string)$v; }

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/schet.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="invoice_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'cli_name', '', ['id' => 'cli-name']) ?>
<?= render_input('hidden', 'store_name', '', ['id' => 'store-name']) ?>
<?= render_input('hidden', 'sotr_name', '', ['id' => 'sotr-name']) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Товары</div>
    <div class="tab-header" data-tab-index="2">Оплата</div>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr>
        <td class="form-label">Счет №</td>
        <td class="form-label">Дата<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'number', $values['number'] > 0 ? $values['number'] : '', [
                'id' => 'inv-number',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td><div style="display:flex;gap:10px"><?= render_input('date', 'date', $values['date'] ?: date('Y-m-d'), [
                'id' => 'inv-date',
                'required' => !$isReadonly,
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:128px',
            ]) ?><?= render_input('time', 'time', $values['time'] ?: date('H:i'), [
                'id' => 'inv-time',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></div></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Контрагент<span class="required">*</span></td>
      </tr>
      <tr>
        <td colspan="3"><?= render_lookup('client', 'client_id', $values['client_id'], $currentClientName,
                h(json_encode($clientList, JSON_UNESCAPED_UNICODE)),
                'client_form.php?mode=new', $isReadonly, ['id' => 'client-id', 'data-name-input' => 'cli-name']) ?></td>
      </tr>
      <tr>
        <td class="form-label">Скидка %</td>
        <td class="form-label">Сумма скидки</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><div style="display:flex;gap:10px;align-items:center"><?= render_input('text', 'discount', dv($values['discount']), [
                'id' => 'inv-discount',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:100px;flex:0 0 auto',
            ]) ?><?php if (!$isReadonly): ?><?= render_btn_icon('img/make.png', ['id'=>'apply-discount-btn','type'=>'button','title'=>'Установить скидку','class'=>'icon-btn']) ?><?php endif; ?></div></td>
        <td><?= render_input('text', 'sum_discount', dv($values['sum_discount']), [
                'id' => 'inv-sum-discount',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Итоговая сумма</td>
        <td class="form-label">Позиций</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum', dv($values['sum']), [
                'id' => 'inv-sum',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'inv-pos',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:80px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Сумма НДС</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'sum_nds', dv($values['sum_nds']), [
                'id' => 'inv-sum-nds',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px',
            ]) ?></td>
      </tr>
      <tr>
        <td class="form-label">Сумма оплаты</td>
        <td class="form-label">Дата оплаты</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?php
            $sumPlatBg = ((float)$values['sum_plat'] < (float)$values['sum']) ? ';color:#c0392b;font-weight:bold' : '';
            echo render_input('text', 'sum_plat', dv($values['sum_plat']), [
                'id' => 'inv-sum-plat',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px' . $sumPlatBg,
            ]) ?></td>
        <td><?= render_input('date', 'date_plat', $values['date_plat'], [
                'id' => 'inv-date-plat',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:128px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Участок<span class="required">*</span></td>
        <td class="form-label">Сотрудник<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_lookup('store', 'store_id', $values['store_id'], $currentStoreName,
                h(json_encode($storeList, JSON_UNESCAPED_UNICODE)),
                'store_form.php?mode=new', $isReadonly, ['id' => 'store-id', 'data-name-input' => 'store-name']) ?></td>
        <td><?= render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                'sotr_form.php?mode=new', $isReadonly, ['id' => 'sotr-id', 'data-name-input' => 'sotr-name']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Состояние</td>
      </tr>
      <tr>
        <td colspan="3"><div class="field-radio-group">
          <?php foreach ($stateOptions as $opt): ?>
            <?php
            $radioAttrs = [
                'type' => 'radio',
                'name' => 'state',
                'value' => $opt,
                'class' => 'field-radio',
                'id' => 'inv-state-' . h(str_replace([' ', '.'], '_', $opt)),
            ];
            if ($opt === $values['state']) $radioAttrs['checked'] = true;
            if ($isReadonly) $radioAttrs['disabled'] = true;
            ?>
            <input<?= _render_btn_attrs($radioAttrs) ?> />
            <label class="field-radio-label" for="<?= h('inv-state-' . str_replace([' ', '.'], '_', $opt)) ?>"><?= h($opt) ?></label>
          <?php endforeach; ?>
        </div></td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], [
                'id' => 'inv-note',
                'class' => 'full',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
            ]) ?></td>
      </tr>
    </table>
  </div><!-- /.tab-pane.active (Параметры) -->

  <div class="tab-pane" data-tab-index="1">
<?php if ($id > 0 && ($mode !== 'new')): 
$inv2ColWidths = load_columns_widths($conn, 'invoice2');
$inv2DefaultWidths = ['product_name' => 'auto', 'quant' => '80px', 'price' => '90px', 'discount' => '80px', 'sum' => '100px', 'note' => '250px'];
$inv2W = function($name) use ($inv2ColWidths, $inv2DefaultWidths) {
    return isset($inv2ColWidths[$name]) ? $inv2ColWidths[$name] . 'px' : $inv2DefaultWidths[$name];
};
$inv2ColDefaults = invoice2_columns_defaults();
$inv2ColConfig = load_columns_config($conn, 'invoice2', $inv2ColDefaults);
$inv2ColInitialJs = json_encode(array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
}, $inv2ColConfig), JSON_UNESCAPED_UNICODE);
$inv2ColDefaultsJs = json_encode(array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
}, $inv2ColDefaults), JSON_UNESCAPED_UNICODE);
$itemsData = array_map(function($item) {
    return ['id' => (int)$item['invoice2_id'], 'product_id' => (int)$item['product_id'], 'code' => (string)$item['code'],
        'product_name' => (string)$item['product_name'], 'quant' => dv($item['quant']), 'price' => dv($item['price']),
        'discount' => dv($item['discount']), 'sum' => dv($item['sum']), 'sum_discount' => dv($item['sum_discount']),
        'sum_nds' => dv($item['sum_nds']), 'note' => (string)$item['note']];
}, $itemsList);
$productList = [];
$pr = $conn->query("SELECT product_id, product_name, code FROM product ORDER BY product_name");
while ($p = $pr->fetch_assoc()) {
    $productList[] = ['id' => (int)$p['product_id'], 'name' => $p['product_name']];
}
?>
    <div class="toolbar" style="margin-top:0;padding-top:0" id="inv2-toolbar">
      <div class="toolbar-left">
        <button type="button" class="icon-btn" title="Добавить" id="inv2-add-btn" data-invoice-id="<?= $id ?>"><img src="img/add.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Товар" id="inv2-product-btn" disabled><img src="img/edit.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Изменить" id="inv2-edit-btn" disabled><img src="img/edit.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Удалить" id="inv2-del-btn" disabled><img src="img/delete.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Копировать" id="inv2-copy-btn" disabled><img src="img/copy.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Обновить" id="inv2-refresh-btn"><img src="img/refresh.png" alt="" /></button>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Экспорт" id="inv2-export-btn"><img src="img/export.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="inv2-export-csv">Экспорт в CSV</a>
            <a class="dropdown-item" href="#" id="inv2-export-xls">Экспорт в Excel</a>
          </div>
        </div>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Печать" id="inv2-print-btn"><img src="img/print.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="inv2-print-all">Все записи</a>
            <a class="dropdown-item" href="#" id="inv2-print-selected">Выбранные</a>
            <a class="dropdown-item" href="#" id="inv2-print-page">Текущая страница</a>
          </div>
        </div>
        <button type="button" class="icon-btn" title="Импорт отмеченных" id="inv2-import-btn"><img src="img/import.png" alt="" /></button>
        <div class="dropdown selected-actions" id="inv2-sel-wrap">
          <button type="button" class="menu-btn" id="inv2-sel-btn">Выбрано <b><span id="inv2-sel-count">0</span></b> <span class="btn-caret">▼</span></button>
          <div class="dropdown-menu" style="min-width:160px">
            <a class="dropdown-item" href="#" id="inv2-sel-clear">Очистить выбор</a>
            <a class="dropdown-item" href="#" id="inv2-sel-invert">Инвертировать выбор</a>
            <a class="dropdown-item" href="#" id="inv2-sel-show">Показать выбранные</a>
            <a class="dropdown-item" href="#" id="inv2-sel-export">Экспорт</a>
            <a class="dropdown-item" href="#" id="inv2-sel-print">Печать</a>
            <a class="dropdown-item" href="#" id="inv2-sel-delete">Удалить отмеченные</a>
          </div>
        </div>
      </div>
      <div class="toolbar-right">
        <div id="inv2-search-form" style="display:flex;gap:4px;align-items:center;">
          <input class="quick-search" type="text" id="inv2-search-input" name="q" placeholder="Быстрый поиск" />
          <button class="icon-btn clear-filter-btn" type="button" id="inv2-clear-filter-btn" title="Очистить фильтр" style="display:none">✕</button>
          <button class="icon-btn search-toggle-btn" type="button" id="inv2-search-btn" title="Искать"><img src="img/find.png" alt="" /></button>
          <button class="icon-btn search-mini-btn" type="button" id="inv2-search-cond-btn" title="Условия поиска"><img src="img/look.png" alt="" /></button>
        </div>
        <button type="button" class="icon-btn" id="inv2-sort-btn" title="Сортировка"><img src="img/sort.png" alt="" /></button>
        <button type="button" class="icon-btn" id="inv2-columns-btn" title="Настройка столбцов таблицы"><img src="img/setup.png" alt="" /></button>
      </div>
    </div>
    <div id="inv2-filter-banner" class="mode-banner" style="display:none;margin-bottom:8px"></div>
    <div class="table-wrap" style="max-height:360px;overflow-y:auto">
<table class="data-table invoice2-table" style="min-width:auto;table-layout:fixed;width:100%" data-save-url="invoice2_field_save.php"
       data-invoice2-table data-items='<?= h(json_encode($itemsData, JSON_UNESCAPED_UNICODE)) ?>' data-products='<?= h(json_encode($productList, JSON_UNESCAPED_UNICODE)) ?>' data-columns='<?= h($inv2ColInitialJs) ?>' data-columns-defaults='<?= h($inv2ColDefaultsJs) ?>'>
        <colgroup>
          <col class="col-check" style="width:32px" />
          <col class="col-product_name" style="width:<?= $inv2W('product_name') ?>" />
          <col class="col-quant" style="width:<?= $inv2W('quant') ?>" />
          <col class="col-price" style="width:<?= $inv2W('price') ?>" />
          <col class="col-discount" style="width:<?= $inv2W('discount') ?>" />
          <col class="col-sum" style="width:<?= $inv2W('sum') ?>" />
          <col class="col-note" style="width:<?= $inv2W('note') ?>" />
        </colgroup>
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="inv2-check-all" /></th>
            <th class="col-product_name" data-col="product_name">Товар</th>
            <th class="col-quant" data-col="quant">Кол-во</th>
            <th class="col-price" data-col="price">Цена</th>
            <th class="col-discount" data-col="discount">Скидка</th>
            <th class="col-sum" data-col="sum">Сумма</th>
            <th class="col-note" data-col="note">Примечание</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="pagination" id="inv2-pagination" style="min-height:28px"></div>
<?php elseif ($mode === 'new'): ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните счёт, чтобы добавить товары</div>
<?php endif; ?>
  </div><!-- /.tab-pane (Товары) -->

  <div class="tab-pane" data-tab-index="2">
<?php if ($id > 0 && $mode !== 'new'):
$platDataJs = json_encode(array_map(function($p) {
    $dt = strtotime((string)$p['datetime']);
    return [
        'id' => (int)$p['plat_id'],
        'datetime' => $dt ? date('d.m.Y H:i', $dt) : '-',
        'client_name' => (string)($p['client_name'] ?? '-'),
        'zat_name' => (string)($p['zat_name'] ?? '-'),
        'sum' => (float)$p['sum'],
        'plat_type' => (string)$p['plat_type'],
        'out_flag' => (int)$p['out_flag'],
        'note' => (string)$p['note'],
    ];
}, $platList), JSON_UNESCAPED_UNICODE);
$platTypeOptionsJs = json_encode(['Наличные', 'Безнал.', 'Карта', 'Прочее'], JSON_UNESCAPED_UNICODE);
?>
    <div class="toolbar" style="margin-top:0;padding-top:0" id="plat-toolbar">
      <div class="toolbar-left">
        <button type="button" class="icon-btn" title="Добавить" id="plat-add-btn" data-plat-url="plat_form.php?mode=new&amp;doc_id=<?= (int)$id ?>&amp;doc_type=10&amp;client_id=<?= (int)$values['client_id'] ?>&amp;sum_in=<?= urlencode($values['sum'] - $values['sum_plat']) ?>&amp;sotr_id=<?= (int)$CurSotrID ?>&amp;zat_id=<?= $zatIdForPlat ?>&amp;return_url=<?= urlencode('invoice_form.php?mode=edit&id=' . (int)$id) ?>"><img src="img/add.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Изменить" id="plat-edit-btn" disabled><img src="img/edit.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Удалить" id="plat-del-btn" disabled><img src="img/delete.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Обновить" id="plat-refresh-btn"><img src="img/refresh.png" alt="" /></button>
        <div class="dropdown selected-actions" id="plat-sel-wrap">
          <button type="button" class="menu-btn" id="plat-sel-btn">Выбрано <b><span id="plat-sel-count">0</span></b> <span class="btn-caret">▼</span></button>
          <div class="dropdown-menu" style="min-width:160px">
            <a class="dropdown-item" href="#" id="plat-sel-clear">Очистить выбор</a>
            <a class="dropdown-item" href="#" id="plat-sel-invert">Инвертировать выбор</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" id="plat-sel-show">Показать выбранные</a>
            <a class="dropdown-item" href="#" id="plat-sel-export">Экспорт</a>
            <a class="dropdown-item" href="#" id="plat-sel-print">Печать</a>
            <a class="dropdown-item" href="#" id="plat-sel-delete">Удалить отмеченные</a>
          </div>
        </div>
      </div>
      <div class="toolbar-right">
        <div id="plat-search-form" style="display:flex;gap:4px;align-items:center;">
          <input class="quick-search" type="text" id="plat-search-input" name="q" placeholder="Быстрый поиск" />
          <button class="icon-btn clear-filter-btn" type="button" id="plat-clear-filter-btn" title="Очистить фильтр" style="display:none">✕</button>
          <button class="icon-btn search-toggle-btn" type="button" id="plat-search-btn" title="Искать"><img src="img/find.png" alt="" /></button>
          <button class="icon-btn search-mini-btn" type="button" id="plat-search-cond-btn" title="Условия поиска"><img src="img/look.png" alt="" /></button>
        </div>
      </div>
    </div>
    <div id="plat-filter-banner" class="mode-banner" style="display:none;margin-bottom:8px"></div>
    <div class="table-wrap" style="max-height:360px;overflow-y:auto">
      <table class="data-table plat-table" style="min-width:auto;table-layout:fixed;width:100%"
             data-plat-table data-items='<?= h($platDataJs) ?>' data-plat-types='<?= h($platTypeOptionsJs) ?>'>
        <colgroup>
          <col class="col-check" style="width:32px" />
          <col class="col-datetime" style="width:140px" />
          <col class="col-client" style="width:auto" />
          <col class="col-zat" style="width:150px" />
          <col class="col-sum" style="width:100px" />
          <col class="col-plat_type" style="width:100px" />
          <col class="col-out_flag" style="width:70px" />
          <col class="col-note" style="width:auto" />
        </colgroup>
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" id="plat-check-all" /></th>
            <th data-col="datetime">Дата/Время</th>
            <th data-col="client_name">Контрагент</th>
            <th data-col="zat_name">Вид операции</th>
            <th data-col="sum">Сумма</th>
            <th data-col="plat_type">Вид платежа</th>
            <th data-col="out_flag">Тип</th>
            <th data-col="note">Примечание</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="pagination" id="plat-pagination" style="min-height:28px"></div>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните счёт, чтобы добавить платежи</div>
<?php endif; ?>
  </div><!-- /.tab-pane (Оплата) -->
</div><!-- /.tab-container -->

<style>
.form-modal { max-width: 990px; }
.page--form { max-width: 990px; }
.form { max-width: 990px; }
.tab-container { margin-bottom: 16px; }
.tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
.tab-header { padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold; color: var(--muted); border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; user-select: none; }
.tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
.tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
.tab-pane { display: none; width: 100%; }
.tab-pane.active { display: block; width: 100%; }
.tab-container { width: 100%; }
.table-wrap { width: 100%; box-sizing: border-box; }
.data-table.invoice2-table { width: 100%; min-width: auto; table-layout: auto; }
.invoice2-table tbody td.col-inv2-empty { text-align:center; padding:20px; color:var(--muted); }
.form-table { width: 100%; border-collapse: collapse; }
.form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
.form-table td:last-child { padding-right: 0; }
.form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
.form-table tr.col-3 td { width: 33%; }
input.num { width: 120px; text-align: right; }
input.full { width: 100%; box-sizing: border-box; }
textarea.full { width: 100%; box-sizing: border-box; }
</style>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'invoice.php', ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'invoice.php', ['class'=>'btn-secondary'])]
) ?>
</form>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode, 'focusField' => $focusField]);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
  <style>
    .lookup-wrap { position: relative; max-width: 430px; }
    .field-radio-group { display: flex; gap: 16px; align-items: center; padding: 6px 0; flex-wrap: wrap; }
    .field-radio-label { font-size: 14px; cursor: pointer; }
    .field--readonly .field-radio-label { cursor: default; }
    .field-radio { width: auto; margin: 0; }
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/lookup.js"></script>
  <script src="assets/inline-edit.js"></script>
  <script src="assets/search-panel.js"></script>
  <script src="assets/columns-panel.js"></script>
  <script src="assets/column-resize.js"></script>
  <script src="assets/embedded-table.js"></script>
  <script src="assets/keyboard.js"></script>
  <script>
    (function () {
      var tabContainer = document.querySelector('.tab-container');
      if (tabContainer) {
        var headers = tabContainer.querySelectorAll('.tab-header');
        var panes = tabContainer.querySelectorAll('.tab-pane');
        headers.forEach(function (hdr) {
          hdr.addEventListener('click', function () {
            var idx = parseInt(hdr.dataset.tabIndex, 10);
            headers.forEach(function (h) { h.classList.remove('active'); });
            panes.forEach(function (p) { p.classList.remove('active'); });
            hdr.classList.add('active');
            var pane = tabContainer.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
            if (pane) pane.classList.add('active');
            if (idx === 1 && typeof tryInitInv2ColResize === 'function') tryInitInv2ColResize();
          });
        });
      }

      /* ---- invoice2 table management ---- */
      window.invoice2Dirty = window.invoice2Dirty || false;

      var autoSaveInProgress = false;
      var autoSaveNeeded = false;

      function autoSaveInvoice() {
        if (autoSaveInProgress) { autoSaveNeeded = true; return; }
        var invIdEl = document.querySelector('input[name="id"]');
        var invoiceId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
        if (!invoiceId) return;
        var fd = new FormData();
        fd.set('field', '_recalc_totals');
        fd.set('invoice_id', String(invoiceId));
        autoSaveInProgress = true;
        fetch('invoice2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function(r) { return r.json(); }).then(function(data) {
            autoSaveInProgress = false;
            if (data && data.ok) {
              window.invoice2Dirty = false;
              var cancelBtn = document.querySelector('.form-actions a.btn-secondary');
              if (cancelBtn) { cancelBtn.style.pointerEvents = ''; cancelBtn.style.opacity = ''; }
            }
            if (autoSaveNeeded) { autoSaveNeeded = false; autoSaveInvoice(); }
          }).catch(function(err) {
            autoSaveInProgress = false;
            if (autoSaveNeeded) { autoSaveNeeded = false; autoSaveInvoice(); }
          });
      }

      document.addEventListener('submit', function (e) {
        var sb = e.submitter;
        if (sb && sb.name === 'action' && sb.value === 'apply') {
          e.preventDefault();
          var f = e.target;
          var fd = new FormData(f);
          fd.set('ajax', '1');
          fetch(f.getAttribute('action') || 'invoice_form.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data && data.ok) { window.applyInvoice2Totals(data); }
            });
        }
      });

      var discBtn = document.getElementById('apply-discount-btn');
      if (discBtn) {
        discBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var discountEl = document.getElementById('inv-discount');
          var discount = discountEl ? parseFloat(discountEl.value.replace(',', '.')) : 0;
          if (isNaN(discount)) discount = 0;
          if (!confirm('Изменить скидку у всех товаров счета на ' + discount + ' %?')) return;
          var invIdEl = document.querySelector('input[name="id"]');
          var invoiceId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
          if (!invoiceId) return;
          var fd = new FormData();
          fd.set('field', '_apply_discount');
          fd.set('invoice_id', String(invoiceId));
          fd.set('discount', String(discount));
          fetch('invoice2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data && data.ok) {
                window.applyInvoice2Totals(data);
                var inv2Fd = new FormData();
                inv2Fd.set('field', '_list');
                inv2Fd.set('invoice_id', String(invoiceId));
                fetch('invoice2_field_save.php', { method: 'POST', body: inv2Fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                  .then(function(r) { return r.json(); })
                  .then(function(list) {
                    if (Array.isArray(list)) {
                      window.__invoice2Data = list;
                      if (window.__invoice2Render) window.__invoice2Render();
                    }
                  });
              }
            });
        });
      }

      window.applyInvoice2Totals = function applyInvoice2Totals(d) {
        var s = d && d.total_sum !== undefined ? d.total_sum : (d && d.sum !== undefined ? d.sum : undefined);
        if (s !== undefined) { var el = document.getElementById('inv-sum'); if (el) el.value = s || ''; }
        var sd = d && d.total_sum_discount !== undefined ? d.total_sum_discount : (d && d.sum_discount !== undefined ? d.sum_discount : undefined);
        if (sd !== undefined) { var el = document.getElementById('inv-sum-discount'); if (el) el.value = sd || ''; }
        var snds = d && d.total_sum_nds !== undefined ? d.total_sum_nds : (d && d.sum_nds !== undefined ? d.sum_nds : undefined);
        if (snds !== undefined) { var el = document.getElementById('inv-sum-nds'); if (el) el.value = snds || ''; }
        if (d && d.pos !== undefined) { var el = document.getElementById('inv-pos'); if (el) el.value = d.pos > 0 ? String(d.pos) : ''; }
        if (d && d.sum_plat !== undefined) { var el = document.getElementById('inv-sum-plat'); if (el) { el.value = d.sum_plat || ''; var sv = d && d.sum !== undefined ? d.sum : (document.getElementById('inv-sum') ? document.getElementById('inv-sum').value : 0); el.style.color = (parseFloat(d.sum_plat) || 0) < (parseFloat(sv) || 0) ? '#c0392b' : ''; el.style.fontWeight = (parseFloat(d.sum_plat) || 0) < (parseFloat(sv) || 0) ? 'bold' : ''; } }
        if (d && d.ok) { window.invoice2Dirty = true; autoSaveInvoice(); }
        var cancelBtn = document.querySelector('.form-actions a.btn-secondary');
        if (cancelBtn) {
          if (window.invoice2Dirty) { cancelBtn.style.pointerEvents = 'none'; cancelBtn.style.opacity = '0.5'; }
          else { cancelBtn.style.pointerEvents = ''; cancelBtn.style.opacity = ''; }
        }
      };

      var tableEl = document.querySelector('.invoice2-table');
      if (!tableEl) return;
      if (tableEl.dataset.invoice2Inited) return;
      tableEl.dataset.invoice2Inited = '1';
      if (window.__openFormModal) return;

      window.__invoice2Data = [];
      try { window.__invoice2Data = JSON.parse(tableEl.dataset.items || '[]'); } catch(e) {}
      var PAGE_SIZE = 15;
      var currentPage = 1;
      var searchText = '';
      var searchActive = false;
      var sortCol = -1;
      var sortDir = 'asc';
      var selectedId = 0;
      var tbody = tableEl.querySelector('tbody');
      var saveUrl = tableEl.getAttribute('data-save-url') || 'invoice2_field_save.php';
      var invIdEl = document.querySelector('input[name="id"]');
      var invId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
      var INV2_SEARCH_COLS = [
        { key: 'product_name', label: 'Товар' },
        { key: 'quant', label: 'Кол-во' },
        { key: 'price', label: 'Цена' },
        { key: 'discount', label: 'Скидка' },
        { key: 'sum', label: 'Сумма' },
        { key: 'note', label: 'Примечание' }
      ];
      var INV2_COL_KEYS = INV2_SEARCH_COLS.map(function(c) { return c.key; });
      var searchCols = new Set(INV2_COL_KEYS);
      var searchCond = 'contains';

      function cn(v) { return (v === '' || v === null || v === undefined) ? '-' : v; }
      function nv(v) { var n = parseFloat(String(v !== null && v !== undefined ? v : '0').replace(',','.')); return isNaN(n) || n === 0 ? '-' : v; }
      function fmtInt(v) {
        if (v === null || v === undefined || v === '') return '-';
        var s = String(v).replace(',', '.');
        var n = parseFloat(s);
        if (isNaN(n) || n === 0) return '-';
        return n % 1 === 0 ? String(Math.round(n)) : String(v);
      }
      function hl(v) {
        if (!searchActive || !searchText) return cn(v);
        var s = String(v !== null && v !== undefined ? v : '');
        if (s === '') return '-';
        var st = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var re = new RegExp('(' + st + ')', 'gi');
        return s.replace(re, '<span class="hl">$1</span>');
      }

      function renderInvoice2() {
        if (!tbody) return;
        var filtered = window.__invoice2Data;
        if (searchActive && searchText) {
          var st = searchText.toLowerCase();
          var cond = searchCond || 'contains';
          var colSet = (searchCols && searchCols.size > 0) ? searchCols : new Set(INV2_COL_KEYS);
          filtered = filtered.filter(function(item) {
            return INV2_COL_KEYS.some(function(key) {
              if (!colSet.has(key)) return false;
              var val = (item[key] || '').toString().toLowerCase();
              if (cond === 'contains') return val.indexOf(st) >= 0;
              if (cond === 'not_contains') return val.indexOf(st) < 0;
              if (cond === 'starts_with') return val.indexOf(st) === 0;
              if (cond === 'ends_with') return val.indexOf(st) === val.length - st.length;
              if (cond === 'equals') return val === st;
              if (cond === 'not_equals') return val !== st;
              return false;
            });
          });
        }
        if (sortCol >= 0) {
          var key = ['product_name','quant','price','discount','sum','note'][sortCol] || 'id';
          filtered.sort(function(a, b) {
            var va = (a[key] || '').toString().toLowerCase();
            var vb = (b[key] || '').toString().toLowerCase();
            var na = parseFloat(va.replace(',','.'));
            var nb = parseFloat(vb.replace(',','.'));
            if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
            if (va < vb) return sortDir === 'asc' ? -1 : 1;
            if (va > vb) return sortDir === 'asc' ? 1 : -1;
            return 0;
          });
        }
        var total = filtered.length;
        var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (currentPage > pages) currentPage = pages;
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageItems = filtered.slice(start, start + PAGE_SIZE);

        if (total === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="col-inv2-empty">Нет товаров</td></tr>';
        } else {
          var html = '';
          for (var i = 0; i < pageItems.length; i++) {
            var item = pageItems[i];
            var sel = (item.id === selectedId) ? ' selected' : '';
            html += '<tr data-id="' + item.id + '" data-row-id="' + item.id + '" class="inv2-row' + sel + '">'
              + '<td class="cell-editable" data-field="product_id" data-value="' + (item.product_id || 0) + '"><span class="cell-value">' + hl(item.product_name) + '</span></td>'
              + '<td class="cell-editable" data-field="quant" data-value="' + (item.quant || '0') + '" style="text-align:center"><span class="cell-value">' + hl(fmtInt(item.quant)) + '</span></td>'
              + '<td class="cell-editable" data-field="price" data-value="' + (item.price || '0') + '" style="text-align:right"><span class="cell-value">' + hl(nv(item.price)) + '</span></td>'
              + '<td class="cell-editable" data-field="discount" data-value="' + (item.discount || '0') + '" style="text-align:right"><span class="cell-value">' + hl(fmtInt(item.discount)) + '</span></td>'
              + '<td data-field="sum" style="text-align:right">' + hl(fmtInt(item.sum)) + '</td>'
              + '<td class="cell-editable" data-field="note" data-value="' + (item.note || '') + '"><span class="cell-value">' + hl(item.note) + '</span></td></tr>';
          }
          tbody.innerHTML = html;
        }

        /* row selection */
        tbody.querySelectorAll('.inv2-row').forEach(function(tr) {
          tr.addEventListener('click', function(e) {
            var id = parseInt(tr.dataset.id, 10);
            if (selectedId === id) {
              selectedId = 0;
              tr.classList.remove('selected');
            } else {
              tbody.querySelectorAll('.inv2-row.selected').forEach(function(r) { r.classList.remove('selected'); });
              selectedId = id;
              tr.classList.add('selected');
            }
            updateInv2Buttons();
          });
          tr.addEventListener('dblclick', function(e) {
            var id = parseInt(tr.dataset.id, 10);
            if (id > 0) openInv2Form('edit', id);
          });
        });

        /* pagination */
        var pagEl = document.getElementById('inv2-pagination');
        if (pagEl) {
          if (pages <= 1) { pagEl.innerHTML = ''; } else {
            var ph = '';
            var firstDisabled = currentPage <= 1 ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
            var lastDisabled = currentPage >= pages ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
            ph += '<a class="page-btn" href="#" data-page="1"' + firstDisabled + '>«</a>';
            var startP = Math.max(1, currentPage - 2);
            var endP = Math.min(pages, currentPage + 2);
            for (var p = startP; p <= endP; p++) {
              var active = p === currentPage ? ' active' : '';
              ph += '<a class="page-btn' + active + '" href="#" data-page="' + p + '">' + p + '</a>';
            }
            ph += '<a class="page-btn" href="#" data-page="' + pages + '"' + lastDisabled + '>»</a>';
            ph += '<span class="page-info">' + currentPage + ' из ' + pages + '</span>';
            pagEl.innerHTML = ph;
            pagEl.querySelectorAll('a.page-btn').forEach(function(a) {
              a.addEventListener('click', function(e) {
                e.preventDefault();
                var pg = parseInt(a.dataset.page, 10);
                if (pg > 0 && pg !== currentPage) { currentPage = pg; renderInvoice2(); }
              });
            });
          }
        }

        var countEl = document.getElementById('inv2-count');
        if (countEl) countEl.textContent = total;

        /* Banner */
        var bannerEl = document.getElementById('inv2-filter-banner');
        if (searchActive && searchText) {
          if (bannerEl) {
            bannerEl.style.display = '';
            var colLabels = INV2_SEARCH_COLS.filter(function(c) { return !searchCols || searchCols.has(c.key); }).map(function(c) { return c.label; });
            if (colLabels.length === 0) colLabels = INV2_SEARCH_COLS.map(function(c) { return c.label; });
            var condLabel = ({ contains: 'Содержит', not_contains: 'Не содержит', starts_with: 'Начинается с', ends_with: 'Заканчивается на', equals: 'Равно', not_equals: 'Не равно' })[searchCond] || 'Содержит';
            bannerEl.innerHTML = '<img src="img/filter.png" alt="" /><span class="filter-chip"><span class="filter-chip-text">(' + colLabels.join(', ') + ' ' + condLabel + '  «' + searchText + '»)</span><button type="button" class="filter-chip-close" id="inv2-banner-clear" title="Снять фильтр">✕</button></span>';
            var bc = bannerEl.querySelector('#inv2-banner-clear');
            if (bc) bc.addEventListener('click', function() { clearFilterBtn.click(); });
          }
        } else {
          if (bannerEl) bannerEl.style.display = 'none';
        }
      }
      window.__invoice2Render = renderInvoice2;

      function updateInv2Buttons() {
        var editBtn = document.getElementById('inv2-edit-btn');
        var delBtn = document.getElementById('inv2-del-btn');
        var copyBtn = document.getElementById('inv2-copy-btn');
        var productBtn = document.getElementById('inv2-product-btn');
        var disabled = !selectedId;
        if (editBtn) editBtn.disabled = disabled;
        if (delBtn) delBtn.disabled = disabled;
        if (copyBtn) copyBtn.disabled = disabled;
        if (productBtn) productBtn.disabled = disabled;
      }

      function openInv2Form(mode, itemId) {
        var url = 'invoice2_form.php?mode=' + mode + '&id=' + itemId + '&invoice_id=' + invId;
        location.href = url;
      }

      function refreshInvoice2Data() {
        var fd = new FormData();
        fd.set('field', '_list');
        fd.set('invoice_id', String(invId));
        fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data && Array.isArray(data)) {
              window.__invoice2Data = data;
              renderInvoice2();
            }
          });
      }

      renderInvoice2();

      var productData = [];
      try { productData = JSON.parse(tableEl.dataset.products || '[]'); } catch(e) {}
      InlineEdit.init({
        tbody: tbody,
        saveUrl: saveUrl,
        fields: {
          product_id: { dbField: 'product_id', type: 'lookup', label: 'Товар' },
          quant:      { dbField: 'quant', type: 'text', label: 'Кол-во' },
          price:      { dbField: 'price', type: 'text', label: 'Цена' },
          discount:   { dbField: 'discount', type: 'text', label: 'Скидка' },
          note:       { dbField: 'note', type: 'textarea', label: 'Примечание' }
        },
        getLookupData: function (field) {
          if (field === 'product_id') return productData;
          return [];
        },
        onSaveSuccess: function (data, field) {
          if (data && data.item && data.item.id) {
            var item = data.item;
            var found = false;
            window.__invoice2Data = window.__invoice2Data.map(function(it) {
              if (it.id == item.id) { found = true; return item; }
              return it;
            });
            if (!found) window.__invoice2Data.push(item);
            renderInvoice2();
          }
          window.applyInvoice2Totals(data);
        }
      });

      /* Toolbar buttons */
      document.getElementById('inv2-add-btn')?.addEventListener('click', function() {
        openInv2Form('new', 0);
      });
      document.getElementById('inv2-edit-btn')?.addEventListener('click', function() {
        if (selectedId) openInv2Form('edit', selectedId);
      });
      document.getElementById('inv2-del-btn')?.addEventListener('click', function() {
        if (!selectedId) return;
        if (!confirm('Удалить товар?')) return;
        var fd = new FormData();
        fd.set('field', '_delete');
        fd.set('invoice2_id', String(selectedId));
        fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d && d.ok) {
              window.__invoice2Data = window.__invoice2Data.filter(function(item) { return item.id !== selectedId; });
              selectedId = 0;
              renderInvoice2();
              window.applyInvoice2Totals(d);
            }
          });
      });
      document.getElementById('inv2-copy-btn')?.addEventListener('click', function() {
        if (selectedId) openInv2Form('copy', selectedId);
      });
      document.getElementById('inv2-product-btn')?.addEventListener('click', function() {
        if (!selectedId) return;
        var item = window.__invoice2Data.find(function(it) { return it.id === selectedId; });
        var productId = item ? (item.product_id || 0) : 0;
        if (!productId) return;
        var url = 'tmc_form.php?mode=edit&id=' + productId;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal(url, { onRestore: function() { refreshInvoice2Data(); } });
        } else {
          window.open(url, '_blank');
        }
      });
      document.getElementById('inv2-refresh-btn')?.addEventListener('click', function() {
        refreshInvoice2Data();
      });

      /* Search */
      var searchInput = document.getElementById('inv2-search-input');
      var searchBtn = document.getElementById('inv2-search-btn');
      var searchCondBtn = document.getElementById('inv2-search-cond-btn');
      var clearFilterBtn = document.getElementById('inv2-clear-filter-btn');

      if (searchInput) {
        searchInput.addEventListener('input', function() {
          if (!searchActive) return;
          searchText = searchInput.value;
          currentPage = 1;
          renderInvoice2();
        });
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            searchActive = true;
            searchText = searchInput.value.trim();
            searchBtn.classList.add('active');
            currentPage = 1;
            renderInvoice2();
          }
        });
      }
      if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', function() {
          searchActive = false;
          searchText = '';
          searchCols = new Set(INV2_COL_KEYS);
          searchCond = 'contains';
          if (searchBtn) searchBtn.classList.remove('active');
          if (searchInput) searchInput.value = '';
          currentPage = 1;
          renderInvoice2();
          document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        });
      }
      function closeInv2Panels() {
        document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        document.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
      }
      if (searchBtn && searchCondBtn && typeof SearchPanel !== 'undefined') {
        var ns = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(ns, searchBtn);
        searchBtn = ns;
        var nc = searchCondBtn.cloneNode(true);
        searchCondBtn.parentNode.replaceChild(nc, searchCondBtn);
        searchCondBtn = nc;
        SearchPanel.init({
          form: document.getElementById('inv2-search-form'),
          condBtn: searchCondBtn,
          toggleBtn: searchBtn,
          columns: INV2_SEARCH_COLS,
          pageUrl: location.href,
          popupCheckboxes: true,
          emptyClass: 'search-cond-placeholder',
          closeAllPanels: closeInv2Panels,
          labels: {
            cols: 'Столбцы',
            cond: 'Условие',
            emptyCols: 'Выберите столбцы…'
          },
          onApply: function(state) {
            searchCols = state.cols;
            searchCond = state.cond;
            searchActive = true;
            searchText = searchInput ? searchInput.value.trim() : '';
            searchBtn.classList.add('active');
            currentPage = 1;
            renderInvoice2();
            closeInv2Panels();
          },
          onToggle: function(state) {
            if (searchActive) {
              searchActive = false;
              searchText = '';
              if (searchInput) searchInput.value = '';
              searchBtn.classList.remove('active');
            } else {
              searchActive = true;
              searchText = searchInput ? searchInput.value.trim() : '';
              searchBtn.classList.add('active');
            }
            currentPage = 1;
            renderInvoice2();
          }
        });
      }

      /* Clear filter button visibility */
      function updateClearFilterBtn() {
        if (clearFilterBtn) {
          clearFilterBtn.style.display = (searchActive && searchText) ? '' : 'none';
        }
      }
      var origRender = renderInvoice2;
      renderInvoice2 = function() {
        origRender();
        updateClearFilterBtn();
      };

      /* Column header sort */
      tableEl.querySelectorAll('thead th[data-col]').forEach(function(th) {
        th.addEventListener('click', function() {
          var colIdx = Array.from(th.parentNode.children).indexOf(th);
          if (sortCol === colIdx) {
            sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
          } else {
            sortCol = colIdx;
            sortDir = 'asc';
          }
          currentPage = 1;
          renderInvoice2();
          /* update sort indicator */
          tableEl.querySelectorAll('thead th[data-col]').forEach(function(h) {
            h.querySelector('.sort-indicator')?.remove();
          });
          var ind = document.createElement('span');
          ind.className = 'sort-indicator';
          ind.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
          th.appendChild(ind);
        });
      });
      var sortBtn = document.getElementById('inv2-sort-btn');
      if (sortBtn) sortBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeInv2Panels();
        var cols = INV2_SEARCH_COLS;
        var directions = [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }];
        var levels = sortCol >= 0
          ? [{ col: INV2_COL_KEYS[sortCol], dir: sortDir }]
          : [{ col: 'product_name', dir: 'asc' }];

        function colLabel(k) { for (var i = 0; i < cols.length; i++) if (cols[i].key === k) return cols[i].label; return k; }
        function dirLabel(k) { for (var i = 0; i < directions.length; i++) if (directions[i].key === k) return directions[i].label; return k; }
        function serialize(lvs) { return lvs.map(function(l) { return l.col + ':' + l.dir; }).join(','); }
        function usedCols(lvs, exceptIdx) { var u = []; lvs.forEach(function(l, i) { if (i !== exceptIdx) u.push(l.col); }); return u; }

        var backdrop = document.createElement('div');
        backdrop.className = 'sort-modal-backdrop';
        backdrop.innerHTML =
          '<div class="sort-modal" role="dialog" aria-labelledby="inv2SortTitle">' +
            '<div class="sort-modal-header">' +
              '<span id="inv2SortTitle">Сортировка</span>' +
              '<button class="sort-modal-close" type="button" title="Закрыть">✕</button>' +
            '</div>' +
            '<div class="sort-modal-body">' +
              '<div class="sort-level-actions">' +
                '<button type="button" class="sort-add-level" id="inv2SortAddBtn">+ Добавить уровень</button>' +
                '<button type="button" class="sort-remove-level" id="inv2SortRemoveBtn">− Удалить уровень</button>' +
              '</div>' +
              '<div class="sort-levels">' +
                '<div class="sort-cols">' +
                  '<div class="sort-cols-header">Столбец</div>' +
                  '<div class="sort-cols-list" id="inv2SortColsList"></div>' +
                '</div>' +
                '<div class="sort-dirs">' +
                  '<div class="sort-dirs-header">Направление</div>' +
                  '<div class="sort-dirs-list" id="inv2SortDirsList"></div>' +
                '</div>' +
              '</div>' +
              '<div class="sort-modal-actions">' +
                 '<button type="button" class="sort-apply" id="inv2SortApplyBtn"><img src="img/ok.png" alt="" />Сортировать</button>' +
                 '<button type="button" class="sort-cancel" id="inv2SortCancelBtn"><img src="img/cancel.png" alt="" />Отменить</button>' +
              '</div>' +
            '</div>' +
          '</div>';
        document.body.appendChild(backdrop);

        var modal = backdrop.querySelector('.sort-modal');
        var colsList = backdrop.querySelector('#inv2SortColsList');
        var dirsList = backdrop.querySelector('#inv2SortDirsList');
        var addBtn = backdrop.querySelector('#inv2SortAddBtn');
        var removeBtn = backdrop.querySelector('#inv2SortRemoveBtn');
        var cancelBtn = backdrop.querySelector('#inv2SortCancelBtn');
        var applyBtn = backdrop.querySelector('#inv2SortApplyBtn');
        var closeBtn = backdrop.querySelector('.sort-modal-close');

        var pop = document.createElement('div');
        pop.className = 'sort-pop';
        document.body.appendChild(pop);

        function positionPopup(target, p) {
          var r = target.getBoundingClientRect();
          var pw = p.offsetWidth || 320;
          var ph = p.offsetHeight;
          var left = r.left;
          if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
          var top = r.bottom + 4;
          if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
          p.style.left = left + 'px';
          p.style.top = top + 'px';
        }

        function closePop() { pop.classList.remove('open'); pop.dataset.kind = ''; }
        function closeModal() { backdrop.classList.remove('open'); closePop(); }

        function render() {
          colsList.innerHTML = '';
          dirsList.innerHTML = '';
          addBtn.disabled = levels.length >= cols.length;
          removeBtn.disabled = levels.length <= 1;
          levels.forEach(function(l, i) {
            var cRow = document.createElement('div');
            cRow.className = 'sort-row';
            var cLabel = document.createElement('span');
            cLabel.className = 'sort-label';
            cLabel.textContent = i === 0 ? 'Сначала по' : 'Затем по';
            var cSel = document.createElement('div');
            cSel.className = 'sort-select';
            cSel.tabIndex = 0;
            cSel.innerHTML = '<span class="sort-select-label">' + colLabel(l.col) + '</span>';
            cSel.addEventListener('click', function(idx) {
              return function(e) { e.stopPropagation(); openColPop(idx, cSel); };
            }(i));
            cRow.appendChild(cLabel);
            cRow.appendChild(cSel);
            colsList.appendChild(cRow);

            var dRow = document.createElement('div');
            dRow.className = 'sort-row';
            dRow.style.gap = '0';
            var dSel = document.createElement('div');
            dSel.className = 'sort-select';
            dSel.tabIndex = 0;
            dSel.innerHTML = '<span class="sort-select-label">' + dirLabel(l.dir) + '</span>';
            dSel.addEventListener('click', function(idx) {
              return function(e) { e.stopPropagation(); openDirPop(idx, dSel); };
            }(i));
            dRow.appendChild(dSel);
            dirsList.appendChild(dRow);
          });
        }

        function openColPop(idx, target) {
          var used = usedCols(levels, idx);
          pop.innerHTML = '';
          cols.forEach(function(c) {
            var isSel = levels[idx].col === c.key;
            var isUsed = used.indexOf(c.key) !== -1;
            var item = document.createElement('div');
            item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
            item.textContent = c.label;
            if (isUsed && !isSel) {
              item.style.opacity = '0.45';
              item.style.cursor = 'default';
            } else {
              item.addEventListener('click', function(colKey) {
                return function(e) {
                  e.stopPropagation();
                  levels[idx].col = colKey;
                  closePop();
                  render();
                };
              }(c.key));
            }
            pop.appendChild(item);
          });
          pop.classList.add('open');
          pop.dataset.kind = 'col';
          positionPopup(target, pop);
        }

        function openDirPop(idx, target) {
          pop.innerHTML = '';
          directions.forEach(function(d) {
            var isSel = levels[idx].dir === d.key;
            var item = document.createElement('div');
            item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
            item.textContent = d.label;
            item.addEventListener('click', function(dirKey) {
              return function(e) {
                e.stopPropagation();
                levels[idx].dir = dirKey;
                closePop();
                render();
              };
            }(d.key));
            pop.appendChild(item);
          });
          pop.classList.add('open');
          pop.dataset.kind = 'dir';
          positionPopup(target, pop);
        }

        addBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          if (levels.length >= cols.length) return;
          var used = usedCols(levels, -1);
          var available = [];
          cols.forEach(function(c) { if (used.indexOf(c.key) === -1) available.push(c); });
          if (available.length === 0) return;
          levels.push({ col: available[0].key, dir: 'asc' });
          render();
        });

        removeBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          if (levels.length <= 1) return;
          levels.pop();
          render();
        });

        cancelBtn.addEventListener('click', function(e) { e.stopPropagation(); closeModal(); });
        closeBtn.addEventListener('click', function(e) { e.stopPropagation(); closeModal(); });

        applyBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          if (levels.length > 0) {
            var col = levels[0].col;
            var idx = INV2_COL_KEYS.indexOf(col);
            sortCol = idx >= 0 ? idx : 0;
            sortDir = levels[0].dir;
            currentPage = 1;
            renderInvoice2();
            document.querySelectorAll('.sort-indicator').forEach(function(s) { s.remove(); });
            var ths = tableEl.querySelectorAll('thead th[data-col]');
            if (ths[sortCol]) {
              var ind = document.createElement('span');
              ind.className = 'sort-indicator';
              ind.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
              ths[sortCol].appendChild(ind);
            }
          }
          closeModal();
        });

        document.addEventListener('click', function(e) {
          if (!backdrop.classList.contains('open')) return;
          if (e.target.closest('.sort-modal, .sort-pop.open')) return;
          e.stopPropagation();
          closeModal();
        });

        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
        });

        render();
        backdrop.classList.add('open');
      });

      /* select first row by default */
      function selectFirstRow() {
        var row = tbody.querySelector('.inv2-row');
        if (row) {
          selectedId = parseInt(row.dataset.id, 10);
          row.classList.add('selected');
          updateInv2Buttons();
        }
      }
      selectFirstRow();

      updateInv2Buttons();
      window.refreshInv2Data = refreshInvoice2Data;
      if (window.ColumnsPanel) {
        ColumnsPanel.init({
          btn: document.getElementById('inv2-columns-btn'),
          saveUrl: 'invoice2_columns_save.php',
          tbl: 'invoice2',
          closeAllPanels: closeInv2Panels,
          initialColumns: <?= $inv2ColInitialJs ?>,
          defaultColumns: <?= $inv2ColDefaultsJs ?>,
          onSave: function(state) {
            var fieldMap = { product_name: 'product_id' };
            state.forEach(function(c) {
              var visible = c.visible !== false;
              document.querySelectorAll('.invoice2-table .col-' + c.name + ', .invoice2-table th[data-col="' + c.name + '"]').forEach(function(el) {
                el.style.display = visible ? '' : 'none';
              });
              var bodyField = fieldMap[c.name] || c.name;
              document.querySelectorAll('.invoice2-table tbody [data-field="' + bodyField + '"]').forEach(function(td) {
                td.style.display = visible ? '' : 'none';
              });
            });
            closeInv2Panels();
          }
        });
      }
    })();
<?php if ((int)$id > 0 && $mode !== 'new'): ?>
    /* defer column resize for invoice2 until tab is visible */
    var inv2ColResizeInited = false;
    function tryInitInv2ColResize() {
      if (inv2ColResizeInited) return;
      var pane = document.querySelector('.tab-pane[data-tab-index="1"]');
      if (!pane || !pane.classList.contains('active')) return;
      var tbl = pane.querySelector('.invoice2-table');
      if (!tbl || tbl.dataset.colResizeInited) return;
      inv2ColResizeInited = true;
      if (window.ColumnResize) ColumnResize.init({ saveUrl: 'invoice_column_width_save.php', tbl: 'invoice2', selector: '.invoice2-table' });
    }
    setTimeout(tryInitInv2ColResize, 100);
<?php endif; ?>
<?php if ($id > 0 && $mode !== 'new'): ?>
    (function () {
      if (window.__openFormModal) return;
      var tableEl = document.querySelector('[data-plat-table]');
      if (!tableEl) return;
      if (tableEl.dataset.platInited) return;
      tableEl.dataset.platInited = '1';
      var platData = [];
      try { platData = JSON.parse(tableEl.dataset.items || '[]'); } catch(e) {}
      window.__platData = platData;
      window.__platOriginalData = platData.slice();
      var platShowOnlyFilter = false;
      var PLAT_TYPE_OPTIONS = [];
      try { PLAT_TYPE_OPTIONS = JSON.parse(tableEl.dataset.platTypes || '[]'); } catch(e) {}
      var editBtn = document.getElementById('plat-edit-btn');
      var delBtn = document.getElementById('plat-del-btn');
      var refreshBtn = document.getElementById('plat-refresh-btn');
      var invId = <?= (int)$id ?>;
      var PLAT_SEARCH_COLS = [
        { key: 'datetime', label: 'Дата/Время' },
        { key: 'client_name', label: 'Контрагент' },
        { key: 'zat_name', label: 'Вид операции' },
        { key: 'sum', label: 'Сумма' },
        { key: 'plat_type', label: 'Вид платежа' },
        { key: 'out_flag', label: 'Тип' },
        { key: 'note', label: 'Примечание' }
      ];
      var searchActive = false;
      var searchCond = 'contains';

      var platTable = EmbeddedTable.create({
        tableEl: tableEl,
        data: platData,
        columns: [
          { key: 'check', label: '' },
          { key: 'datetime', label: 'Дата/Время' },
          { key: 'client_name', label: 'Контрагент' },
          { key: 'zat_name', label: 'Вид операции' },
          { key: 'sum', label: 'Сумма' },
          { key: 'plat_type', label: 'Вид платежа' },
          { key: 'out_flag', label: 'Тип' },
          { key: 'note', label: 'Примечание' }
        ],
        searchCols: PLAT_SEARCH_COLS,
        filterBannerEl: document.getElementById('plat-filter-banner'),
        selWrapEl: document.getElementById('plat-sel-wrap'),
        selCountEl: document.getElementById('plat-sel-count'),
        checkAllEl: '#plat-check-all',
        onSelectionChange: function (id) { if (editBtn) editBtn.disabled = !(id > 0); if (delBtn) delBtn.disabled = !(id > 0); },
        onRowDblClick: function (id) { if (id > 0) window.location.href = 'plat_form.php?mode=edit&id=' + id; },
        renderRow: function (item, sel, h) {
          var sum = parseFloat(String(item.sum != null ? item.sum : '0').replace(',','.'));
          var sumStr = isNaN(sum) ? '-' : sum.toFixed(2);
          return '<tr class="plat-row" data-row-id="' + item.id + sel + '">'
            + '<td><input type="checkbox" class="plat-check" /></td>'
            + '<td>' + h.hl(item.datetime, h) + '</td>'
            + '<td>' + h.hl(item.client_name, h) + '</td>'
            + '<td>' + h.hl(item.zat_name, h) + '</td>'
            + '<td class="cell-editable" data-field="sum" data-value="' + (item.sum || '0') + '" style="text-align:right"><span class="cell-value">' + h.hl(sumStr, h) + '</span></td>'
            + '<td class="cell-editable" data-field="plat_type" data-value="' + h.esc(item.plat_type) + '"><span class="cell-value">' + h.esc(item.plat_type) + '</span></td>'
            + '<td>' + (item.out_flag === 1 ? 'Расход' : 'Приход') + '</td>'
            + '<td class="cell-editable" data-field="note" data-value="' + h.esc(item.note) + '"><span class="cell-value">' + h.hl(item.note, h) + '</span></td></tr>';
        }
      });

      /* Toolbar buttons */
      document.getElementById('plat-add-btn')?.addEventListener('click', function() {
        window.location.href = this.getAttribute('data-plat-url');
      });
      editBtn?.addEventListener('click', function() { var id = platTable.getSelectedId(); if (id > 0) window.location.href = 'plat_form.php?mode=edit&id=' + id + '&return_url=' + encodeURIComponent('invoice_form.php?mode=edit&id=' + invId); });
      delBtn?.addEventListener('click', function() { var id = platTable.getSelectedId(); if (id > 0) window.location.href = 'plat_form.php?mode=delete&id=' + id + '&return_url=' + encodeURIComponent('invoice_form.php?mode=edit&id=' + invId); });
      refreshBtn?.addEventListener('click', function() { window.location.href = 'invoice_form.php?mode=edit&id=' + invId; });

      /* Search */
      var searchInput = document.getElementById('plat-search-input');
      var clearBtn = document.getElementById('plat-clear-filter-btn');
      var searchBtn = document.getElementById('plat-search-btn');
      var condBtn = document.getElementById('plat-search-cond-btn');
      var filterBanner = document.getElementById('plat-filter-banner');



      function updatePlatBanner() {
        if (!filterBanner) return;
        var parts = [];
        if (searchActive) {
          var st = searchInput ? searchInput.value.trim() : '';
          var colLabels = PLAT_SEARCH_COLS.filter(function(c) { return c.key !== 'check'; }).map(function(c) { return c.label; });
          var condLabel = ({ contains: 'Содержит', not_contains: 'Не содержит', starts_with: 'Начинается с', ends_with: 'Заканчивается на', equals: 'Равно', not_equals: 'Не равно' })[searchCond] || 'Содержит';
          parts.push('<span class="filter-chip"><span class="filter-chip-text">(' + colLabels.join(', ') + ' ' + condLabel + '  «' + st + '»)</span><button type="button" class="filter-chip-close" id="plat-banner-clear" title="Снять фильтр">✕</button></span>');
        }
        if (platShowOnlyFilter) {
          parts.push('<span class="filter-chip" style="margin-left:6px"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="plat-banner-showoff" title="Показать все">✕</button></span>');
        }
        if (parts.length > 0) {
          filterBanner.innerHTML = '<img src="img/filter.png" alt="" />' + parts.join('');
          filterBanner.style.display = 'flex';
          var clearBtn2 = filterBanner.querySelector('#plat-banner-clear');
          if (clearBtn2) clearBtn2.addEventListener('click', clearPlatSearch);
          var showOff = filterBanner.querySelector('#plat-banner-showoff');
          if (showOff) showOff.addEventListener('click', function() {
            platShowOnlyFilter = false;
            platTable.setData(window.__platOriginalData.slice());
            updatePlatBanner();
          });
        } else {
          filterBanner.style.display = 'none';
        }
      }

      function doPlatSearch() {
        var st = searchInput.value.trim();
        searchActive = st !== '';
        clearBtn.style.display = searchActive ? '' : 'none';
        platTable.setSearch(searchActive, st, searchCond);
        if (searchBtn) searchBtn.classList.toggle('active', searchActive);
        updatePlatBanner();
      }

      function clearPlatSearch() {
        searchActive = false;
        searchInput.value = '';
        clearBtn.style.display = 'none';
        if (searchBtn) searchBtn.classList.remove('active');
        platTable.setSearch(false, '', searchCond);
        updatePlatBanner();
      }
      if (searchInput) searchInput.addEventListener('input', doPlatSearch);
      if (clearBtn) clearBtn.addEventListener('click', clearPlatSearch);
      function closePlatPanels() {
        document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        document.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
      }

      if (searchBtn && condBtn && typeof SearchPanel !== 'undefined') {
        var ns = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(ns, searchBtn);
        searchBtn = ns;
        var nc = condBtn.cloneNode(true);
        condBtn.parentNode.replaceChild(nc, condBtn);
        condBtn = nc;
        SearchPanel.init({
          form: document.getElementById('plat-search-form'),
          condBtn: condBtn,
          toggleBtn: searchBtn,
          columns: PLAT_SEARCH_COLS,
          pageUrl: location.href,
          popupCheckboxes: true,
          emptyClass: 'search-cond-placeholder',
          closeAllPanels: closePlatPanels,
          labels: {
            cols: 'Столбцы',
            cond: 'Условие',
            emptyCols: 'Выберите столбцы…'
          },
          onApply: function(state) {
            searchCond = state.cond;
            searchActive = true;
            if (searchBtn) searchBtn.classList.add('active');
            doPlatSearch();
            closePlatPanels();
          },
          onClear: function() {
            searchCond = 'contains';
            doPlatSearch();
            closePlatPanels();
          },
          onToggle: function(state) {
            if (searchActive) {
              searchActive = false;
              if (searchInput) searchInput.value = '';
              if (clearBtn) clearBtn.style.display = 'none';
              if (searchBtn) searchBtn.classList.remove('active');
              platTable.setSearch(false, '', searchCond);
              updatePlatBanner();
            } else {
              searchActive = true;
              var st = searchInput ? searchInput.value.trim() : '';
              if (clearBtn) clearBtn.style.display = st ? '' : 'none';
              if (searchBtn) searchBtn.classList.add('active');
              platTable.setSearch(true, st, searchCond);
              updatePlatBanner();
            }
          }
        });
      }

      function refreshPlatData() {
        var savedIds = Array.from(platTable.getCheckedIds());
        fetch('invoice_form.php?mode=edit&id=' + invId + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d && d.html) {
              var tmp = document.createElement('div');
              tmp.innerHTML = d.html;
              var nt = tmp.querySelector('[data-plat-table]');
              if (nt) {
                try { platTable.setData(JSON.parse(nt.dataset.items || '[]')); window.__platData = platTable.getData(); } catch(e) {}
              }
            }
            savedIds.forEach(function(id) { platTable.getCheckedIds().add(id); });
            platTable._syncChecks();
            platTable._updateBatchUI();
          });
      }

      InlineEdit.init({
        tbody: tableEl.querySelector('tbody'),
        saveUrl: 'plat_field_save.php',
        fields: {
          sum:       { dbField: 'sum', type: 'text', label: 'Сумма' },
          plat_type: { dbField: 'plat_type', type: 'select', label: 'Вид платежа', options: PLAT_TYPE_OPTIONS },
          note:      { dbField: 'note', type: 'textarea', label: 'Примечание' }
        },
        onSaveSuccess: function(data, field) {
          if (data && data.ok && data.sum_plat !== undefined) {
            window.applyInvoice2Totals(data);
            autoSaveInvoice();
          }
          refreshPlatData();
        }
      });

      document.getElementById('plat-sel-clear')?.addEventListener('click', function(e) { e.preventDefault(); platTable.clearChecked(); });
      document.getElementById('plat-sel-invert')?.addEventListener('click', function(e) { e.preventDefault(); platTable.invertChecked(); });

      /* Batch actions */
      document.getElementById('plat-sel-show')?.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        if (platShowOnlyFilter) {
          platShowOnlyFilter = false;
          platTable.setData(window.__platOriginalData.slice());
          updatePlatBanner();
          return;
        }
        platShowOnlyFilter = true;
        window.__platOriginalData = platTable.getData().slice();
        var savedSel = platTable.getSelectedId();
        var filtered = window.__platOriginalData.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        platTable.setData(filtered);
        if (savedSel && !filtered.some(function(i) { return i.id === savedSel; })) {
          platTable.selectFirst();
        }
        updatePlatBanner();
      });
      document.getElementById('plat-sel-export')?.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        var data = platTable.getData();
        var items = data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var csv = '\uFEFF';
        csv += 'Дата/Время;Контрагент;Вид операции;Сумма;Вид платежа;Тип;Примечание\n';
        items.forEach(function(i) {
          csv += (i.datetime || '') + ';' + (i.client_name || '') + ';' + (i.zat_name || '') + ';' + (i.sum != null ? i.sum : '0') + ';' + (i.plat_type || '') + ';' + (i.out_flag === 1 ? 'Расход' : 'Приход') + ';' + (i.note || '') + '\n';
        });
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'payments.csv'; a.click();
        URL.revokeObjectURL(a.href);
      });
      document.getElementById('plat-sel-print')?.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        var data = platTable.getData();
        var items = data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var w = window.open('', '_blank', 'width=800,height=600');
        var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Печать</title><style>body{font:14px sans-serif;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 10px;text-align:left}th{background:#eee}</style></head><body><table><thead><tr><th>Дата/Время</th><th>Контрагент</th><th>Вид операции</th><th>Сумма</th><th>Вид платежа</th><th>Тип</th><th>Примечание</th></tr></thead><tbody>';
        items.forEach(function(i) {
          h += '<tr><td>' + (i.datetime || '') + '</td><td>' + (i.client_name || '') + '</td><td>' + (i.zat_name || '') + '</td><td>' + (i.sum != null ? i.sum : '0') + '</td><td>' + (i.plat_type || '') + '</td><td>' + (i.out_flag === 1 ? 'Расход' : 'Приход') + '</td><td>' + (i.note || '') + '</td></tr>';
        });
        h += '</tbody></table></body></html>';
        w.document.write(h);
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
      });
      document.getElementById('plat-sel-delete')?.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        if (!confirm('Удалить ' + ids.length + ' отмеченных платежей?')) return;
        var lastSumPlat = '';
        (function next(i) {
          if (i >= ids.length) {
            platTable.clearChecked();
            if (lastSumPlat !== undefined && lastSumPlat !== '') {
              window.applyInvoice2Totals({ sum_plat: lastSumPlat });
              window.invoice2Dirty = true;
            }
            autoSaveInvoice();
            refreshPlatData();
            return;
          }
          var fd = new FormData();
          fd.set('field', '_delete');
          fd.set('plat_id', String(ids[i]));
          fetch('plat_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d && d.ok && d.sum_plat !== undefined) lastSumPlat = d.sum_plat; next(i + 1); })
            .catch(function() { next(i + 1); });
        })(0);
      });

      window.__platTable = platTable;

      if (!window.__platHotkeysInited) {
        window.__platHotkeysInited = true;
        document.addEventListener('keydown', function(e) {
          if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
          var pt = window.__platTable;
          if (!pt || !pt.tbody) return;
          var rows = Array.from(pt.tbody.querySelectorAll('[data-row-id]'));
          if (rows.length === 0) return;
          var curId = pt.getSelectedId();
          var curIdx = -1;
          for (var k = 0; k < rows.length; k++) {
            if (parseInt(rows[k].dataset.rowId, 10) === curId) { curIdx = k; break; }
          }
          switch (e.key) {
            case 'Insert':
              e.preventDefault();
              var ab = document.getElementById('plat-add-btn');
              if (ab) ab.click();
              break;
            case 'Enter':
              e.preventDefault();
              if (curId > 0) { var eb = document.getElementById('plat-edit-btn'); if (eb && !eb.disabled) eb.click(); }
              break;
            case 'Delete':
              e.preventDefault();
              if (curId > 0) { var db = document.getElementById('plat-del-btn'); if (db && !db.disabled) db.click(); }
              break;
            case 'ArrowDown':
              e.preventDefault();
              if (curIdx < 0) { pt.selectFirst(); break; }
              if (curIdx < rows.length - 1) {
                var nd = parseInt(rows[curIdx + 1].dataset.rowId, 10);
                pt._selectRow(nd, rows[curIdx + 1]);
                rows[curIdx + 1].scrollIntoView({ block: 'nearest' });
              }
              break;
            case 'ArrowUp':
              e.preventDefault();
              if (curIdx > 0) {
                var pv = parseInt(rows[curIdx - 1].dataset.rowId, 10);
                pt._selectRow(pv, rows[curIdx - 1]);
                rows[curIdx - 1].scrollIntoView({ block: 'nearest' });
              }
              break;
            case 'Home':
              e.preventDefault();
              pt.selectFirst();
              var ft = pt.tbody.querySelector('[data-row-id].selected');
              if (ft) ft.scrollIntoView({ block: 'nearest' });
              break;
            case 'End':
              e.preventDefault();
              if (rows.length > 0) {
                var lt = rows[rows.length - 1];
                pt._selectRow(parseInt(lt.dataset.rowId, 10), lt);
                lt.scrollIntoView({ block: 'nearest' });
              }
              break;
          }
        });
      }
    })();
<?php endif; ?>
  </script>
</body>
</html>
