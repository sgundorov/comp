<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/firm_columns.php';
require_once __DIR__ . '/config/firm_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

ensure_marks_table($conn);

$config = $firmPageConfig;
$tp = new TablePage($conn, $config);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'firm');

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
handle_marks_actions($conn, $tp, 'firm', function($action) use ($conn) {
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
    $ok = save_columns_config($conn, 'firm', $clean);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- CITY FILTER (manual, like country filter in izgot) -----
$cityFilter = (string)($_GET['city_id'] ?? '');
$cityFilterIds = [];
$cityFilterNames = [];

if ($cityFilter !== '') {
    $cityFilterIds = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
}

if (count($cityFilterIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($cityFilterIds), '?'));
    $stmt = @$conn->prepare("SELECT city_id, city FROM city WHERE city_id IN ($placeholders) ORDER BY city");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($cityFilterIds)), $cityFilterIds);
        $stmt->execute();
        $cr = $stmt->get_result();
        while ($row = $cr->fetch_assoc()) {
            $cityFilterNames[(int)$row['city_id']] = (string)$row['city'];
        }
        $stmt->close();
    }
    $tp->appendWhere("f.city_id IN ($placeholders)", $cityFilterIds, str_repeat('i', count($cityFilterIds)));
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['firm_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$cityList = [];
$cityCountryMap = [];
$countryById = [];
$crs = $conn->query("SELECT city_id, city, country_id FROM city ORDER BY city");
if ($crs) while ($cr = $crs->fetch_assoc()) {
    $cityList[] = ['id' => (int)$cr['city_id'], 'name' => (string)$cr['city']];
    if ((int)($cr['country_id'] ?? 0) > 0) {
        $cityCountryMap[(int)$cr['city_id']] = ['country_id' => (int)$cr['country_id']];
    }
}
$crs2 = $conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($crs2) while ($cr = $crs2->fetch_assoc()) $countryById[(int)$cr['country_id']] = (string)$cr['country'];

$urlCols = $searchCols;

$clearQs = function($drop) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
    $drop = is_array($drop) ? $drop : [$drop];
    $qs = [];
    if (!in_array('q', $drop, true) && $searchActive && $search !== '') $qs['q'] = $search;
    if (!in_array('cols', $drop, true) && $searchActive && count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
    if (!in_array('cond', $drop, true) && $searchActive) $qs['cond'] = $searchCond;
    if (!in_array('sf', $drop, true) && $searchActive) $qs['sf'] = '1';
    if (!in_array('sort', $drop, true) && $sortQs !== '') $qs['sort'] = $sortQs;
    return 'firm.php' . ($qs ? '?' . http_build_query($qs) : '');
};

$tp->buildFilters();
$filters = $tp->filters;
if (count($cityFilterIds) > 0) {
    $names = [];
    foreach ($cityFilterIds as $cid) {
        $names[] = $cityFilterNames[$cid] ?? ('#' . $cid);
    }
    $filters[] = ['kind' => 'city', 'text' => 'Город = ' . implode(', ', $names), 'clear' => 'city_id'];
}

$exportQs = http_build_query(array_filter([
    'q'    => $searchActive && $search !== '' ? $search : null,
    'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
    'cond' => $searchActive ? $searchCond : null,
    'sf'   => $searchActive ? '1' : null,
    'sort' => $sortQs !== '' ? $sortQs : null,
], function ($v) { return $v !== null && $v !== ''; }));
?><?php
render_head_start('Фирмы');
?>
  <style>
    .data-table tbody tr:hover td.col-name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-name { background: #3a5a8a; }

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
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'firm.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/firm.png" alt="" /> Фирма</h1>

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
          return 'firm.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);
    ?>

    <?php
      $exportDropdownHtml = '';
      foreach (['csv' => 'Фирмы.csv', 'xls' => 'Фирмы.xls'] as $fmt => $filename) {
          $fullUrl = 'firm_export.php?format=' . $fmt . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($fmt === 'csv' ? 'CSV' : 'XLS (Excel)') . '">' . ($fmt === 'csv' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }
      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('firm', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('firm_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'firm.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '200px', 'address' => '250px', 'phone' => '130px', 'email' => '180px', 'city' => '150px', 'director' => '150px', 'note' => '300px']); ?>
<?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
    'thAttrsCallback' => function($cn, $cm) use ($cityFilterIds) {
        if ($cm && !empty($cm['filter']) && $cn === 'city') {
            $param = $cm['param'] ?? ($cn . '_id');
            return ' data-col="' . h($cn) . '" data-param="' . h($param) . '" data-values="' . h(implode(',', $cityFilterIds)) . '"';
        }
        return '';
    },
    'thHtmlCallback' => function($cn, $cm) {
        if ($cm && !empty($cm['filter']) && $cn === 'city') {
            return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
        }
        return '';
    },
]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'firm_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':       $raw = (string)(int)$r['firm_id']; return [$raw, $raw];
                case 'name':     $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'address':  $raw = (string)$r['address']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'phone':    $raw = (string)$r['phone']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'email':    $raw = (string)$r['email']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'city':     $raw = (string)$r['city']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'director': $raw = (string)$r['director']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note':     $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/column-filter.js']]);
