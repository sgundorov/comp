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
$now = date('Y-m-d H:i');
$values = [
    'client_id'    => 0,
    'cli_name'     => '',
    'datetime'     => $mode === 'new' ? $now : '',
    'date'         => '',
    'time'         => '',
    'impotant_flag' => '0',
    'contype_id'   => 0,
    'contype_name' => '',
    'sotr_id'      => 0,
    'sotr_name'    => '',
    'note'         => '',
];
$origClientName = '';
$origContypeName = '';
$origSotrName = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT ct.client_id, ct.cli_name, ct.datetime, ct.impotant_flag,
                                   ct.contype_id, ct.contype, ct.sotr_id, ct.note
                            FROM contact ct WHERE ct.contact_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origClientName = (string)($r['cli_name'] ?? '');
        $origContypeName = (string)($r['contype'] ?? '');
        $values['client_id']    = (int)($r['client_id'] ?? 0);
        $values['cli_name']     = $mode === 'copy' ? '' : (string)$r['cli_name'];
        $values['datetime']     = (string)($r['datetime'] ?? '');
        $values['impotant_flag'] = (string)($r['impotant_flag'] ?? '0');
        $values['contype_id']   = (int)($r['contype_id'] ?? 0);
        $values['contype_name'] = $mode === 'copy' ? '' : (string)$r['contype'];
        $values['sotr_id']      = (int)($r['sotr_id'] ?? 0);
        $values['note']         = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['client_id']    = (int)($_POST['client_id'] ?? 0);
    $values['cli_name']     = trim((string)($_POST['cli_name'] ?? ''));
    $values['datetime']     = trim((string)($_POST['datetime'] ?? ''));
    $values['impotant_flag'] = !empty($_POST['impotant_flag']) ? '1' : '0';
    $values['contype_id']   = (int)($_POST['contype_id'] ?? 0);
    $values['contype_name'] = trim((string)($_POST['contype_name'] ?? ''));
    $values['sotr_id']      = (int)($_POST['sotr_id'] ?? 0);
    $values['sotr_name']    = trim((string)($_POST['sotr_name'] ?? ''));
    $values['note']         = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['client_id'] <= 0) {
            $errors[] = 'Выберите контрагента из списка.';
            $focusField = 'client-id';
        }
        if ($values['contype_id'] <= 0) {
            $errors[] = 'Выберите вид контакта из списка.';
            $focusField = 'contype-id';
        }
        if ($values['datetime'] === '') {
            $values['datetime'] = date('Y-m-d H:i:s');
        } else {
            $dt = strtotime($values['datetime']);
            if ($dt === false) {
                $errors[] = 'Неверный формат даты/времени.';
                $focusField = 'datetime';
            } else {
                $values['datetime'] = date('Y-m-d H:i:s', $dt);
            }
        }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM contact WHERE contact_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($mode === 'new' || $mode === 'copy') {
            // auto-fill denormalized names
            if ($values['client_id'] > 0 && $values['cli_name'] === '') {
                $cr = $conn->prepare("SELECT name FROM client WHERE client_id = ?");
                $cr->bind_param('i', $values['client_id']);
                $cr->execute();
                $crd = $cr->get_result()->fetch_assoc();
                $values['cli_name'] = (string)($crd['name'] ?? '');
                $cr->close();
            }
            if ($values['contype_id'] > 0 && $values['contype_name'] === '') {
                $cr = $conn->prepare("SELECT contype FROM contype WHERE contype_id = ?");
                $cr->bind_param('i', $values['contype_id']);
                $cr->execute();
                $crd = $cr->get_result()->fetch_assoc();
                $values['contype_name'] = (string)($crd['contype'] ?? '');
                $cr->close();
            }
            $stmt = $conn->prepare("INSERT INTO contact (client_id, cli_name, datetime, impotant_flag, contype_id, contype, sotr_id, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$values['client_id'], $values['cli_name'], $values['datetime'], $values['impotant_flag'], $values['contype_id'], $values['contype_name'], $values['sotr_id'], $values['note']]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            } else {
                $id = (int)$stmt->insert_id;
            }
            $stmt->close();
            if ($mode === 'new' || $mode === 'copy') {
                $pageOfNew = computePageOfNew($conn, 'contact', 'contact_id', 'id', 'desc', $id, $id, '');
            }
        } else {
            // edit mode - re-fetch denormalized names
            if ($values['client_id'] > 0) {
                $cr = $conn->prepare("SELECT name FROM client WHERE client_id = ?");
                $cr->bind_param('i', $values['client_id']);
                $cr->execute();
                $crd = $cr->get_result()->fetch_assoc();
                $values['cli_name'] = (string)($crd['name'] ?? '');
                $cr->close();
            } else {
                $values['cli_name'] = '';
            }
            if ($values['contype_id'] > 0) {
                $cr = $conn->prepare("SELECT contype FROM contype WHERE contype_id = ?");
                $cr->bind_param('i', $values['contype_id']);
                $cr->execute();
                $crd = $cr->get_result()->fetch_assoc();
                $values['contype_name'] = (string)($crd['contype'] ?? '');
                $cr->close();
            } else {
                $values['contype_name'] = '';
            }
            $stmt = $conn->prepare("UPDATE contact SET client_id = ?, cli_name = ?, datetime = ?, impotant_flag = ?, contype_id = ?, contype = ?, sotr_id = ?, note = ? WHERE contact_id = ?");
            bind_auto($stmt, [$values['client_id'], $values['cli_name'], $values['datetime'], $values['impotant_flag'], $values['contype_id'], $values['contype_name'], $values['sotr_id'], $values['note'], $id]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $stmt->close();
        }
        if (empty($errors) && $isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $resp = ['ok' => true, 'mode' => $mode, 'id' => $id];
            if ($mode === 'new' || $mode === 'copy') $resp['page'] = $pageOfNew;
            echo json_encode($resp);
            exit;
        }
        if (empty($errors)) {
            header('Location: contact.php');
            exit;
        }
    }
}

