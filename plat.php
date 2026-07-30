<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/plat_columns.php';
require_once __DIR__ . '/config/plat_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/controls.php';

$accessFlags = render_access_control($conn, 'Plat');

ensure_marks_table($conn);

$tp = new TablePage($conn, $platPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'plat');

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

handle_marks_actions($conn, $tp, 'plat', function($action) use ($conn, $tp, $COLUMN_DEFAULTS) {
    if ($action === 'columnsSave') {
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
        $ok = save_columns_config($conn, 'plat', $clean);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
        exit;
    }
    return null;
});

$urlCols = $searchCols;

// ---- date range filter ----
$dateFrom = (string)($_GET['date_from'] ?? '');
$dateTo   = (string)($_GET['date_to'] ?? '');
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = '';

// ---- column filters must run BEFORE getTotalCount ----
$tp->processColumnFilters($conn);

if ($dateFrom !== '') {
    $tp->appendWhere("DATE(p.datetime) >= ?", [$dateFrom], 's');
}
if ($dateTo !== '') {
    $tp->appendWhere("DATE(p.datetime) <= ?", [$dateTo], 's');
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['plat_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;
if ($dateFrom !== '' || $dateTo !== '') {
    $df = $dateFrom !== '' ? date('d-m-Y', strtotime($dateFrom)) : '...';
    $dt = $dateTo !== '' ? date('d-m-Y', strtotime($dateTo)) : '...';
    $filters[] = ['kind' => 'period', 'text' => "Период с $df по $dt", 'clear' => ['date_from', 'date_to']];
}

$exportQsExtra = [];
if ($dateFrom !== '') $exportQsExtra['date_from'] = $dateFrom;
if ($dateTo !== '') $exportQsExtra['date_to'] = $dateTo;
$exportQs = $tp->buildExportQs($exportQsExtra);

$clientFilterOptions = [];
$clr = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($clr) while ($cr = $clr->fetch_assoc()) $clientFilterOptions[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$zatFilterOptions = [];
$zr = $conn->query("SELECT zat_id, name FROM zat ORDER BY name");
if ($zr) while ($z = $zr->fetch_assoc()) $zatFilterOptions[] = ['id' => (int)$z['zat_id'], 'name' => (string)$z['name']];

$sotrFilterOptions = [];
$sr = $conn->query("SELECT sotr_id, TRIM(CONCAT_WS(' ', last_name, first_name)) AS name FROM sotr ORDER BY name");
if ($sr) while ($s = $sr->fetch_assoc()) $sotrFilterOptions[] = ['id' => (int)$s['sotr_id'], 'name' => (string)$s['name']];

function cellValue($row, $colName, $vc) {
    switch ($colName) {
        case 'id':        return [$row['plat_id'], $row['plat_id']];
        case 'datetime':
            $dt = strtotime((string)$row['datetime']);
            $display = $dt ? date('Y-m-d H:i', $dt) : '';
            return [$row['datetime'], $display];
        case 'client':    return [(int)$row['client_id'], h($row['client_name'])];
        case 'zat':       return [(int)$row['zat_id'], h($row['zat_name'])];
        case 'sum':
            $f = (float)$row['sum'];
            return [$row['sum'], number_format($f, 2, ',', ' ')];
        case 'plat_type': return [$row['plat_type'], h($row['plat_type'])];
        case 'doc_id':    return [(int)$row['doc_id'] > 0 ? (int)$row['doc_id'] : '', (int)$row['doc_id'] > 0 ? (int)$row['doc_id'] : ''];
        case 'sotr':      return [(int)$row['sotr_id'], h($row['sotr_name'])];
        case 'out_flag':  return [(int)$row['out_flag'], (int)$row['out_flag'] === 1 ? 'Расход' : 'Приход'];
        case 'note':      return [$row['note'], h($row['note'])];
    }
    return ['', ''];
}

$paginationHtml = '';

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => 'Кассовая книга.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => 'Кассовая книга.xls', 'format' => 'XLS (Excel)'],
] as $item) {
    $fullUrl = 'plat_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}
$printQs = $exportQs !== '' ? '?' . $exportQs : '';
$printDropdownHtml = render_print_dropdown_items('plat', $exportQs, (int)$page, $marksCount > 0);

render_head_start('Кассовая книга');
?>
  <style>
    .data-table tbody tr:hover td.col-client { background: #2c3a4d; }
    .data-table tbody tr:hover td.col-zat { background: #2c3a4d; }
    .data-table tbody tr:hover td.col-sotr { background: #2c3a4d; }
    .data-table tbody tr:hover td.col-sum { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-client { background: #3a5a8a; }
    .data-table tbody tr.selected td.col-zat { background: #3a5a8a; }
    .data-table tbody tr.selected td.col-sotr { background: #3a5a8a; }
    .data-table tbody tr.selected td.col-sum { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-sum { background: #3a5a8a; }
    .row--out td { color: #d4c84a !important; }
    .row--out td.col-check input { color: #d4c84a; }
    .col-sum { text-align: right; white-space: nowrap; }
    .col-datetime { white-space: nowrap; }
  </style>
<?php
render_export_modal();
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'plat.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/plat.png" alt="" /> Кассовая книга</h1>

    <?php render_toolbar_wrapper_open([
        'total'        => $total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => $marksCount,
        'search'       => $search,
        'page'         => $page,
        'pages'        => $pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]); ?>

    <?php render_toolbar_left('plat_form', $marksCount, $exportDropdownHtml, $printDropdownHtml); ?>

    <div class="toolbar-center" style="display:flex;gap:6px;align-items:center;margin:0 12px">
      <label style="font-size:12px;color:var(--muted)">Период</label>
      <input type="date" id="dateFrom" value="<?= h($dateFrom) ?>" style="max-width:140px;font-size:12px;padding:2px 4px" />
      <span style="color:var(--muted)">—</span>
      <input type="date" id="dateTo" value="<?= h($dateTo) ?>" style="max-width:140px;font-size:12px;padding:2px 4px" />
      <button type="button" class="icon-btn" id="dateApplyBtn" title="Применить фильтр по дате" onclick="applyDateFilter()"><img src="img/filter.png" alt="" /></button>
    </div>

    <?php render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'plat.php'); ?>

    <?php render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
    <table class="data-table" data-table="plat">
      <?php render_table_colgroup($visibleColumns, $columnWidths, plat_columns_widths_print()); ?>
      <?php render_table_thead(
          $visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0,
          ['thAttrsCallback' => function($cn, $cm, $i) {
              if ($cn === 'plat_type') {
                  $attrs = ' data-param="plat_type"';
                  $raw = (string)($_GET['plat_type'] ?? '');
                  if ($raw !== '') {
                      $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v >= 1 && $v <= 4));
                      if (count($ids) > 0) $attrs .= ' data-values="' . implode(',', $ids) . '"';
                  }
                  return $attrs;
              }
              if ($cn === 'out_flag') {
                  $attrs = ' data-param="out_type"';
                  $raw = (string)($_GET['out_type'] ?? '');
                  if ($raw !== '') {
                      $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v === 0 || $v === 1));
                      if (count($ids) > 0) $attrs .= ' data-values="' . implode(',', $ids) . '"';
                  }
                  return $attrs;
              }
              return '';
          }]
      ); ?>
      <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'plat_id', 'cellValue', [
           'searchActive' => $searchActive,
           'searchCols' => $searchCols,
           'hasCheckbox' => true,
          'trExtraAttrs' => function($r) {
              if ((int)($r['out_flag'] ?? 0) === 1) return ' class="row--out"';
              return '';
          },
      ]); ?>
    </table>
    </div>

    <?php
    $pagExtra = [];
    if ($dateFrom !== '') $pagExtra['date_from'] = $dateFrom;
    if ($dateTo !== '') $pagExtra['date_to'] = $dateTo;
    $tp->renderPagination($pagExtra);
    ?>

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/access.js', 'assets/lookup.js', 'assets/column-filter.js', 'assets/export-modal.js', 'assets/columns-panel.js', 'assets/column-resize.js', 'assets/inline-edit.js']]);
?>
  <script>
  function applyDateFilter() {
    var params = new URLSearchParams(window.location.search);
    var df = document.getElementById('dateFrom').value;
    var dt = document.getElementById('dateTo').value;
    if (df) params.set('date_from', df); else params.delete('date_from');
    if (dt) params.set('date_to', dt); else params.delete('date_to');
    params.delete('page');
    window.location.href = 'plat.php?' + params.toString();
  }
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

    SelectionToolbar.initTableSelection('plat.php', toolbar.getAttribute('data-search') || '');

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
    window.rowSel = rowSel;

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
      pageUrl: 'plat.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'plat.php?action=invertSelection&' + other.toString();
      },
      getExportUrl: function () { return 'plat_export.php?format=csv&all=1&' + new URLSearchParams(location.search).toString(); },
      getPrintUrl: function () { return 'plat_print.php?all=1&' + new URLSearchParams(location.search).toString(); }
    });

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }

    SearchPanel.init({
      form: searchForm,
      condBtn: searchCondBtn,
      toggleBtn: searchToggleBtn,
      columns: SEARCH_COLS,
      pageUrl: 'plat.php',
      popupCheckboxes: true,
      emptyClass: 'search-cond-placeholder',
      closeAllPanels: closeAllPanels,
      labels: {
        cols: 'Колонки',
        cond: 'Условие',
        emptyCols: 'Нет столбцов для поиска'
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
        location.href = 'plat.php?' + params.toString();
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
        location.href = 'plat.php?' + params.toString();
      },
      onSubmit: function () {
        searchToggleBtn.click();
      }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'plat.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) { if (e.target.closest('input.row-check')) return; selectRow(id); });
      tr.addEventListener('dblclick', function () { if (window.__accessFlags && window.__accessFlags.change_flag) return; if (typeof window.__openFormModal === 'function') window.__openFormModal('plat_form.php?mode=edit&id=' + id); });
    });

    document.getElementById('rowOpenBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('plat_form.php?mode=edit&id=' + id); });
    document.getElementById('rowCopyBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('plat_form.php?mode=copy&id=' + id); });
    document.getElementById('rowDeleteBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('plat_form.php?mode=delete&id=' + id); });

    const tableWrapEl = document.querySelector('.table-wrap');
    if (typeof bindTableKeyboardShortcuts === 'function') {
      bindTableKeyboardShortcuts({ formPrefix: 'plat_form', rowSel: rowSel, currentPage: currentPage, currentPages: currentPages, navigate: navigate, tableWrapEl: tableWrapEl, accessFlags: window.__accessFlags });
    }

    (function applyInitialFocus() {
      const rows = rowSel.getRows(); if (rows.length === 0) return;
      const raw = (toolbar.dataset.focus || '').toString();
      if (raw === 'first') { rowSel.selectByIndex(0); return; }
      if (raw === 'last') { rowSel.selectByIndex(rows.length - 1); return; }
      const id = parseInt(raw, 10);
      if (id > 0 && rowSel.selectById(id, false)) return;
      rowSel.selectByIndex(0);
    })();

    __refreshSelectionUI();
  })();

  ExportModal.init();

  window.__accessFlags = <?= json_encode($accessFlags) ?>;
  if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
</script>
<?php render_form_modal_script([
    'form_prefix' => 'plat_form',
    'base_url' => 'plat.php',
    'lookup_tables' => ['client', 'zat', 'sotr'],
    'extra_open' => 'initFormLookups();',
    'extra_restore' => 'initFormLookups();',
]); ?>
<script>
ColumnsPanel.init({
  btn: document.getElementById('columnsBtn'),
  saveUrl: 'plat_columns_save.php',
  tbl: 'plat',
  closeAllPanels: function() {
    document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
    document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
    document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
  },
  initialColumns: <?= json_encode(array_map(function ($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
  }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
  defaultColumns: <?= json_encode(array_map(function ($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
  }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
});

if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'plat_field_save.php',
    fields: <?php
      $inlineFields = [];
      $valCases = '';
      foreach ($visibleColumns as $vc) {
        $cn = $vc['name'];
        if (!empty($vc['readonly']) || $cn === 'id') continue;
        if ($cn === 'sum') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'text', 'label' => $vc['label']];
        } elseif ($cn === 'datetime') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'text', 'label' => $vc['label']];
        } elseif ($cn === 'note') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'textarea', 'label' => $vc['label']];
        } elseif ($cn === 'plat_type') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'select', 'label' => $vc['label'], 'options' => ['Наличные', 'Безнал.', 'Карта', 'Прочее']];
        } else {
          $isLookup = !empty($vc['param']);
          $inlineFields[$cn] = ['dbField' => $isLookup ? $vc['param'] : $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
          if ($isLookup) $valCases .= "    case " . json_encode($cn, JSON_UNESCAPED_UNICODE) . ": if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
      }
    ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
    validate: function (field, value) { switch (field) { <?= $valCases ?> } return null; },
    onOpenForm: function(url) { if (window.__openFormModal) window.__openFormModal(url); },
    getLookupData: function (field) {
      switch (field) { case 'client': return __clientLookupData; case 'zat': return __zatLookupData; case 'sotr': return __sotrLookupData; }
      return [];
    }
});
}

