<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$mode      = (string)($_GET['mode'] ?? 'new');
$docum2Id  = (int)($_GET['id'] ?? 0);
$documId   = (int)($_GET['docum_id'] ?? 0);
$isAjax    = (string)($_GET['ajax'] ?? $_POST['ajax'] ?? '') === '1' || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
$errors    = [];

$values = [
    'product_id'    => 0,
    'product_name'  => '',
    'quant'         => '1',
    'price'         => '0',
    'discount'      => '0',
    'sum'           => '0.00',
    'sum_discount'  => '0.00',
    'note'          => '',
];

if ($docum2Id > 0) {
    $stmt = $conn->prepare("SELECT * FROM docum2 WHERE docum2_id = ?");
    $stmt->bind_param('i', $docum2Id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        $values['product_id']    = (int)$r['product_id'];
        $values['product_name']  = (string)$r['product_name'];
        $values['quant']         = (string)($r['quant'] ?: '1');
        $values['price']         = (string)($r['price'] ?: '0');
        $values['discount']      = (string)$r['discount'];
        $values['sum']           = (string)$r['sum'];
        $values['sum_discount']  = (string)$r['sum_discount'];
        $values['note']          = (string)$r['note'];
        $documId = (int)$r['docum_id'];
    }
}

$parentAccepted = false;
if ($documId > 0) {
    $stmt = $conn->prepare("SELECT discount, store_id, typeop, accept_flag FROM docum WHERE docum_id = ?");
    $stmt->bind_param('i', $documId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r && $docum2Id <= 0) {
        $values['discount'] = (string)$r['discount'];
    }
    $parentStoreId = $r ? (int)$r['store_id'] : 0;
    $parentTypeop = $r ? (int)$r['typeop'] : 120;
    $parentAccepted = $r && (int)$r['accept_flag'] === 1;
} else {
    $parentTypeop = 120;
}

if ($mode === 'copy') {
    $docum2Id = 0;
}

