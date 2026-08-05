<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$mode      = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'new');
$ainv2Id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$number    = (int)($_GET['number'] ?? $_POST['number'] ?? 0);
$isAjax    = (string)($_GET['ajax'] ?? $_POST['ajax'] ?? '') === '1' || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
$errors    = [];

function _fmt_qty($v) { return fmt_num($v, 3); }
function _fmt_d2($v, $dec) { return fmt_num($v, $dec); }
function recalc_ainv_totals(mysqli $conn, int $number): array {
    if ($number <= 0) return ['sum' => '0.00', 'pos' => 0];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COUNT(*) FROM ainv2 WHERE number = ?");
    $stmt->bind_param('i', $number);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum = (float)$row[0];
    $pos = (int)$row[1];
    $upd = $conn->prepare("UPDATE ainv SET sum = ?, poz = ? WHERE number = ?");
    bind_auto($upd, [$sum, $pos, $number]);
    $upd->execute();
    $upd->close();
    return ['sum' => number_format($sum, 2, '.', ''), 'pos' => $pos];
}

$values = [
    'product_id'   => 0,
    'product_name' => '',
    'code'         => '',
    'quant_old'    => '',
    'quant'        => '',
    'dif'          => '',
    'price'        => '',
    'sum'          => '0.00',
    'state'        => '',
    'note'         => '',
];

if ($ainv2Id > 0) {
    $stmt = $conn->prepare("SELECT * FROM ainv2 WHERE id = ?");
    $stmt->bind_param('i', $ainv2Id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        $values['product_id']   = (int)$r['product_id'];
        $values['product_name'] = (string)$r['product_name'];
        $values['code']         = (string)$r['code'];
        $values['quant_old']    = (string)$r['quant_old'];
        $values['quant']        = (string)$r['quant'];
        $values['dif']          = (string)$r['dif'];
        $values['price']        = (string)$r['price'];
        $values['sum']          = (string)$r['sum'];
        $values['state']        = (string)$r['state'];
        $values['note']         = (string)$r['note'];
        $number = (int)$r['number'];
    }
}

if ($mode === 'copy') {
    $ainv2Id = 0;
}

$parent = null;
$parentAccepted = false;
if ($number > 0) {
    $stmt = $conn->prepare("SELECT number, date, time, firm_id, mesto_id, accept_flag FROM ainv WHERE number = ?");
    $stmt->bind_param('i', $number);
    $stmt->execute();
    $parent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $parentAccepted = $parent && (int)$parent['accept_flag'] === 1;
}

$productList = [];
$hideSql = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$q = $conn->query("SELECT product_id, product_name, code, residue, price_in FROM product" . $hideSql . " ORDER BY product_name");
while ($r = $q->fetch_assoc()) {
    $productList[] = [
        'id'       => (int)$r['product_id'],
        'name'     => $r['product_name'],
        'code'     => (string)$r['code'],
        'residue'  => (string)($r['residue'] ?: '0'),
        'price_in' => (string)($r['price_in'] ?: '0'),
    ];
}

$currentProductName = '';
if ($values['product_id'] > 0) {
    foreach ($productList as $p) {
        if ($p['id'] === $values['product_id']) { $currentProductName = $p['name']; break; }
    }
}

$isReadonly = $mode === 'delete' || $parentAccepted;

