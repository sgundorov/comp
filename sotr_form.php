<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

ensure_marks_table($conn);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) {
    $mode = 'edit';
}

$errors = [];
$focusField = '';
$values = [
    'last_name'   => '',
    'first_name'  => '',
    'second_name' => '',
    'role_id'     => 0,
    'phone'       => '',
    'sphone'      => '',
    'email'       => '',
    'address'     => '',
    'login'       => '',
    'passw'       => '',
    'inn'         => '',
    'title'       => '',
    'note'        => '',
];
$origLastName = '';

function load_role_list(mysqli $conn): array {
    $out = [];
    $rs = $conn->query("SELECT role_id, role FROM role ORDER BY role");
    if ($rs) while ($r = $rs->fetch_assoc()) {
        $out[] = ['id' => (int)$r['role_id'], 'name' => (string)$r['role']];
    }
    return $out;
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT last_name, first_name, second_name, role_id, phone, sphone, email, address, login, passw, inn, title, note
                            FROM sotr WHERE sotr_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origLastName = (string)$r['last_name'];
        $values['last_name']   = ($mode === 'copy') ? '' : (string)$r['last_name'];
        $values['first_name']  = (string)$r['first_name'];
        $values['second_name'] = (string)$r['second_name'];
        $values['role_id']     = (int)($r['role_id'] ?? 0);
        $values['phone']       = (string)$r['phone'];
        $values['sphone']      = (string)$r['sphone'];
        $values['email']       = (string)$r['email'];
        $values['address']     = (string)$r['address'];
        $values['login']       = (string)$r['login'];
        $values['passw']       = (string)$r['passw'];
        $values['inn']         = (string)$r['inn'];
        $values['title']       = (string)$r['title'];
        $values['note']        = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['last_name']   = trim((string)($_POST['last_name'] ?? ''));
    $values['first_name']  = trim((string)($_POST['first_name'] ?? ''));
    $values['second_name'] = trim((string)($_POST['second_name'] ?? ''));
    $values['role_id']     = (int)($_POST['role_id'] ?? 0);
    $values['phone']       = trim((string)($_POST['phone'] ?? ''));
    $values['sphone']      = trim((string)($_POST['sphone'] ?? ''));
    $values['email']       = trim((string)($_POST['email'] ?? ''));
    $values['address']     = trim((string)($_POST['address'] ?? ''));
    $values['login']       = trim((string)($_POST['login'] ?? ''));
    $values['passw']       = (string)($_POST['passw'] ?? '');
    $values['inn']         = trim((string)($_POST['inn'] ?? ''));
    $values['title']       = trim((string)($_POST['title'] ?? ''));
    $values['note']        = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['last_name'] === '') { $errors[] = 'Поле «Фамилия» обязательно для заполнения.'; if ($focusField === '') $focusField = 'last_name'; }
        if ($values['first_name'] === '') { $errors[] = 'Поле «Имя» обязательно для заполнения.'; if ($focusField === '') $focusField = 'first_name'; }
        if ($values['role_id'] <= 0) { $errors[] = 'Поле «Роль» обязательно для заполнения.'; if ($focusField === '') $focusField = 'role_id'; }
        if ($values['role_id'] > 0) {
            $check = $conn->prepare("SELECT role_id FROM role WHERE role_id = ?");
            $check->bind_param('i', $values['role_id']);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) $errors[] = 'Выбранная роль не найдена.';
            $check->close();
        }
    }

    if (count($errors) === 0) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM sotr WHERE sotr_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: sotr.php');
            exit;
        }

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO sotr (last_name, first_name, second_name, role_id, phone, sphone, email, address, login, passw, inn, title, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$values['last_name'], $values['first_name'], $values['second_name'],
                $values['role_id'],
                $values['phone'], $values['sphone'], $values['email'],
                $values['address'], $values['login'], $values['passw'],
                $values['inn'], $values['title'], $values['note']]);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'sotr', 'id', 'last_name', 'asc', $values['last_name'], $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'id' => $newId, 'name' => trim($values['last_name'] . ' ' . $values['first_name'] . ' ' . $values['second_name']), 'page' => $pageOfNew], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: sotr.php?focus=' . $newId);
            exit;
        }

        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE sotr SET last_name=?, first_name=?, second_name=?, role_id=?, phone=?, sphone=?, email=?, address=?, login=?, passw=?, inn=?, title=?, note=? WHERE sotr_id=?");
            bind_auto($stmt, [$values['last_name'], $values['first_name'], $values['second_name'],
                $values['role_id'],
                $values['phone'], $values['sphone'], $values['email'],
                $values['address'], $values['login'], $values['passw'],
                $values['inn'], $values['title'], $values['note'],
                $id]);
            $stmt->execute();
            $stmt->close();

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: sotr.php?focus=' . $id);
            exit;
        }
    }
}

