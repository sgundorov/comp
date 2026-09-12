<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$showHoursFlag  = (int)($appSettings['ShowHoursFlag'] ?? 0) === 1;
$showDaysFlag   = (int)($appSettings['ShowDaysFlag'] ?? 0) === 1;
$showMonthsFlag = (int)($appSettings['ShowMonthsFlag'] ?? 0) === 1;

function fetch_price_list(mysqli $conn, int $productId, int $prplanId): array {
    if ($productId <= 0 || $prplanId <= 0) return [];
    $stmt = $conn->prepare("SELECT price_id AS id, name, bdays, edays, bmonths, emonths, btime, etime, price, pricef, hprice, mprice, fixed_flag
        FROM price WHERE product_id = ? AND prplan_id = ? ORDER BY price_id ASC");
    $stmt->bind_param('ii', $productId, $prplanId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function norm_time_val(string $v): string {
    return norm_time_smart($v) ?: '00:00:00';
}

function disp_time($v): string {
    $v = trim((string)$v);
    if ($v === '' || preg_match('/^0{1,2}:00(:00)?$/', $v)) return '';
    $v = substr($v, 0, 5);
    if ($v[0] === '0' && isset($v[1]) && $v[1] !== ':') $v = substr($v, 1);
    return $v;
}

function disp_num($v) {
    $f = (float)$v;
    return $f == 0 ? '' : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
}

$mode      = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id        = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$prplanId  = (int)($_GET['prplan_id'] ?? $_POST['prplan_id'] ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$values = [
    'name' => '', 'bdays' => 0, 'edays' => 0, 'bmonths' => 0, 'emonths' => 0,
    'btime' => '', 'etime' => '',
    'price' => 0, 'pricef' => 0, 'hprice' => 0, 'mprice' => 0,
    'fixed_flag' => 0,
];
$productName = '';
$prplanName = '';
$origName = '';

if ($productId > 0) {
    $r = @$conn->query("SELECT product_name FROM product WHERE product_id = " . $productId);
    if ($r && $row = $r->fetch_assoc()) $productName = (string)$row['product_name'];
}
if ($prplanId > 0) {
    $r = @$conn->query("SELECT prplan FROM prplan WHERE prplan_id = " . $prplanId);
    if ($r && $row = $r->fetch_assoc()) $prplanName = (string)$row['prplan'];
}
if ($productId <= 0 || $prplanId <= 0) {
    $errors[] = 'Не указан товар или тарифный план.';
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = @$conn->prepare("SELECT * FROM price WHERE price_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $row = $r->fetch_assoc()) {
            foreach ($values as $k => $v) { $values[$k] = $row[$k] ?? $v; }
            $productId = (int)$row['product_id'];
            $prplanId  = (int)$row['prplan_id'];
        } else {
            $errors[] = 'Запись не найдена.';
        }
        $stmt->close();
    }
}
$origName = (string)$values['name'];

$isReadonly = ($mode === 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name']       = trim((string)($_POST['name'] ?? ''));
    $values['bdays']      = (int)($_POST['bdays'] ?? 0);
    $values['edays']      = (int)($_POST['edays'] ?? 0);
    $values['bmonths']    = (int)($_POST['bmonths'] ?? 0);
    $values['emonths']    = (int)($_POST['emonths'] ?? 0);
    $values['btime']      = norm_time_val((string)($_POST['btime'] ?? ''));
    $values['etime']      = norm_time_val((string)($_POST['etime'] ?? ''));
    $values['price']      = (float)str_replace(',', '.', (string)($_POST['price'] ?? '0'));
    $values['pricef']     = (float)str_replace(',', '.', (string)($_POST['pricef'] ?? '0'));
    $values['hprice']     = (float)str_replace(',', '.', (string)($_POST['hprice'] ?? '0'));
    $values['mprice']     = (float)str_replace(',', '.', (string)($_POST['mprice'] ?? '0'));
    $values['fixed_flag'] = (int)(!empty($_POST['fixed_flag']));
    // Фиксированный тариф: поля etime/hprice/mprice неактивны и не сохраняются
    if ($values['fixed_flag'] === 1) {
        $values['etime']  = '00:00:00';
        $values['hprice'] = 0;
        $values['mprice'] = 0;
    }

    if (!$isReadonly && $values['name'] === '') {
        $errors[] = 'Введите название тарифа.';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM price WHERE price_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, '_deleted' => true,
                    'price_list' => fetch_price_list($conn, $productId, $prplanId),
                    'sgroup_list' => fetch_price_list($conn, $productId, $prplanId)], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: tmc.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO price (product_id, prplan_id, name, bdays, edays, bmonths, emonths, btime, etime, price, pricef, hprice, mprice, fixed_flag)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$productId, $prplanId, $values['name'], $values['bdays'], $values['edays'],
                $values['bmonths'], $values['emonths'],
                $values['btime'], $values['etime'], $values['price'], $values['pricef'],
                $values['hprice'], $values['mprice'], $values['fixed_flag']]);
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            $stmt->close();
            $id = $newId;
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => 'new', 'id' => $newId, 'name' => $values['name'],
                    'price_list' => fetch_price_list($conn, $productId, $prplanId),
                    'sgroup_list' => fetch_price_list($conn, $productId, $prplanId)], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: tmc.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE price SET name=?, bdays=?, edays=?, bmonths=?, emonths=?, btime=?, etime=?, price=?, pricef=?, hprice=?, mprice=?, fixed_flag=? WHERE price_id=?");
            bind_auto($stmt, [$values['name'], $values['bdays'], $values['edays'],
                $values['bmonths'], $values['emonths'],
                $values['btime'], $values['etime'], $values['price'], $values['pricef'],
                $values['hprice'], $values['mprice'], $values['fixed_flag'], $id]);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => 'edit', 'id' => $id, 'name' => $values['name'],
                    'price_list' => fetch_price_list($conn, $productId, $prplanId),
                    'sgroup_list' => fetch_price_list($conn, $productId, $prplanId)], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: tmc.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Тариф товара (новый)',
    'edit'   => 'Тариф товара: ' . ($origName !== '' ? $origName : ('#' . $id)),
    'copy'   => 'Тариф товара: ' . $origName . ' (копия)',
    'delete' => 'Тариф товара: ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Тариф товара';

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<style>
  .form-table { width:100%; border-collapse:collapse; }
  .form-table td { vertical-align:top; padding-bottom:6px; padding-right:24px; }
  .form-table td:last-child { padding-right:0; }
  .form-table td.form-label { font-size:12px; color:var(--muted); padding-bottom:2px; }
  input.num { width:120px; text-align:right; }
  input.half { width:60px; }
  .form .lookup-wrap { max-width:100%; width:100%; box-sizing:border-box; }
