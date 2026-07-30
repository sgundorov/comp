<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/status_columns.php';
require_once __DIR__ . '/config/status_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/controls.php';

ensure_marks_table($conn);

$tp = new TablePage($conn, $statusPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'status');

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
handle_marks_actions($conn, $tp, 'status', function($action) use ($conn) {
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
    $ok = save_columns_config($conn, 'status', $clean);
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
foreach ($rows as $r) { if (isset($marks[(int)$r['status_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Состояния заявок');
?>
  <style>
    .data-table tbody tr:hover td.col-status { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-status { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-status { background: #3a5a8a; }
    .color-picker-wrap { display: flex; align-items: center; gap: 8px; }
    .color-rect { width: 80px; height: 32px; border: 2px solid var(--line); border-radius: 3px; cursor: pointer; flex-shrink: 0; transition: border-color .15s; }
    .color-rect:hover { border-color: var(--accent); }
    .color-rect:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .color-rect--empty { background-image: repeating-linear-gradient(45deg, transparent, transparent 3px, rgba(0,0,0,.08) 3px, rgba(0,0,0,.08) 6px); }
    .color-picker-btn { width: 28px; height: 28px; border: 1px solid var(--line); background: var(--btn); color: #fff; border-radius: 2px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .color-picker-btn:hover { background: var(--btn-hover); }
    .color-picker-btn:disabled { opacity: .4; cursor: default; }
    .color-picker-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; }
    .color-picker-modal.open { display: flex; }
    .color-picker-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.4); }
    .color-picker-modal-content { position: relative; background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 16px; max-width: 520px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .color-picker-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-weight: 600; font-size: 15px; }
    .color-picker-modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text); padding: 0 4px; line-height: 1; }
    .color-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, 32px); gap: 4px; justify-content: center; }
    .color-swatch-item { width: 32px; height: 32px; border: 2px solid transparent; border-radius: 3px; cursor: pointer; box-sizing: border-box; transition: transform .1s, border-color .1s; }
    .color-swatch-item:hover { border-color: var(--accent); transform: scale(1.12); }
    .color-swatch-item.selected { border-color: #fff; box-shadow: 0 0 0 2px var(--accent); }
    .color-picker-footer { display: flex; gap: 8px; justify-content: center; margin-top: 12px; }
    .color-picker-custom-btn, .color-picker-clear-btn { padding: 4px 14px; border: 1px solid var(--line); border-radius: 3px; background: var(--btn); color: var(--text); cursor: pointer; font-size: 13px; }
    .color-picker-custom-btn:hover, .color-picker-clear-btn:hover { background: var(--btn-hover); }
  </style>
<?php
render_export_modal();
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'status.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/status.png" alt="" /> Состояния заявок</h1>

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
          return 'status.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs, true);
    ?>

    <?php
      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => 'Состояния заявок.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => 'Состояния заявок.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'status_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }
      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml =
          '<a class="dropdown-item" href="status_print.php' . $printQs . '" target="_blank">Печатать таблицу</a>' .
          '<a class="dropdown-item" href="status_print.php?all=1' . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Печатать выбранные</a>' .
          '<a class="dropdown-item" href="status_print.php?page=' . (int)$page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Печатать страницу</a>';
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
    render_toolbar_left('status_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'status.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'status' => '200px', 'color' => '100px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'status_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':
                    $raw = (string)(int)$r['status_id'];
                    return [$raw, $raw];
                case 'status':
                    $raw = (string)$r['status'];
                    return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'color':
                    $raw = $r['color'] ? sprintf('#%06x', (int)$r['color']) : '';
                    return [$raw, 'Пример'];
                case 'note':
                    $raw = (string)$r['note'];
                    return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'searchActive' => $searchActive,
            'searchCols' => $searchCols,
            'checkboxCallback' => function($rid) use ($marks) {
                return '<input type="checkbox" class="row-check" value="' . $rid . '" data-id="' . $rid . '"' . (isset($marks[$rid]) ? ' checked' : '') . ' />';
            },
            'tdExtraAttrs' => function($cn, $vc, $r, $i) {
                if ($cn === 'color') {
                    $dbColor = $r['color'] ?? 0;
                    $color = $dbColor ? sprintf('#%06x', (int)$dbColor) : '';
                    if ($color) return ' style="background-color:' . h($color) . '"';
                }
                return '';
            },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

    <?php render_form_modal(); ?>
    <?= render_color_picker_modal() ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/color-picker.js']]);
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
    ColorPicker.init();

    SelectionToolbar.initTableSelection('status.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

    function updateRowActionButtons() {
      const id = rowSel.getSelectedId();
      const enabled = id !== 0;
      rowOpenBtn.disabled = !enabled;
      rowCopyBtn.disabled = !enabled;
      rowDeleteBtn.disabled = !enabled;
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
      pageUrl: 'status.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'status.php?action=invertSelection&' + other.toString();
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
      pageUrl: 'status.php',
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
        location.href = 'status.php?' + params.toString();
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
        location.href = 'status.php?' + params.toString();
      },
      onSubmit: function () {
        searchToggleBtn.click();
      }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'status.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'status_columns_save.php',
      tbl: 'status',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'status' => 200, 'color' => 100, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input.row-check')) return;
        selectRow(id);
      });
      tr.addEventListener('dblclick', function () {
        window.__openFormModal('status_form.php?mode=edit&id=' + id);
      });
    });

    rowOpenBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('status_form.php?mode=edit&id=' + id);
    });
    rowCopyBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('status_form.php?mode=copy&id=' + id);
    });
    rowDeleteBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('status_form.php?mode=delete&id=' + id);
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
      formPrefix: 'status_form',
      rowSel: rowSel,
      currentPage: currentPage,
      currentPages: currentPages,
      navigate: navigate,
      tableWrapEl: tableWrapEl
    });

    __refreshSelectionUI();
  })();
  </script>
    <?php render_form_modal_script([
      'form_prefix'   => 'status_form',
      'base_url'      => 'status.php',
      'lookup_tables' => [],
      'extra_restore' => 'body.querySelectorAll(\'.color-picker-wrap\').forEach(function (el) { if (window.ColorPicker) ColorPicker.wrap(el); });',
      'extra_open'    => 'body.querySelectorAll(\'.color-picker-wrap\').forEach(function (el) { if (window.ColorPicker) ColorPicker.wrap(el); });',
    ]); ?>
  <script>
  InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'status_field_save.php',
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
        if ($isLookup) {
          $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
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

  ExportModal.init();
  ColumnResize.init({ saveUrl: 'status_column_width_save.php', tbl: 'status' });
  </script>
<?php render_page_footer();
