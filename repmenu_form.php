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
    'number'   => '',
    'gr_id'    => (string)((int)($_GET['gr_id'] ?? 0) ?: 0),
    'name'     => '',
    'fname'    => '',

    'hide_flag'=> '0',
    'note'     => '',
];
$origName = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT m.number, m.gr_id, m.name, m.fname, m.HIDE_FLAG, m.note FROM repmenu m WHERE m.rp_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$r['name'];
        $values['number']    = ($mode === 'copy') ? '' : (string)$r['number'];
        $values['gr_id']     = (string)$r['gr_id'];
        $values['name']      = ($mode === 'copy') ? '' : (string)$r['name'];
        $values['fname']     = (string)$r['fname'];

        $values['hide_flag'] = (string)$r['HIDE_FLAG'];
        $values['note']      = (string)$r['note'];
    }
}

if (($mode === 'new' || $mode === 'copy') && (int)$values['gr_id'] > 0 && $values['number'] === '') {
    $maxStmt = @$conn->prepare("SELECT IFNULL(MAX(number), 0) + 1 AS next_num FROM repmenu WHERE gr_id = ?");
    if ($maxStmt) {
        $gid = (int)$values['gr_id'];
        $maxStmt->bind_param('i', $gid);
        $maxStmt->execute();
        $maxRes = $maxStmt->get_result();
        $maxRow = $maxRes ? $maxRes->fetch_assoc() : null;
        $values['number'] = (string)($maxRow['next_num'] ?? 1);
        $maxStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['number']    = trim((string)($_POST['number'] ?? ''));
    $values['gr_id']     = (string)($_POST['gr_id'] ?? '0');
    $values['name']      = trim((string)($_POST['name'] ?? ''));
    $values['fname']     = trim((string)($_POST['fname'] ?? ''));

    $values['hide_flag'] = (string)($_POST['hide_flag'] ?? '0');
    $values['note']      = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['name'] === '') { $errors[] = 'Название обязательно.'; $focusField = 'name'; }
        if ((int)$values['gr_id'] <= 0) { $errors[] = 'Группа обязательна.'; $focusField = 'gr_id'; }
    }

    if (($mode === 'new' || $mode === 'copy') && (int)$values['gr_id'] > 0 && ($values['number'] === '' || (int)$values['number'] === 0)) {
        $maxStmt = @$conn->prepare("SELECT IFNULL(MAX(number), 0) + 1 AS next_num FROM repmenu WHERE gr_id = ?");
        if ($maxStmt) {
            $gid = (int)$values['gr_id'];
            $maxStmt->bind_param('i', $gid);
            $maxStmt->execute();
            $maxRes = $maxStmt->get_result();
            $maxRow = $maxRes ? $maxRes->fetch_assoc() : null;
            $values['number'] = (string)($maxRow['next_num'] ?? 1);
            $maxStmt->close();
        }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM repmenu WHERE rp_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'repmenu' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: repmenu.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = @$conn->prepare("INSERT INTO repmenu (number, gr_id, name, fname, HIDE_FLAG, note) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                bind_auto($stmt, [
                    (int)$values['number'],
                    (int)$values['gr_id'],
                    $values['name'],
                    $values['fname'],
                    (int)$values['hide_flag'],
                    $values['note'],
                ]);
                $stmt->execute();
                $newId = (int)$stmt->insert_id;
                $stmt->close();
            } else {
                $newId = 0;
            }
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'repmenu', 'number', 'number', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['name'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: repmenu.php');
            exit;
        }
        if ($mode === 'edit') {
            $stmt = @$conn->prepare("UPDATE repmenu SET number = ?, gr_id = ?, name = ?, fname = ?, HIDE_FLAG = ?, note = ? WHERE rp_id = ?");
            if ($stmt) {
                bind_auto($stmt, [
                    (int)$values['number'],
                    (int)$values['gr_id'],
                    $values['name'],
                    $values['fname'],
                    (int)$values['hide_flag'],
                    $values['note'],
                    $id,
                ]);
                $stmt->execute();
                $stmt->close();
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: repmenu.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Документ (новый)',
    'edit'   => 'Документ: ' . $origName,
    'copy'   => 'Документ: ' . $origName . ' (копия)',
    'delete' => 'Документ: ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Документ';

$isReadonly = ($mode === 'delete');

$repgroupList = [];
$crs = @$conn->query("SELECT gr_id AS id, name FROM repgroup ORDER BY name");
if ($crs) while ($cr = $crs->fetch_assoc()) $repgroupList[] = ['id' => (int)$cr['id'], 'name' => (string)$cr['name']];

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/repmenu.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="repmenu_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<style>.repmenu-field{margin-bottom:10px}.repmenu-field:last-child{margin-bottom:0}.repmenu-label{display:block;font-size:12px;color:var(--muted);margin-bottom:2px;text-align:left}</style>

<div style="display:flex;gap:16px;align-items:flex-end;margin-bottom:10px;">
  <div style="flex:0 0 auto;">
    <label class="repmenu-label" for="repmenu-number">Номер</label>
    <?= render_input('number', 'number', $values['number'], [
        'id' => 'repmenu-number',
        'readonly' => true,
        'tabindex' => '-1',
        'style' => 'max-width:80px',
    ]) ?>
  </div>
  <div style="flex:0 0 auto;padding-bottom:2px;">
    <label style="display:inline-flex;align-items:center;gap:6px;">
      <input type="checkbox" name="hide_flag" value="1" <?= $values['hide_flag'] === '1' ? 'checked' : '' ?> <?= $isReadonly ? 'disabled' : '' ?> />
      <span>Не показывать</span>
    </label>
  </div>
</div>

<div class="repmenu-field">
  <label class="repmenu-label" for="repmenu-gr_id">Группа <span class="required">*</span></label>
  <?= render_lookup('repgroup', 'gr_id', $values['gr_id'],
      ($repgroupList[array_search($values['gr_id'], array_column($repgroupList, 'id'))] ?? ['name' => ''])['name'],
      h(json_encode($repgroupList, JSON_UNESCAPED_UNICODE)),
      'repgroup_form.php?mode=new',
      $isReadonly,
      ['id' => 'repmenu-gr_id']
  ) ?>
</div>

<div class="repmenu-field">
  <label class="repmenu-label" for="repmenu-name">Название <span class="required">*</span></label>
  <?= render_input('text', 'name', $values['name'], [
      'id' => 'repmenu-name',
      'required' => !$isReadonly,
      'readonly' => $isReadonly,
      'tabindex' => $isReadonly ? '-1' : null,
  ]) ?>
</div>

<div class="repmenu-field">
  <label class="repmenu-label" for="repmenu-fname">Файл шаблон</label>
  <div style="display:flex;gap:4px;align-items:center;">
    <?= render_input('text', 'fname', $values['fname'], [
        'id' => 'repmenu-fname',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
        'style' => 'flex:1',
    ]) ?>
    <?php if (!$isReadonly): ?>
    <button type="button" class="icon-btn" id="repmenu-fname-btn" title="Выбрать файл" style="height:28px;display:inline-flex;align-items:center;" onclick="document.getElementById('repmenu-fname-input').click();">
      <img src="img/folder.png" alt="" />
    </button>
    <input type="file" id="repmenu-fname-input" style="display:none" onchange="var f=this.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fetch('repmenu_upload.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.ok){document.getElementById('repmenu-fname').value=d.path;}else{alert(d.error||'Ошибка загрузки');}});" />
    <?php endif; ?>
  </div>
</div>

<div class="repmenu-field">
  <label class="repmenu-label" for="repmenu-note">Примечание</label>
  <?= render_input('text', 'note', $values['note'], [
      'id' => 'repmenu-note',
      'readonly' => $isReadonly,
      'tabindex' => $isReadonly ? '-1' : null,
      'style' => 'width:100%',
  ]) ?>
</div>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'repmenu.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'repmenu.php', ['class'=>'btn-secondary'])]
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
  <style>.form-modal{max-width:600px}.page--form{max-width:600px}.form{max-width:600px}.form-table td{padding-bottom:10px}.form-table tr:last-child td{padding-bottom:0}</style>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
</body>
</html>
