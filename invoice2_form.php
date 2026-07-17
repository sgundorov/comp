<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$mode       = (string)($_GET['mode'] ?? 'new');
$invoice2Id = (int)($_GET['id'] ?? $_POST['invoice2_id'] ?? 0);
$invoiceId  = (int)($_GET['invoice_id'] ?? 0);
$isAjax     = (string)($_GET['ajax'] ?? $_POST['ajax'] ?? '') === '1' || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
$isOffer    = ((string)($_GET['kind'] ?? '') === 'offer');
$errors     = [];

$values = [
    'product_id'    => 0,
    'product_name'  => '',
    'quant'         => '1',
    'price'         => '0',
    'discount'      => '0',
    'sum'           => '0.00',
    'sum_discount'  => '0.00',
    'sum_nds'       => '0.00',
    'note'          => '',
];

if ($invoice2Id > 0) {
    $stmt = $conn->prepare("SELECT * FROM invoice2 WHERE invoice2_id = ?");
    $stmt->bind_param('i', $invoice2Id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        $values['product_id']   = (int)$r['product_id'];
        $values['product_name'] = (string)$r['product_name'];
        $values['quant']        = (string)($r['quant'] ?: '1');
        $values['price']        = (string)($r['price'] ?: '0');
        $values['discount']     = (string)$r['discount'];
        $values['sum']          = (string)$r['sum'];
        $values['sum_discount'] = (string)$r['sum_discount'];
        $values['note']         = (string)$r['note'];
        $invoiceId = (int)$r['invoice_id'];
    }
}

if ($invoiceId > 0) {
    $stmt = $conn->prepare("SELECT discount FROM invoice WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r && $invoice2Id <= 0) {
        $values['discount'] = (string)$r['discount'];
    }
}

if ($mode === 'copy') {
    $invoice2Id = 0;
}

$productList = [];
$q = $conn->query("SELECT product_id, product_name, code, price_out FROM product ORDER BY product_name");
while ($r = $q->fetch_assoc()) {
    $productList[] = [
        'id'        => (int)$r['product_id'],
        'name'      => $r['product_name'],
        'code'      => (string)$r['code'],
        'price_out' => (string)($r['price_out'] ?: '0'),
    ];
}

$currentProductName = '';
if ($values['product_id'] > 0) {
    foreach ($productList as $p) {
        if ($p['id'] === $values['product_id']) {
            $currentProductName = $p['name'];
            break;
        }
    }
}

function recalc_invoice_totals(mysqli $conn, int $invoiceId): array {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COALESCE(SUM(sum_nds),0), COUNT(*) FROM invoice2 WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum   = (float)$row[0];
    $sd    = (float)$row[1];
    $snds  = (float)$row[2];
    $pos   = (int)$row[3];
    $ps = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = (SELECT doctype_id FROM invoice WHERE invoice_id = ?)");
    $ps->bind_param('ii', $invoiceId, $invoiceId);
    $ps->execute();
    $sumPlat = (float)$ps->get_result()->fetch_row()[0];
    $ps->close();
    $upd = $conn->prepare("UPDATE invoice SET sum = ?, sum_discount = ?, sum_nds = ?, pos = ?, sum_plat = ? WHERE invoice_id = ?");
    bind_auto($upd, [$sum, $sd, $snds, $pos, $sumPlat, $invoiceId]);
    $upd->execute();
    $upd->close();
    return ['sum' => number_format($sum, 2, '.', ''), 'sum_discount' => number_format($sd, 2, '.', ''), 'sum_nds' => number_format($snds, 2, '.', ''), 'pos' => $pos, 'sum_plat' => number_format($sumPlat, 2, '.', '')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'delete' && $invoice2Id > 0) {
        $stmt = $conn->prepare("DELETE FROM invoice2 WHERE invoice2_id = ?");
        $stmt->bind_param('i', $invoice2Id);
        $stmt->execute();
        $stmt->close();
        $totals = recalc_invoice_totals($conn, $invoiceId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'id' => $invoice2Id,
            '_deleted' => true,
            'sum' => $totals['sum'],
            'sum_discount' => $totals['sum_discount'],
            'sum_nds' => $totals['sum_nds'],
            'pos' => $totals['pos'],
            'sum_plat' => $totals['sum_plat'],
        ]);
        exit;
    }
    $values['product_id']   = (int)($_POST['product_id'] ?? 0);
    $values['quant']        = str_replace(',', '.', trim((string)($_POST['quant'] ?? '1')));
    $values['price']        = str_replace(',', '.', trim((string)($_POST['price'] ?? '0')));
    $values['discount']     = str_replace(',', '.', trim((string)($_POST['discount'] ?? '0')));
    $values['note']         = trim((string)($_POST['note'] ?? ''));
    $quant    = max(0, (float)$values['quant']);
    $price    = max(0, (float)$values['price']);
    $discount = max(0, (float)$values['discount']);
    $sum          = $quant * $price * (1 - $discount / 100);
    $sum_discount = $quant * $price * ($discount / 100);
    $values['sum']          = number_format($sum, 2, '.', '');
    $values['sum_discount'] = number_format($sum_discount, 2, '.', '');

    if ($values['product_id'] <= 0) {
        $errors[] = 'Выберите товар';
    }
    if ($quant <= 0) {
        $errors[] = 'Количество должно быть больше 0';
    }

    if (empty($errors)) {
        $productName = '';
        foreach ($productList as $p) {
            if ($p['id'] === $values['product_id']) { $productName = $p['name']; break; }
        }
        if ($invoice2Id > 0) {
            $stmt = $conn->prepare("UPDATE invoice2 SET product_id = ?, product_name = ?, quant = ?, price = ?, discount = ?, sum = ?, sum_discount = ?, sum_nds = 0, note = ? WHERE invoice2_id = ?");
            bind_auto($stmt, [$values['product_id'], $productName, $quant, $price, $discount, $sum, $sum_discount, $values['note'], $invoice2Id]);
            $stmt->execute();
            $stmt->close();
            if ($invoiceId <= 0) {
                $q2 = $conn->query("SELECT invoice_id FROM invoice2 WHERE invoice2_id = $invoice2Id");
                $invoiceId = $q2 && ($r2 = $q2->fetch_assoc()) ? (int)$r2['invoice_id'] : 0;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO invoice2 (invoice_id, product_id, product_name, quant, price, discount, sum, sum_discount, sum_nds, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
            bind_auto($stmt, [$invoiceId, $values['product_id'], $productName, $quant, $price, $discount, $sum, $sum_discount, $values['note']]);
            $stmt->execute();
            $invoice2Id = $conn->insert_id;
            $stmt->close();
        }

        $totals = recalc_invoice_totals($conn, $invoiceId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'id' => $invoice2Id,
            'product_id' => $values['product_id'],
            'product_name' => $productName,
            'quant' => number_format($quant, 3, '.', ''),
            'price' => number_format($price, 2, '.', ''),
            'discount' => number_format($discount, 1, '.', ''),
            'sum' => number_format($sum, 2, '.', ''),
            'sum_discount' => number_format($sum_discount, 2, '.', ''),
            'sum_nds' => number_format(0, 2, '.', ''),
            'note' => $values['note'],
            'total_sum' => $totals['sum'],
            'total_sum_discount' => $totals['sum_discount'],
            'total_sum_nds' => $totals['sum_nds'],
            'pos' => $totals['pos'],
            'sum_plat' => $totals['sum_plat'],
        ]);
        exit;
    }
}

