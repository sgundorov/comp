<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/embedded-subtable-template.php';
require_once __DIR__ . '/config/invo_columns.php';

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
    $stmt = $conn->prepare("SELECT number, date, time, client_id, state, store_id, payment_type, discount, sum_discount, sum, sum_nds, sum_plat, date_plat, sotr_id, pos, note FROM invoice WHERE invoice_id = ?");
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
    $values['store_id'] = $CurStoreID ?? 0;
    $values['sotr_id']  = $CurSotrID ?? 0;
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
        $focusField = 'invo-date';
    }
    if ($values['client_id'] <= 0) {
        $errors[] = 'Поле «Контрагент» обязательно для заполнения.';
    }
    if ($values['store_id'] <= 0) {
        $errors[] = 'Поле «Участок» обязательно для заполнения.';
    }
    if ($values['sotr_id'] <= 0) {
        $errors[] = 'Поле «Сотрудник» обязательно для заполнения.';
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
            header('Location: invo.php');
            exit;
        }

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO invoice (doctype_id, number, date, time, client_id, state, store_id, payment_type, discount, sum_discount, sum, sum_nds, sum_plat, date_plat, sotr_id, pos, note) VALUES (10, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['state'], $values['store_id'], $values['payment_type'], $disc, $sumDisc, $sumVal, $sumNds, $sumPlat, $values['date_plat'], $values['sotr_id'], $values['pos'], $values['note']]);
            $stmt->execute();
            $newId = (int)$stmt->insert_id;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE invoice SET number = ?, date = ?, time = ?, client_id = ?, state = ?, store_id = ?, payment_type = ?, discount = ?, sum_discount = ?, sum = ?, sum_nds = ?, sum_plat = ?, date_plat = ?, sotr_id = ?, pos = ?, note = ? WHERE invoice_id = ?");
            bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['client_id'], $values['state'], $values['store_id'], $values['payment_type'], $disc, $sumDisc, $sumVal, $sumNds, $sumPlat, $values['date_plat'], $values['sotr_id'], $values['pos'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            $newId = $id;
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

        $isApply = ($_POST['action'] ?? '') === 'apply';

        if ($isAjax && !$isApply) {
            $pageOfNew = 0;
            if (defined('PAGE_SIZE') && PAGE_SIZE > 0 && ($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'invoice', 'invoice_id', 'number', 'desc', $numVal, $newId, 'doctype_id = 10');
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew]);
            exit;
        }
        if (!$isAjax && !$isApply) {
            header('Location: invo.php');
            exit;
        }

        // Apply: stay on form, reload for re-render
        if ($newId > 0) {
            $id = $newId;
            $mode = 'edit';
            $stmt = $conn->prepare("SELECT invoice2_id, product_id, code, product_name, quant, price, discount, sum, sum_discount, sum_nds, note, guarantee, guarant_unit FROM invoice2 WHERE invoice_id = ? ORDER BY invoice2_id ASC");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $itemsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

$clientList = [];
$cr = @$conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($cr) while ($cr2 = $cr->fetch_assoc()) $clientList[] = ['id' => (int)$cr2['client_id'], 'name' => (string)$cr2['name']];

$storeList = [];
$srs = @$conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($srs) while ($sr = $srs->fetch_assoc()) $storeList[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrList = [];
$sotrs = @$conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
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
    $pageTitle = 'Счет';
}
$isReadonly = ($mode === 'delete');

$platDataForSubtable = [];
if (!empty($platList)) {
    foreach ($platList as $p) {
        $dt = strtotime((string)$p['datetime']);
        $platDataForSubtable[] = [
            'id' => (int)$p['plat_id'],
            'datetime' => $dt ? date('d.m.Y H:i', $dt) : '-',
            'client_name' => (string)($p['client_name'] ?? '-'),
            'zat_name' => (string)($p['zat_name'] ?? '-'),
            'sum' => (string)(float)$p['sum'],
            'plat_type' => (string)$p['plat_type'],
            'out_flag' => (int)$p['out_flag'] ? 'Расход' : 'Приход',
            'note' => (string)$p['note'],
        ];
    }
}

$stateOptions = ['Черновик', 'Выставлен', 'Оплачен', 'Отменен'];

function dv($v) { return ((float)str_replace(',', '.', $v)) == 0 ? '' : (string)$v; }
function fmt_qty($v) { $n = (float)str_replace(',', '.', $v); if ($n == 0) return ''; $s = number_format($n, 3, '.', ''); $s = rtrim(rtrim($s, '0'), '.'); return $n == (int)$n ? (string)(int)$n : str_replace('.', ',', $s); }

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="invo_form.php" autocomplete="off" data-form-modal>
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
                'id' => 'invo-number',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td><div style="display:flex;gap:10px"><?= render_input('date', 'date', $values['date'] ?: date('Y-m-d'), [
                'id' => 'invo-date',
                'required' => !$isReadonly,
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:128px',
            ]) ?><?= render_input('time', 'time', $values['time'] ?: date('H:i'), [
                'id' => 'invo-time',
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
                'client_form.php?mode=new', $isReadonly, ['id' => 'invo-client-id', 'data-name-input' => 'cli-name']) ?></td>
      </tr>
      <tr>
        <td class="form-label">Скидка %</td>
        <td class="form-label">Сумма скидки</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'discount', dv($values['discount']), [
                'id' => 'invo-discount',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
                'style' => 'max-width:100px',
            ]) ?></td>
        <td><?= render_input('text', 'sum_discount', dv($values['sum_discount']), [
                'id' => 'invo-sum-discount',
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
                'id' => 'invo-sum',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'invo-pos',
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
                'id' => 'invo-sum-nds',
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
                'id' => 'invo-sum-plat',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px' . $sumPlatBg,
            ]) ?></td>
        <td><?= render_input('date', 'date_plat', $values['date_plat'], [
                'id' => 'invo-date-plat',
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
                'store_form.php?mode=new', $isReadonly, ['id' => 'invo-store-id', 'data-name-input' => 'store-name']) ?></td>
        <td><?= render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                'sotr_form.php?mode=new', $isReadonly, ['id' => 'invo-sotr-id', 'data-name-input' => 'sotr-name']) ?></td>
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
                'id' => 'invo-state-' . h(str_replace([' ', '.'], '_', $opt)),
            ];
            if ($opt === $values['state']) $radioAttrs['checked'] = true;
            if ($isReadonly) $radioAttrs['disabled'] = true;
            ?>
            <input<?= _render_btn_attrs($radioAttrs) ?> />
            <label class="field-radio-label" for="<?= h('invo-state-' . str_replace([' ', '.'], '_', $opt)) ?>"><?= h($opt) ?></label>
          <?php endforeach; ?>
        </div></td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], [
                'id' => 'invo-note',
                'class' => 'full',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
            ]) ?></td>
      </tr>
    </table>
  </div>

  <div class="tab-pane" data-tab-index="1">
    <?php if ($id > 0 && ($mode !== 'new')): ?>
    <?= render_embedded_subtable([
        'prefix'     => 'd2',
        'columns'    => [
            ['key' => 'product_name', 'label' => 'Товар'],
            ['key' => 'quant',        'label' => 'Кол-во'],
            ['key' => 'price',        'label' => 'Цена'],
            ['key' => 'discount',     'label' => 'Скидка'],
            ['key' => 'sum',          'label' => 'Сумма'],
            ['key' => 'note',         'label' => 'Примечание'],
        ],
        'colWidths'  => ['product_name' => '250px', 'quant' => '80px', 'price' => '100px', 'discount' => '80px', 'sum' => '100px', 'note' => 'auto'],
        'data'       => $itemsList ? array_map(function($i) { return ['id' => (int)$i['invoice2_id'], 'product_id' => (int)$i['product_id'], 'product_name' => $i['product_name'], 'quant' => fmt_qty($i['quant']), 'price' => dv($i['price']), 'discount' => fmt_qty($i['discount']), 'sum' => dv($i['sum']), 'note' => $i['note']]; }, $itemsList) : [],
        'hasExport'  => false,
        'hasPrint'   => false,
        'hasSearch'  => true,
    ]) ?>
    <?php elseif ($mode === 'new'): ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните счет, чтобы добавить товары</div>
    <?php endif; ?>
  </div>

  <div class="tab-pane" data-tab-index="2">
    <?php if ($id > 0 && ($mode !== 'new')): ?>
    <?= render_embedded_subtable([
        'prefix'     => 'plat',
        'columns'    => [
            ['key' => 'datetime',    'label' => 'Дата/Время'],
            ['key' => 'client_name', 'label' => 'Контрагент'],
            ['key' => 'zat_name',    'label' => 'Вид операции'],
            ['key' => 'sum',         'label' => 'Сумма'],
            ['key' => 'plat_type',   'label' => 'Вид платежа'],
            ['key' => 'out_flag',    'label' => 'Тип'],
            ['key' => 'note',        'label' => 'Примечание'],
        ],
        'colWidths'  => ['datetime' => '140px', 'client_name' => 'auto', 'zat_name' => '150px', 'sum' => '100px', 'plat_type' => '100px', 'out_flag' => '70px', 'note' => 'auto'],
        'data'       => $platDataForSubtable,
        'hasExport'  => false,
        'hasPrint'   => false,
        'hasSearch'  => true,
    ]) ?>
    <?php elseif ($mode === 'new'): ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните счет, чтобы добавить платежи</div>
    <?php endif; ?>
  </div>
