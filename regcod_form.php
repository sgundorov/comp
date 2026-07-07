<?php
require_once __DIR__ . '/config.php';
ensure_marks_table($conn);
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/regcod_columns.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'new');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';

$values = [
    'client_id'  => 0,
    'client'     => '',
    'datetime'   => '',
    'regcod'     => '',
    'product_id' => 0,
    'product'    => '',
    'quant'      => '1',
    'date'       => date('Y-m-d'),
    'city'       => '',
    'signat'     => '',
    'days'       => '',
    'sotr_id'    => 0,
    'block_flag' => 0,
    'note'       => '',
];

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM regcod WHERE regcod_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        foreach ($values as $k => $v) {
            if (isset($r[$k])) $values[$k] = (string)$r[$k];
        }
        $values['client_id']  = (int)$r['client_id'];
        $values['product_id'] = (int)$r['product_id'];
        $values['quant']      = (string)$r['quant'];
        $values['days']       = ((int)$r['days'] === 0) ? '' : (string)$r['days'];
        $values['block_flag'] = (int)$r['block_flag'];
        $values['datetime']   = (string)$r['datetime'];
    }
}

if ($mode === 'copy') {
    $id = 0;
}

$clientId = (int)($_GET['client_id'] ?? $values['client_id'] ?? 0);
$clientName = '';
$cityId = 0;
if ($clientId > 0) {
    $cStmt = $conn->prepare("SELECT name, city_id FROM client WHERE client_id = ?");
    $cStmt->bind_param('i', $clientId);
    $cStmt->execute();
    $cR = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();
    if ($cR) {
        $clientName = (string)$cR['name'];
        $cityId = (int)$cR['city_id'];
        if ($mode === 'new' || $mode === 'copy') {
            $values['client_id'] = $clientId;
            $values['client'] = $clientName;
            $values['date'] = date('Y-m-d');
            $values['city'] = '';
            $values['product_id'] = 0;
        }
    }
}

$clientList = [];
$crs = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$cityList = [];
$cs = $conn->query("SELECT city_id, name FROM city ORDER BY name");
if ($cs) while ($csRow = $cs->fetch_assoc()) $cityList[] = ['id' => (int)$csRow['city_id'], 'name' => (string)$csRow['name']];

$productList = [];
$prs = $conn->query("SELECT product_id, product_name, article AS code FROM product WHERE hide_flag = 0 ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) $productList[] = ['id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name'], 'code' => (string)$pr['code']];

$currentClientName = '';
foreach ($clientList as $c) { if ($c['id'] == $values['client_id']) { $currentClientName = $c['name']; break; } }
$currentCityName = '';
foreach ($cityList as $c) { if ($c['name'] === $values['city']) { $currentCityName = $c['name']; break; } }
$currentProductName = '';
foreach ($productList as $p) { if ($p['id'] == $values['product_id']) { $currentProductName = $p['name']; break; } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('[regcod] POST: ' . json_encode($_POST));
    $values['client_id']  = (int)($_POST['client_id'] ?? 0);
    $values['client']     = trim((string)($_POST['client'] ?? ''));
    $values['date']       = trim((string)($_POST['date'] ?? date('Y-m-d')));
    $values['regcod']     = trim((string)($_POST['regcod'] ?? ''));
    $values['product_id'] = (int)($_POST['product_id'] ?? 0);
    $values['product']    = trim((string)($_POST['product'] ?? ''));
    $values['quant']      = trim((string)($_POST['quant'] ?? '1'));
    $values['city']       = trim((string)($_POST['city'] ?? ''));
    $values['signat']     = trim((string)($_POST['signat'] ?? ''));
    $values['days']       = trim((string)($_POST['days'] ?? '0'));
    $values['block_flag'] = (int)(!empty($_POST['block_flag']) ? 1 : 0);
    $values['note']       = trim((string)($_POST['note'] ?? ''));

    if ($values['product_id'] <= 0) {
        $errors[] = 'Выберите товар.';
        $focusField = 'product-id';
        error_log('[regcod] validation error: product_id=' . $values['product_id']);
    }

    if (empty($errors)) {
        $productName = '';
        foreach ($productList as $p) { if ($p['id'] == $values['product_id']) { $productName = $p['name']; break; } }
        $values['product'] = $productName;

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM regcod WHERE regcod_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => 'delete', 'id' => $id]);
            exit;
        }

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO regcod (client_id, client, datetime, regcod, product_id, product, quant, date, city, signat, days, sotr_id, block_flag, note) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
            bind_auto($stmt, [$values['client_id'], $values['client'], $values['regcod'], $values['product_id'], $values['product'], $values['quant'], $values['date'], $values['city'], $values['signat'], $values['days'], $values['block_flag'], $values['note']]);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE regcod SET client_id = ?, client = ?, regcod = ?, product_id = ?, product = ?, quant = ?, date = ?, city = ?, signat = ?, days = ?, block_flag = ?, note = ? WHERE regcod_id = ?");
            bind_auto($stmt, [$values['client_id'], $values['client'], $values['regcod'], $values['product_id'], $values['product'], $values['quant'], $values['date'], $values['city'], $values['signat'], $values['days'], $values['block_flag'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            $newId = $id;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId]);
        exit;
    }
}