$sumDisplay          = fmt_num($values['sum'], 2);
$sumDiscountDisplay  = fmt_num($values['sum_discount'], 2);
$priceDisplay        = fmt_num($values['price'], 2);
$quantDisplay        = fmt_num($values['quant'], 3);
$discountDisplay     = fmt_num($values['discount'], 2);

ob_start();
?>
<?php
$productNameForTitle = $values['product_name'] ?: '?';
$docLabel  = $isOffer ? 'КП' : 'счета';
$pageTitle = $mode === 'copy'   ? "Товар $docLabel: $productNameForTitle (копия)"
           : ($mode === 'delete' ? "Товар $docLabel: $productNameForTitle (удаление)"
           : ($invoice2Id > 0    ? "Товар $docLabel: $productNameForTitle"
                                 : "Товар $docLabel (новый)"));
$isReadonly = $mode === 'delete';
$actionUrl = 'invoice2_form.php?mode=' . $mode . ($invoice2Id > 0 ? '&id=' . $invoice2Id : '') . '&invoice_id=' . $invoiceId;
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form" method="post" action="<?= $actionUrl ?>" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'invoice2_id', $invoice2Id) ?>
<?= render_input('hidden', 'invoice_id', $invoiceId) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<table class="form-table">
  <tr>
    <td class="form-label" colspan="3">Товар<span class="required">*</span></td>
  </tr>
  <tr>
    <td colspan="3"><div style="display:flex;align-items:stretch;gap:0"><?= render_lookup('product', 'product_id', $values['product_id'], $currentProductName,
        h(json_encode($productList, JSON_UNESCAPED_UNICODE)),
        'tmc_form.php?mode=new', $isReadonly, ['id' => 'product-id']) ?><?php if (!$isReadonly && $values['product_id'] > 0): ?><button type="button" class="lookup-tool" title="Товар" id="inv2-product-edit-btn" style="background:var(--tool);color:#fff;width:24px;height:24px;border-radius:0 1px 1px 0"><svg viewBox="0 0 24 24" style="width:14px;height:14px" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button><?php endif; ?></div></td>
  </tr>
  <tr>
    <td class="form-label">Кол-во</td>
    <td class="form-label">&nbsp;</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('text', 'quant', $quantDisplay, ['id' => 'inv2-quant', 'style' => 'max-width:100px', 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Цена</td>
    <td class="form-label">Скидка %</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'price', $priceDisplay, ['id' => 'inv2-price', 'style' => 'max-width:140px', 'readonly' => $isReadonly]) ?></td>
    <td><?= render_input('text', 'discount', $discountDisplay, ['id' => 'inv2-discount', 'style' => 'max-width:100px', 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Сумма</td>
    <td class="form-label">Сумма скидки</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'sum', $sumDisplay, ['id' => 'inv2-sum', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:140px;font-weight:bold']) ?></td>
    <td><?= render_input('text', 'sum_discount', $sumDiscountDisplay, ['id' => 'inv2-sum-discount', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:140px']) ?></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label" colspan="3">Примечание</td>
  </tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'note', $values['note'], ['id' => 'inv2-note', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
  </tr>
  <tr>
    <td colspan="3"><?= render_form_note() ?></td>
  </tr>
</table>

<?= render_form_actions($isReadonly ? [
    render_btn_danger('img/delete.png', 'Удалить', ['type' => 'submit', 'formnovalidate' => true, 'autofocus' => true]),
    '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отмена</button>',
] : [
    render_btn_primary('img/save.png', 'Сохранить', ['type' => 'submit', 'formnovalidate' => true]),
    '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отмена</button>',
]) ?>
</form>

<style>
.invoice2-form-recalc { font-size:12px; color:var(--muted); margin-top:4px; }
.form-table { width: 100%; border-collapse: collapse; }
.form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
.form-table td:last-child { padding-right: 0; }
.form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
.form-table tr.col-3 td { width: 33%; }
input.num { width: 120px; text-align: right; }
input.full { width: 100%; box-sizing: border-box; }
textarea.full { width: 100%; box-sizing: border-box; }
</style>
<script>
(function(){
  var btn = document.getElementById('inv2-product-edit-btn');
  if (!btn || btn.__bound) return;
  btn.__bound = true;
  btn.addEventListener('click', function(){
    var wrap = btn.closest('[data-form-modal]') || btn.closest('form');
    if (!wrap) return;
    var h = wrap.querySelector('[name="product_id"]');
    var pid = h ? parseInt(h.value, 10) : 0;
    if (!pid) return;
    var fn = window.__openFormModal || window.openFormModal;
    if (typeof fn === 'function') {
      fn('tmc_form.php?mode=edit&id=' + pid, {
        onRestore: function(d, bodyEl) {
          if (!d || !d.ok) return;
          var root = bodyEl || document;
          setTimeout(function(){
            var ph = root.querySelector('[name="product_id"]');
            if (ph && d.id) ph.value = d.id;
            var li = root.querySelector('.lookup-input');
            if (li && d.product_name) li.value = d.product_name;
          }, 100);
        }
      });
    }
  });
})();
</script>
<?php
$html = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $html]);
} else {
    ?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
  <link rel="stylesheet" href="assets/table.css" />
</head>
<body>
<div class="page-wrap" id="pageWrap">
  <?= $html ?>
</div>
<script>
(function () {
  var wrap = document.getElementById('pageWrap');
  function init() {
    var listEl = wrap.querySelector('[data-countries]');
    if (!listEl) return;
    var raw = listEl.getAttribute('data-countries');
    if (!raw) return;
    var data;
    try { data = JSON.parse(raw); } catch(e) { return; }
    var productIdInput = wrap.querySelector('[name="product_id"]');
    var priceInput = wrap.querySelector('#inv2-price');
    var quantInput = wrap.querySelector('#inv2-quant');
    var discountInput = wrap.querySelector('#inv2-discount');
    var sumInput = wrap.querySelector('#inv2-sum');
    var sumDiscInput = wrap.querySelector('#inv2-sum-discount');

    function doRecalc() {
      var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
      var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
      var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
      var sum = q * p * (1 - d / 100);
      var sumD = q * p * (d / 100);
      if (sumInput) { var sf = sum.toFixed(2); sumInput.value = sf === '0.00' ? '' : sf.replace('.', ','); }
      if (sumDiscInput) { var sdf = sumD.toFixed(2); sumDiscInput.value = sdf === '0.00' ? '' : sdf.replace('.', ','); }
    }

    function onProductSelect(id, name) {
      var item = data.find(function (p) { return p.id === id; });
      if (!item) return;
      if (priceInput) priceInput.value = item.price_out && item.price_out != '0' ? item.price_out : '';
      doRecalc();
    }

    var oldChoose = null;
    var lookupWraps = wrap.querySelectorAll('[data-lookup]');
    lookupWraps.forEach(function (root) {
      var d;
      try { d = JSON.parse(root.getAttribute('data-countries') || '[]'); } catch(e) { return; }
      if (!Array.isArray(d)) return;
      var bound = window.bindLookup({ root: root, data: d, readonly: false, onSelect: onProductSelect });
      root.dataset.lookupInited = '1';
    });

    if (quantInput) quantInput.addEventListener('input', doRecalc);
    if (priceInput) priceInput.addEventListener('input', doRecalc);
    if (discountInput) discountInput.addEventListener('input', doRecalc);
    if (quantInput && !quantInput.value) quantInput.value = '1';
    doRecalc();
  }
  init();
})();
</script>
</body>
</html>
<?php
}
