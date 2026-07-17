<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/promo_columns.php';
require_once __DIR__ . '/config/promo_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

ensure_marks_table($conn);

$tp = new TablePage($conn, $promoPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'promo');

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
handle_marks_actions($conn, $tp, 'promo', function($action) use ($conn) {
    if ($action === 'recalc') {
        header('Content-Type: application/json; charset=utf-8');
        $promos = [];
        $pRes = $conn->query("SELECT promo_id FROM promo ORDER BY promo_id");
        if ($pRes) while ($p = $pRes->fetch_assoc()) $promos[] = (int)$p['promo_id'];
        if (empty($promos)) { echo json_encode(['ok' => true, 'message' => 'Нет записей для пересчета']); exit; }
        $firstId = $promos[0];
        $total = 0;
        $counts = array_fill_keys($promos, 0);
        $cRes = $conn->query("SELECT promo_id FROM client");
        if ($cRes) while ($c = $cRes->fetch_assoc()) {
            $pid = (int)($c['promo_id'] ?? 0);
            if ($pid <= 0 || !in_array($pid, $promos, true)) $pid = $firstId;
            $counts[$pid]++;
            $total++;
        }
        $conn->begin_transaction();
        try {
            foreach ($counts as $pid => $cnt) {
                $pct = $total > 0 ? round($cnt / $total * 100, 2) : 0;
                $stmt = $conn->prepare("UPDATE promo SET count = ?, procent = ? WHERE promo_id = ?");
                bind_auto($stmt, [$cnt, $pct, $pid]);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            echo json_encode(['ok' => true, 'message' => 'Пересчет выполнен']);
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['ok' => false, 'error' => 'Ошибка пересчета']);
        }
        exit;
    }
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
    $ok = save_columns_config($conn, 'promo', $clean);
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
foreach ($rows as $r) { if (isset($marks[(int)$r['promo_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Источники рекламы');
?>
  <style>
    .data-table tbody tr:hover td.col-promo { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-promo { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-promo { background: #3a5a8a; }

    .data-table tbody td.col-count,
    .data-table tbody td.col-procent { text-align: right; }
  </style>
<?php
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'promo.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/promo.png" alt="" /> Источник рекламы</h1>

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
          return 'promo.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);
    ?>

    <?php
      $exportDropdownHtml = '<a class="dropdown-item" href="promo_export.php?format=csv' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Источники рекламы.csv">Экспорт в CSV</a>'
        . '<a class="dropdown-item" href="promo_export.php?format=xls' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Источники рекламы.xls">Экспорт в Excel</a>';
      $printDropdownHtml = render_print_dropdown_items('promo', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('promo_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'promo.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['promo_id' => '60px', 'promo' => '200px', 'count' => '80px', 'procent' => '80px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'promo_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'promo_id': $raw = (string)(int)$r['promo_id']; return [$raw, $raw];
                case 'promo':    $raw = (string)($r['promo'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'bdate':    $raw = (string)($r['bdate'] ?? ''); $dt = $raw !== '' ? date('d.m.Y', strtotime($raw)) : ''; return [$raw, $dt];
                case 'edate':    $raw = (string)($r['edate'] ?? ''); $dt = $raw !== '' ? date('d.m.Y', strtotime($raw)) : ''; return [$raw, $dt];
                case 'count':    $v = $r['count'] ?? null; $raw = ($v !== null && $v !== '' && (int)$v !== 0) ? (string)(int)$v : ''; return [$raw, $raw];
                case 'procent':  $v = $r['procent'] ?? null; $raw = ($v !== null && $v !== '' && (float)$v !== 0.0) ? (string)(float)$v : ''; return [$raw, $raw !== '' ? $raw . '%' : ''];
                case 'note':     $raw = (string)($r['note'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'checkboxCallback' => function($rid) use ($marks) {
                return '<input type="checkbox" class="row-check" data-id="' . $rid . '"' . (isset($marks[$rid]) ? ' checked' : '') . ' />';
            },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes();
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

    SelectionToolbar.initTableSelection('promo.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

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
      pageUrl: 'promo.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'promo.php?action=invertSelection&' + other.toString();
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
      pageUrl: 'promo.php',
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
        location.href = 'promo.php?' + params.toString();
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
        location.href = 'promo.php?' + params.toString();
      },
      onSubmit: function () {
        searchToggleBtn.click();
      }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'promo.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'promo_columns_save.php',
      tbl: 'promo',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['promo_id' => 46, 'promo' => 200, 'count' => 60, 'procent' => 60, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input.row-check')) return;
        selectRow(id);
      });
      tr.addEventListener('dblclick', function () {
        window.__openFormModal('promo_form.php?mode=edit&id=' + id);
      });
    });

    rowOpenBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('promo_form.php?mode=edit&id=' + id);
    });
    rowCopyBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('promo_form.php?mode=copy&id=' + id);
    });
    rowDeleteBtn.addEventListener('click', function () {
      const id = rowSel.getSelectedId();
      if (id !== 0) window.__openFormModal('promo_form.php?mode=delete&id=' + id);
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
      formPrefix: 'promo_form',
      rowSel: rowSel,
      currentPage: currentPage,
      currentPages: currentPages,
      navigate: navigate,
      tableWrapEl: tableWrapEl
    });

    __refreshSelectionUI();

    var recalcBtn = document.createElement('button');
    recalcBtn.className = 'menu-btn toolbar-sep-left';
    recalcBtn.title = 'Пересчет';
    recalcBtn.innerHTML = '<img src="img/calc.png" alt="" /><span>Пересчет</span>';
    recalcBtn.addEventListener('click', function () {
      if (!confirm('Пересчитать количество и процент для всех видов рекламы?')) return;
      recalcBtn.disabled = true;
      fetch('promo.php?action=recalc', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (d) {
        recalcBtn.disabled = false;
        if (d.ok) { location.reload(); } else { alert(d.error || 'Ошибка'); }
      }).catch(function () { recalcBtn.disabled = false; alert('Ошибка сети'); });
    });
    var printDropdown = document.querySelector('.toolbar-left .menu-btn:last-child');
    if (printDropdown) printDropdown.after(recalcBtn); else document.querySelector('.toolbar-left').appendChild(recalcBtn);
  })();
  </script>
    <?php render_form_modal_script([
      'form_prefix'   => 'promo_form',
      'base_url'      => 'promo.php',
      'lookup_tables' => [],
    ]); ?>
  <script>
  InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'promo_field_save.php',
    fields: <?php
      $inlineFields = [];
      $valCases = '';
      foreach ($visibleColumns as $vc) {
        $cn = $vc['name'];
        if (!empty($vc['readonly']) || $cn === 'promo_id') continue;
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

  ColumnResize.init({ saveUrl: 'promo_column_width_save.php', tbl: 'promo' });
  </script>
<?php render_page_footer();
