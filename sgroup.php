<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sgroup_columns.php';
require_once __DIR__ . '/config/sgroup_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$TBL = 'sgroup';
$sgroupKind = (string)($_GET['kind'] ?? '');
$sgroupIsService = ($sgroupKind === 'service');
$sgroupServiceFlag = $sgroupIsService ? '1' : '0';
$PAGE_URL = $sgroupIsService ? 'sgroup.php?kind=service' : 'sgroup.php';
$FORM_PREFIX = 'sgroup_form';
$PAGE_TITLE = $sgroupIsService ? 'Экспорт в Excel' : 'Подгруппы товаров';
$PAGE_TITLE_FORM = $sgroupIsService ? 'Экспорт в Excel' : 'Подгруппа товаров';

ensure_marks_table($conn);

$allGroups = [];
$stmt = @$conn->prepare("SELECT group_id, name FROM `group` WHERE service_flag = ? ORDER BY name");
if ($stmt) {
    $stmt->bind_param('s', $sgroupServiceFlag);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($r = $res->fetch_assoc()) {
        $allGroups[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']];
    }
    $stmt->close();
}

$tp = new TablePage($conn, $sgroupPageConfig);

$page         = $tp->page;
$search       = $tp->search;
$searchActive = $tp->searchActive;
$searchCols   = $tp->searchCols;
$searchCond   = $tp->searchCond;
$sortLevels   = $tp->sortLevels;
$sortIsDefault= $tp->sortIsDefault;
$sortQs       = $tp->sortQs;
$orderBy      = $tp->orderBy;
$columnsConfig= $tp->columnsConfig;
$visibleColumns= $tp->visibleColumns;
$offset       = $tp->offset;
$showOnly     = $tp->showOnly;
$marks        = $tp->marks;
$marksCount   = $tp->marksCount;
$COLUMN_DEFAULTS = $tp->columns;
$COL_META        = $tp->colMeta;
$columnWidths    = load_columns_widths($conn, $TBL);
$urlCols = $searchCols;

$tp->appendWhere("sg.service_flag = ?", [$sgroupServiceFlag], 's');

$groupFilter = (string)($_GET['group_id'] ?? '');
$groupIds = [];
if ($groupFilter !== '') {
    $groupIds = array_values(array_filter(array_map('intval', explode(',', $groupFilter)), fn($v) => $v > 0));
}
if (count($groupIds) > 0) {
    $place = implode(',', array_fill(0, count($groupIds), '?'));
    $tp->appendWhere("sg.group_id IN ($place)", $groupIds, str_repeat('i', count($groupIds)));
}

if (!function_exists('build_filter')) {
function build_filter(string $search, array $cols = [], string $cond = 'contains'): array {
    $colToExpr = [
        'id'    => 'sg.sgroup_id',
        'name'  => 'sg.name',
        'group' => 'g.name',
        'note'  => 'sg.note',
    ];
    $condToOp = [
        'contains'     => function ($e) { return "$e LIKE ?"; },
        'not_contains' => function ($e) { return "$e NOT LIKE ?"; },
        'starts_with'  => function ($e) { return "$e LIKE ?"; },
        'ends_with'    => function ($e) { return "$e LIKE ?"; },
        'equals'       => function ($e) { return "$e = ?"; },
        'not_equals'   => function ($e) { return "$e <> ?"; },
    ];
    $where = '';
    $params = [];
    $types  = '';
    if ($search !== '' && count($cols) > 0 && isset($condToOp[$cond])) {
        $op = $condToOp[$cond];
        $parts = [];
        foreach ($cols as $col) {
            if (!isset($colToExpr[$col])) continue;
            $parts[] = $op($colToExpr[$col]);
            switch ($cond) {
                case 'contains':     $params[] = '%' . $search . '%'; break;
                case 'not_contains': $params[] = '%' . $search . '%'; break;
                case 'starts_with':  $params[] = $search . '%'; break;
                case 'ends_with':    $params[] = '%' . $search; break;
                case 'equals':       $params[] = $search; break;
                case 'not_equals':   $params[] = $search; break;
            }
            $types .= 's';
        }
        if (count($parts) > 0) {
            $where = 'WHERE (' . implode(' OR ', $parts) . ')';
        }
    }
    return [$where, $params, $types];
}

function fetch_filtered_ids(mysqli $conn, string $search, array $cols = [], string $cond = 'contains'): array {
    global $sgroupServiceFlag;
    [$where, $params, $types] = build_filter($search, $cols, $cond);
    $sql = "SELECT sg.sgroup_id FROM sgroup sg LEFT JOIN `group` g ON g.group_id = sg.group_id WHERE sg.service_flag = $sgroupServiceFlag $where";
    $ids = [];
    if ($types === '') {
        $res = @$conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['sgroup_id'];
    } else {
        $stmt = @mysqli_prepare($conn, $sql);
        if ($stmt) {
            stmt_bind($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['sgroup_id'];
            $stmt->close();
        }
    }
    return $ids;
}
}

handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn, $sgroupServiceFlag) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if ($col === 'group') {
            $stmt = @$conn->prepare("SELECT group_id, name FROM `group` WHERE service_flag = ? ORDER BY name");
            $out = [];
            if ($stmt) {
                $stmt->bind_param('s', $sgroupServiceFlag);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']];
                $stmt->close();
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    return null;
});

$marks = load_marks_set($conn, $TBL);
$marksCount = count_marks($conn, $TBL);

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$groupNames = [];
if (count($groupIds) > 0) {
    $place = implode(',', array_fill(0, count($groupIds), '?'));
    $stmt = @$conn->prepare("SELECT group_id, name FROM `group` WHERE group_id IN ($place) ORDER BY name");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($groupIds)), $groupIds);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $groupNames[(int)$r['group_id']] = (string)$r['name'];
        $stmt->close();
    }
}

