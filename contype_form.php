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
$values = ['contype' => '', 'impotant_flag' => '0', 'note' => ''];
$origContype = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT contype, impotant_flag, note FROM contype WHERE contype_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origContype = (string)$r['contype'];
        $values['contype'] = ($mode === 'copy') ? '' : (string)$r['contype'];
        $values['impotant_flag'] = (string)($r['impotant_flag'] ?? '0');
        $values['note'] = ($mode === 'copy') ? '' : (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['contype'] = trim((string)($_POST['contype'] ?? ''));
    $values['impotant_flag'] = !empty($_POST['impotant_flag']) ? '1' : '0';
    $values['note'] = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete' && $values['contype'] === '') {
        $errors[] = 'Поле «Вид контакта» обязательно для заполнения.';
        $focusField = 'contype';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM contype WHERE contype_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'contype' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $origContype]);
                exit;
            }
            header('Location: contype.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $baseContype = $values['contype'];
            $insertCounter = 1;
            $stmt = $conn->prepare("INSERT INTO contype (contype, impotant_flag, note) VALUES (?, ?, ?)");
            bind_auto($stmt, [$values['contype'], $values['impotant_flag'], $values['note']]);
            $stmt->execute();
            $dup = (mysqli_errno($conn) === 1062);
            while ($dup && $insertCounter < 100) {
                $values['contype'] = $baseContype . ' (' . $insertCounter . ')';
                bind_auto($stmt, [$values['contype'], $values['impotant_flag'], $values['note']]);
                $stmt->execute();
                $dup = (mysqli_errno($conn) === 1062);
                $insertCounter++;
            }
            $newId = $conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'contype', 'id', 'id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['contype'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: contype.php');
            exit;
        }
        if ($mode === 'edit') {
            $baseContype = $values['contype'];
            $editCounter = 1;
            $stmt = $conn->prepare("UPDATE contype SET contype = ?, impotant_flag = ?, note = ? WHERE contype_id = ?");
            bind_auto($stmt, [$values['contype'], $values['impotant_flag'], $values['note'], $id]);
            $stmt->execute();
            $dup = (mysqli_errno($conn) === 1062);
            while ($dup && $editCounter < 100) {
                $values['contype'] = $baseContype . ' (' . $editCounter . ')';
                bind_auto($stmt, [$values['contype'], $values['impotant_flag'], $values['note'], $id]);
                $stmt->execute();
                $dup = (mysqli_errno($conn) === 1062);
                $editCounter++;
            }
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['contype']]);
                exit;
            }
            header('Location: contype.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Вид контакта (новый)',
    'edit'   => 'Вид контакта: ' . $origContype,
    'copy'   => 'Вид контакта: ' . $origContype . ' (копия)',
    'delete' => 'Вид контакта: ' . $origContype . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/contype.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="contype_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (2 > 5): ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'contype.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?= render_field('Вид контакта', render_input('text', 'contype', $values['contype'], [
        'id' => 'contype-name-input',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<div class="field field--narrow<?= $isReadonly ? ' field--readonly' : '' ?>" style="display:flex;flex-direction:row;align-items:center;gap:6px;">
  <?= render_checkbox('impotant_flag', '1', $values['impotant_flag'] === '1', ['id' => 'contype-impotant', 'disabled' => $isReadonly]) ?>
  <label class="field-label" for="contype-impotant" style="margin:0;">Важный</label>
</div>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'contype-note-input',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'contype.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'contype.php', ['class'=>'btn-secondary'])]
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
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script>
    (function () {
      const cancelLinks = document.querySelectorAll('a.btn-secondary[href]');
      cancelLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
          if (window.parent && window.parent !== window) {
            e.preventDefault();
            try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
          }
        });
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          if (window.parent && window.parent !== window) {
            try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
          } else {
            window.location.href = 'contype.php';
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
</body>
</html>
