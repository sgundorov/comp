<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/invoice_columns.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';

$accessFlags = get_access_flags($conn, 'Invoice');

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$invoiceKind = (string)($_GET['kind'] ?? $_POST['kind'] ?? $_SESSION['invoice_kind'] ?? '');
$invoiceIsOffer = ($invoiceKind === 'offer');
$invoiceDoctypeId = $invoiceIsOffer ? 5 : 10;
$formCancelUrl = $invoiceIsOffer ? 'invoice.php?kind=offer' : 'invoice.php';
$pageTitleLabel = $invoiceIsOffer ? 'Коммерческое предложение' : 'Счет';
$pageIcon = $invoiceIsOffer ? 'img/invoice.png' : 'img/schet.png';

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
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM invoice WHERE doctype_id = $invoiceDoctypeId");
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
    $docType = $invoiceDoctypeId;
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

    if ($mode !== 'delete' && empty($_POST['auto_save'])) {
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
            $marksTblForDelete = $invoiceIsOffer ? 'kom' : 'invoice';
            $conn->query("DELETE FROM marks WHERE tbl = '$marksTblForDelete' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => '#' . $id]);
                exit;
            }
        header('Location: ' . $formCancelUrl);
        exit;
    }
    $formRedirectUrl = $formCancelUrl;

    $maxRetries = 10;
        $saved = false;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($mode === 'new' || $mode === 'copy') {
                $stmt = $conn->prepare("INSERT INTO invoice (doctype_id, number, date, time, client_id, state, store_id, payment_type, discount, sum_discount, sum, sum_nds, sum_plat, date_plat, sotr_id, pos, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                bind_auto($stmt, [$invoiceDoctypeId, $numVal, $values['date'], $values['time'], $values['client_id'], $values['state'], $values['store_id'], $values['payment_type'], $disc, $sumDisc, $sumVal, $sumNds, $sumPlat, $values['date_plat'], $values['sotr_id'], $values['pos'], $values['note']]);
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
                $nr = $conn->query("SELECT COALESCE(MAX(number),0)+1 AS next_num FROM invoice WHERE doctype_id = $invoiceDoctypeId");
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

        if ($newId > 0 && ($mode === 'new' || $mode === 'copy')) {
            $id = $newId;
            $mode = 'edit';
        }

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
            echo json_encode(['ok' => true, 'id' => $newId]);
            exit;
        }
        if ($isAjax) {
            $pageOfNew = 0;
            if (defined('PAGE_SIZE') && PAGE_SIZE > 0 && ($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'invoice', 'invoice_id', 'number', 'desc', $numVal, $newId, "doctype_id = $invoiceDoctypeId");
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
        header('Location: ' . $formRedirectUrl);
        exit;
    }
}

render:
$clientList = [];
$hideSql = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$crs = $conn->query("SELECT client_id, name FROM client" . $hideSql . " ORDER BY name");
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
$formActionUrl = $invoiceIsOffer ? 'invoice_form.php?kind=offer' : 'invoice_form.php';

if ($mode === 'edit') {
    $pageTitle = $pageTitleLabel . ' ' . $titleNum . ' (' . $currentClientName . ')';
} elseif ($mode === 'delete') {
    $pageTitle = $pageTitleLabel . ' ' . $titleNum . ' (удаление)';
} elseif ($mode === 'new') {
    $pageTitle = $pageTitleLabel . ': новый';
} elseif ($mode === 'copy') {
    $pageTitle = $pageTitleLabel . ' ' . $titleNum . ' (копия)';
} else {
    $pageTitle = $pageTitleLabel;
}
$isReadonly = ($mode === 'delete');

$stateOptions = ['Черновик', 'Выставлен', 'Оплачен', 'Отменен'];

function dv($v) { return fmt_num($v, 3); }

$inv2ProductList = [];
$hideSql = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$pr = $conn->query("SELECT product_id, product_name, code FROM product" . $hideSql . " ORDER BY product_name");
if ($pr) while ($p = $pr->fetch_assoc()) {
    $inv2ProductList[] = ['id' => (int)$p['product_id'], 'name' => $p['product_name']];
}

$inv2Table = new EmbeddedTable([
    'prefix' => 'inv2',
    'saveUrl' => 'invoice2_field_save.php',
    'parentField' => 'invoice_id',
    'childFormUrl' => 'invoice2_form.php' . ($invoiceIsOffer ? '?kind=offer' : ''),
    'columnResizeUrl' => 'invoice_column_width_save.php',
    'columnResizeTbl' => 'invoice2',
    'colWidths' => array_merge(['quant' => '100px', 'price' => '100px', 'discount' => '100px', 'sum' => '100px', 'sum_nds' => '80px'], load_columns_widths($conn, 'invoice2')),
    'childFormName' => 'Invoice2Form',
    'pageSize' => 15,
    'hasExport' => true,
    'hasPrint' => true,
    'hasImport' => true,
    'hasSearch' => true,
    'readonly' => $isReadonly,
    'totalsCallback' => 'applyInvoice2Totals',
    'columns' => [
        ['name' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'param' => 'product_id'],
        ['name' => 'quant', 'label' => 'Кол-во', 'type' => 'text'],
        ['name' => 'price', 'label' => 'Цена', 'type' => 'text'],
        ['name' => 'discount', 'label' => 'Скидка', 'type' => 'text'],
        ['name' => 'sum', 'label' => 'Сумма', 'type' => 'text', 'readonly' => true],
        ['name' => 'sum_nds', 'label' => 'НДС', 'type' => 'text', 'readonly' => true],
        ['name' => 'note', 'label' => 'Примечание', 'type' => 'textarea'],
    ],
    'columnLabels' => [
        'product_name' => 'Товар',
        'quant' => 'Кол-во',
        'price' => 'Цена',
        'discount' => 'Скидка',
        'sum' => 'Сумма',
        'sum_nds' => 'НДС',
        'note' => 'Примечание',
    ],
    'lookupData' => ['product_id' => $inv2ProductList],
    'accessFlags' => $accessFlags,
]);

$platTable = new EmbeddedTable([
    'prefix' => 'plat',
    'saveUrl' => 'plat_field_save.php',
    'parentField' => 'doc_id',
    'childFormUrl' => 'plat_form.php?doc_type=' . $invoiceDoctypeId . '&client_id=' . $values['client_id'] . '&sotr_id=' . $CurSotrID . '&zat_id=' . $zatIdForPlat . '&sum_in=' . urlencode(max(0, (float)$values['sum'] - (float)$values['sum_plat'])),
    'childFormName' => 'PlatForm',
    'colWidths' => ['datetime' => '140px', 'client_name' => 'auto', 'zat_name' => '150px', 'sum' => '100px', 'out_flag' => '80px', 'note' => 'auto'],
    'columnResizeUrl' => 'plat_column_width_save.php',
    'columnResizeTbl' => 'plat',
    'pageSize' => 15,
    'hasExport' => true,
    'hasPrint' => true,
    'hasSearch' => true,
    'readonly' => $isReadonly,
    'totalsCallback' => 'applyInvoice2Totals',
    'columns' => [
        ['name' => 'datetime', 'label' => 'Дата/Время'],
        ['name' => 'client_name', 'label' => 'Контрагент'],
        ['name' => 'zat_name', 'label' => 'Вид операции'],
        ['name' => 'sum', 'label' => 'Сумма'],
        ['name' => 'out_flag', 'label' => 'Тип'],
        ['name' => 'note', 'label' => 'Примечание', 'type' => 'textarea'],
    ],
    'columnLabels' => [
        'datetime' => 'Дата/Время',
        'client_name' => 'Контрагент',
        'zat_name' => 'Вид операции',
        'sum' => 'Сумма',
        'out_flag' => 'Тип',
        'note' => 'Примечание',
    ],
    'accessFlags' => $accessFlags,
]);

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="<?= $pageIcon ?>" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="<?= h($formActionUrl) ?>" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'kind', $invoiceKind) ?>
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
        <td class="form-label"><?= h($pageTitleLabel) ?> №</td>
        <td class="form-label">Дата<span class="required">*</span></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'number', $values['number'] > 0 ? $values['number'] : '', [
                'id' => 'inv-number',
                'readonly' => $isReadonly,
                'tabindex' => '-1',
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
<?php if ((int)($appSettings['nds_rate'] ?? 22) != 0): ?>
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
<?php endif; ?>
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
$itemsData = array_map(function($item) {
    return ['id' => (int)$item['invoice2_id'], 'product_id' => (int)$item['product_id'], 'code' => (string)$item['code'],
        'product_name' => (string)$item['product_name'], 'quant' => dv($item['quant']), 'price' => dv($item['price']),
        'discount' => dv($item['discount']), 'sum' => dv($item['sum']), 'sum_discount' => dv($item['sum_discount']),
        'sum_nds' => dv($item['sum_nds']), 'note' => (string)$item['note']];
}, $itemsList);
echo $inv2Table->render($itemsData);
?>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px" data-tab-placeholder="1">Сохраните документ, чтобы добавить товары</div>
<?php endif; ?>
  </div><!-- /.tab-pane (Товары) -->

  <div class="tab-pane" data-tab-index="2">
<?php if ($id > 0 && $mode !== 'new'):
$platData = array_map(function($p) {
    $dt = strtotime((string)$p['datetime']);
    return [
        'id' => (int)$p['plat_id'],
        'datetime' => $dt ? date('d.m.Y H:i', $dt) : '-',
        'client_name' => (string)($p['client_name'] ?? '-'),
        'zat_name' => (string)($p['zat_name'] ?? '-'),
        'sum' => (float)$p['sum'],
        'out_flag' => (int)$p['out_flag'] ? 'Расход' : 'Приход',
        'note' => (string)$p['note'],
    ];
}, $platList);
echo $platTable->render($platData);
?>
<?php else: ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px" data-tab-placeholder="2">Сохраните документ, чтобы добавить платежи</div>
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
       render_btn_link_icon_text('img/cancel.png', 'Отменить', $formCancelUrl, ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', $formCancelUrl, ['class'=>'btn-secondary'])]
) ?>
<?php if ($mode === 'new' && $id <= 0): ?>
<input type="hidden" name="auto_save_ready" value="1" />
<?php endif; ?>
</form>
<script>if(typeof FormModalCore!=='undefined'&&FormModalCore.initTabAutoSave)FormModalCore.initTabAutoSave([1,2]);</script>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'id' => (int)($newId ?? $id), 'html' => $formHtml, 'mode' => $mode, 'focusField' => $focusField]);
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
  <script src="assets/embedded-table.js"></script>
  <script src="assets/embedded-subtable.js"></script>
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
          });
        });
      }

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
                if (window.__inv2Table) window.__inv2Table.refresh();
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

      document.getElementById('inv2-product-btn')?.addEventListener('click', function() {
        var tbl = window.__inv2Table;
        if (!tbl) return;
        var sid = tbl.selectedId;
        if (!sid) return;
        var item = tbl.data.find(function(it) { return it.id === sid; });
        var productId = item ? (item.product_id || 0) : 0;
        if (!productId) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('tmc_form.php?mode=edit&id=' + productId, { onRestore: function() { tbl.refresh(); } });
        } else {
          window.open('tmc_form.php?mode=edit&id=' + productId, '_blank');
        }
      });
    })();
<?php $inv2Table->renderScripts(); ?>
<?php $platTable->renderScripts(); ?>
    if (typeof window.__openFormModal === 'undefined') {
      initInv2Table();
      initPlatTable();
    }
  </script>
</body>
</html>