var __clientLookupData = <?= json_encode($clientFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
var __zatLookupData = <?= json_encode($zatFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
var __sotrLookupData = <?= json_encode($sotrFilterOptions, JSON_UNESCAPED_UNICODE) ?>;

ColumnResize.init({ saveUrl: 'plat_column_width_save.php', tbl: 'plat' });
ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'plat.php' });
ColumnFilter.init({ thSelector: '.col-zat', pageUrl: 'plat.php' });
ColumnFilter.init({ thSelector: '.col-sotr', pageUrl: 'plat.php' });
ColumnFilter.init({ thSelector: '.col-plat_type', pageUrl: 'plat.php', param: 'plat_type' });
ColumnFilter.init({ thSelector: '.col-out_flag', pageUrl: 'plat.php', param: 'out_type' });

(function() {
  var cp = new URLSearchParams(window.location.search).get('client_id');
  if (cp) {
    var addBtn = document.querySelector('[data-form-open*="plat_form.php?mode=new"]');
    if (addBtn) addBtn.setAttribute('data-form-open', 'plat_form.php?mode=new&client_id=' + cp);
  }
})();

function initFormLookups() {
  var modalBody = document.getElementById('formModalBody');
  if (!modalBody) return;
  var formEl = modalBody.querySelector('form');
  var isDeleteMode = formEl && formEl.classList.contains('form--delete');
  modalBody.querySelectorAll('[data-lookup]').forEach(function (root) {
    if (root.dataset.lookupInited) return;
    var rawJson = root.getAttribute('data-countries');
    if (!rawJson) return;
    var data;
    try { data = JSON.parse(rawJson); } catch (e) { return; }
    if (!Array.isArray(data)) return;
    if (isDeleteMode) return;
    var nameInputId = root.getAttribute('data-name-input');
    var nameInput = nameInputId ? modalBody.querySelector('#' + nameInputId) : null;
    var isZat = root.getAttribute('data-lookup') === 'zat';
    var handler = nameInput ? function(id, name) {
      if (nameInput) nameInput.value = name;
      if (isZat) setFormOutFlagFromZat(id, data);
    } : null;
    try { bindLookup({ root: root, data: data, readonly: false, onSelect: handler }); root.dataset.lookupInited = '1'; } catch (e) {}
    if (isZat) setFormOutFlagFromZat(getCurrentZatId(root), data);
  });
  initFormSumCalc();
}

