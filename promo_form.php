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
$values = ['promo' => '', 'bdate' => ($mode === 'new' ? date('Y-m-d') : ''), 'edate' => '', 'count' => '', 'procent' => '', 'note' => ''];
$origPromo = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT promo, bdate, edate, count, procent, note FROM promo WHERE promo_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origPromo = (string)$r['promo'];
        $values['promo']   = ($mode === 'copy') ? '' : (string)$r['promo'];
        $values['bdate']   = ($mode === 'copy') ? '' : (string)$r['bdate'];
        $values['edate']   = ($mode === 'copy') ? '' : (string)$r['edate'];
        $values['count']   = ($mode === 'copy') ? '' : (string)$r['count'];
        $values['procent'] = ($mode === 'copy') ? '' : (string)$r['procent'];
        $values['note']    = ($mode === 'copy') ? '' : (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['promo']   = trim((string)($_POST['promo'] ?? ''));
    $values['bdate']   = trim((string)($_POST['bdate'] ?? ''));
    $values['edate']   = trim((string)($_POST['edate'] ?? ''));
    $values['count']   = trim((string)($_POST['count'] ?? ''));
    $values['procent'] = trim((string)($_POST['procent'] ?? ''));
    $values['note']    = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete' && $values['promo'] === '') {
        $errors[] = 'Поле «Вид рекламы» обязательно для заполнения.';
        $focusField = 'promo';
    }
    if ($mode !== 'delete' && $values['promo'] !== '') {
        $check = $conn->prepare("SELECT promo_id FROM promo WHERE LOWER(promo) = LOWER(?) AND promo_id <> ?");
        $excludeId = ($mode === 'edit') ? $id : 0;
        bind_auto($check, [$values['promo'], $excludeId]);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) $errors[] = 'Источник рекламы с таким названием уже существует.';
        $check->close();
    }
    if ($mode !== 'delete' && $values['bdate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['bdate'])) {
        $errors[] = 'Дата начала должна быть в формате ГГГГ-ММ-ДД.';
        $focusField = 'bdate';
    }
    if ($mode !== 'delete' && $values['edate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['edate'])) {
        $errors[] = 'Дата окончания должна быть в формате ГГГГ-ММ-ДД.';
        $focusField = 'edate';
    }
    if ($mode !== 'delete' && $values['count'] !== '' && (!ctype_digit($values['count']) || (int)$values['count'] < 0)) {
        $errors[] = 'Количество клиентов должно быть целым неотрицательным числом.';
        $focusField = 'count';
    }
    if ($mode !== 'delete' && $values['procent'] !== '' && (!is_numeric($values['procent']) || (float)$values['procent'] < 0 || (float)$values['procent'] > 100)) {
        $errors[] = 'Процент должен быть числом от 0 до 100.';
        $focusField = 'procent';
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM promo WHERE promo_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'promo' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $origPromo]);
                exit;
            }
            header('Location: promo.php');
            exit;
        }
        $bdateVal   = $values['bdate'] !== '' ? $values['bdate'] : null;
        $edateVal   = $values['edate'] !== '' ? $values['edate'] : null;
        $countVal   = $values['count'] !== '' ? (int)$values['count'] : null;
        $procentVal = $values['procent'] !== '' ? (float)$values['procent'] : null;
        $noteVal    = $values['note'] !== '' ? $values['note'] : null;

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO promo (promo, bdate, edate, count, procent, note) VALUES (?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [$values['promo'], $bdateVal, $edateVal, $countVal, $procentVal, $noteVal]);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'promo', 'promo_id', 'promo_id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['promo'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: promo.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE promo SET promo = ?, bdate = ?, edate = ?, count = ?, procent = ?, note = ? WHERE promo_id = ?");
            bind_auto($stmt, [$values['promo'], $bdateVal, $edateVal, $countVal, $procentVal, $noteVal, $id]);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['promo']]);
                exit;
            }
            header('Location: promo.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Вид рекламы (новый)',
    'edit'   => 'Вид рекламы: ' . $origPromo,
    'copy'   => 'Вид рекламы: ' . $origPromo . ' (копия)',
    'delete' => 'Вид рекламы: ' . $origPromo . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Экспорт в Excel';

$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/promo.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="promo_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?= render_field('Источник рекламы', render_input('text', 'promo', $values['promo'], [
        'id' => 'promo-name-input',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<div class="field-row">
<?= render_field('Дата начала',
    render_input('date', 'bdate', $values['bdate'], [
        'id' => 'promo-bdate-input',
        'style' => 'width:138px',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false
) ?>
<?= render_field('Дата окончания',
    render_input('date', 'edate', $values['edate'], [
        'id' => 'promo-edate-input',
        'style' => 'width:138px',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false
) ?>
</div>

<div class="field-row">
<?= render_field('Количество клиентов',
    render_input('number', 'count', $values['count'], [
        'id' => 'promo-count-input',
        'min' => '0',
        'step' => '1',
        'style' => 'width:80px',
        'readonly' => true,
        'tabindex' => '-1',
    ]),
    false
) ?>
<?= render_field('Процент',
    render_input('number', 'procent', $values['procent'], [
        'id' => 'promo-procent-input',
        'min' => '0',
        'max' => '100',
        'step' => '0.01',
        'style' => 'width:80px',
        'readonly' => true,
        'tabindex' => '-1',
    ]),
    false
) ?>
</div>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'promo-note-input',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'promo.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'promo.php', ['class'=>'btn-secondary'])]
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
            window.location.href = 'promo.php';
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
