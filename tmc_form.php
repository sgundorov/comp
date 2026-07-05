<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/table-helper.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';

ob_start();

$mode = (string)($_POST['mode'] ?? $_GET['mode'] ?? 'new');
$id   = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isReadonly = ($mode === 'delete');
$errors = [];

$categList = [];
$stmt = @$conn->prepare("SELECT categ_id, categ AS name FROM categ ORDER BY categ");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $categList[] = ['id' => (int)$r['categ_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$groupList = [];
$stmt = @$conn->prepare("SELECT group_id, name FROM `group` ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $groupList[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$sgroupList = [];
$stmt = @$conn->prepare("SELECT sgroup_id, name FROM sgroup ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $sgroupList[] = ['id' => (int)$r['sgroup_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$countryList = [];
$stmt = @$conn->prepare("SELECT country_id, country AS name FROM country ORDER BY country");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $countryList[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$izgotList = [];
$stmt = @$conn->prepare("SELECT izgot_id, izgot AS name FROM izgot ORDER BY izgot");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $izgotList[] = ['id' => (int)$r['izgot_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$unitList = [];
$stmt = @$conn->prepare("SELECT unit_id, unit AS name FROM unit ORDER BY unit");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $unitList[] = ['id' => (int)$r['unit_id'], 'name' => (string)$r['name']]; $stmt->close(); }

function getCategName($conn, $id) {
    $stmt = @$conn->prepare("SELECT categ AS name FROM categ WHERE categ_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}
function getGroupName($conn, $id) {
    $stmt = @$conn->prepare("SELECT name FROM `group` WHERE group_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}
function getSgroupName($conn, $id) {
    $stmt = @$conn->prepare("SELECT name FROM sgroup WHERE sgroup_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}
function getCountryName($conn, $id) {
    $stmt = @$conn->prepare("SELECT country AS name FROM country WHERE country_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}
function getIzgotName($conn, $id) {
    $stmt = @$conn->prepare("SELECT izgot AS name FROM izgot WHERE izgot_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}
function getUnitName($conn, $id) {
    $stmt = @$conn->prepare("SELECT unit AS name FROM unit WHERE unit_id = ?");
    if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); if ($res && $r = $res->fetch_assoc()) { $n = (string)$r['name']; $stmt->close(); return $n; } $stmt->close(); } return '';
}

$values = [
    'product_id'    => 0,
    'product_name'  => '',
    'article'       => '',
    'categ_id'      => 0,
    'group_id'      => 0,
    'sgroup_id'     => 0,
    'country_id'    => 0,
    'izgot_id'      => 0,
    'unit_id'       => 0,
    'residue'       => 0,
    'price_in'      => 0,
    'price_out'     => 0,
    'note'          => '',
    'noquant_flag'  => 0,
    'hide_flag'     => 0,
    'site'          => '',
    'description'   => '',
    'photo'         => '',
];

if (($mode === 'edit' || $mode === 'copy' || $mode === 'delete') && $id > 0) {
    $stmt = @$conn->prepare("SELECT * FROM product WHERE product_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $r = $res->fetch_assoc()) {
            foreach ($values as $k => $v) {
                $values[$k] = $r[$k] ?? $v;
            }
        }
        $stmt->close();
    }
}

$historyList = [];
if ($values['product_id'] > 0) {
    $hStmt = $conn->prepare("SELECT d2.quant, d2.price, d2.discount, d2.sum, d2.note, d2.accept_flag,
        tp.typeop AS typeop_name, d.number, d.date, d.time, d.typeop, COALESCE(tp.prihod_flag, 0) AS prihod_flag
        FROM docum2 d2
        JOIN docum d ON d.docum_id = d2.docum_id
        LEFT JOIN typeop tp ON tp.typeop_id = d.typeop
        WHERE d2.product_id = ?
        ORDER BY d2.docum2_id DESC");
    if ($hStmt) {
        $hStmt->bind_param('i', $values['product_id']);
        $hStmt->execute();
        $hRes = $hStmt->get_result();
        while ($hr = $hRes->fetch_assoc()) {
            $historyList[] = [
                'typeop_name'  => (string)$hr['typeop_name'],
                'typeop'       => (int)$hr['typeop'],
                'number'       => (int)$hr['number'],
                'date'         => (string)$hr['date'],
                'time'         => (string)$hr['time'],
                'quant'        => (float)$hr['quant'],
                'price'        => (string)$hr['price'],
                'discount'     => (string)$hr['discount'],
                'sum'          => (string)$hr['sum'],
                'note'         => (string)$hr['note'],
                'accept_flag'  => (int)$hr['accept_flag'],
                'prihod_flag'  => (int)$hr['prihod_flag'],
            ];
        }
        $hStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['product_name'] = (string)($_POST['product_name'] ?? '');
    $values['article']      = (string)($_POST['article'] ?? '');
    $values['categ_id']     = (int)($_POST['categ_id'] ?? 0);
    $values['group_id']     = (int)($_POST['group_id'] ?? 0);
    $values['sgroup_id']    = (int)($_POST['sgroup_id'] ?? 0);
    $values['country_id']   = (int)($_POST['country_id'] ?? 0);
    $values['izgot_id']     = (int)($_POST['izgot_id'] ?? 0);
    $values['unit_id']      = (int)($_POST['unit_id'] ?? 0);
    $values['residue']      = str_replace(',', '.', (string)($_POST['residue'] ?? '0'));
    $values['price_in']     = str_replace(',', '.', (string)($_POST['price_in'] ?? '0'));
    $values['price_out']    = str_replace(',', '.', (string)($_POST['price_out'] ?? '0'));
    $values['note']         = (string)($_POST['note'] ?? '');
    $values['noquant_flag'] = (int)(!empty($_POST['noquant_flag']));
    $values['hide_flag']    = (int)(!empty($_POST['hide_flag']));
    $values['site']         = (string)($_POST['site'] ?? '');
    $values['description']  = (string)($_POST['description'] ?? '');

    $errors = [];
    $focusField = '';
    if (in_array($mode, ['new', 'edit', 'copy']) && trim($values['product_name']) === '') {
        $errors[] = 'Поле «Наименование» обязательно для заполнения.';
        $focusField = 'product_name';
    }

    if (empty($errors)) {
        $photo = $values['photo'];

        if (!empty($_POST['delete_photo'])) {
            if ($values['photo'] !== '') {
                $oldFile = __DIR__ . '/' . $values['photo'];
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $photo = '';
        }

        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['photo']['tmp_name'];
            $orig = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $uploadDir = __DIR__ . '/uploads/tmc';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                    @exec('icacls ' . escapeshellarg($uploadDir) . ' /grant "IUSR:(R)" /T /Q 2>NUL');
                }
                $safeBase = preg_replace('/[\\\\\/:*?"<>|]/u', '_', pathinfo($orig, PATHINFO_FILENAME));
                $safeBase = preg_replace('/\s+/u', ' ', trim($safeBase));
                $fileName = $safeBase . '.' . $ext;
                $dest = $uploadDir . '/' . $fileName;
                $counter = 1;
                while (file_exists($dest)) {
                    $fileName = $safeBase . ' (' . $counter . ').' . $ext;
                    $dest = $uploadDir . '/' . $fileName;
                    $counter++;
                }
                if (@move_uploaded_file($tmp, $dest)) {
                    @exec('icacls ' . escapeshellarg($dest) . ' /grant "IUSR:(R)" /Q 2>NUL');
                    if ($photo !== '') {
                        $oldFile = __DIR__ . '/' . $photo;
                        if (file_exists($oldFile)) @unlink($oldFile);
                    }
                    $photo = 'uploads/tmc/' . $fileName;
                }
            }
        }
        $values['photo'] = $photo;

        if ($mode === 'delete') {
        $stmt = @$conn->prepare("DELETE FROM product WHERE product_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            if ($ajax) {
                ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => 'delete', 'id' => $id], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($mode === 'new' || $mode === 'copy') {
        $baseInsertName = trim($values['product_name']);
        $values['product_name'] = $baseInsertName;
        $insertCounter = 1;
        $stmt = @$conn->prepare("INSERT INTO product (product_name, article, categ_id, group_id, sgroup_id, country_id, izgot_id, unit_id, residue, price_in, price_out, note, noquant_flag, hide_flag, site, description, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            do {
                bind_auto($stmt, [$values['product_name'], $values['article'],
                    $values['categ_id'], $values['group_id'], $values['sgroup_id'], $values['country_id'],
                    $values['izgot_id'], $values['unit_id'],
                    $values['residue'], $values['price_in'], $values['price_out'],
                    $values['note'],
                    $values['noquant_flag'], $values['hide_flag'],
                    $values['site'], $values['description'], $values['photo']]);
                $stmt->execute();
                $dup = (mysqli_errno($conn) === 1062);
                if ($dup) {
                    $values['product_name'] = $baseInsertName . ' (' . $insertCounter . ')';
                    $insertCounter++;
                }
            } while ($dup && $insertCounter < 100);
            $newId = $stmt->insert_id;
            $stmt->close();
            if ($ajax) {
                $pageOfNew = computePageOfNew($conn, 'product', 'id', 'id', 'asc', $newId, $newId, 'service_flag = 0');
                ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => 'new', 'id' => $newId, 'page' => $pageOfNew, 'product_name' => $values['product_name']], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } elseif ($mode === 'edit') {
        $baseEditName = trim($values['product_name']);
        $values['product_name'] = $baseEditName;
        $editCounter = 1;
        $stmt = @$conn->prepare("UPDATE product SET product_name=?, article=?, categ_id=?, group_id=?, sgroup_id=?, country_id=?, izgot_id=?, unit_id=?, residue=?, price_in=?, price_out=?, note=?, noquant_flag=?, hide_flag=?, site=?, description=?, photo=? WHERE product_id=?");
        if ($stmt) {
            do {
                bind_auto($stmt, [$values['product_name'], $values['article'],
                    $values['categ_id'], $values['group_id'], $values['sgroup_id'], $values['country_id'],
                    $values['izgot_id'], $values['unit_id'],
                    $values['residue'], $values['price_in'], $values['price_out'],
                    $values['note'],
                    $values['noquant_flag'], $values['hide_flag'],
                    $values['site'], $values['description'], $values['photo'],
                    $id]);
                $stmt->execute();
                $dup = (mysqli_errno($conn) === 1062);
                if ($dup) {
                    $values['product_name'] = $baseEditName . ' (' . $editCounter . ')';
                    $editCounter++;
                }
            } while ($dup && $editCounter < 100);
            $stmt->close();
            if ($ajax) {
                ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'mode' => 'edit', 'id' => $id, 'product_name' => $values['product_name']], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
    if (!$ajax) {
        header('Location: tmc.php');
        exit;
    }
    }
}

$name = $values['product_name'] !== '' ? $values['product_name'] : '(без названия)';
$pageTitle = match ($mode) {
    'new'    => 'Товар (новый)',
    'edit'   => "Товар: $name",
    'copy'   => "Товар: $name (копия)",
    'delete' => "Товар: $name (удаление)",
    default  => 'Товар',
};

$categName = $values['categ_id'] > 0 ? getCategName($conn, $values['categ_id']) : '';
$groupName = $values['group_id'] > 0 ? getGroupName($conn, $values['group_id']) : '';
$sgroupName = $values['sgroup_id'] > 0 ? getSgroupName($conn, $values['sgroup_id']) : '';
$countryName = $values['country_id'] > 0 ? getCountryName($conn, $values['country_id']) : '';
$izgotName = $values['izgot_id'] > 0 ? getIzgotName($conn, $values['izgot_id']) : '';
$unitName = $values['unit_id'] > 0 ? getUnitName($conn, $values['unit_id']) : '';

$residueList = [];
if ($id > 0) {
    $rStmt = $conn->prepare("SELECT r.quant, COALESCE(st.name, '?') AS store_name FROM residue r LEFT JOIN store st ON r.wh_id = st.store_id WHERE r.product_id = ? ORDER BY st.name");
    if ($rStmt) {
        $rStmt->bind_param('i', $id);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        while ($rr = $rRes->fetch_assoc()) {
            $residueList[] = ['store_name' => (string)$rr['store_name'], 'quant' => (string)$rr['quant']];
        }
        $rStmt->close();
    }
}

ob_start();
if (!$ajax):
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<title><?= h($pageTitle) ?></title>
<link rel="stylesheet" href="app.css" />
</head>
<body>
<?php endif; ?>
<h2 class="page-title<?= $mode === 'delete' ? ' page-title--delete' : '' ?>"><img src="img/tmc.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form" method="post" action="tmc_form.php" enctype="multipart/form-data" data-form-modal style="max-width:790px !important">
<input type="hidden" name="mode" value="<?= h($mode) ?>" />
<?php if ($id > 0): ?><input type="hidden" name="id" value="<?= (int)$id ?>" /><?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="tab-container">
  <div class="tab-headers">
    <div class="tab-header active" data-tab-index="0">Параметры</div>
    <div class="tab-header" data-tab-index="1">Прочее</div>
    <div class="tab-header" data-tab-index="2">Остатки</div>
    <div class="tab-header" data-tab-index="3">История</div>
  </div>

  <div class="tab-pane active" data-tab-index="0">
    <table class="form-table">
      <tr>
        <td class="form-label" colspan="3">Наименование</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'product_name', $values['product_name'], ['class' => 'full', 'id' => 'tmc-name', 'required' => !$isReadonly]) ?></td>
      </tr>
      <tr>
        <td class="form-label">Категория</td>
        <td class="form-label">Группа</td>
        <td class="form-label">Подгруппа</td>
      </tr>
      <tr class="col-3">
        <td><?= render_lookup('categ', 'categ_id', $values['categ_id'], $categName, h(json_encode($categList, JSON_UNESCAPED_UNICODE)), 'categ_form.php?mode=new', $isReadonly, ['id' => 'tmc-categ']) ?></td>
        <td><?= render_lookup('group', 'group_id', $values['group_id'], $groupName, h(json_encode($groupList, JSON_UNESCAPED_UNICODE)), 'group_form.php?mode=new', $isReadonly, ['id' => 'tmc-group']) ?></td>
        <td><?= render_lookup('sgroup', 'sgroup_id', $values['sgroup_id'], $sgroupName, h(json_encode($sgroupList, JSON_UNESCAPED_UNICODE)), 'sgroup_form.php?mode=new', $isReadonly, ['id' => 'tmc-sgroup']) ?></td>
      </tr>
      <tr>
        <td class="form-label">Артикул</td>
        <td class="form-label" colspan="2">&nbsp;</td>
      </tr>
      <tr>
        <td><?= render_input('text', 'article', $values['article'], ['id' => 'tmc-article']) ?></td>
        <td colspan="2">
          <label><input type="checkbox" name="hide_flag" value="1"<?= $values['hide_flag'] ? ' checked' : '' ?><?= $isReadonly ? ' disabled' : '' ?> /> Не показывать</label>
          &nbsp;
          <label><input type="checkbox" name="noquant_flag" value="1"<?= $values['noquant_flag'] ? ' checked' : '' ?><?= $isReadonly ? ' disabled' : '' ?> /> Без количества</label>
        </td>
      </tr>
      <tr>
        <td class="form-label">Производитель</td>
        <td class="form-label">Страна</td>
        <td class="form-label">Сайт</td>
      </tr>
      <tr class="col-3">
        <td><?= render_lookup('izgot', 'izgot_id', $values['izgot_id'], $izgotName, h(json_encode($izgotList, JSON_UNESCAPED_UNICODE)), 'izgot_form.php?mode=new', $isReadonly, ['id' => 'tmc-izgot']) ?></td>
        <td><?= render_lookup('country', 'country_id', $values['country_id'], $countryName, h(json_encode($countryList, JSON_UNESCAPED_UNICODE)), 'country_form.php?mode=new', $isReadonly, ['id' => 'tmc-country']) ?></td>
        <td><?= render_input('text', 'site', $values['site'], ['id' => 'tmc-site']) ?></td>
      </tr>
      <tr>
        <td class="form-label">Закупочная цена</td>
        <td class="form-label">Розничная цена</td>
        <td>&nbsp;</td>
      </tr>
      <tr class="col-3">
        <td><?= render_input('text', 'price_in', $values['price_in'], ['id' => 'tmc-price_in', 'class' => 'num']) ?></td>
        <td><?= render_input('text', 'price_out', $values['price_out'], ['id' => 'tmc-price_out', 'class' => 'num']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label">Количество</td>
        <td class="form-label">Единица измерения</td>
        <td>&nbsp;</td>
      </tr>
      <tr class="col-3">
        <td><?= render_input('text', 'residue', $values['residue'], ['id' => 'tmc-residue', 'class' => 'num']) ?></td>
        <td><?= render_lookup('unit', 'unit_id', $values['unit_id'], $unitName, h(json_encode($unitList, JSON_UNESCAPED_UNICODE)), 'unit_form.php?mode=new', $isReadonly, ['id' => 'tmc-unit']) ?></td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Примечание</td>
      </tr>
      <tr>
        <td colspan="3"><?= render_input('text', 'note', $values['note'], ['class' => 'full', 'id' => 'tmc-note']) ?></td>
      </tr>
      <tr><td colspan="3"><?= render_form_note() ?></td></tr>
    </table>
  </div>

  <div class="tab-pane" data-tab-index="1">
    <table class="form-table">
      <tr>
        <td class="form-label" colspan="3">Описание</td>
      </tr>
      <tr>
        <td colspan="3"><textarea name="description" id="tmc-description" class="full" rows="8" style="width:100%;box-sizing:border-box;"<?= $isReadonly ? ' readonly' : '' ?>><?= h($values['description']) ?></textarea></td>
      </tr>
      <tr>
        <td class="form-label" colspan="3">Фото</td>
      </tr>
      <tr>
        <td colspan="3">
          <?php if (!$isReadonly): ?>
          <label class="file-label" style="display:inline-flex;align-items:center;gap:8px;">
            <input type="file" name="photo" id="tmc-photo" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" />
            <span class="btn-secondary" style="cursor:pointer;padding:4px 12px;font-size:13px;height:auto;border-radius:2px;color:#fff;">Выбрать файл</span>
            <span class="file-name" style="font-size:12px;color:var(--muted);"><?= $values['photo'] !== '' ? h(basename($values['photo'])) : 'Файл не выбран' ?></span>
          </label>
          <div id="tmc-photo-preview" style="margin-top:8px;">
            <?php if ($values['photo'] !== ''): ?>
              <img src="<?= h($values['photo']) ?>" alt="Фото" style="max-width:300px;max-height:200px;border:1px solid var(--line);border-radius:2px;" />
            <?php endif; ?>
          </div>
          <script nonce="opencode">
          document.getElementById('tmc-photo')?.addEventListener('change', function(){
            var fn=this.closest('.file-label').querySelector('.file-name');
            fn.textContent=this.files&&this.files[0]?this.files[0].name:'Файл не выбран';
            var preview=document.getElementById('tmc-photo-preview');
            if(preview&&this.files&&this.files[0]){
              var reader=new FileReader();
              reader.onload=function(e){
                preview.innerHTML='<img src="'+e.target.result+'" alt="Фото" style="max-width:300px;max-height:200px;border:1px solid var(--line);border-radius:2px;" />';
              };
              reader.readAsDataURL(this.files[0]);
            }
          });
          </script>
          <?php else: ?>
          <div style="margin-top:8px;">
            <?php if ($values['photo'] !== ''): ?>
              <img src="<?= h($values['photo']) ?>" alt="Фото" style="max-width:300px;max-height:200px;border:1px solid var(--line);border-radius:2px;" />
            <?php endif; ?>
          </div>
          <span style="font-size:12px;color:var(--muted);"><?= $values['photo'] !== '' ? h(basename($values['photo'])) : 'Файл не выбран' ?></span>
          <?php endif; ?>
          <?php if ($values['photo'] !== '' && !$isReadonly): ?>
            <label style="margin-left:12px;"><input type="checkbox" name="delete_photo" value="1" /> Удалить фото</label>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>

  <div class="tab-pane" data-tab-index="2">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
      <button type="button" class="btn-secondary" id="recalc-residue-btn" title="Пересчитать"><img src="img/refresh.png" alt="" /> Пересчет</button>
      <span style="font-size:12px;color:var(--muted)">Остатки на участках</span>
    </div>
    <div class="table-wrap" style="max-height:300px;overflow-y:auto">
      <table class="data-table" style="min-width:auto;table-layout:fixed;width:100%" id="residue-table">
        <colgroup>
          <col style="width:250px" />
          <col style="width:100px" />
        </colgroup>
        <thead>
          <tr>
            <th>Участок</th>
            <th style="text-align:right">Количество</th>
          </tr>
        </thead>
        <tbody id="residue-tbody">
          <?php if (empty($residueList)): ?>
          <tr><td colspan="2" style="text-align:center;color:var(--muted);padding:20px">Нет данных об остатках</td></tr>
          <?php else: foreach ($residueList as $rl): ?>
          <tr>
            <td><?= h($rl['store_name']) ?></td>
            <td style="text-align:right"><?= h(rtrim(rtrim(number_format((float)$rl['quant'], 3, '.', ''), '0'), '.')) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane" data-tab-index="3">
    <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="text" id="history-search" placeholder="Поиск…" style="width:160px;padding:4px 8px;border:1px solid var(--line);border-radius:4px;" />
      <label style="font-size:12px;color:var(--muted)">Период с</label>
      <input type="date" id="history-date-from" style="padding:4px 6px;border:1px solid var(--line);border-radius:4px;" />
      <label style="font-size:12px;color:var(--muted)">по</label>
      <input type="date" id="history-date-to" style="padding:4px 6px;border:1px solid var(--line);border-radius:4px;" />
      <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer">
        <input type="checkbox" id="history-approved" /> Утверждённые
      </label>
    </div>
    <div id="history-wrap" style="max-height:300px;border:1px solid var(--line);border-radius:4px;overflow-y:auto;">
      <table class="data-table" id="history-table" style="min-width:auto;">
        <colgroup>
          <col style="width:30px" />
          <col style="width:120px" data-resizable />
          <col style="width:60px" data-resizable />
          <col style="width:80px" data-resizable />
          <col style="width:60px" data-resizable />
          <col style="width:80px" data-resizable />
          <col style="width:80px" data-resizable />
          <col style="width:60px" data-resizable />
          <col style="width:80px" data-resizable />
          <col style="width:auto" data-resizable />
        </colgroup>
        <thead style="position:sticky;top:0;z-index:1;background:#1f2c3a;color:#ffe9a8;">
          <tr>
            <th></th>
            <th data-sort="typeop_name">Вид документа</th>
            <th data-sort="number">№</th>
            <th data-sort="date">Дата</th>
            <th data-sort="time">Время</th>
            <th data-sort="quant">Кол-во</th>
            <th data-sort="price">Цена</th>
            <th data-sort="discount">Скидка</th>
            <th data-sort="sum">Сумма</th>
            <th data-sort="note">Примечание</th>
          </tr>
        </thead>
        <tbody id="history-tbody">
          <?php if (empty($historyList)): ?>
          <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:20px">Нет записей</td></tr>
          <?php else: foreach ($historyList as $hl): ?>
          <tr<?= $hl['accept_flag'] ? '' : ' style="color:#b59800"' ?>>
            <td><?php if ($hl['accept_flag']): ?><img src="img/lock.png" alt="Утв." title="Утверждено" width="14" height="14" /><?php endif; ?></td>
            <td><?= h($hl['typeop_name']) ?></td>
            <td><?= (int)$hl['number'] ?></td>
            <td><?= h($hl['date']) ?></td>
            <td><?= h($hl['time']) ?></td>
            <td style="text-align:right"><?php
              $q = $hl['quant'];
              $qFmt = rtrim(rtrim(number_format(abs($q), 3, '.', ''), '0'), '.');
              if ($hl['typeop'] == 100) echo '+/−' . $qFmt;
              elseif ($hl['prihod_flag'] == 0 && $q > 0) echo '−' . $qFmt;
              else echo $qFmt;
            ?></td>
            <td style="text-align:right"><?= h(number_format((float)$hl['price'], 2, '.', '')) ?></td>
            <td style="text-align:right"><?= (float)$hl['discount'] != 0 ? h(number_format((float)$hl['discount'], 1, '.', '')) : '' ?></td>
            <td style="text-align:right"><?= h(number_format((float)$hl['sum'], 2, '.', '')) ?></td>
            <td><?= h($hl['note']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$actions = $isReadonly
    ? [render_btn_danger('img/delete.png', 'Удалить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_link_icon_text('img/cancel.png', 'Отменить', 'tmc.php', ['class'=>'btn-secondary'])]
    : [render_btn_primary('img/save.png', 'Сохранить', ['type'=>'submit','formnovalidate'=>true]),
       render_btn_link_icon_text('img/cancel.png', 'Отменить', 'tmc.php', ['class'=>'btn-secondary'])];
echo render_form_actions($actions);
?>
</form>

<script>
(function () {
  var tabContainer = document.querySelector('.tab-container');
  if (!tabContainer) return;
  var headers = tabContainer.querySelectorAll('.tab-header');
  var panes = tabContainer.querySelectorAll('.tab-pane');
  headers.forEach(function (hdr) {
    hdr.addEventListener('click', function () {
      var idx = parseInt(hdr.dataset.tabIndex, 10);
      headers.forEach(function (h) { h.classList.remove('active'); });
      panes.forEach(function (p) { p.classList.remove('active'); });
      hdr.classList.add('active');
      var pane = tabContainer.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
      if (pane) pane.classList.add('active');
    });
  });

  var recalcBtn = document.getElementById('recalc-residue-btn');
  if (recalcBtn) {
    recalcBtn.addEventListener('click', function () {
      var idEl = document.querySelector('input[name="id"]');
      if (!idEl) return;
      var pid = parseInt(idEl.value, 10);
      if (!pid) return;
      var btn = recalcBtn;
      btn.style.opacity = '0.5';
      var fd = new FormData();
      fd.append('id', pid);
      fd.append('field', '_residue_recalc');
      fetch('tmc_field_save.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.style.opacity = '1';
          if (!data.ok) { alert(data.error || '\u041e\u0448\u0438\u0431\u043a\u0430'); return; }
          var tbody = document.getElementById('residue-tbody');
          if (!tbody) return;
          if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--muted);padding:20px">\u041d\u0435\u0442 \u0434\u0430\u043d\u043d\u044b\u0445 \u043e\u0431 \u043e\u0441\u0442\u0430\u0442\u043a\u0430\u0445</td></tr>';
          } else {
            tbody.innerHTML = data.rows.map(function (r) {
              var q = parseFloat(r.quant) || 0;
              var qStr = q % 1 === 0 ? String(q) : q.toFixed(3).replace(/\.?0+$/, '');
              return '<tr><td>' + r.store_name + '</td><td style="text-align:right">' + qStr + '</td></tr>';
            }).join('');
          }
        })
        .catch(function () { btn.style.opacity = '1'; });
    });
  }

  var historySearch = document.getElementById('history-search');
  var historyDateFrom = document.getElementById('history-date-from');
  var historyDateTo = document.getElementById('history-date-to');
  var historyApproved = document.getElementById('history-approved');

  function filterHistory() {
    var st = (historySearch ? historySearch.value : '').toLowerCase();
    var dFrom = historyDateFrom ? historyDateFrom.value : '';
    var dTo = historyDateTo ? historyDateTo.value : '';
    var onlyApproved = historyApproved ? historyApproved.checked : false;
    var rows = document.querySelectorAll('#history-tbody tr');
    rows.forEach(function (tr) {
      var text = tr.textContent.toLowerCase();
      var matchSearch = (st === '' || text.indexOf(st) >= 0);
      var matchApproved = !onlyApproved || tr.querySelector('img[alt]');
      var matchDate = true;
      if (dFrom || dTo) {
        var dateCell = tr.children[3];
        var cellDate = dateCell ? dateCell.textContent.trim() : '';
        if (dFrom && cellDate < dFrom) matchDate = false;
        if (dTo && cellDate > dTo) matchDate = false;
      }
      tr.style.display = (matchSearch && matchApproved && matchDate) ? '' : 'none';
    });
  }

  if (historySearch) historySearch.addEventListener('input', filterHistory);
  if (historyDateFrom) { historyDateFrom.addEventListener('change', filterHistory); historyDateFrom.addEventListener('input', filterHistory); }
  if (historyDateTo) { historyDateTo.addEventListener('change', filterHistory); historyDateTo.addEventListener('input', filterHistory); }
  if (historyApproved) historyApproved.addEventListener('change', filterHistory);

  var histTable = document.getElementById('history-table');
  if (histTable) {
    var histSortCol = -1, histSortDir = 'asc', histResizing = false;
    histTable.querySelector('thead').addEventListener('click', function (e) {
      if (histResizing) return;
      var th = e.target.closest('th[data-sort]');
      if (!th) return;
      var allThs = Array.from(histTable.querySelectorAll('thead th[data-sort]'));
      var ci = allThs.indexOf(th);
      if (ci < 0) return;
      if (histSortCol === ci) { histSortDir = histSortDir === 'asc' ? 'desc' : 'asc'; }
      else { histSortCol = ci; histSortDir = 'asc'; }
      allThs.forEach(function (h, i) {
        var handle = h.querySelector('div');
        var txt = h.getAttribute('data-sort-label') || h.textContent.replace(/\s*[\u25b2\u25bc]\s*$/, '').trim();
        h.setAttribute('data-sort-label', txt);
        h.textContent = txt + (i === ci ? (histSortDir === 'asc' ? ' \u25b2' : ' \u25bc') : '');
        if (handle) h.appendChild(handle);
      });
      var tbody = histTable.querySelector('tbody');
      var rows = Array.from(tbody.querySelectorAll('tr'));
      var thsAll = Array.from(histTable.querySelectorAll('thead th'));
      rows.sort(function (a, b) {
        var ai = thsAll.indexOf(allThs[ci]);
        var bi = thsAll.indexOf(allThs[ci]);
        var va = (a.children[ai] ? a.children[ai].textContent : '').trim();
        var vb = (b.children[bi] ? b.children[bi].textContent : '').trim();
        var na = parseFloat(va.replace(',', '.'));
        var nb = parseFloat(vb.replace(',', '.'));
        if (!isNaN(na) && !isNaN(nb)) return histSortDir === 'asc' ? na - nb : nb - na;
        return histSortDir === 'asc' ? va.localeCompare(vb, 'ru') : vb.localeCompare(va, 'ru');
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });

    histTable.querySelectorAll('thead th').forEach(function (th, idx) {
      th.style.position = 'relative';
      th.style.cursor = 'default';
      var handle = document.createElement('div');
      handle.style.cssText = 'position:absolute;right:0;top:0;bottom:0;width:5px;cursor:col-resize;z-index:1;';
      th.appendChild(handle);
      var startX, startW;
      handle.addEventListener('mousedown', function (e) {
        e.preventDefault();
        e.stopPropagation();
        histResizing = true;
        startX = e.clientX;
        startW = th.offsetWidth;
        var colEl = histTable.querySelectorAll('col')[idx];
        function onMove(ev) {
          var nw = Math.max(30, startW + ev.clientX - startX);
          th.style.width = nw + 'px';
          if (colEl) colEl.style.width = nw + 'px';
        }
        function onUp() {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
          document.body.style.cursor = '';
          setTimeout(function () { histResizing = false; }, 0);
        }
        document.body.style.cursor = 'col-resize';
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    });
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
})();
</script>

<style>
.form-modal { max-width: 790px !important; }
.form { max-width: 790px; }
.tab-container { margin-bottom: 16px; }
.tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
.tab-header {
  padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold;
  color: var(--muted); border: 1px solid transparent; border-bottom: none;
  border-radius: 4px 4px 0 0; user-select: none;
}
.tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
.tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.form-table { width: 100%; border-collapse: collapse; }
.form-table td { vertical-align: top; padding-bottom: 6px; padding-right: 8px; }
.form-table td:last-child { padding-right: 0; }
.form-table td.form-label { font-size: 12px; color: var(--muted); padding-bottom: 2px; }
.form-table tr.col-3 td { width: 33%; }
input.num { width: 120px; text-align: right; }
input.full { width: 100%; box-sizing: border-box; }
textarea.full { width: 100%; box-sizing: border-box; }
</style>
<script>
document.querySelectorAll('[data-lookup]:not([data-lookup-bound])').forEach(function(root){
  if (root.dataset.lookupInited) return;
  var raw = root.getAttribute('data-countries');
  if (!raw) return;
  var data;
  try { data = JSON.parse(raw); } catch(e) { return; }
  if (!Array.isArray(data)) return;
  root.dataset.lookupInited = '1';
  try { window.bindLookup({root:root, data:data, readonly: root.hasAttribute('data-readonly')}); } catch(e) {}
});
</script>

<?php if (!$ajax): ?>
<script src="assets/lookup.js"></script>
<script src="assets/inline-edit.js"></script>
<script>
(function(){
  document.querySelectorAll('[data-lookup]').forEach(function(root){
    if (root.dataset.lookupInited) return;
    var raw = root.getAttribute('data-countries');
    if (!raw) return;
    var data;
    try { data = JSON.parse(raw); } catch(e) { return; }
    if (!Array.isArray(data)) return;
    root.dataset.lookupInited = '1';
    try { window.bindLookup({root:root, data:data, readonly: root.hasAttribute('data-readonly')}); } catch(e) { console.error('bindLookup error', e); }
  });
  var tc = document.querySelector('.tab-container');
  if (tc) {
    tc.querySelectorAll('.tab-header').forEach(function(h){
      h.addEventListener('click', function(){
        var idx = parseInt(h.dataset.tabIndex, 10);
        tc.querySelectorAll('.tab-header').forEach(function(t){t.classList.remove('active');});
        tc.querySelectorAll('.tab-pane').forEach(function(p){p.classList.remove('active');});
        h.classList.add('active');
        var pane = tc.querySelector('.tab-pane[data-tab-index="'+idx+'"]');
        if (pane) pane.classList.add('active');
      });
    });
  }
})();
</script>
</body>
</html>
<?php endif;
$html = ob_get_clean();

if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    $resp = ['html' => $html];
    if (!empty($focusField)) $resp['focusField'] = $focusField;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
} else {
    echo $html;
}