// ---- handle save ----
if ($isAjax && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    if ($parentAccepted) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Акт утверждён, редактирование запрещено']);
        exit;
    }
    if ($action === 'save') {
        $errors = [];
        $productId = (int)($_POST['product_id'] ?? 0);
        $quant = (float)str_replace(',', '.', trim((string)($_POST['quant'] ?? '')));
        $state = (int)($_POST['state'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if ($productId <= 0) $errors[] = 'Выберите товар';
        if ($state < 0 || $state > 10) $errors[] = 'Состояние должно быть от 0 до 10';

        $quantOld = 0.0;
        $price = 0.0;
        $productName = '';
        $code = '';
        if ($productId > 0) {
            $pStmt = $conn->prepare("SELECT product_name, code, residue, price_in FROM product WHERE product_id = ?");
            $pStmt->bind_param('i', $productId);
            $pStmt->execute();
            $pRes = $pStmt->get_result();
            if ($pRow = $pRes->fetch_assoc()) {
                $productName = (string)$pRow['product_name'];
                $code = (string)$pRow['code'];
                $quantOld = (float)($pRow['residue'] ?: 0);
                $price = (float)($pRow['price_in'] ?: 0);
            }
            $pStmt->close();
        }
        if ($ainv2Id > 0 && $productId === $values['product_id']) {
            $quantOld = (float)str_replace(',', '.', (string)($_POST['quant_old'] ?? '0'));
            $price = (float)str_replace(',', '.', (string)($_POST['price'] ?? '0'));
        }

        $dif = $quant - $quantOld;
        $sum = $price * $dif;

        if (empty($errors)) {
            if ($ainv2Id > 0) {
                $stmt = $conn->prepare("UPDATE ainv2 SET product_id = ?, code = ?, product_name = ?, quant_old = ?, quant = ?, dif = ?, price = ?, sum = ?, state = ?, note = ? WHERE id = ?");
                bind_auto($stmt, [$productId, $code, $productName, $quantOld, $quant, $dif, $price, $sum, $state, $note, $ainv2Id]);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO ainv2 (number, date, time, firm_id, mesto_id, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                bind_auto($stmt, [
                    $number,
                    $parent ? (string)$parent['date'] : date('Y-m-d'),
                    $parent ? (string)$parent['time'] : date('H:i:s'),
                    $parent ? (int)$parent['firm_id'] : 0,
                    $parent ? (int)$parent['mesto_id'] : 0,
                    $productId, $code, $productName, $quantOld, $quant, $dif, $price, $sum, $state, $note,
                ]);
                $stmt->execute();
                $ainv2Id = (int)$stmt->insert_id;
                $stmt->close();
            }
            $totals = recalc_ainv_totals($conn, $number);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'mode' => 'edit',
                'id' => $ainv2Id,
                'number' => $number,
                'product_id' => $productId,
                'product_name' => $productName,
                'quant_old' => _fmt_qty($quantOld),
                'quant' => _fmt_qty($quant),
                'dif' => _fmt_qty($dif),
                'price' => _fmt_d2($price, 2),
                'sum' => _fmt_d2($sum, 2),
                'state' => $state > 0 ? (string)$state : '',
                'note' => $note,
                'total_sum' => $totals['sum'],
                'pos' => $totals['pos'],
            ]);
            exit;
        }
    } elseif ($action === 'delete') {
        if ($ainv2Id > 0) {
            $stmt = $conn->prepare("DELETE FROM ainv2 WHERE id = ?");
            $stmt->bind_param('i', $ainv2Id);
            $stmt->execute();
            $stmt->close();
        }
        $totals = recalc_ainv_totals($conn, $number);
        $itemsList = [];
        if ($number > 0) {
            $stmt = $conn->prepare("SELECT id, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE number = ? ORDER BY id ASC");
            $stmt->bind_param('i', $number);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $itemsList[] = [
                    'id'           => (int)$r['id'],
                    'product_id'   => (int)$r['product_id'],
                    'code'         => (string)$r['code'],
                    'product_name' => (string)$r['product_name'],
                    'quant_old'    => _fmt_qty($r['quant_old']),
                    'quant'        => _fmt_qty($r['quant']),
                    'dif'          => _fmt_qty($r['dif']),
                    'price'        => _fmt_d2($r['price'], 2),
                    'sum'          => _fmt_d2($r['sum'], 2),
                    'state'        => (int)$r['state'] > 0 ? (string)(int)$r['state'] : '',
                    'note'         => (string)$r['note'],
                ];
            }
            $stmt->close();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            '_deleted' => true,
            'id' => $ainv2Id,
            'number' => $number,
            'total_sum' => $totals['sum'],
            'pos' => $totals['pos'],
            'sgroup_list' => $itemsList,
        ]);
        exit;
    }
}

if ($mode === 'new' && !$ainv2Id) {
    $values['quant'] = '';
}

$sumDisplay      = _fmt_d2($values['sum'], 2);
$quantOldDisplay = _fmt_qty($values['quant_old']);
$quantDisplay    = _fmt_qty($values['quant']);
$difDisplay      = _fmt_qty($values['dif']);
$priceDisplay    = _fmt_d2($values['price'], 2);

ob_start();
?>
<?php
$productNameForTitle = $values['product_name'] ?: '?';
$pageTitle = $mode === 'copy'   ? "Товар акта: $productNameForTitle (копия)"
           : ($mode === 'delete' ? "Товар акта: $productNameForTitle (удаление)"
           : ($ainv2Id > 0      ? "Товар акта: $productNameForTitle"
                                : 'Товар акта (новый)'));