$productList = [];
$priceColD2 = in_array($parentTypeop, [20, 110], true) ? 'price_in' : 'price_out';
$q = $conn->query("SELECT product_id, product_name, article AS code, $priceColD2 AS price FROM product WHERE hide_flag = 0 ORDER BY product_name");
while ($r = $q->fetch_assoc()) {
    $productList[] = [
        'id'    => (int)$r['product_id'],
        'name'  => $r['product_name'],
        'code'  => (string)$r['code'],
        'price' => (string)$r['price'],
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

$residueList = [];
if ($values['product_id'] > 0) {
    $rStmt = $conn->prepare("SELECT r.quant, COALESCE(st.name, '?') AS store_name FROM residue r LEFT JOIN store st ON r.wh_id = st.store_id WHERE r.product_id = ? ORDER BY st.name");
    if ($rStmt) {
        $rStmt->bind_param('i', $values['product_id']);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        while ($rr = $rRes->fetch_assoc()) {
            $residueList[] = ['store_name' => (string)$rr['store_name'], 'quant' => (string)$rr['quant']];
        }
        $rStmt->close();
    }
}

function recalc_docum_totals(mysqli $conn, int $documId): array {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(sum),0), COALESCE(SUM(sum_discount),0), COUNT(*) FROM docum2 WHERE docum_id = ?");
    $stmt->bind_param('i', $documId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    $sum  = (float)$row[0];
    $sd   = (float)$row[1];
    $pos  = (int)$row[2];
    $spStmt = $conn->prepare("SELECT COALESCE(d.sum_plat,0), d.typeop, COALESCE(tp.prihod_flag,0) AS prihod_flag FROM docum d LEFT JOIN typeop tp ON tp.typeop_id = d.typeop WHERE d.docum_id = ?");
    $spStmt->bind_param('i', $documId);
    $spStmt->execute();
    $spRow = $spStmt->get_result()->fetch_assoc();
    $spStmt->close();
    $sumPlat = (float)($spRow['sum_plat'] ?? 0);
    $prihodFlag = (int)($spRow['prihod_flag'] ?? 0);
    $sumPlatRed = $prihodFlag ? ($sum > -$sumPlat) : ($sumPlat < $sum);
    $sumBalans = $sum - $sumPlat;
    $upd = $conn->prepare("UPDATE docum SET sum = ?, sum_discount = ?, pos = ?, sum_balans = ? WHERE docum_id = ?");
    bind_auto($upd, [$sum, $sd, $pos, $sumBalans, $documId]);
    $upd->execute();
    $upd->close();
    $sumPlatFmt = _fmt_d2($sumPlat, 2);
    return ['total_sum' => _fmt_d2($sum, 2), 'total_sum_discount' => _fmt_d2($sd, 2), 'pos' => $pos, 'sum_plat' => $sumPlatFmt, 'sum_plat_red' => $sumPlatRed];
}

function _fmt_qty($v) { return fmt_num($v, 3); }
function _fmt_d2($v, $dec) { return fmt_num($v, $dec); }

$isReadonly = $mode === 'delete' || $parentAccepted;

// ---- handle save ----
if ($isAjax && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    if ($action === 'save') {
        $errors = [];
        $productId = (int)($_POST['product_id'] ?? 0);
        $productName = trim((string)($_POST['product_name'] ?? ''));
        $quant = (float)str_replace(',', '.', (string)($_POST['quant'] ?? '1'));
        $price = (float)str_replace(',', '.', (string)($_POST['price'] ?? '0'));
        $discount = (float)str_replace(',', '.', (string)($_POST['discount'] ?? '0'));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($productId <= 0 && $productName === '') $errors[] = 'Укажите товар';
        if ($quant <= 0) $errors[] = 'Количество должно быть больше нуля';
        if (empty($errors)) {
            $sum = $quant * $price * (1 - $discount / 100);
            $sum_discount = $quant * $price * ($discount / 100);
            if ($docum2Id > 0) {
                $stmt = $conn->prepare("UPDATE docum2 SET product_id = ?, product_name = ?, quant = ?, price = ?, discount = ?, sum = ?, sum_discount = ?, note = ? WHERE docum2_id = ?");
                bind_auto($stmt, [$productId, $productName, $quant, $price, $discount, $sum, $sum_discount, $note, $docum2Id]);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO docum2 (docum_id, product_id, product_name, quant, price, discount, sum, sum_discount, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                bind_auto($stmt, [$documId, $productId, $productName, $quant, $price, $discount, $sum, $sum_discount, $note]);
                $stmt->execute();
                $docum2Id = $conn->insert_id;
                $stmt->close();
            }
            $totals = recalc_docum_totals($conn, $documId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $docum2Id,
                'docum_id' => $documId,
                'product_id' => $productId,
                'product_name' => $productName,
                'quant' => _fmt_qty($quant),
                'price' => _fmt_d2($price, 2),
                'discount' => _fmt_d2($discount, 1),
                'sum' => _fmt_d2($sum, 2),
                'sum_discount' => _fmt_d2($sum_discount, 2),
                'note' => $note,
                'total_sum' => $totals['total_sum'],
                'total_sum_discount' => $totals['total_sum_discount'],
                'pos' => $totals['pos'],
                'sum_plat' => $totals['sum_plat'],
                'sum_plat_red' => $totals['sum_plat_red'],
            ]);
            exit;
        }
    } elseif ($action === 'delete') {
        if ($docum2Id > 0) {
            $stmt = $conn->prepare("DELETE FROM docum2 WHERE docum2_id = ?");
            $stmt->bind_param('i', $docum2Id);
            $stmt->execute();
            $stmt->close();
        }
        $totals = recalc_docum_totals($conn, $documId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            '_deleted' => true,
            'id' => $docum2Id,
            'docum_id' => $documId,
            'total_sum' => $totals['total_sum'],
            'total_sum_discount' => $totals['total_sum_discount'],
            'pos' => $totals['pos'],
            'sum_plat' => $totals['sum_plat'],
            'sum_plat_red' => $totals['sum_plat_red'],
        ]);
        exit;
    }
}

$sumDisplay          = _fmt_d2($values['sum'], 2);
$sumDiscountDisplay  = _fmt_d2($values['sum_discount'], 2);
$quantDisplay        = _fmt_qty($values['quant']);
$discountDisplay     = _fmt_d2($values['discount'], 1);

ob_start();
?>
<?php
$productNameForTitle = $values['product_name'] ?: '?';
$pageTitle = $mode === 'copy'   ? "Товар документа: $productNameForTitle (копия)"
           : ($mode === 'delete' ? "Товар документа: $productNameForTitle (удаление)"
           : ($docum2Id > 0     ? "Товар документа: $productNameForTitle"
                                : 'Товар документа (новый)'));
?>
<style>.form-modal .lookup-wrap{max-width:100%;flex:1}.form-table{width:100%}</style>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form" method="post" action="<?= h('docum2_form.php?mode=' . $mode . ($docum2Id > 0 ? '&id=' . $docum2Id : '') . '&docum_id=' . $documId) ?>" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'docum2_id', $docum2Id) ?>
<?= render_input('hidden', 'docum_id', $documId) ?>

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
        'tmc_form.php?mode=new', $isReadonly, ['id' => 'd2-product-id']) ?><?php if (!$isReadonly && $values['product_id'] > 0): ?><button type="button" class="lookup-tool" title="Товар" id="d2-product-edit-btn" style="background:var(--tool);color:#fff;width:24px;height:24px;border-radius:0 1px 1px 0"><svg viewBox="0 0 24 24" style="width:14px;height:14px" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button><?php endif; ?></div></td>
  </tr>
  <tr>
    <td class="form-label">Кол-во</td>
    <td class="form-label">&nbsp;</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('text', 'quant', $quantDisplay, ['id' => 'd2-quant', 'style' => 'max-width:100px', 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Цена</td>
    <?php if (!in_array($parentTypeop, [127, 100], true)): ?><td class="form-label">Скидка %</td><?php endif; ?>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'price', $values['price'], ['id' => 'd2-price', 'style' => 'max-width:140px', 'readonly' => $isReadonly]) ?></td>
    <?php if (!in_array($parentTypeop, [127, 100], true)): ?><td><?= render_input('text', 'discount', $discountDisplay, ['id' => 'd2-discount', 'style' => 'max-width:100px', 'readonly' => $isReadonly]) ?></td><?php endif; ?>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Сумма</td>
    <?php if (!in_array($parentTypeop, [127, 100], true)): ?><td class="form-label">Сумма скидки</td><?php endif; ?>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><?= render_input('text', 'sum', $sumDisplay, ['id' => 'd2-sum', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:140px;font-weight:bold']) ?></td>
    <?php if (!in_array($parentTypeop, [127, 100], true)): ?><td><?= render_input('text', 'sum_discount', $sumDiscountDisplay, ['id' => 'd2-sum-discount', 'readonly' => true, 'tabindex' => '-1', 'style' => 'max-width:140px']) ?></td><?php endif; ?>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label" colspan="3">Примечание</td>
  </tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'note', $values['note'], ['id' => 'd2-note', 'class' => 'full', 'readonly' => $isReadonly]) ?></td>
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
  var priceInput = wrap.querySelector('#d2-price');
  var quantInput = wrap.querySelector('#d2-quant');
  var discountInput = wrap.querySelector('#d2-discount');
  var sumInput = wrap.querySelector('#d2-sum');
  var sumDiscInput = wrap.querySelector('#d2-sum-discount');
  function _rf(v) {
    if (v === 0) return '';
    return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
  }
  function recalc() {
    var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
    var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
    var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
    if (sumInput) sumInput.value = _rf(q * p * (1 - d / 100));
    if (sumDiscInput) sumDiscInput.value = _rf(q * p * (d / 100));
  }
  if (quantInput) quantInput.addEventListener('input', recalc);
  if (priceInput) priceInput.addEventListener('input', recalc);
  if (discountInput) discountInput.addEventListener('input', recalc);
  var productEditBtn = document.getElementById('d2-product-edit-btn');
  if (productEditBtn) {
    productEditBtn.addEventListener('click', function(e) {
      e.preventDefault();
      var pid = wrap.querySelector('#d2-product-id') ? wrap.querySelector('#d2-product-id').value : 0;
      if (pid > 0) {
        var url = 'tmc_form.php?mode=edit&id=' + pid;
        if (window.__openFormModal) window.__openFormModal(url);
      }
    });
  }
  var productIdInput = wrap.querySelector('#d2-product-id');
  if (productIdInput && priceInput) {
    productIdInput.addEventListener('change', function() {
      var pid = parseInt(this.value, 10);
      if (pid > 0) {
        var lookupEl = this.closest('[data-lookup]');
        var list = lookupEl ? JSON.parse(lookupEl.getAttribute('data-countries') || '[]') : [];
        for (var i = 0; i < list.length; i++) {
          if (list[i].id === pid) {
            priceInput.value = (list[i].price || '').replace('.', ',');
            recalc();
            break;
          }
        }
      }
    });
  }
})();
</script>
<?php
$html = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $html, 'focusField' => 'product-id']);
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
  <script src="assets/search-panel.js"></script>
  <script>
  (function() {
    if (window.__openFormModal) return;
    var priceInput = document.querySelector('#d2-price');
    var quantInput = document.querySelector('#d2-quant');
    var discountInput = document.querySelector('#d2-discount');
    var sumInput = document.querySelector('#d2-sum');
    var sumDiscInput = document.querySelector('#d2-sum-discount');
    function _rf(v) {
      if (v === 0) return '';
      return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
    }
    function recalc() {
      var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
      var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
      var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
      if (sumInput) sumInput.value = _rf(q * p * (1 - d / 100));
      if (sumDiscInput) sumDiscInput.value = _rf(q * p * (d / 100));
    }
    if (quantInput) quantInput.addEventListener('input', recalc);
    if (priceInput) priceInput.addEventListener('input', recalc);
    if (discountInput) discountInput.addEventListener('input', recalc);
    var productIdInput = document.querySelector('#d2-product-id');
    if (productIdInput && priceInput) {
      productIdInput.addEventListener('change', function() {
        var pid = parseInt(this.value, 10);
        if (pid > 0) {
          var lookupEl = this.closest('[data-lookup]');
          var list = lookupEl ? JSON.parse(lookupEl.getAttribute('data-countries') || '[]') : [];
          for (var i = 0; i < list.length; i++) {
            if (list[i].id === pid) {
              priceInput.value = (list[i].price || '').replace('.', ',');
              recalc();
              break;
            }
          }
        }
      });
    }
  })();
  </script>
</body>
</html>
<?php
?>
