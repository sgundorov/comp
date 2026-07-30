<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/unit_columns.php';
require_once __DIR__ . '/config/unit_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Unit');

ensure_marks_table($conn);

$tp = new TablePage($conn, $unitPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'unit');

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
handle_marks_actions($conn, $tp, 'unit', function($action) use ($conn) {
    return null;
});

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'columnsSave') {
    $raw = $_POST['columns'] ?? [];
    $clean = [];
    $allowed = [];
    foreach ($COLUMN_DEFAULTS as $c) $allowed[$c['name']] = true;
    $seen = [];
    if (is_array($raw)) {
        foreach ($raw as $i => $row) {
            if (!is_array($row)) continue;
            $name = (string)($row['name'] ?? '');
            if (!isset($allowed[$name]) || isset($seen[$name])) continue;
            $seen[$name] = true;
            $clean[] = [
                'name'    => $name,
                'visible' => !empty($row['visible']) ? 1 : 0,
                'order'   => (int)($row['order'] ?? $i),
            ];
        }
    }
    foreach ($COLUMN_DEFAULTS as $i => $c) {
        if (!isset($seen[$c['name']])) {
            $clean[] = ['name' => $c['name'], 'visible' => 1, 'order' => count($clean) + $i];
        }
    }
    usort($clean, function ($a, $b) { return $a['order'] - $b['order']; });
    $ok = save_columns_config($conn, 'unit', $clean);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['unit_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Единицы измерения');
?>
  <style>
    .data-table tbody tr:hover td.col-unit { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-unit { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-unit { background: #3a5a8a; }
  </style>
<?php
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'unit.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/unit.png" alt="" /> Единицы измерения</h1>

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
          return 'unit.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs, true);
    ?>

    <?php
      $exportDropdownHtml = '';
      foreach (['csv' => 'Единицы измерения.csv', 'xls' => 'Единицы измерения.xls'] as $fmt => $filename) {
          $fullUrl = 'unit_export.php?format=' . $fmt . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($fmt === 'csv' ? 'CSV' : 'XLS (Excel)') . '">' . ($fmt === 'csv' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }
      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('unit', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('unit_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'unit.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'unit' => '200px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'unit_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':   $raw = (string)(int)$r['unit_id']; return [$raw, $raw];
                case 'unit': $raw = (string)$r['unit']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note': $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'searchActive' => $searchActive,
            'searchCols' => $searchCols,
            'checkboxCallback' => function($rid) use ($marks) {
                return '<input type="checkbox" class="row-check" value="' . $rid . '" data-id="' . $rid . '"' . (isset($marks[$rid]) ? ' checked' : '') . ' />';
            },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js']]);
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

    const selWrap   = document.getElementById('selectedActions');
    const selCount  = document.getElementById('selectedCount');

    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);
    const currentTotal = parseInt(toolbar.dataset.total || '0', 10);

    SelectionToolbar.initTableSelection('unit.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

    function updateRowActionButtons() {
      const id = rowSel.getSelectedId();
      const enabled = id !== 0;
      const af = window.__accessFlags || {};
      rowOpenBtn.disabled = !enabled || !!af.change_flag;
      rowCopyBtn.disabled = !enabled || !!af.insert_flag;
      rowDeleteBtn.disabled = !enabled || !!af.delete_flag;
    }

    const rowSel = RowSelect.init({
      tbody: tbody,
      rowClass: 'selected',
      onChange: updateRowActionButtons,
      currentPage: currentPage,
      totalPages: currentPages,
      navigate: navigate
    });

    function selectRow(rowId) { rowSel.selectById(rowId, true); }

    function navigate(params) {
      const url = new URL(window.location.href);
      params(url.searchParams);
      window.location.href = url.pathname + '?' + url.searchParams.toString();
    }

    const SORT_COLS = <?= json_encode(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) { return $c['search'] ? ['key' => $c['name'], 'label' => $c['label']] : null; }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.init({
      pageUrl: 'unit.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'unit.php?action=invertSelection&' + other.toString();
      },
      getExportUrl: function () {
        return null;
      },
      getPrintUrl: function () {
        return null;
      }
    });

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel, .search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }

    SearchPanel.init({
      form: searchForm,
      condBtn: searchCondBtn,
      toggleBtn: searchToggleBtn,
      columns: SEARCH_COLS,
      pageUrl: 'unit.php',
      popupCheckboxes: true,
      emptyClass: 'search-cond-placeholder',
      closeAllPanels: closeAllPanels,
      labels: {
        cols: 'Колонки',
        cond: 'Условие',
        emptyCols: 'Выберите колонки…'
      },
      onApply: function (state) {
        var params = new URLSearchParams(location.search);
        if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
        else params.delete('cols');
        params.set('cond', state.cond);
        params.set('sf', '1');
        var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim();
        if (q !== '') params.set('q', q); else params.delete('q');
        params.delete('page');
        location.href = 'unit.php?' + params.toString();
      },
      onToggle: function (state) {
        var params = new URLSearchParams(location.search);
        var q = searchForm.querySelector('input[name="q"]').value.trim();
        if (q !== '') {
          params.set('q', q);
          if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
          params.set('cond', state.cond);
          params.set('sf', '1');
        } else {
          params.delete('q');
          params.delete('cols');
          params.delete('cond');
          params.delete('sf');
        }
        params.delete('page');
        location.href = 'unit.php?' + params.toString();
      },
      onSubmit: function () {
        searchToggleBtn.click();
      }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'unit.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'unit_columns_save.php',
      tbl: 'unit',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'unit' => 200, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input.row-check')) return;
        selectRow(id);
      });
      tr.addEventListener('dblclick', function () {
        if (window.__accessFlags && window.__accessFlags.change_flag) return;
        window.__openFormModal('unit_form.php?mode=edit&id=' + id);
      });
    });

    rowOpenBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('unit_form.php?mode=edit&id=' + id);
    });
    rowCopyBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('unit_form.php?mode=copy&id=' + id);
    });
    rowDeleteBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('unit_form.php?mode=delete&id=' + id);
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
      formPrefix: 'unit_form',
      rowSel: rowSel,
      currentPage: currentPage,
      currentPages: currentPages,
      navigate: navigate,
      tableWrapEl: tableWrapEl,
      accessFlags: window.__accessFlags
    });

    __refreshSelectionUI();
  })();
  </script>
    <?php render_form_modal_script([
      'form_prefix'   => 'unit_form',
      'base_url'      => 'unit.php',
      'lookup_tables' => [],
    ]); ?>
  <script>
  if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
  InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'unit_field_save.php',
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
    validate: function (field, value) {
      switch (field) {
<?= $valCases ?>
      }
      return null;
    },
    onOpenForm: window.__openFormModal
  });
  }
  
  ColumnResize.init({ saveUrl: 'unit_column_width_save.php', tbl: 'unit' });
  ExportModal.init();
  </script>
<?php render_page_footer();
