<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

function recalc_group_pos(mysqli $conn, int $groupId): void {
    if ($groupId <= 0) return;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sgroup WHERE group_id = ?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    $upd = $conn->prepare("UPDATE `group` SET pos = ? WHERE group_id = ?");
    bind_auto($upd, [$cnt, $groupId]);
    $upd->execute();
    $upd->close();
}

function fetch_sgroup_list_for_group(mysqli $conn, int $groupId, string $serviceFlag): array {
    if ($groupId <= 0) return [];
    $stmt = $conn->prepare("SELECT sgroup_id AS id, name, note FROM sgroup WHERE group_id = ? AND service_flag = ? ORDER BY sgroup_id ASC");
    $stmt->bind_param('is', $groupId, $serviceFlag);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$mode    = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');
$id      = (int)($_GET['id']   ?? $_POST['id']   ?? 0);
$groupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
if (!in_array($mode, ['new', 'edit', 'copy', 'delete'], true)) $mode = 'edit';

$serviceFlag = (string)($_GET['service_flag'] ?? $_POST['service_flag'] ?? '0');
$serviceFlag = $serviceFlag === '1' ? '1' : '0';
$isService = ($serviceFlag === '1');

$errors = [];
$focusField = '';
$values = ['name' => '', 'note' => ''];
$origName = '';
$groupName = '';

if ($groupId > 0) {
    $gStmt = $conn->prepare("SELECT name, service_flag FROM `group` WHERE group_id = ?");
    $gStmt->bind_param('i', $groupId);
    $gStmt->execute();
    $gRow = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();
    if ($gRow) {
        $groupName = (string)$gRow['name'];
        $serviceFlag = (string)$gRow['service_flag'];
        $serviceFlag = $serviceFlag === '1' ? '1' : '0';
        $isService = ($serviceFlag === '1');
    }
}

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT name, note, group_id FROM sgroup WHERE sgroup_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$r['name'];
        $values['name'] = (string)$r['name'];
        $values['note'] = (string)$r['note'];
        $groupId = (int)$r['group_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim((string)($_POST['name'] ?? ''));
    $values['note'] = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['name'] === '') { $errors[] = 'Введите наименование.'; $focusField = 'name'; }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM sgroup WHERE sgroup_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            if ($groupId > 0) recalc_group_pos($conn, $groupId);
            if ($isAjax) {
                $newPos = 0;
                if ($groupId > 0) { $q = $conn->query("SELECT pos FROM `group` WHERE group_id = $groupId"); $r = $q ? $q->fetch_assoc() : null; $newPos = $r ? (int)$r['pos'] : 0; }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, '_deleted' => true, 'pos' => $newPos, 'sgroup_list' => fetch_sgroup_list_for_group($conn, $groupId, $serviceFlag)]);
                exit;
            }
            header('Location: sgroup.php' . ($isService ? '?kind=service' : ''));
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $stmt = $conn->prepare("INSERT INTO sgroup (group_id, name, note, service_flag) VALUES (?, ?, ?, ?)");
            bind_auto($stmt, [$groupId, $values['name'], $values['note'], $serviceFlag]);
            $stmt->execute();
            $newId = (int)$conn->insert_id;
            $stmt->close();
            if ($groupId > 0) recalc_group_pos($conn, $groupId);
            if ($isAjax) {
                $newPos = 0;
                if ($groupId > 0) { $q = $conn->query("SELECT pos FROM `group` WHERE group_id = $groupId"); $r = $q ? $q->fetch_assoc() : null; $newPos = $r ? (int)$r['pos'] : 0; }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['name'], 'note' => $values['note'], 'pos' => $newPos, 'sgroup_list' => fetch_sgroup_list_for_group($conn, $groupId, $serviceFlag)]);
                exit;
            }
            header('Location: sgroup.php' . ($isService ? '?kind=service' : ''));
            exit;
        }
        if ($mode === 'edit') {
            $stmt = $conn->prepare("UPDATE sgroup SET name = ?, note = ? WHERE sgroup_id = ?");
            bind_auto($stmt, [$values['name'], $values['note'], $id]);
            $stmt->execute();
            $stmt->close();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $id, 'name' => $values['name'], 'note' => $values['note'], 'sgroup_list' => fetch_sgroup_list_for_group($conn, $groupId, $serviceFlag)]);
                exit;
            }
            header('Location: sgroup.php' . ($isService ? '?kind=service' : ''));
            exit;
        }
    }
}

$pageTitleLabel = $isService ? 'Подгруппа услуг' : 'Подгруппа товаров';
$titles = [
    'new'    => $pageTitleLabel . ' (новая)',
    'edit'   => $pageTitleLabel . ': ' . $origName,
    'copy'   => $pageTitleLabel . ': ' . $origName . ' (копия)',
    'delete' => $pageTitleLabel . ': ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? $pageTitleLabel;
$isReadonly = ($mode === 'delete');

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="sgroup_form.php?ajax=1" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'group_id', $groupId) ?>
<?= render_input('hidden', 'service_flag', $serviceFlag) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if ($groupName): ?>
<?= render_field('Группа',
    render_input('text', 'group_name', $groupName, [
        'id' => 'sgroup-group-name',
        'readonly' => true,
        'tabindex' => '-1',
        'style' => 'background:#fffde7',
    ]),
    false,
    ['readonly' => true]
) ?>
<?php endif; ?>

<?= render_field('Название подгруппы',
    render_input('text', 'name', $values['name'], [
        'id' => 'sgroup-name',
        'required' => !$isReadonly,
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    true,
    ['readonly' => $isReadonly]
) ?>

<?= render_field('Примечание',
    render_input('text', 'note', $values['note'], [
        'id' => 'sgroup-note',
        'readonly' => $isReadonly,
        'tabindex' => $isReadonly ? '-1' : null,
    ]),
    false,
    ['wide' => true, 'readonly' => $isReadonly]
) ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           '<button type="button" class="btn btn-secondary" data-form-close><img src="img/cancel.png" alt=""> Отменить</button>']
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
      var isService = <?= json_encode($isService) ?>;
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          window.location.href = 'sgroup.php' + (isService ? '?kind=service' : '');
        }
      });
    })();
  </script>
</body>
</html>