// Load lookup data
$clientList = [];
$crs = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $clientList[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];
}

$contypeList = [];
$crs2 = $conn->query("SELECT contype_id, contype FROM contype ORDER BY contype");
if ($crs2) while ($cr = $crs2->fetch_assoc()) {
    $contypeList[] = ['id' => (int)$cr['contype_id'], 'name' => (string)$cr['contype']];
}

$sotrList = [];
$crs3 = $conn->query("SELECT sotr_id, TRIM(CONCAT_WS(' ', last_name, first_name)) AS name FROM sotr ORDER BY name");
if ($crs3) while ($cr = $crs3->fetch_assoc()) {
    $sotrList[] = ['id' => (int)$cr['sotr_id'], 'name' => (string)$cr['name']];
}

$currentClientName = '';
foreach ($clientList as $c) {
    if ($c['id'] === (int)$values['client_id']) { $currentClientName = $c['name']; break; }
}
if ($currentClientName === '' && $origClientName !== '' && (int)$values['client_id'] > 0) {
    $currentClientName = $origClientName;
}

$currentContypeName = '';
foreach ($contypeList as $c) {
    if ($c['id'] === (int)$values['contype_id']) { $currentContypeName = $c['name']; break; }
}
if ($currentContypeName === '' && $origContypeName !== '' && (int)$values['contype_id'] > 0) {
    $currentContypeName = $origContypeName;
}

$currentSotrName = '';
foreach ($sotrList as $c) {
    if ($c['id'] === (int)$values['sotr_id']) { $currentSotrName = $c['name']; break; }
}

