<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/config/ainv_columns.php';

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
    'number'       => 0,
    'date'         => '',
    'time'         => '',
    'mesto_id'     => 0,
    'firm_id'      => 0,
    'first_card'   => 0,
    'last_card'    => 0,
    'sum'          => '0.00',
    'pos'          => 0,
    'type'         => 0,
    'accept_flag'  => 0,
    'sotr_id'      => 0,
    'note'         => '',
];

function recalc_ainv(mysqli $conn, int $number): array {
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

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT number, date, time, mesto_id, firm_id, first_card, last_card, sum, poz, type, accept_flag, sotr_id, note FROM ainv WHERE number = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $values['number']      = (int)$r['number'];
        $values['date']        = (string)$r['date'];
        $values['time']        = (string)$r['time'];
        $values['mesto_id']    = (int)$r['mesto_id'];
        $values['firm_id']     = (int)$r['firm_id'];
        $values['first_card']  = (int)$r['first_card'];
        $values['last_card']   = (int)$r['last_card'];
        $values['sum']         = (string)$r['sum'];
        $values['pos']         = (int)$r['poz'];
        $values['type']        = (int)$r['type'];
        $values['accept_flag'] = (int)$r['accept_flag'];
        $values['sotr_id']     = (int)$r['sotr_id'];
        $values['note']        = (string)$r['note'];
    }
}

if ($mode === 'new' || $mode === 'copy') {
    $nr = $conn->query("SELECT COALESCE(MAX(number), 0) + 1 AS next_num FROM ainv");
    if ($nr && ($nrow = $nr->fetch_assoc())) $values['number'] = (int)$nrow['next_num'];
}
if ($mode === 'delete' && $values['accept_flag'] === 1) {
    $errors[] = 'Нельзя удалить утверждённый акт.';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'html' => '<div class="flash flash--error">Нельзя удалить утверждённый акт.</div>', 'mode' => $mode]);
        exit;
    }
    header('Location: ainv.php');
    exit;
}
if ($mode === 'new') {
    $values['date'] = date('Y-m-d');
    $values['time'] = date('H:i:s');
    $values['sotr_id'] = $CurSotrID ?? 0;
    $frq = @$conn->query("SELECT firm_id FROM firm");
    if ($frq && $frq->num_rows === 1) {
        $frow = $frq->fetch_assoc();
        $values['firm_id'] = (int)$frow['firm_id'];
    }
    $mrq = @$conn->query("SELECT mesto_id FROM mesto");
    if ($mrq && $mrq->num_rows === 1) {
        $mrow = $mrq->fetch_assoc();
        $values['mesto_id'] = (int)$mrow['mesto_id'];
    }
}