</style>
<form class="form" method="post" action="price_form.php?ajax=1" autocomplete="off" data-form-modal>
<input type="hidden" name="mode" value="<?= h($mode) ?>" />
<input type="hidden" name="id" value="<?= (int)$id ?>" />
<input type="hidden" name="product_id" value="<?= (int)$productId ?>" />
<input type="hidden" name="prplan_id" value="<?= (int)$prplanId ?>" />

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php $roAttrs = ['readonly' => true, 'tabindex' => '-1']; ?>
<table class="form-table">
  <tr><td class="form-label" colspan="3">Товар</td></tr>
  <tr>
    <td colspan="3"><?= render_lookup('product', 'product_id', $productId, $productName, '[]', '', true, ['id' => 'pf-product']) ?></td>
  </tr>
  <!-- Тарифный план | Фиксированный тариф -->
  <tr>
    <td class="form-label">Тарифный план</td>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_lookup('prplan', 'prplan_id', $prplanId, $prplanName, '[]', '', true, ['id' => 'pf-prplan']) ?></td>
    <td>
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="checkbox" name="fixed_flag" id="pf-fixed" value="1"<?= $values['fixed_flag'] ? ' checked' : '' ?><?= $isReadonly ? ' disabled' : '' ?> /> Фиксированный тариф
      </label>
    </td>
    <td>&nbsp;</td>
  </tr>

  <!-- Дни: Дней от [bdays][edays] | За день [price] | Выходной [pricef] -->
  <?php if ($showDaysFlag): ?>
  <tr>
    <td class="form-label">Дней от / по</td>
    <td class="form-label">За день</td>
    <td class="form-label">Выходной</td>
  </tr>
  <tr class="col-3">
    <td><div style="display:flex;gap:8px;align-items:center"><?= render_input('text', 'bdays', (int)$values['bdays'] == 0 ? '' : (int)$values['bdays'], array_merge(['id' => 'pf-bdays', 'class' => 'num half'], $isReadonly ? ['readonly' => true] : [])) ?><?= render_input('text', 'edays', (int)$values['edays'] == 0 ? '' : (int)$values['edays'], array_merge(['id' => 'pf-edays', 'class' => 'num half'], $isReadonly ? ['readonly' => true] : [])) ?></div></td>
    <td><?= render_input('text', 'price', disp_num($values['price']), array_merge(['id' => 'pf-price', 'class' => 'num'], $isReadonly ? ['readonly' => true] : [])) ?></td>
    <td><?= render_input('text', 'pricef', disp_num($values['pricef']), array_merge(['id' => 'pf-pricef', 'class' => 'num'], $isReadonly ? ['readonly' => true] : [])) ?></td>
  </tr>
  <?php else: ?>
  <input type="hidden" name="bdays" value="<?= (int)$values['bdays'] ?>" />
  <input type="hidden" name="edays" value="<?= (int)$values['edays'] ?>" />
  <input type="hidden" name="price" value="<?= h(disp_num($values['price'])) ?>" />
  <input type="hidden" name="pricef" value="<?= h(disp_num($values['pricef'])) ?>" />
  <?php endif; ?>

  <!-- Часы: Время от [btime][etime] | За час [hprice] -->
  <?php if ($showHoursFlag): ?>
  <tr>
    <td class="form-label" id="pf-lbl-btime">Время от / по</td>
    <td class="form-label" id="pf-lbl-hprice">За час</td>
    <td>&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><div style="display:flex;gap:8px;align-items:center"><?= render_input('text', 'btime', disp_time($values['btime']), array_merge(['id' => 'pf-btime', 'class' => 'half', 'placeholder' => 'чч:мм'], $isReadonly ? ['readonly' => true] : [])) ?><?= render_input('text', 'etime', disp_time($values['etime']), array_merge(['id' => 'pf-etime', 'class' => 'half', 'placeholder' => 'чч:мм'], $isReadonly ? ['readonly' => true] : [])) ?></div></td>
    <td><?= render_input('text', 'hprice', disp_num($values['hprice']), array_merge(['id' => 'pf-hprice', 'class' => 'num'], $isReadonly ? ['readonly' => true] : [])) ?></td>
    <td>&nbsp;</td>
  </tr>
  <?php else: ?>
  <input type="hidden" name="btime" value="<?= h(disp_time($values['btime'])) ?>" />
  <input type="hidden" name="etime" value="<?= h(disp_time($values['etime'])) ?>" />
  <input type="hidden" name="hprice" value="<?= h(disp_num($values['hprice'])) ?>" />
  <?php endif; ?>

  <!-- Месяцы: Месяцев с [bmonths][emonths] | За месяц [mprice] -->
  <?php if ($showMonthsFlag): ?>
  <tr>
    <td class="form-label" id="pf-lbl-mprice">Месяцев с / по</td>
    <td class="form-label">За месяц</td>
    <td>&nbsp;</td>
  </tr>
  <tr class="col-3">
    <td><div style="display:flex;gap:8px;align-items:center"><?= render_input('text', 'bmonths', (int)$values['bmonths'] == 0 ? '' : (int)$values['bmonths'], array_merge(['id' => 'pf-bmonths', 'class' => 'num half'], $isReadonly ? ['readonly' => true] : [])) ?><?= render_input('text', 'emonths', (int)$values['emonths'] == 0 ? '' : (int)$values['emonths'], array_merge(['id' => 'pf-emonths', 'class' => 'num half'], $isReadonly ? ['readonly' => true] : [])) ?></div></td>
    <td><?= render_input('text', 'mprice', disp_num($values['mprice']), array_merge(['id' => 'pf-mprice', 'class' => 'num'], $isReadonly ? ['readonly' => true] : [])) ?></td>
    <td>&nbsp;</td>
  </tr>
  <?php else: ?>
  <input type="hidden" name="bmonths" value="<?= (int)$values['bmonths'] ?>" />
  <input type="hidden" name="emonths" value="<?= (int)$values['emonths'] ?>" />
  <input type="hidden" name="mprice" value="<?= h(disp_num($values['mprice'])) ?>" />
  <?php endif; ?>

  <tr><td class="form-label">Название *</td></tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'name', $values['name'], array_merge(['id' => 'pf-name', 'class' => 'full', 'required' => !$isReadonly], $isReadonly ? ['readonly' => true] : [])) ?></td>
  </tr>
</table>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
) ?>
</form>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode], JSON_UNESCAPED_UNICODE);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
</head>
<body>
  <style>.form-modal{max-width:640px}.page--form{max-width:640px}.form{max-width:none}</style>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script>
  (function () {
    var fx = document.getElementById('pf-fixed');
    function syncFixed() {
      if (!fx) return;
      var on = fx.checked;
      ['pf-etime', 'pf-hprice', 'pf-mprice'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.disabled = on;
        el.style.opacity = on ? '0.5' : '';
      });
      var lbl = document.getElementById('pf-lbl-btime');
      if (lbl) lbl.textContent = on ? 'Время' : 'Время от';
    }
    if (fx) fx.addEventListener('change', syncFixed);
    syncFixed();
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); window.parent.postMessage({ type: 'form-cancel' }, '*'); }
    });
  })();
  </script>
</body>
</html>
