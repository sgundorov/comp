<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) {
    $mode = 'edit';
}

$errors = [];
$focusField = '';
$returnUrl = (string)($_GET['return_url'] ?? $_POST['return_url'] ?? '');
$values = [
    'datetime'  => '',
    'client_id' => 0,
    'zat_id'    => 0,
    'sum_in'    => '',
    'sum_out'   => '',
    'sum'       => '',
    'out_flag'  => 0,
    'plat_type' => 'Наличные',
    'doc_id'    => '',
    'doc_type'  => 0,
    'sotr_id'   => 0,
    'note'      => '',
];
$origInfo = '';

if ($mode === 'new') {
    $values['doc_id']    = (int)($_GET['doc_id'] ?? 0) > 0 ? (string)(int)$_GET['doc_id'] : '';
    $values['doc_type']  = (int)($_GET['doc_type'] ?? 0);
    $values['client_id'] = (int)($_GET['client_id'] ?? 0);
    $sumRemainIn  = (string)($_GET['sum_in'] ?? '');
    $sumRemainOut = (string)($_GET['sum_out'] ?? '');
    if ($values['doc_type'] == 120) {
        $values['sum_in']  = $sumRemainIn;
        $values['sum_out'] = '';
    } else {
        $values['sum_in']  = '';
        $values['sum_out'] = $sumRemainOut;
    }
    $values['sum']       = (string)($_GET['sum'] ?? '');
    $values['sotr_id']   = (int)($_GET['sotr_id'] ?? $CurSotrID);
    if (!empty($_GET['zat_id'])) $values['zat_id'] = (int)$_GET['zat_id'];
    $docIdVal = (int)$values['doc_id'];
    $docTypeVal = (int)$values['doc_type'];
    if ($docIdVal > 0 && $values['client_id'] <= 0 && in_array($docTypeVal, [10, 20, 110, 120, 127], true)) {
        $parentTable = $docTypeVal === 10 ? 'invoice' : 'docum';
        $pStmt = $conn->prepare("SELECT client_id FROM $parentTable WHERE " . ($docTypeVal === 10 ? 'invoice_id' : 'docum_id') . " = ?");
        $pStmt->bind_param('i', $docIdVal);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if ($pRow) $values['client_id'] = (int)$pRow['client_id'];
    }
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT datetime, client_id, zat_id, sum_in, sum_out, sum, out_flag, plat_type, doc_id, doc_type, sotr_id, note FROM plat WHERE plat_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $dt = strtotime((string)$r['datetime']);
        $origInfo = (string)$r['datetime'];
        $values['datetime']  = $dt ? date('Y-m-d', $dt) : '';
        $values['datetime_time'] = $dt ? date('H:i', $dt) : '';
        $values['client_id'] = (int)$r['client_id'];
        $values['zat_id']    = (int)$r['zat_id'];
        $values['sum_in']    = (float)$r['sum_in'] > 0 ? (string)$r['sum_in'] : '';
        $values['sum_out']   = (float)$r['sum_out'] > 0 ? (string)$r['sum_out'] : '';
        $values['sum']       = (string)$r['sum'];
        $values['out_flag']  = (int)$r['out_flag'];
        $values['plat_type'] = (string)$r['plat_type'];
        $values['doc_id']    = (int)$r['doc_id'] > 0 ? (string)(int)$r['doc_id'] : '';
        $values['doc_type']  = (int)$r['doc_type'];
        $values['sotr_id']   = (int)$r['sotr_id'];
        $values['note']      = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['datetime']  = trim((string)($_POST['date'] ?? ''));
    $values['datetime_time'] = trim((string)($_POST['time'] ?? ''));
    $values['client_id'] = (int)($_POST['client_id'] ?? 0);
    $values['zat_id']    = (int)($_POST['zat_id'] ?? 0);
    $values['sum_in']    = str_replace(',', '.', trim((string)($_POST['sum_in'] ?? '')));
    $values['sum_out']   = str_replace(',', '.', trim((string)($_POST['sum_out'] ?? '')));
    $values['plat_type'] = trim((string)($_POST['plat_type'] ?? 'Наличные'));
    $values['doc_id']    = trim((string)($_POST['doc_id'] ?? ''));
    $values['doc_type']  = (int)($_POST['doc_type'] ?? 0);
    $values['sotr_id']   = (int)($_POST['sotr_id'] ?? 0);
    $values['note']      = trim((string)($_POST['note'] ?? ''));

    if ($values['datetime'] === '') {
        $errors[] = 'Поле «Дата» обязательно для заполнения.';
        $focusField = 'plat-date';
    }
    if ($values['client_id'] <= 0) {
        $errors[] = 'Поле «Контрагент» обязательно для заполнения.';
        $focusField = $focusField ?: 'client-id';
    }
    if ($values['zat_id'] <= 0) {
        $errors[] = 'Поле «Вид операции» обязательно для заполнения.';
        $focusField = $focusField ?: 'zat-id';
    }
    if ($values['sotr_id'] <= 0) {
        $errors[] = 'Поле «Сотрудник» обязательно для заполнения.';
        $focusField = $focusField ?: 'sotr-id';
    }

    $sumInVal = $values['sum_in'] !== '' ? (float)$values['sum_in'] : 0;
    $sumOutVal = $values['sum_out'] !== '' ? (float)$values['sum_out'] : 0;

    if ($sumInVal <= 0 && $sumOutVal <= 0) {
        $errors[] = 'Заполните Сумму прихода или Сумму расхода.';
        $focusField = $focusField ?: 'plat-sum-in';
    }

    $sum = $sumInVal - $sumOutVal;
    if ($values['zat_id'] > 0) {
        $zr = $conn->query("SELECT out_flag FROM zat WHERE zat_id = " . $values['zat_id']);
        $outFlag = $zr && ($zrow = $zr->fetch_assoc()) ? (int)$zrow['out_flag'] : 0;
    } else {
        $outFlag = 0;
    }

    if (!in_array($values['plat_type'], ['Наличные', 'Безнал.', 'Карта', 'Прочее'], true)) {
        $errors[] = 'Некорректный вид платежа.';
    }

    if (empty($errors)) {
        $datetimeStr = $values['datetime'] . ' ' . ($values['datetime_time'] ?: '00:00') . ':00';

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM plat WHERE plat_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'plat' AND row_id = " . (int)$id);
            $delDocId = (int)$values['doc_id'];
            $delSumPlat = 0;
            $delDocType = (int)$values['doc_type'];
            if ($delDocId > 0 && in_array($delDocType, [10, 20, 110, 120, 127], true)) {
                $parentTable = $delDocType === 10 ? 'invoice' : 'docum';
                $parentKey = $delDocType === 10 ? 'invoice_id' : 'docum_id';
                $sp = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = ?");
                $sp->bind_param('ii', $delDocId, $delDocType);
                $sp->execute();
                $delSumPlat = (float)$sp->get_result()->fetch_row()[0];
                $sp->close();
                if ($delDocType === 10) {
                    $upd = $conn->prepare("UPDATE $parentTable SET sum_plat = ? WHERE $parentKey = ?");
                    bind_auto($upd, [$delSumPlat, $delDocId]);
                } else {
                    $sumRow = $conn->query("SELECT COALESCE(`sum`,0) FROM docum WHERE docum_id = $delDocId")->fetch_row();
                    $docSum = (float)($sumRow[0] ?? 0);
                    $upd = $conn->prepare("UPDATE docum SET sum_plat = ?, sum_balans = ? WHERE docum_id = ?");
                    bind_auto($upd, [$delSumPlat, $docSum - $delSumPlat, $delDocId]);
                }
                $upd->execute();
                $upd->close();
            }
            if ($isAjax) {
                $spRedStmt = $conn->prepare("SELECT d.sum_plat, d.sum, COALESCE(tp.prihod_flag,0) AS prihod_flag FROM docum d LEFT JOIN typeop tp ON tp.typeop_id = d.typeop WHERE d.docum_id = ?");
                $spRedStmt->bind_param('i', $delDocId);
                $spRedStmt->execute();
                $spRedRow = $spRedStmt->get_result()->fetch_assoc();
                $spRedStmt->close();
                $spSum = (float)($spRedRow['sum'] ?? 0);
                $prihodFlag = (int)($spRedRow['prihod_flag'] ?? 0);
                $sumPlatRed = $prihodFlag ? ($spSum > -$delSumPlat) : ($delSumPlat < $spSum);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => '#' . $id, 'sum_plat' => number_format($delSumPlat, 2, '.', ''), 'sum_plat_red' => $sumPlatRed]);
                exit;
            }
            header('Location: plat.php');
            exit;
        }

        $d = $values['datetime'];
        $t = $values['datetime_time'] ?: '00:00';
        $docIdVal = $values['doc_id'] !== '' ? (int)$values['doc_id'] : 0;

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO plat (datetime, date, time, client_id, zat_id, sum_in, sum_out, sum, out_flag, plat_type, doc_id, doc_type, sotr_id, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$datetimeStr, $d, $t, $values['client_id'], $values['zat_id'], $sumInVal, $sumOutVal, $sum, $outFlag, $values['plat_type'], $docIdVal, $values['doc_type'], $values['sotr_id'], $values['note']]);
        } else {
            $stmt = $conn->prepare("UPDATE plat SET datetime = ?, date = ?, time = ?, client_id = ?, zat_id = ?, sum_in = ?, sum_out = ?, sum = ?, out_flag = ?, plat_type = ?, doc_id = ?, doc_type = ?, sotr_id = ?, note = ? WHERE plat_id = ?");
            bind_auto($stmt, [$datetimeStr, $d, $t, $values['client_id'], $values['zat_id'], $sumInVal, $sumOutVal, $sum, $outFlag, $values['plat_type'], $docIdVal, $values['doc_type'], $values['sotr_id'], $values['note'], $id]);
        }

        if (!$stmt->execute()) {
            $errors[] = 'Ошибка БД: ' . $stmt->error;
            $stmt->close();
            goto render;
        }
        $newId = ($mode === 'new' || $mode === 'copy') ? $conn->insert_id : $id;
        $stmt->close();

        $saveSumPlat = 0;
        $saveDocType = (int)$values['doc_type'];
        if ($docIdVal > 0 && in_array($saveDocType, [10, 20, 110, 120, 127], true)) {
            $parentTable = $saveDocType === 10 ? 'invoice' : 'docum';
            $parentKey = $saveDocType === 10 ? 'invoice_id' : 'docum_id';
            $sp = $conn->prepare("SELECT COALESCE(SUM(sum),0) FROM plat WHERE doc_id = ? AND doc_type = ?");
            $sp->bind_param('ii', $docIdVal, $saveDocType);
            $sp->execute();
            $saveSumPlat = (float)$sp->get_result()->fetch_row()[0];
            $sp->close();
            if ($saveDocType === 10) {
                $upd = $conn->prepare("UPDATE $parentTable SET sum_plat = ? WHERE $parentKey = ?");
                bind_auto($upd, [$saveSumPlat, $docIdVal]);
            } else {
                $sumRow = $conn->query("SELECT COALESCE(`sum`,0) FROM docum WHERE docum_id = $docIdVal")->fetch_row();
                $docSum = (float)($sumRow[0] ?? 0);
                $upd = $conn->prepare("UPDATE docum SET sum_plat = ?, sum_balans = ? WHERE docum_id = ?");
                bind_auto($upd, [$saveSumPlat, $docSum - $saveSumPlat, $docIdVal]);
            }
            $upd->execute();
            $upd->close();
        }
        if ($isAjax) {
            $pageOfNew = computePageOfNew($conn, 'plat', 'id', 'id', 'desc', $newId, $newId, '');
            $spRedStmt = $conn->prepare("SELECT d.sum_plat, d.sum, COALESCE(tp.prihod_flag,0) AS prihod_flag FROM docum d LEFT JOIN typeop tp ON tp.typeop_id = d.typeop WHERE d.docum_id = ?");
            $spRedStmt->bind_param('i', $docIdVal);
            $spRedStmt->execute();
            $spRedRow = $spRedStmt->get_result()->fetch_assoc();
            $spRedStmt->close();
            $spSumPlat = (float)($spRedRow['sum_plat'] ?? $saveSumPlat);
            $spSum = (float)($spRedRow['sum'] ?? 0);
            $prihodFlag = (int)($spRedRow['prihod_flag'] ?? 0);
            $sumPlatRed = $prihodFlag ? ($spSum > -$spSumPlat) : ($spSumPlat < $spSum);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => '#' . $newId, 'page' => $pageOfNew, 'sum_plat' => number_format($saveSumPlat, 2, '.', ''), 'sum_plat_red' => $sumPlatRed]);
            exit;
        }
        $redirectTo = $returnUrl ?: 'plat.php';
        header('Location: ' . $redirectTo);
        exit;
    }
}