function getCurrentZatId(root) {
  var inputId = root.querySelector('.lookup-id');
  return inputId ? parseInt(inputId.value, 10) : 0;
}

function setFormOutFlagFromZat(id, zatList) {
  var z = zatList.find(function (z) { return z.id === id; });
  var outFlagEl = document.getElementById('formModalBody').querySelector('#plat-out-flag');
  if (!z || !outFlagEl) return;
  outFlagEl.value = z.out_flag === 1 ? 'Расход' : 'Приход';
}

function initFormSumCalc() {
  var modalBody = document.getElementById('formModalBody');
  var sumInEl = modalBody.querySelector('#plat-sum-in');
  var sumOutEl = modalBody.querySelector('#plat-sum-out');
  if (!sumInEl || !sumOutEl) return;
  if (!sumInEl.readOnly) { sumInEl.addEventListener('input', updateFormSum); sumOutEl.addEventListener('input', updateFormSum); }
  updateFormSum();
}

function updateFormSum() {
  var modalBody = document.getElementById('formModalBody');
  var sumInEl = modalBody.querySelector('#plat-sum-in');
  var sumOutEl = modalBody.querySelector('#plat-sum-out');
  var sumEl = modalBody.querySelector('#plat-sum');
  if (!sumInEl || !sumOutEl || !sumEl) return;
  var si = parseFloat(sumInEl.value.replace(',', '.')) || 0;
  var so = parseFloat(sumOutEl.value.replace(',', '.')) || 0;
  sumEl.value = (si - so).toFixed(2);
}
</script>
</body>
</html>
