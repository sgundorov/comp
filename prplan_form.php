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
$values = ['prplan' => '', 'bdate' => '', 'edate' => '', 'note' => ''];
$origPrplan = '';

function prplan_norm_date(string $v): string {
    return norm_date_smart($v);
}

/** дд.мм.гггг -> ГГГГ-ММ-ДД (для нативного поля с календарём) */
function prplan_to_picker(string $v): string {
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($v), $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
    return '';
}

/** значение из календаря (или умный ввод) -> дд.мм.гггг для БД */
function prplan_from_picker(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $norm = norm_date_smart($v);
    if ($norm !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $norm, $m)) return "{$m[3]}.{$m[2]}.{$m[1]}";
    if ($norm !== '' && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $norm)) return $norm;
    return $v;
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT prplan, bdate, edate, note FROM prplan WHERE prplan_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origPrplan = (string)$r['prplan'];
        $values['prplan'] = ($mode === 'copy') ? '' : (string)$r['prplan'];
        $values['bdate']  = ($mode === 'copy') ? '' : prplan_to_picker((string)$r['bdate']);
        $values['edate']  = ($mode === 'copy') ? '' : prplan_to_picker((string)$r['edate']);
        $values['note']   = ($mode === 'copy') ? '' : (string)$r['note'];
        $values['note']   = ($mode === 'copy') ? '' : (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['prplan'] = trim((string)($_POST['prplan'] ?? ''));
    $values['bdate']  = prplan_from_picker((string)($_POST['bdate'] ?? ''));
    $values['edate']  = prplan_from_picker((string)($_POST['edate'] ?? ''));
    $values['note']   = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['prplan'] === '') {
            $errors[] = 'Поле «Тарифный план» обязательно для заполнения.';
            $focusField = 'prplan';
        }
        foreach (['bdate' => 'Начало сезона', 'edate' => 'Конец сезона'] as $df => $dl) {
            if ($values[$df] !== '' && prplan_norm_date($values[$df]) === '') {
                $errors[] = 'Поле «' . $dl . '» должно содержать дату в формате дд.мм.гггг.';
                $focusField = $df;
            }
        }
        if ($values['prplan'] !== '') {
            $check = $conn->prepare("SELECT prplan_id FROM prplan WHERE LOWER(prplan) = LOWER(?) AND prplan_id <> ?");
            $excludeId = ($mode === 'edit') ? $id : 0;
            bind_auto($check, [$values['prplan'], $excludeId]);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) $errors[] = 'Тарифный план с таким названием уже существует.';
            $focusField = $focusField ?: 'prplan';
            $check->close();
        }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM prplan WHERE prplan_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'prplan' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $origPrplan]);
                exit;
            }
            header('Location: prplan.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO prplan (prplan, bdate, edate, note) VALUES (?, ?, ?, ?)");
            bind_auto($stmt, [$values['prplan'], $values['bdate'], $values['edate'], $values['note']]);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();
            if ($isAjax) {
                $sort = get_current_sort(['col' => 'id', 'dir' => 'asc']);
                $pageOfNew = computePageOfNew($conn, 'prplan', 'prplan_id', $sort['col'], $sort['dir'], $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['prplan'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: prplan.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE prplan SET prplan = ?, bdate = ?, edate = ?, note = ? WHERE prplan_id = ?");
            bind_auto($stmt, [$values['prplan'], $values['bdate'], $values['edate'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['prplan']]);
                exit;
            }
            header('Location: prplan.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Тарифный план (новый)',
    'edit'   => 'Тарифный план: ' . $origPrplan,
    'copy'   => 'Тарифный план: ' . $origPrplan . ' (копия)',
    'delete' => 'Тарифный план: ' . $origPrplan . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Тарифный план';

$isReadonly = ($mode === 'delete');
$sezonFlag = (int)($appSettings['SezonFlag'] ?? 0) === 1;

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/price.png" alt="" /> <?= h($pageTitle) ?></h2>
<style>
  /* Вертикальные отступы как у полей .field в других формах:
     метка -> поле 6px, поле -> следующий блок 14px */
  .form-table { border-collapse:collapse; }
  .form-table td { vertical-align:top; padding:0 24px 14px 0; }
  .form-table td.form-label { padding-bottom:6px; }
</style>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="prplan_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?= render_field('Тарифный план', render_input('text', 'prplan', $values['prplan'], [
        'id' => 'prplan-name-input',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<?php if ($sezonFlag): ?>
<table class="form-table">
  <tr>
    <td class="form-label">Начало сезона</td>
    <td class="form-label">Конец сезона</td>
  </tr>
  <tr>
    <td><?= render_input('date', 'bdate', $values['bdate'], [
            'id' => 'prplan-bdate',
            'style' => 'width:calc(75% + 20px)',
            'readonly' => $isReadonly,
            'tabindex' => $isReadonly ? '-1' : null,
        ]) ?></td>
    <td><?= render_input('date', 'edate', $values['edate'], [
            'id' => 'prplan-edate',
            'style' => 'width:calc(75% + 20px)',
            'readonly' => $isReadonly,
            'tabindex' => $isReadonly ? '-1' : null,
        ]) ?></td>
  </tr>
</table>
<?php else: ?>
<input type="hidden" name="bdate" value="<?= h($values['bdate']) ?>" />
<input type="hidden" name="edate" value="<?= h($values['edate']) ?>" />
<?php endif; ?>

<?= render_field('Примечание', render_input('text', 'note', $values['note'], [
        'id' => 'prplan-note-input',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'prplan.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'prplan.php', ['class'=>'btn-secondary'])]
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
            window.location.href = 'prplan.php';
          }
        }
      });
    })();
  </script>
</body>
</html>
