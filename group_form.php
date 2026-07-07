<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';

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

$serviceFlag = (string)($_GET['service_flag'] ?? $_POST['service_flag'] ?? '0');
$serviceFlag = $serviceFlag === '1' ? '1' : '0';

$errors = [];
$focusField = '';
$values = [
    'name' => '',
    'pos'  => '0',
    'note' => '',
];
$origName = '';

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = $conn->prepare("SELECT g.name, g.pos, g.note FROM `group` g WHERE g.group_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$r['name'];
        $values['name'] = ($mode === 'copy') ? '' : (string)$r['name'];
        $values['pos'] = (string)$r['pos'];
        $values['note'] = (string)$r['note'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim((string)($_POST['name'] ?? ''));
    $values['note'] = trim((string)($_POST['note'] ?? ''));

    if ($mode !== 'delete') {
        if ($values['name'] === '') { $errors[] = 'Запись не найдена.'; $focusField = 'name'; }
    }

    if (empty($errors)) {
        if ($mode === 'delete') {
            $stmt = $conn->prepare("DELETE FROM `group` WHERE group_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM marks WHERE tbl = 'group' AND row_id = " . (int)$id);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: group.php');
            exit;
        }
        if ($mode === 'new' || $mode === 'copy') {
            $baseName = $values['name'];
            $counter = 1;
            $stmt = @$conn->prepare("INSERT INTO `group` (name, note, service_flag) VALUES (?, ?, ?)");
            if ($stmt) {
                do {
                    bind_auto($stmt, [$values['name'], $values['note'], $serviceFlag]);
                    $stmt->execute();
                    $dup = (mysqli_errno($conn) === 1062);
                    if ($dup) {
                        $values['name'] = $baseName . ' (' . $counter . ')';
                        $counter++;
                    }
                } while ($dup && $counter < 100);
                $newId = (int)$stmt->insert_id;
                $stmt->close();
            } else {
                $newId = 0;
            }
            if ($isAjax) {
                $pageOfNew = computePageOfNew($conn, 'group', 'id', 'id', 'asc', $newId, $newId, '');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode, 'id' => $newId, 'name' => $values['name'], 'page' => $pageOfNew]);
                exit;
            }
            header('Location: group.php');
            exit;
        }
        if ($mode === 'edit') {
            $baseName = $values['name'];
            $counter = 1;
            $stmt = @$conn->prepare("UPDATE `group` SET name = ?, note = ? WHERE group_id = ?");
            if ($stmt) {
                do {
                    bind_auto($stmt, [$values['name'], $values['note'], $id]);
                    $stmt->execute();
                    $dup = (mysqli_errno($conn) === 1062);
                    if ($dup) {
                        $values['name'] = $baseName . ' (' . $counter . ')';
                        $counter++;
                    }
                } while ($dup && $counter < 100);
                $stmt->close();
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => $mode]);
                exit;
            }
            header('Location: group.php');
            exit;
        }
    }
}

$titles = [
    'new'    => 'Группа товаров (новая)',
    'edit'   => 'Группа товаров: ' . $origName,
    'copy'   => 'Группа товаров: ' . $origName . ' (копия)',
    'delete' => 'Группа товаров: ' . $origName . ' (удаление)',
];
$pageTitle = $titles[$mode] ?? 'Группа товаров';

$isReadonly = ($mode === 'delete');

$sgroupData = [];
if ($id > 0 && $mode !== 'new') {
    $stmt = $conn->prepare("SELECT sgroup_id AS id, name, note FROM sgroup WHERE group_id = ? ORDER BY sgroup_id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sgroupData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$sgTable = new EmbeddedTable([
    'prefix'           => 'sg',
    'columns'          => [
        ['key' => 'name', 'label' => 'Название подгруппы', 'align' => 'left'],
        ['key' => 'note', 'label' => 'Примечание', 'align' => 'left'],
    ],
    'colWidths'  => ['name' => '250px', 'note' => 'auto'],
    'saveUrl'          => 'sgroup_field_save.php',
    'parentField'      => 'group_id',
    'childFormUrl'     => 'sgroup_form.php',
    'childFormName'    => 'sgroup',
    'hasExport'        => true,
    'hasPrint'         => true,
    'exportUrl'        => 'sgroup_export.php',
    'printUrl'         => 'sgroup_print.php',
    'parentParam'      => 'group_id=',
]);

ob_start();
?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/group.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form<?= $mode === 'delete' ? ' form--delete' : '' ?>" method="post" action="group_form.php" autocomplete="off" data-form-modal>
<?= render_input('hidden', 'mode', $mode) ?>
<?= render_input('hidden', 'id', $id) ?>
<?= render_input('hidden', 'service_flag', $serviceFlag) ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Подгруппы</div>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <?= render_field('Название группы',
        render_input('text', 'name', $values['name'], [
            'id' => 'group-name',
            'required' => !$isReadonly,
            'readonly' => $isReadonly,
            'tabindex' => $isReadonly ? '-1' : null,
        ]),
        true,
        ['readonly' => $isReadonly]
    ) ?>

    <?= render_field('Позиций',
        render_input('text', 'pos', $values['pos'], [
            'id' => 'group-pos',
            'readonly' => true,
            'tabindex' => '-1',
            'style' => 'max-width:80px',
        ]),
        false,
        ['readonly' => true]
    ) ?>

    <?= render_field('Примечание',
        render_input('text', 'note', $values['note'], [
            'id' => 'group-note',
            'readonly' => $isReadonly,
            'tabindex' => $isReadonly ? '-1' : null,
        ]),
        false,
        ['wide' => true, 'readonly' => $isReadonly]
    ) ?>
  </div>

  <div class="tab-pane" data-tab-index="1">
    <?= $sgTable->render($sgroupData) ?>
  </div>
</div>

<?= render_form_note() ?>

<?= render_form_actions(
    $mode === 'delete'
        ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'group.php', ['class'=>'btn-secondary'])]
        : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
           render_btn_link_icon_text('img/cancel.png', 'Отменить', 'group.php', ['class'=>'btn-secondary'])]
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
  <style>.form-modal{max-width:990px}.page--form{max-width:990px}.form{max-width:990px}</style>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
  <script src="assets/embedded-subtable.js"></script>
  <script>
    <?php $sgTable->renderScripts(); ?>
  </script>
</body>
</html>
