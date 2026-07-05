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
$values = [
    'categ'         => '',
    'supplier_flag' => '0',
    'problem_flag'  => '0',
    'color'         => '',
    'note'          => '',
];
$origCateg = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT categ, supplier_flag, problem_flag, color, note FROM cli_categ WHERE cli_categ_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origCateg = (string)$r['categ'];
        $values['categ'] = ($mode === 'copy') ? '' : (string)$r['categ'];
        $values['supplier_flag'] = (string)$r['supplier_flag'];
        $values['problem_flag'] = (string)$r['problem_flag'];
        $values['color'] = $r['color'] ? '#' . str_pad(dechex((int)$r['color']), 6, '0', STR_PAD_LEFT) : '';
        $values['note'] = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['categ'] = (string)($_POST['categ'] ?? '');
    $values['supplier_flag'] = (string)($_POST['supplier_flag'] ?? '0');
    $values['problem_flag'] = (string)($_POST['problem_flag'] ?? '0');
    $values['color'] = (string)($_POST['color'] ?? '');
    $values['note'] = (string)($_POST['note'] ?? '');

    if ($mode !== 'delete' && trim($values['categ']) === '') {
        $errors[] = 'Поле «Категория» обязательно для заполнения.';
        $focusField = 'categ';
    }
    if (is_string($values['color']) && strlen($values['color']) > 0 && !preg_match('/^#[0-9a-fA-F]{6}$/', $values['color'])) {
        $errors[] = 'Некорректный формат цвета. Используйте hex-формат, например #ff0000.';
        $focusField = 'color';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM cli_categ WHERE cli_categ_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'cli_categ' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $origCateg]);
                exit;
            }
            header('Location: cli_categ.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO cli_categ (categ, supplier_flag, problem_flag, color, note) VALUES (?, ?, ?, ?, ?)");
            $colorInt = $values['color'] ? (int)hexdec(ltrim($values['color'], '#')) : 0;
            bind_auto($stmt, [$values['categ'], $values['supplier_flag'], $values['problem_flag'], $colorInt, $values['note']]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка при сохранении: ' . $stmt->error;
                $stmt->close();
                goto render;
            }
            $newId = $conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'cli_categ', 'id', 'id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['categ'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: cli_categ.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE cli_categ SET categ = ?, supplier_flag = ?, problem_flag = ?, color = ?, note = ? WHERE cli_categ_id = ?");
            $colorInt = $values['color'] ? (int)hexdec(ltrim($values['color'], '#')) : 0;
            bind_auto($stmt, [$values['categ'], $values['supplier_flag'], $values['problem_flag'], $colorInt, $values['note'], $id]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка при сохранении: ' . $stmt->error;
                $stmt->close();
                goto render;
            }
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['categ']]);
                exit;
            }
            header('Location: cli_categ.php');
            exit;
        }
    }
}

render:
$titles = [
    'new'    => 'Категория контрагентов (новая)',
    'edit'   => 'Категория контрагентов: ' . $origCateg,
    'copy'   => 'Категория контрагентов: ' . $origCateg . ' (копия)',
    'delete' => 'Категория контрагентов: ' . $origCateg . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';
$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/cli_categ.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="cli_categ_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (2 > 5): /* top actions — currently always hidden */ ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'cli_categ.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?= render_field('Категория', render_input('text', 'categ', $values['categ'], [
        'id' => 'cli-categ-name',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row">
  <div class="field field--narrow<?= $isReadonly ? ' field--readonly' : '' ?>" style="display:flex;flex-direction:row;align-items:center;gap:6px;">
    <?= render_checkbox('supplier_flag', '1', $values['supplier_flag'] === '1', ['id' => 'cli-categ-supplier', 'disabled' => $isReadonly]) ?>
    <label class="field-label" for="cli-categ-supplier" style="margin:0;">Поставщик</label>
  </div>
  <div class="field field--narrow<?= $isReadonly ? ' field--readonly' : '' ?>" style="display:flex;flex-direction:row;align-items:center;gap:6px;">
    <?= render_checkbox('problem_flag', '1', $values['problem_flag'] === '1', ['id' => 'cli-categ-problem', 'disabled' => $isReadonly]) ?>
    <label class="field-label" for="cli-categ-problem" style="margin:0;">Проблемный</label>
  </div>
</div>

<?= render_field('Цвет', render_color_picker('color', $values['color'], $isReadonly), false, ['readonly' => $isReadonly]) ?>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'cli-categ-note',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'cli_categ.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'cli_categ.php', ['class'=>'btn-secondary'])]
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
    .color-picker-wrap { display: flex; align-items: center; gap: 8px; }
    .color-rect { width: 80px; height: 32px; border: 2px solid var(--line); border-radius: 3px; cursor: pointer; flex-shrink: 0; transition: border-color .15s; }
    .color-rect:hover { border-color: var(--accent); }
    .color-rect:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .color-rect--empty { background-image: repeating-linear-gradient(45deg, transparent, transparent 3px, rgba(0,0,0,.08) 3px, rgba(0,0,0,.08) 6px); }
    .color-picker-btn { width: 28px; height: 28px; border: 1px solid var(--line); background: var(--btn); color: #fff; border-radius: 2px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .color-picker-btn:hover { background: var(--btn-hover); }
    .color-picker-btn:disabled { opacity: .4; cursor: default; }
    .color-picker-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; }
    .color-picker-modal.open { display: flex; }
    .color-picker-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.4); }
    .color-picker-modal-content { position: relative; background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 16px; max-width: 520px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .color-picker-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-weight: 600; font-size: 15px; }
    .color-picker-modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text); padding: 0 4px; line-height: 1; }
    .color-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, 32px); gap: 4px; justify-content: center; }
    .color-swatch-item { width: 32px; height: 32px; border: 2px solid transparent; border-radius: 3px; cursor: pointer; box-sizing: border-box; transition: transform .1s, border-color .1s; }
    .color-swatch-item:hover { border-color: var(--accent); transform: scale(1.12); }
    .color-swatch-item.selected { border-color: #fff; box-shadow: 0 0 0 2px var(--accent); }
    .color-picker-footer { display: flex; gap: 8px; justify-content: center; margin-top: 12px; }
    .color-picker-custom-btn, .color-picker-clear-btn { padding: 4px 14px; border: 1px solid var(--line); border-radius: 3px; background: var(--btn); color: var(--text); cursor: pointer; font-size: 13px; }
    .color-picker-custom-btn:hover, .color-picker-clear-btn:hover { background: var(--btn-hover); }
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/color-picker.js"></script>
  <?= render_color_picker_modal() ?>
  <script>
    ColorPicker.init();
    document.querySelectorAll('.color-picker-wrap').forEach(function (el) { ColorPicker.wrap(el); });

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
            window.location.href = 'cli_categ.php';
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