</div>

<style>
.form-modal { max-width: 990px; }
.page--form { max-width: 990px; }
.form { max-width: 990px; }
.form-table { width: 100%; border-collapse: collapse; }
.form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
.form-table td:last-child { padding-right: 0; }
.form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
input.full { width: 100%; box-sizing: border-box; }
.field-radio-group { display: flex; gap: 16px; align-items: center; padding: 6px 0; flex-wrap: wrap; }
.field-radio-label { font-size: 14px; cursor: pointer; }
.field-radio { width: auto; margin: 0; }
</style>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'invo.php', ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'invo.php', ['class'=>'btn-secondary'])]
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
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/lookup.js"></script>
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
    })();
  </script>
  <script>
    <?php render_embedded_subtable_scripts([
        'prefix'           => 'd2',
        'columns'          => [
            ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'dbField' => 'product_id'],
            ['key' => 'quant',        'label' => 'Кол-во', 'align' => 'right'],
            ['key' => 'price',        'label' => 'Цена', 'align' => 'right'],
            ['key' => 'discount',     'label' => 'Скидка', 'align' => 'right'],
            ['key' => 'sum',          'label' => 'Сумма', 'align' => 'right'],
            ['key' => 'note',         'label' => 'Примечание'],
        ],
        'saveUrl'          => 'invoice2_field_save.php',
        'parentField'      => 'invoice_id',
        'childFormUrl'     => 'invoice2_form.php',
        'childFormName'    => 'invoice2',
        'hasExport'        => false,
        'hasPrint'         => false,
        'hasSearch'        => true,
        'columnResizeUrl'  => 'invoice2_column_width_save.php',
        'columnResizeTbl'  => 'invoice2',
        'lookupData' => [
            'product_name' => $productList,
        ],
        'totalsCallback' => 'applyInvoice2Totals',
    ]); ?>
    initD2Table();
  </script>
  <script>
    <?php render_embedded_subtable_scripts([
        'prefix'           => 'plat',
        'columns'          => [
            ['key' => 'datetime',    'label' => 'Дата/Время', 'readonly' => true],
            ['key' => 'client_name', 'label' => 'Контрагент', 'readonly' => true],
            ['key' => 'zat_name',    'label' => 'Вид операции', 'readonly' => true],
            ['key' => 'sum',         'label' => 'Сумма', 'align' => 'right'],
            ['key' => 'plat_type',   'label' => 'Вид платежа'],
            ['key' => 'out_flag',    'label' => 'Тип', 'readonly' => true],
            ['key' => 'note',        'label' => 'Примечание'],
        ],
        'saveUrl'          => 'plat_field_save.php',
        'parentField'      => 'doc_id',
        'childFormUrl'     => 'plat_form.php?doc_type=10&client_id=' . $values['client_id'] . '&sotr_id=' . $CurSotrID . '&zat_id=' . $zatIdForPlat . '&sum_in=' . urlencode($values['sum'] - $values['sum_plat']),
        'childFormName'    => 'plat',
        'hasExport'        => false,
        'hasPrint'         => false,
        'hasSearch'        => true,
        'totalsCallback' => 'applyInvoice2Totals',
    ]); ?>
    initPlatTable();
  </script>
</body>
</html>