$actionUrl = 'ainv2_form.php?mode=' . $mode . ($ainv2Id > 0 ? '&id=' . $ainv2Id : '') . '&number=' . $number;
?>
<style>.form-modal .lookup-wrap{max-width:100%;flex:1}.form-table{width:100%}</style>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/ainv.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form" method="post" action="<?= h($actionUrl) ?>" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'id', $ainv2Id) ?>
<?= render_input('hidden', 'number', $number) ?>
<?= render_input('hidden', 'quant_old', $quantOldDisplay) ?>
<?= render_input('hidden', 'price', $priceDisplay) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<table class="form-table">
  <tr>
    <td class="form-label" colspan="3">Товар<span class="required">*</span></td>
  </tr>
  <tr>
    <td colspan="3"><div style="display:flex;align-items:stretch;gap:0;width:100%"><?= render_lookup('product', 'product_id', $values['product_id'], $currentProductName,
        h(json_encode($productList, JSON_UNESCAPED_UNICODE)),
        'tmc_form.php?mode=new', $isReadonly, ['id' => 'a2-product-id']) ?><?php if ($values['product_id'] > 0): ?><button type="button" class="lookup-tool" title="Товар" id="a2-product-edit-btn" style="background:var(--tool);color:#fff;width:24px;height:24px;border-radius:0 1px 1px 0"><svg viewBox="0 0 24 24" style="width:14px;height:14px" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button><?php endif; ?></div></td>
  </tr>
  <tr>
    <td class="form-label">Штрихкод</td>
    <td class="form-label">Документальный остаток</td>
    <td class="form-label">Фактический остаток</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'code', $values['code'], ['id' => 'a2-code', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px']) ?></td>
    <td><?= render_input('text', 'quant_old_display', $quantOldDisplay, ['id' => 'a2-quant-old', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:110px']) ?></td>
    <td><?= render_input('text', 'quant', $quantDisplay, ['id' => 'a2-quant', 'style' => 'max-width:110px', 'readonly' => $isReadonly]) ?></td>
  </tr>
  <tr>
    <td class="form-label">Разница</td>
    <td class="form-label">Цена</td>
    <td class="form-label">Сумма</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'dif', $difDisplay, ['id' => 'a2-dif', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:110px']) ?></td>
    <td><?= render_input('text', 'price_display', $priceDisplay, ['id' => 'a2-price', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px']) ?></td>
    <td><?= render_input('text', 'sum', $sumDisplay, ['id' => 'a2-sum', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:120px;font-weight:bold']) ?></td>
  </tr>
  <tr>
    <td class="form-label">Состояние (0-10)</td>
    <td class="form-label">&nbsp;</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('number', 'state', $values['state'] > 0 ? (string)(int)$values['state'] : '', ['id' => 'a2-state', 'min' => '0', 'max' => '10', 'style' => 'max-width:80px', 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label" colspan="3">Примечание</td>
  </tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'note', $values['note'], ['id' => 'a2-note', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
  </tr>
  <tr>
    <td colspan="3"><?= render_form_note() ?></td>
  </tr>
</table>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','name'=>'action','value'=>'delete','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt="" /> Отменить</button>']
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','name'=>'action','value'=>'save','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt="" /> Отменить</button>']
) ?>
</form>
<style>
    .form-table td.form-label { font-size:12px; color:var(--muted); padding-bottom:2px; }
    .col-3 td { vertical-align:top; padding-bottom:6px; padding-right:28px; }
    .col-3 td:last-child { padding-right:0; }
    input.full { width:100%; box-sizing:border-box; }
</style>
<script>
(function() {
  var wrap = document.querySelector('.form-modal-body') || document.body;
  var quantInput = wrap.querySelector('#a2-quant');
  var quantOldInput = wrap.querySelector('#a2-quant-old');
  var difInput = wrap.querySelector('#a2-dif');
  var priceInput = wrap.querySelector('#a2-price');
  var sumInput = wrap.querySelector('#a2-sum');
  var codeInput = wrap.querySelector('#a2-code');
  var productIdInput = wrap.querySelector('#a2-product-id');
  var hiddenQuantOld = wrap.querySelector('input[name="quant_old"]');
  var hiddenPrice = wrap.querySelector('input[name="price"]');
  var isReadonly = wrap.querySelector('#a2-quant') ? wrap.querySelector('#a2-quant').readOnly : false;

  function _rf3(v) {
    if (v === 0) return '';
    return v.toFixed(3).replace(/\.?0+$/, '').replace('.', ',');
  }
  function _rf2(v) {
    if (v === 0) return '';
    return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
  }
  function recalc() {
    var qo = parseFloat((quantOldInput ? quantOldInput.value : '0').replace(',', '.')) || 0;
    var q = parseFloat((quantInput ? quantInput.value : '0').replace(',', '.')) || 0;
    var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
    var dif = q - qo;
    var sum = p * dif;
    if (difInput) difInput.value = _rf3(dif);
    if (sumInput) sumInput.value = _rf2(sum);
  }
  if (quantInput && !isReadonly) quantInput.addEventListener('input', recalc);
  var productEditBtn = document.getElementById('a2-product-edit-btn');
  if (productEditBtn) {
    productEditBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var pid = productIdInput ? parseInt(productIdInput.value, 10) : 0;
      if (pid > 0) {
        var url = 'tmc_form.php?mode=edit&id=' + pid;
        if (window.__openFormModal) window.__openFormModal(url);
      }
    });
  }
  if (productIdInput && !isReadonly) {
    productIdInput.addEventListener('change', function() {
      var pid = parseInt(this.value, 10);
      if (pid > 0) {
        var lookupEl = this.closest('[data-lookup]');
        var list = lookupEl ? (function(){ try { return JSON.parse(lookupEl.getAttribute('data-countries') || '[]'); } catch(e){ return []; } })() : [];
        for (var i = 0; i < list.length; i++) {
          if (list[i].id === pid) {
            if (codeInput) codeInput.value = list[i].code || '';
            if (quantOldInput) quantOldInput.value = _rf3(parseFloat(list[i].residue || '0'));
            if (hiddenQuantOld) hiddenQuantOld.value = _rf3(parseFloat(list[i].residue || '0'));
            if (priceInput) priceInput.value = _rf2(parseFloat(list[i].price_in || '0'));
            if (hiddenPrice) hiddenPrice.value = _rf2(parseFloat(list[i].price_in || '0'));
            recalc();
            break;
          }
        }
      }
    });
  }
  recalc();
})();
</script>
<?php
$html = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $html, 'focusField' => 'a2-product-id']);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
</head>
<body>
  <div class="page-wrap" id="pageWrap">
    <?= $html ?>
  </div>
  <script src="assets/lookup.js"></script>
  <script>
  (function() {
    if (window.__openFormModal) return;
    var quantInput = document.querySelector('#a2-quant');
    var quantOldInput = document.querySelector('#a2-quant-old');
    var difInput = document.querySelector('#a2-dif');
    var priceInput = document.querySelector('#a2-price');
    var sumInput = document.querySelector('#a2-sum');
    var codeInput = document.querySelector('#a2-code');
    var productIdInput = document.querySelector('#a2-product-id');
    var hiddenQuantOld = document.querySelector('input[name="quant_old"]');
    var hiddenPrice = document.querySelector('input[name="price"]');
    var isReadonly = quantInput ? quantInput.readOnly : false;
    function _rf3(v) { if (v === 0) return ''; return v.toFixed(3).replace(/\.?0+$/, '').replace('.', ','); }
    function _rf2(v) { if (v === 0) return ''; return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ','); }
    function recalc() {
      var qo = parseFloat((quantOldInput ? quantOldInput.value : '0').replace(',', '.')) || 0;
      var q = parseFloat((quantInput ? quantInput.value : '0').replace(',', '.')) || 0;
      var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
    var dif = q - qo;
      var sum = p * dif;
      if (difInput) difInput.value = _rf3(dif);
      if (sumInput) sumInput.value = _rf2(sum);
    }
    if (quantInput && !isReadonly) quantInput.addEventListener('input', recalc);
    if (productIdInput && !isReadonly) {
      productIdInput.addEventListener('change', function() {
        var pid = parseInt(this.value, 10);
        if (pid > 0) {
          var lookupEl = this.closest('[data-lookup]');
          var list = lookupEl ? (function(){ try { return JSON.parse(lookupEl.getAttribute('data-countries') || '[]'); } catch(e){ return []; } })() : [];
          for (var i = 0; i < list.length; i++) {
            if (list[i].id === pid) {
              if (codeInput) codeInput.value = list[i].code || '';
              if (quantOldInput) quantOldInput.value = _rf3(parseFloat(list[i].residue || '0'));
              if (hiddenQuantOld) hiddenQuantOld.value = _rf3(parseFloat(list[i].residue || '0'));
              if (priceInput) priceInput.value = _rf2(parseFloat(list[i].price_in || '0'));
              if (hiddenPrice) hiddenPrice.value = _rf2(parseFloat(list[i].price_in || '0'));
              recalc();
              break;
            }
          }
        }
      });
    }
    recalc();
  })();
  </script>
</body>
</html>
<?php
