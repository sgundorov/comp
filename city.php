<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'City');

$TBL = 'city';

ensure_marks_table($conn);

$allCountries = [];
$res = @$conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($res) while ($r = $res->fetch_assoc()) {
    $allCountries[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['country']];
}

$tp = new TablePage($conn, $cityPageConfig);
$tp->loadColumnWidths($conn);
$tp->loadCountryNames($conn, 'country', 'country', 'country_id');

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, $TBL);

$search           = $tp->search;
$searchActive     = $tp->searchActive;
$searchCols       = $tp->searchCols;
$searchCond       = $tp->searchCond;
$sortLevels       = $tp->sortLevels;
$sortIsDefault    = $tp->sortIsDefault;
$sortQs           = $tp->sortQs;
$orderBy          = $tp->orderBy;
$showOnly         = $tp->showOnly;
$marks            = $tp->marks;
$marksCount       = $tp->marksCount;

require_once __DIR__ . '/lib/marks-actions.php';
handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn, $tp) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if ($col === 'country') {
            $out = $tp->colFilterOptions($conn, 'country');
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([]);
        }
        exit;
    }
    return null;
});

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['city_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Города'); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }
    .data-table tbody tr:hover td.col-city { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-city { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-city { background: #3a5a8a; }
    .data-table thead th[data-sort-col]:hover { background: #3d5468; }
    .col-filter-panel {
      display: none; position: fixed; z-index: 1000; min-width: 240px; max-width: 320px;
      padding: 8px; background: #2f3e4e; border: 2px solid #6b7785;
      border-radius: 2px; box-shadow: 0 8px 18px rgba(0,0,0,.35); text-align: left;
    }
    .col-filter-panel.open { display: block; }
    .col-filter-search { width: 100%; height: 28px; padding: 0 8px; margin: 0 0 6px; box-sizing: border-box; border: 1px solid var(--line); background: #fff; color: #2b2b2b; border-radius: 2px; font-size: 13px; outline: none; }
    .col-filter-list { margin-bottom: 8px; }
    .col-filter-row { display: flex; align-items: center; gap: 6px; padding: 4px; cursor: pointer; color: #fff; font-size: 13px; user-select: none; }
    .col-filter-row:hover { background: var(--btn-hover); }
    .col-filter-row input[type="checkbox"] { width: 14px; height: 14px; flex: 0 0 auto; }
    .col-filter-row .col-filter-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .col-filter-empty { padding: 6px 4px; color: var(--muted); font-size: 12px; font-style: italic; }
    .col-filter-more { padding: 4px; color: var(--muted); font-size: 12px; font-style: italic; border-top: 1px solid var(--line); }
    .col-filter-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 10px 8px; border-top: 1px solid var(--line); }
    .col-filter-actions button { display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 8px; background: var(--btn); color: #fff; border: 1px solid var(--line); border-radius: 2px; cursor: pointer; font-size: 14px; }
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

    <?php $activeMenu = 'city.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/city.png" alt="" /> Город</h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
          $qs = ['page' => (int)$p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'city.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);
    ?>

    <?php
      $exportDropdownHtml = '<a class="dropdown-item" href="city_export.php?format=csv' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Города.csv">Экспорт в CSV</a>'
        . '<a class="dropdown-item" href="city_export.php?format=xls' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Города.xls">Экспорт в Excel</a>';
      $printDropdownHtml = render_print_dropdown_items('city', $exportQs, (int)$page, $marksCount > 0);
    ?>
    <?php render_toolbar_wrapper_open([
        'total'        => (int)$total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => (int)$marksCount,
        'search'       => $search,
        'page'         => (int)$page,
        'pages'        => (int)$pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]);
    render_toolbar_left('city_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'city.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'city' => '200px', 'country' => '160px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm, $i) use ($tp) {
                if ($cm && !empty($cm['filter']) && $cn === 'country') {
                    return ' data-col="' . h($cn) . '" data-param="country_id" data-values="' . h(implode(',', $tp->countryIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && $cn === 'country') {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'city_id', function($r, $cn, $vc) use ($search, $searchCols, $searchCond) {
            $doHilight = $search !== '' && in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':      $raw = (string)(int)$r['city_id']; return [$raw, $raw];
                case 'city':    $raw = (string)$r['city']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'country': $rawId = (string)(int)($r['country_id'] ?? 0); $name = (string)($r['country_name'] ?? $rawId); return [$rawId, $doHilight ? hilight($name, $search, $searchCond) : $name];
                case 'note':    $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/access.js', 'assets/column-filter.js', 'assets/export-modal.js']]);
?>
  <script>
  (function () {
    const toolbar = document.querySelector('.toolbar');
    const tbody   = document.querySelector('table tbody');
    const rowOpenBtn   = document.getElementById('rowOpenBtn');
    const rowCopyBtn   = document.getElementById('rowCopyBtn');
    const rowDeleteBtn = document.getElementById('rowDeleteBtn');
    const sortBtn   = document.getElementById('sortBtn');
    const columnsBtn = document.getElementById('columnsBtn');
    const searchForm  = document.getElementById('searchForm');
    const searchCondBtn  = document.getElementById('searchCondBtn');
    const searchToggleBtn= document.getElementById('searchToggleBtn');

    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

    SelectionToolbar.initTableSelection('city.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel.open, .search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }

    const SORT_COLS = <?= json_encode(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) { return $c['search'] ? ['key' => $c['name'], 'label' => $c['label']] : null; }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.init({
      pageUrl: 'city.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'city.php?action=invertSelection&' + other.toString();
      },
      getExportUrl: function () {
        var sp = new URLSearchParams(location.search);
        ['action', 'page'].forEach(function (k) { sp.delete(k); });
        return 'city_export.php?format=csv&selected=1&' + sp.toString();
      },
      getPrintUrl: function () {
        var sp = new URLSearchParams(location.search);
        ['action', 'page'].forEach(function (k) { sp.delete(k); });
        return 'city_print.php?selected=1&' + sp.toString();
      }
    });

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    SearchPanel.init({
      form: searchForm,
      condBtn: searchCondBtn,
      toggleBtn: searchToggleBtn,
      columns: SEARCH_COLS,
      pageUrl: 'city.php',
      closeAllPanels: closeAllPanels,
      preserveParams: ['sort'],
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'city.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'city_columns_save.php',
      tbl: 'city',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'city' => 200, 'country' => 160, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);

    ExportModal.init();
  })();
  </script>
  <?php render_form_modal_script([
    'form_prefix'   => 'city_form',
    'base_url'      => 'city.php',
    'lookup_tables' => ['country'],
  ]); ?>
  <script>
  (function () {
    const toolbar = document.querySelector('.toolbar');
    const tbody   = document.querySelector('table tbody');
    const rowOpenBtn   = document.getElementById('rowOpenBtn');
    const rowCopyBtn   = document.getElementById('rowCopyBtn');
    const rowDeleteBtn = document.getElementById('rowDeleteBtn');

    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

    const rowSel = RowSelect.init({
      tbody: tbody,
      rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          const af = window.__accessFlags || {};
          if (rowOpenBtn)   rowOpenBtn.disabled   = !enabled || !!af.change_flag;
          if (rowCopyBtn)   rowCopyBtn.disabled   = !enabled || !!af.insert_flag;
          if (rowDeleteBtn) rowDeleteBtn.disabled = !enabled || !!af.delete_flag;
        },
      currentPage: currentPage,
      totalPages: currentPages,
      navigate: navigate
    });

    function selectRow(rowId) { rowSel.selectById(rowId, true); }

    function navigate(apply) {
      const p = new URLSearchParams(location.search);
      apply(p);
      location.href = 'city.php?' + p.toString();
    }

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input.row-check')) return;
        selectRow(id);
      });
      tr.addEventListener('dblclick', function () {
        if (window.__accessFlags && window.__accessFlags.change_flag) return;
        window.__openFormModal('city_form.php?mode=edit&id=' + id);
      });
    });

    rowOpenBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('city_form.php?mode=edit&id=' + id);
    });
    rowCopyBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('city_form.php?mode=copy&id=' + id);
    });
    rowDeleteBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('city_form.php?mode=delete&id=' + id);
    });

    const tableWrapEl = document.querySelector('.table-wrap');

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

    bindTableKeyboardShortcuts({
      formPrefix: 'city_form',
      rowSel: rowSel,
      currentPage: currentPage,
      currentPages: currentPages,
      navigate: navigate,
      tableWrapEl: tableWrapEl,
      accessFlags: window.__accessFlags
    });
  })();

  if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
  InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'city_field_save.php',
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
        $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
        if ($isLookup) {
          $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
      }
    ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
    getLookupData: function (field) {
        var map = <?= json_encode(['country' => $allCountries], JSON_UNESCAPED_UNICODE) ?>;
        return map[field] || [];
    },
    validate: function (field, value) {
      switch (field) {
<?= $valCases ?>
      }
      return null;
    },
    onOpenForm: window.__openFormModal
  });
  }

  ColumnResize.init({ saveUrl: 'city_column_width_save.php', tbl: 'city' });

  ColumnFilter.init({"thSelector":".col-country"});
  </script>
<?php render_page_footer();