render:
$titles = [
    'new'    => 'Операция с деньгами (новая)',
    'edit'   => 'Операция с деньгами: #' . $id,
    'copy'   => 'Операция с деньгами: #' . $id . ' (копия)',
    'delete' => 'Операция с деньгами: #' . $id . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Операция с деньгами';
$isReadonly = ($mode === 'delete');
$parentDocId = (int)$values['doc_id'];
$parentDocType = (int)$values['doc_type'];
$parentAccepted = false;
if ($parentDocId > 0 && in_array($parentDocType, [20, 110, 120, 127], true)) {
    $paStmt = $conn->prepare("SELECT accept_flag FROM docum WHERE docum_id = ?");
    $paStmt->bind_param('i', $parentDocId);
    $paStmt->execute();
    $paRow = $paStmt->get_result()->fetch_assoc();
    $paStmt->close();
    $parentAccepted = $paRow && (int)$paRow['accept_flag'] === 1;
}
$isReadonly = $isReadonly || $parentAccepted;

$clientList = [];
$crs = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];
}

$zatList = [];
$zrs = $conn->query("SELECT zat_id, name, out_flag FROM zat ORDER BY name");
if ($zrs) while ($zr = $zrs->fetch_assoc()) {
    $zatList[] = ['id' => (int)$zr['zat_id'], 'name' => (string)$zr['name'], 'out_flag' => (int)$zr['out_flag']];
}

