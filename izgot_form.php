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
    'izgot'      => '',
    'country_id' => 0,
    'note'       => '',
];
$origIzgot = '';
$origCountryName = '';

function load_country_list(mysqli $conn): array {
    $out = [];
    $rs = $conn->query("SELECT country_id, country FROM country ORDER BY country");
    if ($rs) while ($r = $rs->fetch_assoc()) {
        $out[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['country']];
    }
    return $out;
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT i.izgot, i.country_id, i.note, co.country AS country_name
                            FROM izgot i LEFT JOIN country co ON co.country_id = i.country_id
                            WHERE i.izgot_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origIzgot = (string)$r['izgot'];
        $origCountryName = (string)$r['country_name'];
        $values['izgot'] = ($mode === 'copy') ? '' : (string)$r['izgot'];
        $values['country_id'] = (int)$r['country_id'];
        $values['note'] = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['izgot'] = (string)($_POST['izgot'] ?? '');
    $values['country_id'] = (int)($_POST['country_id'] ?? 0);
    $values['note'] = (string)($_POST['note'] ?? '');

    if ($mode !== 'delete' && trim($values['izgot']) === '') {
        $errors[] = 'Поле «Наименование» обязательно для заполнения.';
        $focusField = 'izgot';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM izgot WHERE izgot_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'izgot' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: izgot.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO izgot (izgot, country_id, note) VALUES (?, ?, ?)");
            bind_auto($stmt, [$values['izgot'], $values['country_id'], $values['note']]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $newId = (int)$conn->insert_id;
            $stmt->close();
            if (empty($errors) && $isAjax) {
                $pageOfNew = computePageOfNew($conn, 'izgot', 'id', 'id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['izgot'], 'page' => $pageOfNew]);
                exit;
            }
            if (empty($errors)) {
                header('Location: izgot.php');
                exit;
            }
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE izgot SET izgot = ?, country_id = ?, note = ? WHERE izgot_id = ?");
            bind_auto($stmt, [$values['izgot'], $values['country_id'], $values['note'], $id]);
            if (!$stmt->execute()) {
                $errors[] = 'Ошибка БД: ' . $stmt->error;
            }
            $stmt->close();
            if (empty($errors) && $isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['izgot']]);
                exit;
            }
            if (empty($errors)) {
                header('Location: izgot.php');
                exit;
            }
        }
    }
}

$countries = load_country_list($conn);
$currentCountryName = '';
foreach ($countries as $c) {
    if ($c['id'] === (int)$values['country_id']) { $currentCountryName = $c['name']; break; }
}
if ($currentCountryName === '' && $origCountryName !== '' && (int)$values['country_id'] > 0) {
    $currentCountryName = $origCountryName;
}

$titles = [
    'new'    => 'Производитель (новый)',
    'edit'   => 'Производитель: ' . $origIzgot,
    'copy'   => 'Производитель: ' . $origIzgot . ' (копия)',
    'delete' => 'Производитель: ' . $origIzgot . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/izgot.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="izgot_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (3 > 5): /* top actions вЂ” currently always hidden */ ?>
<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true,'tabindex'=>'-1']),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'izgot.php', ['class'=>'btn-secondary','tabindex'=>'-1'])]
) ?>
<?php endif; ?>

<?= render_field('Производитель', render_input('text', 'izgot', $values['izgot'], [
        'id' => 'izgot-name-input',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Страна',
    render_lookup('country', 'country_id', $values['country_id'], $currentCountryName,
        h(json_encode($countries, JSON_UNESCAPED_UNICODE)),
        'country_form.php?mode=new', $isReadonly, ['id' => 'izgot-country-id']),
    false,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'izgot-note-input',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'izgot.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'izgot.php', ['class'=>'btn-secondary'])]
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
      const root = document.querySelector('[data-lookup="country"]');
      if (!root) return;
      const COUNTRY_LIST = JSON.parse(root.getAttribute('data-countries') || '[]');
      const isReadonly = <?= $isReadonly ? 'true' : 'false' ?>;

      const lookup = bindLookup({
        root: root,
        data: COUNTRY_LIST,
        readonly: isReadonly
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !lookup.pop.classList.contains('open')) {
          e.preventDefault();
          if (window.parent && window.parent !== window) {
            try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
          } else {
            window.location.href = 'izgot.php';
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