$titles = [
    'new'    => 'Контакт (новый)',
    'edit'   => 'Контакт: ' . $currentClientName,
    'copy'   => 'Контакт: ' . $origClientName . ' (копия)',
    'delete' => 'Контакт: ' . $origClientName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/contact.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="contact_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'cli_name', $values['cli_name'], ['id' => 'contact-cli-name']) ?>
<?= render_input('hidden', 'contype_name', $values['contype_name'], ['id' => 'contact-contype-name']) ?>
<?= render_input('hidden', 'sotr_name', $values['sotr_name'], ['id' => 'contact-sotr-name']) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?= render_field('Контрагент',
    render_lookup('client', 'client_id', $values['client_id'], $currentClientName,
        h(json_encode($clientList, JSON_UNESCAPED_UNICODE)),
        'client_form.php?mode=new', $isReadonly, ['id' => 'client-id', 'data-name-input' => 'contact-cli-name']),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Дата/Время',
    render_input('text', 'datetime', $values['datetime'] !== '' ? date('Y-m-d H:i', strtotime($values['datetime'])) : '', [
        'id' => 'contact-datetime',
        'placeholder' => 'YYYY-MM-DD HH:MM',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
        'style' => 'max-width:170px',
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>

<div class="field field--narrow<?= $isReadonly ? ' field--readonly' : '' ?>" style="display:flex;flex-direction:row;align-items:center;gap:6px;">
  <?= render_checkbox('impotant_flag', '1', $values['impotant_flag'] === '1', ['id' => 'contact-impotant', 'disabled' => $isReadonly]) ?>
  <label class="field-label" for="contact-impotant" style="margin:0;">Важный</label>
</div>

<?= render_field('Вид контакта',
    render_lookup('contype', 'contype_id', $values['contype_id'], $currentContypeName,
        h(json_encode($contypeList, JSON_UNESCAPED_UNICODE)),
        'contype_form.php?mode=new', $isReadonly, ['id' => 'contype-id', 'data-name-input' => 'contact-contype-name']),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Сотрудник',
    render_lookup('sotr', 'sotr_id', $values['sotr_id'], $currentSotrName,
        h(json_encode($sotrList, JSON_UNESCAPED_UNICODE)),
        'sotr_form.php?mode=new', $isReadonly, ['id' => 'sotr-id', 'data-name-input' => 'contact-sotr-name']),
    false,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Содержание контакта',
    render_textarea('note', $values['note'], [
        'id' => 'contact-note',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
        'rows' => 12,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'contact.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'contact.php', ['class'=>'btn-secondary'])]
) ?>
</form>
<script src="assets/lookup.js"></script>
<script>
(function () {
  var clientRoot = document.querySelector('[data-lookup="client"]');
  var contypeRoot = document.querySelector('[data-lookup="contype"]');
  var sotrRoot = document.querySelector('[data-lookup="sotr"]');

  var clientList = clientRoot ? JSON.parse(clientRoot.getAttribute('data-countries') || '[]') : [];
  var contypeList = contypeRoot ? JSON.parse(contypeRoot.getAttribute('data-countries') || '[]') : [];
  var sotrList = sotrRoot ? JSON.parse(sotrRoot.getAttribute('data-countries') || '[]') : [];

  var clientReadonly = clientRoot && clientRoot.querySelector('.lookup-input') && clientRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var contypeReadonly = contypeRoot && contypeRoot.querySelector('.lookup-input') && contypeRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var sotrReadonly = sotrRoot && sotrRoot.querySelector('.lookup-input') && sotrRoot.querySelector('.lookup-input').hasAttribute('readonly');

  var cliNameInput = document.getElementById('contact-cli-name');
  var contypeNameInput = document.getElementById('contact-contype-name');
  var sotrNameInput = document.getElementById('contact-sotr-name');

  function onClientSelect(id, name) {
    if (cliNameInput) cliNameInput.value = name;
  }

  function onContypeSelect(id, name) {
    if (contypeNameInput) contypeNameInput.value = name;
  }

  function onSotrSelect(id, name) {
    if (sotrNameInput) sotrNameInput.value = name;
  }

  if (clientRoot && !clientReadonly) {
    bindLookup({ root: clientRoot, data: clientList, readonly: clientReadonly, onSelect: onClientSelect });
  }
  if (contypeRoot && !contypeReadonly) {
    bindLookup({ root: contypeRoot, data: contypeList, readonly: contypeReadonly, onSelect: onContypeSelect });
  }
  if (sotrRoot && !sotrReadonly) {
    bindLookup({ root: sotrRoot, data: sotrList, readonly: sotrReadonly, onSelect: onSotrSelect });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var anyOpen = document.querySelectorAll('.lookup-pop.open');
      if (anyOpen.length > 0) return;
      e.preventDefault();
      if (window.parent && window.parent !== window) {
        try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
      } else {
        window.location.href = 'contact.php';
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
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
</body>
</html>
