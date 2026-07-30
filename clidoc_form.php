<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';

ensure_clidoc_table($conn);

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'new');
$id   = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$errors = [];
$focusField = '';

$values = [
    'type'      => 0,
    'client_id' => (int)($_GET['client_id'] ?? 0),
    'number'    => '',
    'name'      => '',
    'filename'  => '',
    'note'      => '',
    'date'      => date('Y-m-d'),
    'time'      => date('H:i:s'),
];
$origName = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM clidoc WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        foreach ($values as $k => $v) {
            if (isset($r[$k])) $values[$k] = (string)$r[$k];
        }
        $values['type']      = (int)$r['type'];
        $values['client_id'] = (int)$r['client_id'];
        $values['number']    = (string)$r['number'];
        $origName = (string)$r['name'];
    }
}

$clientId = (int)($values['client_id'] ?: ($_GET['client_id'] ?? 0));
if (($mode === 'new' || $mode === 'copy') && $clientId > 0) {
    $values['client_id'] = $clientId;
    $values['date'] = date('Y-m-d');
    $values['time'] = date('H:i:s');
    $maxStmt = $conn->prepare("SELECT IFNULL(MAX(number), 0) + 1 AS next_num FROM clidoc WHERE type = 0 AND client_id = ?");
    if ($maxStmt) {
        $maxStmt->bind_param('i', $clientId);
        $maxStmt->execute();
        $maxRes = $maxStmt->get_result();
        $maxRow = $maxRes ? $maxRes->fetch_assoc() : null;
        $values['number'] = (string)($maxRow['next_num'] ?? 1);
        $maxStmt->close();
    }
}

$existingFilename = $values['filename'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['number']    = (string)($_POST['number'] ?? '');
    $values['name']      = trim((string)($_POST['name'] ?? ''));
    $values['note']      = trim((string)($_POST['note'] ?? ''));
    $values['client_id'] = (int)($_POST['client_id'] ?? 0);
    $values['date']      = (string)($_POST['date'] ?? date('Y-m-d'));
    $values['time']      = (string)($_POST['time'] ?? date('H:i:s'));
    $uploadedFilename    = (string)($_POST['uploaded_filename'] ?? '');

    if ($values['name'] === '') {
        $errors[] = 'Название документа обязательно.';
        $focusField = 'name';
    }
    if ($values['client_id'] <= 0) {
        $errors[] = 'Клиент не указан.';
    }

    if (empty($errors)) {
        if ($uploadedFilename !== '') {
            $values['filename'] = $uploadedFilename;
        }

        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM clidoc WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'mode' => 'delete', 'id' => $id]);
            exit;
        }

        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO clidoc (type, client_id, number, name, filename, note, date, time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            bind_auto($stmt, [(int)$values['type'], $values['client_id'], (int)$values['number'], $values['name'], $values['filename'], $values['note'], $values['date'], $values['time']]);
            $stmt->execute();
            $newId = (int)$stmt->insert_id;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE clidoc SET name = ?, filename = ?, note = ? WHERE id = ?");
            bind_auto($stmt, [$values['name'], $values['filename'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            $newId = $id;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId]);
        exit;
    }
}

$pageTitle = 'Документ контрагента';
$isReadonly = ($mode === 'delete');
ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form" method="post" action="clidoc_form.php" autocomplete="off" data-form-modal>
<input type="hidden" name="mode" value="<?= h($mode) ?>" />
<?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>" /><?php endif; ?>
<input type="hidden" name="client_id" value="<?= (int)$values['client_id'] ?>" />
<input type="hidden" name="uploaded_filename" id="cd-uploaded-filename" value="<?= h($values['filename']) ?>" />
<input type="hidden" name="number" id="cd-number" value="<?= h($values['number']) ?>" />
<input type="hidden" name="date" value="<?= h($values['date']) ?>" />
<input type="hidden" name="time" value="<?= h($values['time']) ?>" />
<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>
<table class="form-table" style="width:100%">
  <tr>
    <td class="form-label" colspan="2">Документ <span class="required">*</span></td>
  </tr>
  <tr>
    <td colspan="2"><?= render_input('text', 'name', $values['name'], ['id' => 'cd-name', 'required' => !$isReadonly, 'readonly' => $isReadonly, 'style' => 'width:100%']) ?></td>
  </tr>
  <tr>
    <td class="form-label" colspan="2">Файл</td>
  </tr>
  <tr>
    <td colspan="2">
      <?php if (!$isReadonly): ?>
      <div style="display:flex;gap:4px;">
        <input type="text" id="cd-filename" value="<?= h($values['filename']) ?>" style="flex:1;height:28px;padding:0 8px;border:1px solid var(--line);border-radius:2px;font:inherit;color:var(--text);background:var(--bg);box-sizing:border-box;" />
        <button type="button" class="icon-btn" title="Выбрать файл" onclick="document.getElementById('cd-file-input').click();" style="height:28px;box-sizing:border-box;"><img src="img/folder.png" alt="" /></button>
        <input type="file" id="cd-file-input" style="display:none" onchange="var f=this.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('client_id',<?= (int)$values['client_id'] ?>);fd.append('number',document.getElementById('cd-number').value);fetch('clidoc_upload.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.ok){document.getElementById('cd-filename').value=d.filename;document.getElementById('cd-uploaded-filename').value=d.filename;}else{alert(d.error||'Ошибка загрузки');}});" />
      </div>
      <?php else: ?>
      <input type="text" value="<?= h($values['filename']) ?>" readonly style="width:100%;height:28px;padding:0 8px;border:1px solid var(--line);border-radius:2px;font:inherit;color:var(--text);background:var(--bg);box-sizing:border-box;" />
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td class="form-label" colspan="2">Примечание</td>
  </tr>
  <tr>
    <td colspan="2"><?= render_input('text', 'note', $values['note'], ['id' => 'cd-note', 'style' => 'width:100%', 'readonly' => $isReadonly]) ?></td>
  </tr>
</table>
<?php
$actions = $isReadonly
    ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button', 'class'=>'btn-secondary', 'data-form-close'=>'1'])]
    : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_icon_text('img/cancel.png', 'Отменить', ['type'=>'button', 'class'=>'btn-secondary', 'data-form-close'=>'1'])];
echo render_form_actions($actions);
?>
</form>
<?php
$html = ob_get_clean();
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $html, 'mode' => $mode, 'focusField' => $focusField], JSON_UNESCAPED_UNICODE);
} else {
    echo $html;
}
