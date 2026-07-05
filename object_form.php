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
    'object' => '',
    'name'   => '',
    'type'   => 'Документ',
    'note'   => '',
];
$origObject = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT o.object, o.name, o.type, o.note FROM `object` o WHERE o.object_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origObject = (string)$r['object'];
        $values['object'] = ($mode === 'copy') ? '' : (string)$r['object'];
        $values['name']   = (string)$r['name'];
        $values['type']   = (string)$r['type'] ?: 'Документ';
        $values['note']   = (string)$r['note'];
    }
}

$typeOptions = ['Документ', 'Поле', 'Отчет'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['object'] = trim((string)($_POST['object'] ?? ''));
    $values['name']   = trim((string)($_POST['name'] ?? ''));
    $values['type']   = trim((string)($_POST['type'] ?? ''));
    $values['note']   = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['object'] === '') { $errors[] = 'Поле «Обозначение» обязательно для заполнения.'; $focusField = 'object'; }
        if (!in_array($values['type'], $typeOptions, true)) $errors[] = 'Выберите тип объекта.';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM `object` WHERE object_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'object' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: object.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO `object` (`object`, name, type, note) VALUES (?, ?, ?, ?)");
            bind_auto($stmt, [$values['object'], $values['name'], $values['type'], $values['note']]);
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'object', 'id', 'id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['object'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: object.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE `object` SET `object` = ?, name = ?, type = ?, note = ? WHERE object_id = ?");
            bind_auto($stmt, [$values['object'], $values['name'], $values['type'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: object.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Объект доступа (новый)',
    'edit'   => 'Объект доступа: ' . $origObject,
    'copy'   => 'Объект доступа: ' . $origObject . ' (копия)',
    'delete' => 'Объект доступа: ' . $origObject . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

function render_type_radio(string $name, string $value, string $current, bool $readonly, bool $required = false): string {
    $attrs = [
        'type'     => 'radio',
        'name'     => $name,
        'value'    => $value,
        'class'    => 'field-radio',
        'id'       => $name . '_' . strtolower($value),
    ];
    if ($value === $current) $attrs['checked'] = true;
    if ($readonly) $attrs['disabled'] = true;
    if ($required) $attrs['required'] = true;
    $input = '<input' . _render_btn_attrs($attrs) . ' />';
    $label = '<label class="field-radio-label" for="' . h($name . '_' . strtolower($value)) . '">' . h($value) . '</label>';
    return $input . $label;
}

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/object.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="object_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (3 > 5): ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'object.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?= render_field('Объект доступа', render_input('text', 'object', $values['object'], [
        'id' => 'object-name',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Наименование', render_input('text', 'name', $values['name'], [
        'id' => 'object-name-full',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<div class="field field--narrow<?= $isReadonly ? ' field--readonly' : '' ?>">
  <label class="field-label">Тип</label>
  <div class="field-radio-group">
    <?php foreach ($typeOptions as $opt): ?>
      <?= render_type_radio('type', $opt, $values['type'], $isReadonly, !$isReadonly) ?>
    <?php endforeach; ?>
  </div>
</div>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'object-note',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'object.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'object.php', ['class'=>'btn-secondary'])]
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
    .field-radio-group {
      display: flex; gap: 16px; align-items: center; padding: 6px 0;
    }
    .field-radio-label { font-size: 14px; cursor: pointer; }
    .field--readonly .field-radio-label { cursor: default; }
    .field-radio { width: auto; margin: 0; }
  </style>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script>
    (function () {
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          window.location.href = 'object.php';
        }
      });
    })();

    (function () {
      var form = document.querySelector('form[data-form-modal]');
      if (!form) return;
      var focusable = Array.from(form.querySelectorAll(
        'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]):not([disabled]),' +
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