$roleList = load_role_list($conn);
$roleOptions = '';
foreach ($roleList as $r) {
    $sel = $r['id'] === $values['role_id'] ? ' selected' : '';
    $roleOptions .= '<option value="' . $r['id'] . '"' . $sel . '>' . htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') . '</option>';
}

$titles = [
    'new'    => 'Новый сотрудник',
    'edit'   => 'Сотрудник: ' . ($origLastName ?: 'ред.'),
    'copy'   => 'Сотрудник: ' . ($origLastName ?: 'ред.') . ' (копия)',
    'delete' => 'Сотрудник: ' . ($origLastName ?: 'ред.') . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

$currentRoleName = '';
foreach ($roleList as $r) {
    if ($r['id'] === (int)$values['role_id']) { $currentRoleName = $r['name']; break; }
}

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/sotr.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="sotr_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?php if ($mode === 'edit' || $mode === 'delete'): ?>
<?= render_input('hidden', 'id', $id) ?>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (3 > 5): /* top actions вЂ” currently always hidden */ ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'sotr.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?php if ($mode === 'delete'): ?>
  <p>Вы действительно хотите удалить сотрудника <strong><?= h($origLastName) ?></strong>?</p>
<?php else: ?>

<div class="field-row">
  <?= render_field('Фамилия',
      render_input('text', 'last_name', $values['last_name'], [
          'id' => 'f_last_name',
          'required' => !$isReadonly,
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      true,
      ['readonly' => $isReadonly]
  ) ?>
  <?= render_field('Имя',
      render_input('text', 'first_name', $values['first_name'], [
          'id' => 'f_first_name',
          'required' => !$isReadonly,
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      true,
      ['readonly' => $isReadonly]
  ) ?>
</div>

<?= render_field('Отчество',
    render_input('text', 'second_name', $values['second_name'], [
        'id' => 'f_second_name',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row">
  <?= render_field('Сотовый телефон',
      render_input('text', 'sphone', $values['sphone'], [
          'id' => 'f_sphone',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
  <?= render_field('Телефон',
      render_input('text', 'phone', $values['phone'], [
          'id' => 'f_phone',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
</div>

<div class="field-row">
  <?= render_field('Роль',
      render_lookup('role', 'role_id', $values['role_id'], $currentRoleName,
          h(json_encode($roleList, JSON_UNESCAPED_UNICODE)),
          'role_form.php?mode=new', $isReadonly, ['id' => 'f_role_id']),
      true,
      ['readonly' => $isReadonly]
  ) ?>
  <?= render_field('Email',
      render_input('email', 'email', $values['email'], [
          'id' => 'f_email',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
</div>

<?= render_field('Адрес',
    render_input('text', 'address', $values['address'], [
        'id' => 'f_address',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<div class="field-row">
  <div class="field-row-body">
  <?= render_field('Должность',
      render_input('text', 'title', $values['title'], [
          'id' => 'f_title',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
  </div>
  <div class="field-row-quarter">
  <?= render_field('ИНН',
      render_input('text', 'inn', $values['inn'], [
          'id' => 'f_inn',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
  </div>
</div>

<div class="field-row">
  <?= render_field('Логин',
      render_input('text', 'login', $values['login'], [
          'id' => 'f_login',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
  <?= render_field('Пароль',
      render_input('password', 'passw', $values['passw'], [
          'id' => 'f_passw',
          'readonly' => $isReadonly,
          'tabindex' => $isReadonly ? '-1' : null,
      ]),
      false,
      ['readonly' => $isReadonly]
  ) ?>
</div>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'f_note',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?php endif; ?>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'sotr.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'sotr.php', ['class'=>'btn-secondary'])]
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
  <script src="assets/lookup.js"></script>
  <script>
    (function () {
      const root = document.querySelector('[data-lookup="role"]');
      if (!root) return;
      const ROLE_LIST = JSON.parse(root.getAttribute('data-countries') || '[]');
      const isReadonly = <?= $isReadonly ? 'true' : 'false' ?>;

      const lookup = bindLookup({
        root: root,
        data: ROLE_LIST,
        readonly: isReadonly
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !lookup.pop.classList.contains('open')) {
          e.preventDefault();
          window.location.href = 'sotr.php';
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
</body>
</html>
