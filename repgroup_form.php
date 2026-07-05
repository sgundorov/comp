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
$values = ['name' => '', 'note' => ''];
$origName = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT name, note FROM repgroup WHERE gr_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$r['name'];
        $values['name'] = ($mode === 'copy') ? '' : (string)$r['name'];
        $values['note'] = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim((string)($_POST['name'] ?? ''));
    $values['note'] = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['name'] === '') { $errors[] = 'Название обязательно.'; $focusField = 'name'; }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM repgroup WHERE gr_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'repgroup' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: repgroup.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = @$conn->prepare("INSERT INTO repgroup (name, note) VALUES (?, ?)");
            if ($stmt) {
                bind_auto($stmt, [$values['name'], $values['note']]);
                $stmt->execute();
                $newId = (int)$stmt->insert_id;
                $stmt->close();
            } else {
                $newId = 0;
            }
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'repgroup', 'name', 'name', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['name'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: repgroup.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = @$conn->prepare("UPDATE repgroup SET name = ?, note = ? WHERE gr_id = ?");
            if ($stmt) {
                bind_auto($stmt, [$values['name'], $values['note'], $id]);
                $stmt->execute();
                $stmt->close();
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: repgroup.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Группа шаблонов (новая)',
    'edit'   => 'Группа шаблонов: ' . $origName,
    'copy'   => 'Группа шаблонов: ' . $origName . ' (копия)',
    'delete' => 'Группа шаблонов: ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Группа шаблонов';
$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/repgroup.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="repgroup_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>
<?= render_field('Название <span class="required">*</span>',
    render_input('text', 'name', $values['name'], [
        'id' => 'repgroup-name',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>
<?= render_field('Примечание',
    render_input('text', 'note', $values['note'], [
        'id' => 'repgroup-note',
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
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'repgroup.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'repgroup.php', ['class'=>'btn-secondary'])]
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
  <style>.form-modal{max-width:500px}.page--form{max-width:500px}.form{max-width:500px}</style>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
</body>
</html>
