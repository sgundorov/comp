<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/object_columns.php';
require_once __DIR__ . '/config/object_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Object');

$TBL = 'object';

ensure_marks_table($conn);

$tp = new TablePage($conn, $objectPageConfig);

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

if (!function_exists('build_filter')) {
function build_filter(string $search, array $cols = [], string $cond = 'contains'): array {
    $colToExpr = [
        'object' => 'o.object',
        'name'   => 'o.name',
        'type'   => 'o.type',
        'note'   => 'o.note',
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
    [$where, $params, $types] = build_filter($search, $cols, $cond);
    $sql = "SELECT o.object_id FROM `object` o $where";
    $ids = [];
    if ($types === '') {
        $res = @$conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['object_id'];
    } else {
        $stmt = @mysqli_prepare($conn, $sql);
        if ($stmt) {
            stmt_bind($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['object_id'];
            $stmt->close();
        }
    }
    return $ids;
}
}

handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if ($col === 'type') {
            $typeOpts = [['id' => 1, 'name' => 'Документ'], ['id' => 2, 'name' => 'Поле'], ['id' => 3, 'name' => 'Отчет']];
            echo json_encode($typeOpts, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    return null;
});

$typeMap = [1 => 'Документ', 2 => 'Поле', 3 => 'Отчет'];
$typeFilter = trim((string)($_GET['type'] ?? ''));
$typeFilterIds = [];
$typeFilterValues = [];
if ($typeFilter !== '') {
    $typeFilterIds = array_values(array_filter(array_map('intval', explode(',', $typeFilter)), fn($v) => isset($typeMap[$v])));
    $typeFilterValues = array_values(array_filter(array_map(function($id) use ($typeMap) { return $typeMap[$id]; }, $typeFilterIds)));
}

$marks = load_marks_set($conn, $TBL);
$marksCount = count_marks($conn, $TBL);

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$tp->buildFilters();
$filters = $tp->filters;

if (count($typeFilterValues) > 0) {
    $place = implode(',', array_fill(0, count($typeFilterValues), '?'));
    $tp->appendWhere("o.type IN ($place)", $typeFilterValues, str_repeat('s', count($typeFilterValues)));
    $filters[] = ['kind' => 'type', 'text' => 'Тип = ' . implode(', ', $typeFilterValues), 'clear' => ['type']];
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['object_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start('Объекты доступа'); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

    .data-table tbody tr:hover td.col-object { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-object { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-object { background: #3a5a8a; }

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

    <?php $activeMenu = 'object.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/object.png" alt="" /> Объекты доступа</h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $typeFilter) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          if ($typeFilter !== '') $qs['type'] = $typeFilter;
          return 'object.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = http_build_query(array_filter([
          'q'    => $searchActive && $search !== '' ? $search : null,
          'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond' => $searchActive ? $searchCond : null,
          'sf'   => $searchActive ? '1' : null,
          'sort' => $sortQs !== '' ? $sortQs : null,
          'type' => $typeFilter !== '' ? $typeFilter : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => 'Объекты доступа.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => 'Объекты доступа.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'object_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('object', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('object_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'object.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'object' => '150px', 'name' => '200px', 'type' => '100px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($typeFilterIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'type') {
                    return ' data-col="' . h($cn) . '" data-param="type" data-values="' . h(implode(',', $typeFilterIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && $cn === 'type') {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'object_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':     $raw = (string)(int)$r['object_id']; return [$raw, $raw];
                case 'object': $raw = (string)$r['object']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'name':   $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'type':   $raw = (string)$r['type']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note':   $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'searchActive' => $searchActive,
            'searchCols' => $searchCols,
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
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'object' => 150, 'name' => 200, 'type' => 100, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    (function () {
      SelectionToolbar.initTableSelection('object.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

      SelectionToolbar.init({
        pageUrl: 'object.php',
        search: document.querySelector('.toolbar').getAttribute('data-search') || '',
        getExportUrl: function () {
          return 'object_export.php?format=csv&all=1';
        },
        getPrintUrl: function () {
          return 'object_print.php?all=1';
        }
      });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',     label: 'ID' },
          { key: 'object', label: 'Обозначение' },
          { key: 'name',   label: 'Объект' },
          { key: 'type',   label: 'Тип' },
          { key: 'note',   label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'object.php',
        preserveParams: ['sort', 'type'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',     label: 'ID' },
          { key: 'object', label: 'Обозначение' },
          { key: 'name',   label: 'Объект' },
          { key: 'type',   label: 'Тип' },
          { key: 'note',   label: 'Примечание' },
        ],
        pageUrl: 'object.php',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });

      ColumnFilter.init({
        thSelector: '[data-col="type"]',
        closeAllPanels: closeAllPanels,
        pageUrl: 'object.php',
      });
    })();

    ExportModal.init();
  </script>
<?php render_form_modal_script(['form_prefix' => 'object_form', 'base_url' => 'object.php']); ?>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'object_columns_save.php',
      tbl: 'object',
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
            window.__openFormModal('object_form.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('object_form.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'object.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'object_form',
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
        saveUrl: 'object_field_save.php',
        fields: <?php
          $inlineFields = [];
          $valCases = '';
          foreach ($visibleColumns as $vc) {
            $cn = $vc['name'];
            if (!empty($vc['readonly']) || $cn === 'id') continue;
            $isLookup = !empty($vc['param']);
            $type = $isLookup ? 'lookup' : 'text';
            $extra = [];
            if ($cn === 'type') {
                $type = 'select';
                $extra['options'] = [
                    ['value' => 'Документ', 'label' => 'Документ'],
                    ['value' => 'Поле', 'label' => 'Поле'],
                    ['value' => 'Отчет', 'label' => 'Отчет'],
                ];
            }
            $inlineFields[$cn] = array_merge([
              'dbField' => $cn,
              'type'    => $type,
              'label'   => $vc['label'],
            ], $extra);
            $label = json_encode($vc['label'], JSON_UNESCAPED_UNICODE);
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            if ($isLookup) {
              $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
          }
        ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
        getLookupData: function () { return []; },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: window.__openFormModal
      });
    })();

    ColumnResize.init({ saveUrl: 'object_column_width_save.php', tbl: 'object' });
  </script>
<?php render_page_footer();