?>
  <script>
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

    SelectionToolbar.initTableSelection('firm.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

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
      pageUrl: 'firm.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'firm.php?action=invertSelection&' + other.toString();
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
      pageUrl: 'firm.php',
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
        location.href = 'firm.php?' + params.toString();
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
        location.href = 'firm.php?' + params.toString();
      },
      onSubmit: function () {
        searchToggleBtn.click();
      }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'firm.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'firm_columns_save.php',
      tbl: 'firm',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 200, 'address' => 250, 'phone' => 130, 'email' => 180, 'city' => 150, 'director' => 150, 'note' => 300], JSON_UNESCAPED_UNICODE) ?>;
    window.__cityList = <?= json_encode($cityList, JSON_UNESCAPED_UNICODE) ?>;
    window.__cityCountryMap = <?= json_encode($cityCountryMap, JSON_NUMERIC_CHECK) ?>;
    window.__countryById = <?= json_encode($countryById, JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('click', function (e) {
      let a = e.target.closest('a[href*="firm_form.php"]');
      if (a) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        window.__openFormModal(a.getAttribute('href'));
        return;
      }
      const trig = e.target.closest('[data-form-open]');
      if (trig) {
        e.preventDefault();
        window.__openFormModal(trig.getAttribute('data-form-open'));
        return;
      }
    });

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input.row-check')) return;
        selectRow(id);
      });
      tr.addEventListener('dblclick', function () {
        window.__openFormModal('firm_form.php?mode=edit&id=' + id);
      });
    });

    rowOpenBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('firm_form.php?mode=edit&id=' + id);
    });
    rowCopyBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('firm_form.php?mode=copy&id=' + id);
    });
    rowDeleteBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('firm_form.php?mode=delete&id=' + id);
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

    ColumnFilter.init({ thSelector: '.col-city', pageUrl: 'firm.php' });

    bindTableKeyboardShortcuts({
      formPrefix: 'firm_form',
      rowSel: rowSel,
      currentPage: currentPage,
      currentPages: currentPages,
      navigate: navigate,
      tableWrapEl: tableWrapEl
    });

    __refreshSelectionUI();
  </script>
    <?php render_form_modal_script([
      'form_prefix'   => 'firm_form',
      'base_url'      => 'firm.php',
      'lookup_tables' => [],
    ]); ?>
  <script>
    InlineEdit.init({
      tbody: document.querySelector('table tbody'),
      saveUrl: 'firm_field_save.php',
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
      getLookupData: function (field) {
        var map = { city: window.__cityList };
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

    ColumnResize.init({ saveUrl: 'firm_column_width_save.php', tbl: 'firm' });
    ExportModal.init();
  </script>
<?php render_page_footer();