$sotrList = [];
$srs = $conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($srs) while ($sr = $srs->fetch_assoc()) {
    $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];
}

$currentClientName = '';
foreach ($clientList as $c) {
    if ($c['id'] === $values['client_id']) { $currentClientName = $c['name']; break; }
}

$currentZatName = '';
foreach ($zatList as $z) {
    if ($z['id'] === $values['zat_id']) { $currentZatName = $z['name']; break; }
}

$currentSotrName = '';
foreach ($sotrList as $s) {
    if ($s['id'] === $values['sotr_id']) { $currentSotrName = $s['name']; break; }
}

$platTypeOptions = ['Наличные', 'Безнал.', 'Карта', 'Прочее'];

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/plat.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="plat_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'doc_type', $values['doc_type']) ?>
<?= render_input('hidden', 'cli_name', '', ['id' => 'plat-cli-name']) ?>
<?= render_input('hidden', 'zat_name', '', ['id' => 'plat-zat-name']) ?>
<?= render_input('hidden', 'sotr_name', '', ['id' => 'plat-sotr-name']) ?>
<?php if ($returnUrl): ?><?= render_input('hidden', 'return_url', $returnUrl) ?><?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?= render_field('Контрагент',
    render_lookup('client', 'client_id', $values['client_id'], $currentClientName,
        h(json_encode($clientList, JSON_UNESCAPED_UNICODE)),
        'client_form.php?mode=new', $isReadonly, ['id' => 'client-id', 'data-name-input' => 'plat-cli-name']),
    true,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row" style="gap:10px">
  <?= render_field('Дата',
      render_input('date', 'date', $values['datetime'] ?: date('Y-m-d'), [
          'id' => 'plat-date',
          'required' => !$isReadonly,
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
          'style' => 'max-width:170px',
      ]),
      true,
      ['readonly' => $isReadonly]
  ) ?>
  <?= render_field('Время',
      render_input('time', 'time', $values['datetime_time'] ?? date('H:i'), [
          'id' => 'plat-time',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
          'style' => 'max-width:120px',
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
</div>

<?= render_field('Вид операции',
    render_lookup('zat', 'zat_id', $values['zat_id'], $currentZatName,
        h(json_encode($zatList, JSON_UNESCAPED_UNICODE)),
        'zat_form.php?mode=new', $isReadonly, ['id' => 'zat-id', 'data-name-input' => 'plat-zat-name']),
    true,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row">
  <?= render_field('Сумма прихода', render_input('text', 'sum_in', $values['sum_in'], [
          'id' => 'plat-sum-in',
          'placeholder' => '0.00',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
          'style' => 'max-width:130px',
      ]), false, ['readonly' => $isReadonly]) ?>
  <?= render_field('Сумма расхода', render_input('text', 'sum_out', $values['sum_out'], [
          'id' => 'plat-sum-out',
          'placeholder' => '0.00',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
          'style' => 'max-width:130px',
      ]), false, ['readonly' => $isReadonly]) ?>
<?= render_field('Сумма', render_input('text', 'sum', $values['sum'], [
        'id' => 'plat-sum',
        'readonly' => true,
        'tabindex' => '-1',
        'style' => 'max-width:130px;font-weight:bold',
    ]), false, ['readonly' => $isReadonly]) ?>
  <?= render_field('Тип', render_input('text', 'out_flag', (int)$values['out_flag'] === 1 ? 'Расход' : 'Приход', [
          'id' => 'plat-out-flag',
          'readonly' => true,
          'tabindex' => '-1',
          'style' => 'max-width:100px;font-weight:bold',
      ]), false, ['readonly' => $isReadonly]) ?>
</div>

<div class="field<?= $isReadonly ? ' field--readonly' : '' ?>">
  <label class="field-label">Вид платежа</label>
  <div class="field-radio-group">
    <?php foreach ($platTypeOptions as $opt): ?>
      <?php
      $radioAttrs = [
          'type' => 'radio',
          'name' => 'plat_type',
          'value' => $opt,
          'class' => 'field-radio',
          'id' => 'plat-type-' . h(str_replace([' ', '.'], '_', $opt)),
      ];
      if ($opt === $values['plat_type']) $radioAttrs['checked'] = true;
      if ($isReadonly) $radioAttrs['disabled'] = true;
      ?>
      <input<?= _render_btn_attrs($radioAttrs) ?> />
      <label class="field-radio-label" for="<?= h('plat-type-' . str_replace([' ', '.'], '_', $opt)) ?>"><?= h($opt) ?></label>
    <?php endforeach; ?>
  </div>
</div>

<?= render_field('Документ №', render_input('text', 'doc_id', $values['doc_id'], [
        'id' => 'plat-doc-id',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
        'style' => 'max-width:100px',
    ]), false, ['readonly' => $isReadonly]) ?>

<?= render_field('Сотрудник',
    render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
        h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
        'sotr_form.php?mode=new', $isReadonly, ['id' => 'sotr-id', 'data-name-input' => 'plat-sotr-name']),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'plat-note',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]), false, ['wide' => true, 'readonly' => $isReadonly]) ?>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button','data-form-close'=>true,'class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button','data-form-close'=>true,'class'=>'btn-secondary'])]
) ?>
</form>
<script src="assets/lookup.js"></script>
<script>
(function () {
  var clientRoot = document.querySelector('[data-lookup="client"]');
  var zatRoot = document.querySelector('[data-lookup="zat"]');
  var sotrRoot = document.querySelector('[data-lookup="sotr"]');

  var clientList = clientRoot ? JSON.parse(clientRoot.getAttribute('data-countries') || '[]') : [];
  var zatList = zatRoot ? JSON.parse(zatRoot.getAttribute('data-countries') || '[]') : [];
  var sotrList = sotrRoot ? JSON.parse(sotrRoot.getAttribute('data-countries') || '[]') : [];

  var clientReadonly = clientRoot && clientRoot.querySelector('.lookup-input') && clientRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var zatReadonly = zatRoot && zatRoot.querySelector('.lookup-input') && zatRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var sotrReadonly = sotrRoot && sotrRoot.querySelector('.lookup-input') && sotrRoot.querySelector('.lookup-input').hasAttribute('readonly');

  function setOutFlagFromZat(id) {
    var z = zatList.find(function (z) { return z.id === id; });
    var outFlagEl = document.getElementById('plat-out-flag');
    if (!outFlagEl) return;
    outFlagEl.value = z ? (z.out_flag === 1 ? 'Расход' : 'Приход') : 'Приход';
  }

  var cliNameInput = document.getElementById('plat-cli-name');
  var zatNameInput = document.getElementById('plat-zat-name');
  var sotrNameInput = document.getElementById('plat-sotr-name');

  function onClientSelect(id, name) {
    if (cliNameInput) cliNameInput.value = name;
  }
  function onZatSelect(id, name) {
    if (zatNameInput) zatNameInput.value = name;
    setOutFlagFromZat(id);
  }
  function onSotrSelect(id, name) {
    if (sotrNameInput) sotrNameInput.value = name;
  }

  if (clientRoot && !clientReadonly) {
    bindLookup({ root: clientRoot, data: clientList, readonly: clientReadonly, onSelect: onClientSelect });
  }
  if (zatRoot && !zatReadonly) {
    bindLookup({ root: zatRoot, data: zatList, readonly: zatReadonly, onSelect: onZatSelect });
  }
  if (sotrRoot && !sotrReadonly) {
    bindLookup({ root: sotrRoot, data: sotrList, readonly: sotrReadonly, onSelect: onSotrSelect });
  }

  var zatIdInput = document.querySelector('input[name="zat_id"]');
  if (zatIdInput) {
    setOutFlagFromZat(parseInt(zatIdInput.value, 10));
    zatIdInput.addEventListener('change', function () {
      setOutFlagFromZat(parseInt(this.value, 10));
    });
  }

  function updateSum() {
    var sumIn = parseFloat(document.getElementById('plat-sum-in').value.replace(',', '.')) || 0;
    var sumOut = parseFloat(document.getElementById('plat-sum-out').value.replace(',', '.')) || 0;
    document.getElementById('plat-sum').value = (sumIn - sumOut).toFixed(2);
  }
  var sumInEl = document.getElementById('plat-sum-in');
  var sumOutEl = document.getElementById('plat-sum-out');
  if (sumInEl && !sumInEl.readOnly) { sumInEl.addEventListener('input', updateSum); }
  if (sumOutEl && !sumOutEl.readOnly) { sumOutEl.addEventListener('input', updateSum); }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var anyOpen = document.querySelectorAll('.lookup-pop.open');
      if (anyOpen.length > 0) return;
      e.preventDefault();
      if (window.parent && window.parent !== window) {
        try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
      } else {
        window.location.href = 'plat.php';
      }
    }
  });
})();
(function () {
  var form = document.querySelector('form[data-form-modal]');
  if (!form) return;
  var focusable = Array.from(form.querySelectorAll(
    'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
    'button:not([tabindex="-1"]):not([disabled]),' +
    'a[href]:not([tabindex="-1"]),' +
    'textarea:not([tabindex="-1"]):not([readonly]),' +
    'select:not([tabindex="-1"]):not([disabled])'
  )).filter(function (el) { return el.offsetParent !== null; });
  if (focusable.length < 2) return;
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var idx = focusable.indexOf(document.activeElement);
    if (idx === -1) return;
    e.preventDefault();
    if (e.shiftKey) {
      focusable[(idx - 1 + focusable.length) % focusable.length].focus();
    } else {
      focusable[(idx + 1) % focusable.length].focus();
    }
  });
})();
</script>
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
</body>
</html>
