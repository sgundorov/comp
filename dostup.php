<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/table-template.php';

$roleId = max(0, (int)($_GET['role_id'] ?? 0));
$typeFilter = (string)($_GET['type'] ?? 'Документ');
if (!in_array($typeFilter, ['Документ', 'Поле', 'Отчет'], true)) $typeFilter = 'Документ';

$sortCol = (string)($_GET['sort'] ?? 'id');
$sortDir = (string)($_GET['dir'] ?? 'asc');
$allowedSortCols = ['id', 'object', 'name'];
if (!in_array($sortCol, $allowedSortCols, true)) $sortCol = 'id';
if (!in_array($sortDir, ['asc', 'desc'], true)) $sortDir = 'asc';
$sortExpr = match ($sortCol) {
    'id'     => 'o.object_id',
    'object' => 'o.object',
    'name'   => 'o.name',
};

$roles = [];
$rRoles = $conn->query("SELECT role_id, role FROM role ORDER BY role");
if ($rRoles) while ($rr = $rRoles->fetch_assoc()) $roles[] = $rr;
if ($roleId <= 0 && count($roles) > 0) $roleId = (int)$roles[0]['role_id'];

$stmt = $conn->prepare("
    SELECT o.object_id, o.object, o.name, o.type,
           COALESCE(d.dostup_flag, 0) AS dostup_flag,
           COALESCE(d.insert_flag, 0) AS insert_flag,
           COALESCE(d.change_flag, 0) AS change_flag,
           COALESCE(d.delete_flag, 0) AS delete_flag,
           COALESCE(d.save_flag, 0) AS save_flag,
           COALESCE(d.print_flag, 0) AS print_flag
    FROM object o
    LEFT JOIN dostup d ON d.object_id = o.object_id AND d.catsotr_id = ?
    WHERE o.type = ?
    GROUP BY o.object_id
    ORDER BY $sortExpr $sortDir
");
$stmt->bind_param('is', $roleId, $typeFilter);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$PAGE_TITLE = 'Права доступа';

// Admin role locks
$adminRole = $conn->query("SELECT role_id FROM role WHERE role = 'Администратор' LIMIT 1");
$adminRoleId = $adminRole && ($ar = $adminRole->fetch_assoc()) ? (int)$ar['role_id'] : 0;
$restrictedObjIds = [];
if ($adminRoleId > 0) {
    $objCodes = ['Sotr', 'Role', 'Object', 'Setup'];
    $quotedCodes = implode(',', array_map(function($n) use($conn) { return "'" . $conn->real_escape_string($n) . "'"; }, $objCodes));
    $roRes = $conn->query("SELECT object_id FROM object WHERE object IN ($quotedCodes)");
    if ($roRes) while ($ror = $roRes->fetch_assoc()) $restrictedObjIds[] = (int)$ror['object_id'];
}

$roleSelectHtml = '<select id="roleFilterSelect" style="height:30px;font-size:13px;min-width:180px;">';
foreach ($roles as $r) {
    $sel = (int)$r['role_id'] === $roleId ? ' selected' : '';
    $roleSelectHtml .= '<option value="' . (int)$r['role_id'] . '"' . $sel . '>' . h($r['role']) . '</option>';
}
$roleSelectHtml .= '</select>';

$typeOptions = ['Документ', 'Поле', 'Отчет'];
$typeRadioHtml = '';
foreach ($typeOptions as $i => $t) {
    $id = 'type-' . $i;
    $checked = $t === $typeFilter ? ' checked' : '';
    $typeRadioHtml .= '<input type="radio" name="typeFilter" id="' . $id . '" value="' . h($t) . '"' . $checked . ' /><label for="' . $id . '">' . h($t) . '</label>';
}

$flagCols = ['dostup_flag', 'insert_flag', 'change_flag', 'delete_flag', 'save_flag', 'print_flag'];
$dostupColWidths = load_columns_widths($conn, 'dostup');
$dostupDefWidths = ['id' => 46, 'object' => 67, 'name' => 168, 'dostup_flag' => 64, 'insert_flag' => 80, 'change_flag' => 80, 'delete_flag' => 80, 'save_flag' => 80, 'print_flag' => 64];
$qs = 'role_id=' . $roleId . '&type=' . urlencode($typeFilter) . '&sort=' . $sortCol . '&dir=' . $sortDir;

function colW($cn) {
    global $dostupColWidths, $dostupDefWidths;
    return ($dostupColWidths[$cn] ?? $dostupDefWidths[$cn] ?? 80) . 'px';
}

function renderFlag($v) {
    return $v === '1' || $v === 1
        ? '<svg class="flag-x" viewBox="0 0 24 24" width="20" height="20"><line x1="5" y1="5" x2="19" y2="19" stroke="#ff6b6b" stroke-width="3.2" stroke-linecap="round"/><line x1="19" y1="5" x2="5" y2="19" stroke="#ff6b6b" stroke-width="3.2" stroke-linecap="round"/></svg>'
        : '';
}

$qs = 'role_id=' . $roleId . '&type=' . urlencode($typeFilter) . '&sort=' . $sortCol . '&dir=' . $sortDir;

render_head_start($PAGE_TITLE); ?>
  <style>
    .toolbar-dostup { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .toolbar-dostup label { font-size:12px; color:var(--muted); white-space:nowrap; }
    .toolbar-dostup input[type="radio"] { margin:0 4px 0 0; vertical-align:middle; }
    .toolbar-dostup label[for^="type-"] { margin-right:8px; cursor:pointer; font-size:13px; }
    .toolbar-dostup select { height:30px; font-size:13px; min-width:180px; }
    .flag-x { display:inline-block; vertical-align:middle; }
    td.flag-cell { cursor:pointer; text-align:center; }
    td.flag-cell:hover { background:rgba(231,76,60,0.15); }
    td.flag-cell.admin-locked { opacity:0.4; cursor:not-allowed; }
    td.flag-cell.admin-locked:hover { background:transparent; }
    .data-table thead th.col-flag { text-align:center; }
    .data-table thead th[data-sort-col] { cursor:pointer; white-space:nowrap; }
    .data-table thead th[data-sort-col]:hover { background:#3d5468; }
    .sort-indicator { display:inline-block; margin-left:4px; font-size:10px; color:var(--muted); }
  </style>
<?php
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'dostup.php'; include 'menu.php'; ?>
    <h1 class="page-title"><img src="img/dostup.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>
    <div class="toolbar" style="margin-bottom:8px;">
      <div class="toolbar-dostup">
        <label for="roleFilterSelect">Роль:</label>
        <?= $roleSelectHtml ?>
        <span style="width:1px;height:24px;background:var(--border);margin:0 4px;"></span>
        <span style="display:flex;align-items:center;gap:2px;">
          <?= $typeRadioHtml ?>
        </span>
        <button class="icon-btn" title="Обновить" onclick="location.href='dostup.php?<?= h($qs) ?>'" style="margin-left:4px;"><img src="img/refresh.png" alt="" /></button>
      </div>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
      <table class="data-table" id="dostup-table"
             data-role-id="<?= (int)$roleId ?>"
             data-col-resize-url="dostup_column_width_save.php"
             data-col-resize-tbl="dostup">
        <colgroup>
          <col class="col-id" style="width:<?= colW('id') ?>;" />
          <col class="col-object" style="width:<?= colW('object') ?>;" />
          <col class="col-name" style="width:<?= colW('name') ?>;" />
          <col class="col-dostup_flag" style="width:<?= colW('dostup_flag') ?>;" />
          <col class="col-insert_flag" style="width:<?= colW('insert_flag') ?>;" />
          <col class="col-change_flag" style="width:<?= colW('change_flag') ?>;" />
          <col class="col-delete_flag" style="width:<?= colW('delete_flag') ?>;" />
          <col class="col-save_flag" style="width:<?= colW('save_flag') ?>;" />
          <col class="col-print_flag" style="width:<?= colW('print_flag') ?>;" />
        </colgroup>
        <thead>
          <tr>
            <th class="col-id" data-sort-col="id">ID<?= $sortCol === 'id' ? '<span class="sort-indicator">1 ' . ($sortDir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></th>
            <th class="col-object" data-sort-col="object">Обозначение<?= $sortCol === 'object' ? '<span class="sort-indicator">1 ' . ($sortDir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></th>
            <th class="col-name" data-sort-col="name">Объект доступа<?= $sortCol === 'name' ? '<span class="sort-indicator">1 ' . ($sortDir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></th>
            <th class="col-flag col-dostup_flag" title="Запрет доступа">Доступ</th>
            <th class="col-flag col-insert_flag" title="Запрет добавления">Добавить</th>
            <th class="col-flag col-change_flag" title="Запрет изменения">Изменить</th>
            <th class="col-flag col-delete_flag" title="Запрет удаления">Удалить</th>
            <th class="col-flag col-save_flag" title="Запрет сохранения">Записать</th>
            <th class="col-flag col-print_flag" title="Запрет печати и экспорта">Печать</th>
          </tr>
        </thead>
        <tbody>
<?php if (count($rows) === 0): ?>
          <tr><td colspan="9" style="text-align:center;padding:12px;color:var(--muted)">Нет данных</td></tr>
<?php else: ?>
<?php foreach ($rows as $r):
    $oid = (int)$r['object_id'];
    $isLocked = $adminRoleId > 0 && $roleId === $adminRoleId && in_array($oid, $restrictedObjIds);
?>
          <tr>
            <td class="col-id"><?= $oid ?></td>
            <td class="col-object"><?= h($r['object']) ?></td>
            <td class="col-name"><?= h($r['name']) ?></td>
<?php foreach ($flagCols as $f): $locked = $isLocked && $f !== 'print_flag' ? ' admin-locked' : ''; ?>
            <td class="flag-cell col-<?= $f ?><?= $locked ?>" data-object-id="<?= $oid ?>" data-field="<?= $f ?>"><?= renderFlag($r[$f]) ?></td>
<?php endforeach; ?>
          </tr>
<?php endforeach; ?>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php render_form_modal(); ?>
<?php render_script_includes(['scripts' => ['assets/column-resize.js']]); ?>
<?php render_form_modal_script(['form_prefix' => 'setup_form']); ?>
  <script>
    (function () {
      var table = document.getElementById('dostup-table');
      if (!table) return;

      var roleId = table.dataset.roleId;
      var adminRoleId = <?= json_encode($adminRoleId) ?>;
      var restrictedObjIds = <?= json_encode($restrictedObjIds) ?>;

      var roleSelect = document.getElementById('roleFilterSelect');
      if (roleSelect) {
        roleSelect.addEventListener('change', function () {
          var type = document.querySelector('input[name="typeFilter"]:checked');
          var tv = type ? type.value : 'Документ';
          location.href = 'dostup.php?role_id=' + this.value + '&type=' + encodeURIComponent(tv) + '&sort=id&dir=asc';
        });
      }

      document.querySelectorAll('input[name="typeFilter"]').forEach(function (rb) {
        rb.addEventListener('change', function () {
          location.href = 'dostup.php?role_id=' + roleId + '&type=' + encodeURIComponent(this.value) + '&sort=id&dir=asc';
        });
      });

      document.querySelectorAll('thead th[data-sort-col]').forEach(function (th) {
        th.addEventListener('click', function () {
          var col = th.dataset.sortCol;
          if (!col) return;
          var curSort = '<?= h($sortCol) ?>';
          var curDir = '<?= h($sortDir) ?>';
          var dir = (col === curSort && curDir === 'asc') ? 'desc' : 'asc';
          location.href = 'dostup.php?role_id=' + roleId + '&type=<?= urlencode($typeFilter) ?>&sort=' + col + '&dir=' + dir;
        });
      });

      var saving = false;
      table.addEventListener('click', function (e) {
        var td = e.target.closest('td.flag-cell');
        if (!td) return;
        if (saving) return;
        var objectId = parseInt(td.dataset.objectId, 10);
        var field = td.dataset.field;
        if (!objectId || !field || !roleId || roleId === '0') return;

        if (parseInt(roleId, 10) === adminRoleId && restrictedObjIds.indexOf(objectId) !== -1 && field !== 'print_flag') {
          return;
        }

        saving = true;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'dostup_save.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
          saving = false;
          if (xhr.status !== 200) { console.error('dostup_save status', xhr.status); return; }
          try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.ok) {
              td.innerHTML = resp.html;
            } else {
              console.error('dostup_save error', resp.error);
            }
          } catch(ex) { console.error('dostup_save parse', ex); }
        };
        xhr.onerror = function () { saving = false; console.error('dostup_save network error'); };
        xhr.send('object_id=' + objectId + '&role_id=' + roleId + '&field=' + encodeURIComponent(field));
      });

      if (typeof ColumnResize !== 'undefined') {
        ColumnResize.init({
          selector: '#dostup-table',
          saveUrl: 'dostup_column_width_save.php',
          tbl: 'dostup'
        });
      }
    })();
  </script>
<?php render_page_footer(); ?>