// --- ainv2 items (pre-load) ---
$itemsList = [];
if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT id, number, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE number = ? ORDER BY id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $itemsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origApproved = ($mode === 'edit' || $mode === 'delete') && $values['accept_flag'] === 1;
    $origValues = $values;

    $values['number']      = (int)($_POST['number'] ?? 0);
    $values['date']        = trim((string)($_POST['date'] ?? ''));
    $values['time']        = trim((string)($_POST['time'] ?? ''));
    $values['mesto_id']    = (int)($_POST['mesto_id'] ?? 0);
    $values['firm_id']     = (int)($_POST['firm_id'] ?? 0);
    $values['first_card']  = (int)($_POST['first_card'] ?? 0);
    $values['last_card']   = (int)($_POST['last_card'] ?? 0);
    $values['sum']         = str_replace(',', '.', trim((string)($_POST['sum'] ?? '0')));
    $values['pos']         = (int)($_POST['pos'] ?? 0);
    $values['type']        = (int)($_POST['type'] ?? 0);
    $values['accept_flag'] = (int)($_POST['accept_flag'] ?? 0);
    $values['sotr_id']     = (int)($_POST['sotr_id'] ?? 0);
    $values['note']        = trim((string)($_POST['note'] ?? ''));

    if ($origApproved) {
        foreach (['date', 'time', 'mesto_id', 'firm_id', 'first_card', 'last_card', 'sum', 'pos', 'type', 'accept_flag', 'sotr_id'] as $fld) {
            $values[$fld] = $origValues[$fld];
        }
    }

    if ($values['date'] === '') {
        $errors[] = 'Поле «Дата» обязательно для заполнения.';
        $focusField = 'ainv-date';
    }
    if ($values['mesto_id'] <= 0) {
        $errors[] = 'Поле «Место» обязательно для заполнения.';
    }
    if ($values['firm_id'] <= 0) {
        $errors[] = 'Поле «Фирма» обязательно для заполнения.';
    }

    if (empty($errors)) {
        $numVal = $values['number'] > 0 ? $values['number'] : 0;
        $sumVal = (float)$values['sum'];
        $posVal = (int)$values['pos'];

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM ainv2 WHERE number = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM ainv WHERE number = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'ainv' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => '#' . $id]);
                exit;
            }
            header('Location: ainv.php');
            exit;
        }

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO ainv (number, date, time, firm_id, mesto_id, type, poz, first_card, last_card, sum, sotr_id, accept_flag, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$numVal, $values['date'], $values['time'], $values['firm_id'], $values['mesto_id'], $values['type'], $posVal, $values['first_card'], $values['last_card'], $sumVal, $values['sotr_id'], $values['accept_flag'], $values['note']]);
            $stmt->execute();
            $newId = (int)$stmt->insert_id;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE ainv SET date = ?, time = ?, firm_id = ?, mesto_id = ?, type = ?, poz = ?, first_card = ?, last_card = ?, sum = ?, accept_flag = ?, sotr_id = ?, note = ? WHERE number = ?");
            bind_auto($stmt, [$values['date'], $values['time'], $values['firm_id'], $values['mesto_id'], $values['type'], $posVal, $values['first_card'], $values['last_card'], $sumVal, $values['accept_flag'], $values['sotr_id'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            $newId = $id;
        }

        if ($newId > 0 && $mode !== 'delete') {
            $totals = recalc_ainv($conn, $newId);
            $sumVal = (float)$totals['sum'];
            $posVal = (int)$totals['pos'];
            $values['sum'] = $totals['sum'];
            $values['pos'] = $totals['pos'];
        }

        $isApply = ($_POST['action'] ?? '') === 'apply';

        if ($isAjax && !$isApply) {
            $pageOfNew = 0;
            if (defined('PAGE_SIZE') && PAGE_SIZE > 0 && ($mode === 'new' || $mode === 'copy') && $newId > 0) {
                $pageOfNew = computePageOfNew($conn, 'ainv', 'number', 'number', 'desc', $numVal, $newId, '');
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew]);
            exit;
        }
        if (!$isAjax && !$isApply) {
            header('Location: ainv.php');
            exit;
        }

        // Apply: stay on form, reload for re-render
        if ($newId > 0) {
            $id = $newId;
            $mode = 'edit';
            $stmt = $conn->prepare("SELECT id, number, product_id, code, product_name, quant_old, quant, dif, price, sum, state, note FROM ainv2 WHERE number = ? ORDER BY id ASC");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $itemsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

$mestoList = [];
$mr = @$conn->query("SELECT mesto_id, name FROM mesto ORDER BY name");
if ($mr) while ($r = $mr->fetch_assoc()) $mestoList[] = ['id' => (int)$r['mesto_id'], 'name' => (string)$r['name']];

$firmList = [];
$fr = @$conn->query("SELECT firm_id, name FROM firm ORDER BY name");
if ($fr) while ($r = $fr->fetch_assoc()) $firmList[] = ['id' => (int)$r['firm_id'], 'name' => (string)$r['name']];

$productList = [];
$hideSql = (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') ? ' WHERE hide_flag = 0' : '';
$pr = @$conn->query("SELECT product_id, product_name, code, residue, price_in FROM product" . $hideSql . " ORDER BY product_name");
if ($pr) while ($r = $pr->fetch_assoc()) {
    $productList[] = [
        'id'        => (int)$r['product_id'],
        'name'      => (string)$r['product_name'],
        'code'      => (string)$r['code'],
        'residue'   => (string)($r['residue'] ?: '0'),
        'price_in'  => (string)($r['price_in'] ?: '0'),
    ];
}

$currentMestoName = '';
foreach ($mestoList as $m) { if ($m['id'] === $values['mesto_id']) { $currentMestoName = $m['name']; break; } }
$currentFirmName = '';
foreach ($firmList as $f) { if ($f['id'] === $values['firm_id']) { $currentFirmName = $f['name']; break; } }

$sotrList = [];
$so = @$conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($so) while ($r = $so->fetch_assoc()) $sotrList[] = ['id' => (int)$r['sotr_id'], 'name' => (string)$r['name']];

$currentSotrName = '';
foreach ($sotrList as $s) { if ($s['id'] === $values['sotr_id']) { $currentSotrName = $s['name']; break; } }

$singleMesto = count($mestoList) === 1;
$singleFirm  = count($firmList) === 1;

$titleNum = $values['number'] > 0 ? '№ ' . $values['number'] : '(новый)';
if ($mode === 'edit') {
    $pageTitle = 'Акт инвентаризации ' . $titleNum;
} elseif ($mode === 'delete') {
    $pageTitle = 'Акт инвентаризации ' . $titleNum . ' (удаление)';
} elseif ($mode === 'new') {
    $pageTitle = 'Акт инвентаризации: новый';
} elseif ($mode === 'copy') {
    $pageTitle = 'Акт инвентаризации ' . $titleNum . ' (копия)';
} else {
    $pageTitle = 'Акт инвентаризации';
}
$isReadonly = ($mode === 'delete');
$isApproved = $values['accept_flag'] === 1;
$ro = $isReadonly || $isApproved;

$a2Table = new EmbeddedTable([
    'prefix'           => 'a2',
    'columns'          => [
        ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'param' => 'product_id', 'dbField' => 'product_id'],
        ['key' => 'quant_old',    'label' => 'Документальный остаток', 'align' => 'right', 'readonly' => true],
        ['key' => 'quant',        'label' => 'Фактический остаток', 'align' => 'right'],
        ['key' => 'dif',          'label' => 'Разница', 'align' => 'right', 'readonly' => true],
        ['key' => 'price',        'label' => 'Цена', 'align' => 'right', 'readonly' => true],
        ['key' => 'sum',          'label' => 'Сумма', 'align' => 'right', 'readonly' => true],
        ['key' => 'state',        'label' => 'Состояние', 'align' => 'right'],
        ['key' => 'note',         'label' => 'Примечание'],
    ],
    'colWidths'      => ['product_name' => '250px', 'quant_old' => '100px', 'quant' => '90px', 'dif' => '80px', 'price' => '90px', 'sum' => '100px', 'state' => '70px', 'note' => '300px'],
    'saveUrl'          => 'ainv2_field_save.php',
    'parentField'      => 'number',
    'childFormUrl'     => 'ainv2_form.php',
    'childFormName'    => 'ainv2',
    'hasExport'        => false,
    'hasPrint'         => false,
    'hasSearch'        => true,
    'columnResizeUrl'  => 'ainv2_column_width_save.php',
    'columnResizeTbl'  => 'ainv2',
    'lookupData' => [
        'product_name' => $productList ?? [],
    ],
    'totalsCallback' => 'applyAinv2Totals',
    'barcodeAdd' => true,
]);
$a2Table->readonly = $ro;

function dv($v) { return fmt_num($v, 2); }
function fmt_qty($v) { return fmt_num($v, 3); }

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/ainv.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="ainv_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Товары акта</div>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr>
        <td class="form-label">Акт №</td>
        <td class="form-label">Дата<span class="required">*</span>&nbsp;/&nbsp;Время</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'number', $values['number'] > 0 ? $values['number'] : '', [
                'id' => 'ainv-number',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:110px',
            ]) ?></td>
        <td><div style="display:flex;gap:10px"><?= render_input('date', 'date', $values['date'] ?: date('Y-m-d'), [
                'id' => 'ainv-date',
                'required' => !$isReadonly,
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:128px',
            ]) ?><?= render_input('time', 'time', $values['time'] ?: date('H:i:s'), [
                'id' => 'ainv-time',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:110px',
            ]) ?></div></td>
        <td>&nbsp;</td>
      </tr>
      <?php if (!$singleMesto || !$singleFirm): ?>
      <tr>
        <td class="form-label"><?php if (!$singleMesto): ?>Место<span class="required">*</span><?php endif; ?></td>
        <td class="form-label"><?php if (!$singleFirm): ?>Фирма<span class="required">*</span><?php endif; ?></td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?php if ($singleMesto): ?><?= render_input('hidden', 'mesto_id', $values['mesto_id']) ?><?php else: ?><?= render_lookup('mesto', 'mesto_id', $values['mesto_id'], $currentMestoName,
                h(json_encode($mestoList, JSON_UNESCAPED_UNICODE)),
                '', $ro, ['id' => 'ainv-mesto-id']) ?><?php endif; ?></td>
        <td><?php if ($singleFirm): ?><?= render_input('hidden', 'firm_id', $values['firm_id']) ?><?php else: ?><?= render_lookup('firm', 'firm_id', $values['firm_id'], $currentFirmName,
                h(json_encode($firmList, JSON_UNESCAPED_UNICODE)),
                'firm_form.php?mode=new', $ro, ['id' => 'ainv-firm-id']) ?><?php endif; ?></td>
        <td>&nbsp;</td>
      </tr>
      <?php endif; ?>
      <tr>
        <td class="form-label">Первая карточка</td>
        <td class="form-label">Последняя карточка</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('number', 'first_card', $values['first_card'] > 0 ? $values['first_card'] : '', [
                'id' => 'ainv-first-card',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td><?= render_input('number', 'last_card', $values['last_card'] > 0 ? $values['last_card'] : '', [
                'id' => 'ainv-last-card',
                'readonly' => $ro,
                'tabindex' => $ro ? '-1' : null,
                'style' => 'max-width:120px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Сумма</td>
        <td class="form-label">Позиций</td>
        <td class="form-label">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'sum', dv($values['sum']), [
                'id' => 'ainv-sum',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:120px;font-weight:bold',
            ]) ?></td>
        <td><?= render_input('number', 'pos', $values['pos'] > 0 ? (string)$values['pos'] : '', [
                'id' => 'ainv-pos',
                'readonly' => true,
                'tabindex' => '-1',
                'style' => 'max-width:80px',
            ]) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">
          <label class="field-radio-label" style="display:inline-flex;align-items:center;gap:6px;margin-right:20px">
            <?= render_checkbox('type', '1', $values['type'] === 1, ['id' => 'ainv-type', 'disabled' => $ro]) ?> Полная инвентаризация
          </label>
          <label class="field-radio-label" style="display:inline-flex;align-items:center;gap:6px">
            <?= render_checkbox('accept_flag', '1', $values['accept_flag'] === 1, ['id' => 'ainv-accept-flag', 'disabled' => $ro]) ?> Утверждено
          </label>
        </td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Сотрудник</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
                h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
                'sotr_form.php?mode=new', $ro, ['id' => 'ainv-sotr-id']) ?></td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], [
                'id' => 'ainv-note',
                'class' => 'full',
                'readonly' => $isReadonly,
                'tabindex' => $isReadonly ? '-1' : null,
            ]) ?></td>
      </tr>
    </table>
    <?= render_form_note() ?>
  </div>

  <div class="tab-pane" data-tab-index="1">
    <?php if ($id > 0 && ($mode !== 'new')):
    $a2Data = $itemsList ? array_map(function($i) { return [
        'id' => (int)$i['id'],
        'product_id' => (int)$i['product_id'],
        'product_name' => (string)$i['product_name'],
        'quant_old' => fmt_qty($i['quant_old']),
        'quant' => fmt_qty($i['quant']),
        'dif' => fmt_qty($i['dif']),
        'price' => dv($i['price']),
        'sum' => dv($i['sum']),
        'state' => (int)$i['state'] > 0 ? (string)(int)$i['state'] : '',
        'note' => (string)$i['note'],
    ]; }, $itemsList) : [];
    echo $a2Table->render($a2Data);
    ?>
    <?php elseif ($mode === 'new'): ?>
    <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:14px">Сохраните акт, чтобы добавить товары</div>
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

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'ainv.php', ['class'=>'btn-secondary'])]
        : [render_btn_icon_text('img/accept.png', 'Применить', ['type'=>'submit','name'=>'action','value'=>'apply','formnovalidate'=>true,'class'=>'btn-secondary']),
           render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'ainv.php', ['class'=>'btn-secondary'])]
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
    <?php $a2Table->renderScripts(); ?>
    initA2Table();
  </script>
</body>
</html>