ob_start();
$pageTitle = 'Регистрационный код';
$isReadonly = ($mode === 'delete');
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form" method="post" action="regcod_form.php" autocomplete="off" data-form-modal>
<input type="hidden" name="mode" value="<?= h($mode) ?>" />
<?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>" /><?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>
<table class="form-table">
  <tr>
    <td class="form-label">Клиент</td>
    <td class="form-label" style="padding-left:10px">Дата</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_lookup('client', 'client_id', $values['client_id'], $currentClientName,
            '[]',
            '', true, ['id' => 'rc-client-id', 'data-name-input' => 'rc-client-name']) ?></td>
    <td style="padding-left:10px"><?= render_input('date', 'date', $values['date'], ['id' => 'rc-date', 'readonly' => true]) ?></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Рег. код<span class="required">*</span></td>
    <td class="form-label" style="padding-left:10px">&nbsp;</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('text', 'regcod', $values['regcod'], ['id' => 'rc-regcod', 'required' => true, 'readonly' => $isReadonly]) ?></td>
    <td style="padding-left:10px"><label class="checkbox-label"><input type="checkbox" name="block_flag" value="1"<?= $values['block_flag'] ? ' checked' : '' ?> <?= $isReadonly ? 'disabled' : '' ?> /> Заблокирован</label></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label" colspan="3">Регистрация<span class="required">*</span></td>
  </tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'client', $values['client'], ['id' => 'rc-client-name', 'class' => 'full', 'required' => true, 'readonly' => $isReadonly]) ?></td>
  </tr>
  <tr>
    <td class="form-label">Товар</td>
    <td class="form-label" style="padding-left:10px">Кол-во<span class="required">*</span></td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_lookup('product', 'product_id', $values['product_id'], $currentProductName,
            h(json_encode($productList, JSON_UNESCAPED_UNICODE)),
            'tmc_form.php?mode=new', $isReadonly, ['id' => 'rc-product-id', 'data-name-input' => 'rc-product-name']) ?></td>
    <td style="padding-left:10px"><?= render_input('number', 'quant', $values['quant'], ['id' => 'rc-quant', 'style' => 'width:80px', 'required' => true, 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label">Сигнатура</td>
    <td class="form-label" style="padding-left:10px">Дней</td>
    <td class="form-label">&nbsp;</td>
  </tr>
  <tr>
    <td><?= render_input('text', 'signat', $values['signat'], ['id' => 'rc-signat', 'readonly' => $isReadonly]) ?></td>
    <td style="padding-left:10px"><?= render_input('number', 'days', $values['days'], ['id' => 'rc-days', 'style' => 'width:80px', 'readonly' => $isReadonly]) ?></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td class="form-label" colspan="3">Примечание</td>
  </tr>
  <tr>
    <td colspan="3"><?= render_input('text', 'note', $values['note'], ['id' => 'rc-note', 'style' => 'width:100%', 'readonly' => $isReadonly]) ?></td>
  </tr>
</table>
<?php
$actions = $isReadonly
    ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button', 'class'=>'btn-secondary', 'data-form-close'=>'1'])]
    : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button', 'class'=>'btn-secondary', 'data-form-close'=>'1'])];
echo render_form_actions($actions);
?>
</form>
<?php
$html = ob_get_clean();
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $html, 'mode' => $mode, 'focusField' => $focusField], JSON_UNESCAPED_UNICODE);
} else {
    echo $html;
}