$tp->buildFilters();
$filters = $tp->filters;

if (count($groupIds) > 0) {
    $names = [];
    foreach ($groupIds as $gid) $names[] = $groupNames[$gid] ?? ('#' . $gid);
    $filters[] = ['kind' => 'group', 'text' => 'Группа = ' . implode(', ', $names), 'clear' => ['group_id']];
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['sgroup_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

    .data-table tbody tr:hover td.col-name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-name { background: #3a5a8a; }

    .data-table thead th[data-sort-col]:hover { background: #3d5468; }

    .col-filter-panel {
      display: none; position: fixed; z-index: 1000; min-width: 240px; max-width: 320px;
      padding: 8px; background: #2f3e4e; border: 2px solid #6b7785;
      border-radius: 2px; box-shadow: 0 8px 18px rgba(0,0,0,.35); text-align: left;
    }
    .col-filter-panel.open { display: block; }
    .col-filter-search {
      width: 100%; height: 28px; padding: 0 8px; margin: 0 0 6px; box-sizing: border-box;
      border: 1px solid var(--line); background: #fff; color: #2b2b2b;
      border-radius: 2px; font-size: 13px; outline: none;
    }
    .col-filter-list { margin-bottom: 8px; }
    .col-filter-row {
      display: flex; align-items: center; gap: 6px; padding: 4px; cursor: pointer;
      color: #fff; font-size: 13px; user-select: none;
    }
    .col-filter-row:hover { background: var(--btn-hover); }
    .col-filter-row input[type="checkbox"] { width: 14px; height: 14px; flex: 0 0 auto; }
    .col-filter-row .col-filter-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .col-filter-empty { padding: 6px 4px; color: var(--muted); font-size: 12px; font-style: italic; }
    .col-filter-more {
      padding: 4px; color: var(--muted); font-size: 12px; font-style: italic;
      border-top: 1px solid var(--line);
    }
    .col-filter-actions {
      display: flex; gap: 10px; justify-content: flex-end; padding: 10px 8px;
      border-top: 1px solid var(--line);
    }
    .col-filter-actions button {
      display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 8px;
      background: var(--btn); color: #fff; border: 1px solid var(--line);
      border-radius: 2px; cursor: pointer; font-size: 14px;
    }
    .col-filter-actions button img { width: 28px; height: 28px; flex: 0 0 auto; display: block; }
    .col-filter-actions button:hover { background: var(--btn-hover); }
    .col-filter-actions .col-filter-apply { background: var(--accent); border-color: var(--accent); }
    .col-filter-actions .col-filter-apply:hover { background: #cf6d1a; border-color: #cf6d1a; }

  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">

    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/sgroup.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $groupFilter, $sgroupIsService) {
          $qs = ['page' => $p];
          if ($sgroupIsService) $qs['kind'] = 'service';
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($groupFilter !== '') $qs['group_id'] = $groupFilter;
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'sgroup.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = http_build_query(array_filter([
          'q'         => $searchActive && $search !== '' ? $search : null,
          'cols'      => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond'      => $searchActive ? $searchCond : null,
          'sf'        => $searchActive ? '1' : null,
          'kind'      => $sgroupIsService ? 'service' : null,
          'group_id'  => $groupFilter !== '' ? $groupFilter : null,
          'sort'      => $sortQs !== '' ? $sortQs : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'sgroup_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('sgroup', $exportQs, (int)$page, $marksCount > 0);
    ?>
    <?php
    render_toolbar_wrapper_open([
        'total'        => (int)$total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => (int)$marksCount,
        'search'       => $search,
        'page'         => (int)$page,
        'pages'        => (int)$pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
        'kind'         => $sgroupIsService ? 'service' : '',
    ]);
    render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'sgroup.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '250px', 'group' => '200px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($groupIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'group') {
                    $param = $cm['param'] ?? ($cn . '_id');
                    return ' data-col="' . h($cn) . '" data-param="' . h($param) . '" data-values="' . h(implode(',', $groupIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && $cn === 'group') {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'sgroup_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':    $raw = (string)(int)$r['sgroup_id']; return [$raw, $raw];
                case 'name':  $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'group': $rawId = (string)(int)($r['group_id'] ?? 0); $name = (string)($r['group_name'] ?? $rawId); return [$rawId, $doHilight ? hilight($name, $search, $searchCond) : $name];
                case 'note':  $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'tdExtraAttrs' => function($cn, $vc, $r, $i) {
                return ' data-col-idx="' . (int)$i . '"';
            },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

  </div>

<?php
render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/column-filter.js']]);
?>
  <script>
    window.__groups = <?= json_encode($allGroups, JSON_UNESCAPED_UNICODE) ?>;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 250, 'group' => 200, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    (function () {
      const checkAll  = document.getElementById('checkAll');
      const rowChecks = document.querySelectorAll('.row-check');
      const selWrap   = document.getElementById('selectedActions');
      const selCount  = document.getElementById('selectedCount');
      const toolbar   = document.querySelector('.toolbar');
      const search    = toolbar.getAttribute('data-search') || '';
      const markedSet = new Set(Array.from(rowChecks).filter(cb => cb.checked).map(cb => parseInt(cb.value, 10)));
      let globalCount = parseInt(toolbar.getAttribute('data-marks-count') || '0', 10);

      document.querySelectorAll('.submenu a').forEach(function (a) {
        a.addEventListener('click', function () {
          var item = a.closest('.menu-item');
          if (item) item.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
        });
      });

      function refreshCounter() {
        selCount.textContent = 'Выбрано: ' + globalCount;
        selWrap.classList.toggle('visible', globalCount > 0);
        const rowTotal = rowChecks.length;
        let rowOn = 0;
        rowChecks.forEach(cb => { if (cb.checked) rowOn++; });
        if (rowTotal === 0) {
          checkAll.checked = false;
          checkAll.indeterminate = false;
        } else {
          checkAll.checked = rowOn === rowTotal;
          checkAll.indeterminate = rowOn > 0 && rowOn < rowTotal;
        }
      }

      checkAll.addEventListener('change', function () {
        location.href = 'sgroup.php?action=toggleSelectAll' + (search ? '&q=' + encodeURIComponent(search) : '') + (<?= json_encode($sgroupIsService ? 'service' : '') ?> ? '&kind=' + <?= json_encode($sgroupIsService ? 'service' : '') ?> : '');
      });

      rowChecks.forEach(cb => cb.addEventListener('change', function () {
        const id  = parseInt(cb.value, 10);
        const to  = cb.checked;
        cb.disabled = true;
        fetch('sgroup.php?action=toggleSelect', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'id=' + encodeURIComponent(id) + '&to=' + (to ? '1' : '0')
        })
        .then(r => r.json())
        .then(function (j) {
          cb.disabled = false;
          if (j.ok) {
            if (to) markedSet.add(id); else markedSet.delete(id);
            globalCount = (typeof j.count === 'number') ? j.count : globalCount;
            refreshCounter();
          }
        })
        .catch(function () { cb.disabled = false; });
      }));

      SelectionToolbar.init({
        pageUrl: 'sgroup.php',
        search: search,
        getExportUrl: function () {
          if (markedSet.size === 0) return null;
          return 'sgroup_export.php?format=csv&all=1&kind=' + <?= json_encode($sgroupIsService ? 'service' : '') ?>;
        },
        getPrintUrl: function () {
          if (markedSet.size === 0) return null;
          return 'sgroup_print.php?all=1&kind=' + <?= json_encode($sgroupIsService ? 'service' : '') ?>;
        }
      });

      ColumnFilter.init({ thSelector: '.col-group', pageUrl: 'sgroup.php' });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',    label: 'ID' },
          { key: 'name',  label: 'Название подгруппы' },
          { key: 'group', label: 'Группа' },
          { key: 'note',  label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'sgroup.php',
        preserveParams: ['kind', 'group_id', 'sort'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',    label: 'ID' },
          { key: 'name',  label: 'Название подгруппы' },
          { key: 'group', label: 'Группа' },
          { key: 'note',  label: 'Примечание' },
        ],
        pageUrl: 'sgroup.php',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });
    })();

    (function () {
      var addBtn = document.querySelector('button[data-form-open="sgroup_form.php?mode=new"]');
      if (addBtn) {
        var kind = <?= json_encode($sgroupIsService ? 'service' : '') ?>;
        if (kind) addBtn.dataset.formOpen = 'sgroup_form.php?mode=new&kind=' + kind;
      }
    })();

    ExportModal.init();
  </script>
<?php render_form_modal_script(['form_prefix' => $FORM_PREFIX, 'base_url' => $PAGE_URL, 'lookup_tables' => ['group']]); ?>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'sgroup_columns_save.php',
      tbl: 'sgroup',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    (function () {
      const toolbar = document.querySelector('.toolbar');
      if (!toolbar) return;
      const tbody = document.querySelector('table tbody');
      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);
      const initialFocus = parseInt(toolbar.dataset.focus || '0', 10);

      const openBtn   = document.getElementById('rowOpenBtn');
      const copyBtn   = document.getElementById('rowCopyBtn');
      const deleteBtn = document.getElementById('rowDeleteBtn');

      const tableWrapEl = document.querySelector('.table-wrap');

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          if (openBtn)   openBtn.disabled   = !enabled;
          if (copyBtn)   copyBtn.disabled   = !enabled;
          if (deleteBtn) deleteBtn.disabled = !enabled;
        },
        currentPage: currentPage,
        totalPages: currentPages,
        navigate: navigate
      });

      (function applyInitialFocus() {
        const rows = rowSel.getRows();
        if (rows.length === 0) return;
        const raw = (toolbar.dataset.focus || '').toString();
        if (raw === 'first') { rowSel.selectByIndex(0); return; }
        if (raw === 'last')  { rowSel.selectByIndex(rows.length - 1); return; }
        const id = parseInt(raw, 10);
        if (id > 0 && rowSel.selectById(id, false)) return;
        rowSel.selectByIndex(0);
      })();

      if (tbody) {
        tbody.addEventListener('dblclick', function (e) {
          if (e.target.closest('input[type="checkbox"]')) return;
          const tr = e.target.closest('tr[data-row-id]');
          if (!tr) return;
          const id = parseInt(tr.dataset.rowId, 10) || 0;
          if (!id) return;
          rowSel.selectById(id, true);
          if (typeof window.__openFormModal === 'function') {
            window.__openFormModal('sgroup_form.php?mode=edit&id=' + id + '&kind=' + <?= json_encode($sgroupIsService ? 'service' : '') ?>);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('sgroup_form.php?mode=' + mode + '&id=' + id + '&kind=' + <?= json_encode($sgroupIsService ? 'service' : '') ?>);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'sgroup.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'sgroup_form',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: tableWrapEl
      });
    })();

    (function () {
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;

      InlineEdit.init({
        tbody: tbody,
        saveUrl: 'sgroup_field_save.php',
        fields: <?php
          $inlineFields = [];
          $valCases = '';
          foreach ($visibleColumns as $vc) {
            $cn = $vc['name'];
            if (!empty($vc['readonly']) || $cn === 'id') continue;
            $isLookup = !empty($vc['param']);
            $inlineFields[$cn] = [
              'dbField' => $cn,
              'type'    => $isLookup ? 'lookup' : 'text',
              'label'   => $vc['label'],
            ];
            $label = json_encode($vc['label'], JSON_UNESCAPED_UNICODE);
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            if ($isLookup) {
              $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
          }
        ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
        getLookupData: function () { return window.__groups || []; },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: window.__openFormModal
      });
    })();

    ColumnResize.init({ saveUrl: 'sgroup_column_width_save.php', tbl: 'sgroup' });
  </script>
<?php render_page_footer();
