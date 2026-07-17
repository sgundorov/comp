<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sotr_columns.php';
require_once __DIR__ . '/config/sotr_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Sotr');

$TBL = 'sotr';

ensure_marks_table($conn);

$allRoles = [];
$res = @$conn->query("SELECT role_id, role FROM role ORDER BY role");
if ($res) while ($r = $res->fetch_assoc()) {
    $allRoles[] = ['id' => (int)$r['role_id'], 'name' => (string)$r['role']];
}

$roleFilter = trim((string)($_GET['role'] ?? ''));
$roleFilterIds = [];
$roleFilterValues = [];
if ($roleFilter !== '') {
    foreach (explode(',', $roleFilter) as $rid) {
        $rid = (int)trim($rid);
        if ($rid > 0) {
            $found = null;
            foreach ($allRoles as $ar) {
                if ($ar['id'] === $rid) { $found = $ar['name']; break; }
            }
            if ($found !== null) {
                $roleFilterIds[] = $rid;
                $roleFilterValues[] = $found;
            }
        }
    }
}

$tp = new TablePage($conn, $sotrPageConfig);

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

require_once __DIR__ . '/lib/marks-actions.php';
handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
    return null;
});

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    if ($col === 'role') {
        $roleOpts = $allRoles;
        echo json_encode($roleOpts, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$tp->buildFilters();
$filters = $tp->filters;

if (count($roleFilterValues) > 0) {
    $place = implode(',', array_fill(0, count($roleFilterValues), '?'));
    $tp->appendWhere(
        "rl.role IN ($place)",
        $roleFilterValues,
        str_repeat('s', count($roleFilterValues))
    );
    $filters[] = [
        'kind'  => 'role',
        'text'  => 'Роль = ' . implode(', ', $roleFilterValues),
        'clear' => ['role']
    ];
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['sotr_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start('Сотрудники'); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

    .data-table tbody tr:hover td.col-last_name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-last_name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-last_name { background: #3a5a8a; }

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

    <?php $activeMenu = 'sotr.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/sotr.png" alt="" /> Сотрудник</h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $roleFilter) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          if ($roleFilter !== '') $qs['role'] = $roleFilter;
          return 'sotr.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = http_build_query(array_filter([
          'q'    => $searchActive && $search !== '' ? $search : null,
          'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond' => $searchActive ? $searchCond : null,
          'sf'   => $searchActive ? '1' : null,
          'sort' => $sortQs !== '' ? $sortQs : null,
          'role' => $roleFilter !== '' ? $roleFilter : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => 'Сотрудники.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => 'Сотрудники.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'sotr_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('sotr', $exportQs, (int)$page, $marksCount > 0);
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
    ]);
    render_toolbar_left('sotr_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'sotr.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'last_name' => '150px', 'first_name' => '150px', 'second_name' => '150px', 'role' => '130px', 'sphone' => '120px', 'address' => '200px', 'title' => '150px', 'user_status' => '60px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($roleFilterIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'role') {
                    return ' data-values="' . h(implode(',', $roleFilterIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && $cn === 'role') {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'sotr_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':         $raw = (string)(int)$r['sotr_id']; return [$raw, $raw];
                case 'last_name':  $raw = (string)$r['last_name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'first_name': $raw = (string)$r['first_name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'second_name': $raw = (string)$r['second_name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'role':       $rawId = (string)(int)($r['role_id'] ?? 0); $name = (string)($r['role_name'] ?? $rawId); return [$rawId, $doHilight ? hilight($name, $search, $searchCond) : $name];
                case 'sphone':     $raw = (string)$r['sphone']; return [$raw, $raw];
                case 'address':    $raw = (string)$r['address']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'title':      $raw = (string)$r['title']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'user_status':
                    $v = (string)($r['user_status'] ?? '0');
                    return [$v, $v === '1'
                        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        : ''];
                case 'note':       $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js', 'assets/column-filter.js']]);
?>
  <script>
    window.__roles = <?= json_encode($allRoles, JSON_UNESCAPED_UNICODE) ?>;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'last_name' => 150, 'first_name' => 150, 'second_name' => 150, 'role' => 130, 'sphone' => 120, 'address' => 200, 'title' => 150, 'user_status' => 60, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    (function () {
      SelectionToolbar.initTableSelection('sotr.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

      SelectionToolbar.init({
        pageUrl: 'sotr.php',
        search: document.querySelector('.toolbar').getAttribute('data-search') || '',
        getExportUrl: function () {
          return null;
        },
        getPrintUrl: function () {
          return null;
        }
      });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',          label: 'ID' },
          { key: 'last_name',   label: 'Фамилия' },
          { key: 'first_name',  label: 'Имя' },
          { key: 'second_name', label: 'Отчество' },
          { key: 'role',        label: 'Роль' },
          { key: 'address',     label: 'Адрес' },
          { key: 'title',       label: 'Должность' },
          { key: 'note',        label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'sotr.php',
        preserveParams: ['sort', 'role'],
      });

      ColumnFilter.init({
        thSelector: '[data-col="role"]',
        param: 'role',
        closeAllPanels: closeAllPanels,
        pageUrl: 'sotr.php',
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',          label: 'ID' },
          { key: 'last_name',   label: 'Фамилия' },
          { key: 'first_name',  label: 'Имя' },
          { key: 'second_name', label: 'Отчество' },
          { key: 'role',        label: 'Роль' },
          { key: 'sphone',      label: 'Сотовый' },
          { key: 'address',     label: 'Адрес' },
          { key: 'title',       label: 'Должность' },
          { key: 'note',        label: 'Примечание' },
        ],
        pageUrl: 'sotr.php',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });
    })();

    ExportModal.init();
  </script>
  <?php render_form_modal_script([
      'form_prefix'   => 'sotr_form',
      'base_url'      => 'sotr.php',
      'lookup_tables' => [],
  ]); ?>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'sotr_columns_save.php',
      tbl: 'sotr',
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
          const af = window.__accessFlags || {};
          if (openBtn)   openBtn.disabled   = !enabled || !!af.change_flag;
          if (copyBtn)   copyBtn.disabled   = !enabled || !!af.insert_flag;
          if (deleteBtn) deleteBtn.disabled = !enabled || !!af.delete_flag;
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
          if (window.__accessFlags && window.__accessFlags.change_flag) return;
          const tr = e.target.closest('tr[data-row-id]');
          if (!tr) return;
          const id = parseInt(tr.dataset.rowId, 10) || 0;
          if (!id) return;
          rowSel.selectById(id, true);
          if (typeof window.__openFormModal === 'function') {
            window.__openFormModal('sotr_form.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('sotr_form.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'sotr.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'sotr_form',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: tableWrapEl,
        accessFlags: window.__accessFlags
      });
    })();

    (function () {
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;
      if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) return;

      InlineEdit.init({
        tbody: tbody,
        saveUrl: 'sotr_field_save.php',
        fields: <?php
          $inlineFields = [];
          $valCases = '';
          foreach ($visibleColumns as $vc) {
            $cn = $vc['name'];
            if (!empty($vc['readonly']) || $cn === 'id' || $cn === 'passw') continue;
            $isLookup = !empty($vc['param']);
            $type = $cn === 'user_status' ? 'checkbox' : ($isLookup ? 'lookup' : 'text');
            $inlineFields[$cn] = [
              'dbField' => $cn,
              'type'    => $type,
              'label'   => $vc['label'],
            ];
            $label = json_encode($vc['label'], JSON_UNESCAPED_UNICODE);
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            if ($isLookup) {
              $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
          }
        ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
        getLookupData: function () { return window.__roles || []; },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: window.__openFormModal
      });
    })();

    ColumnResize.init({ saveUrl: 'sotr_column_width_save.php', tbl: 'sotr' });
  </script>
<?php render_page_footer();
