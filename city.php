<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$TBL = 'city';



ensure_marks_table($conn);

$allCountries = [];
$res = @$conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($res) while ($r = $res->fetch_assoc()) {
    $allCountries[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['country']];
}

$tp = new TablePage($conn, $cityPageConfig);

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
$countryFilter= $tp->countryFilter;
$countryIds   = $tp->countryIds;
$COLUMN_DEFAULTS = $tp->columns;
$COL_META        = $tp->colMeta;
$columnWidths    = load_columns_widths($conn, $TBL);
$urlCols = $searchCols;

require_once __DIR__ . '/lib/marks-actions.php';
handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if ($col === 'country') {
            $stmt = @$conn->prepare("SELECT country_id, country FROM country ORDER BY country");
            $out = [];
            if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id'=>(int)$r['country_id'],'name'=>(string)$r['country']]; $stmt->close(); }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([]);
        }
        exit;
    }
    return null;
});

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$tp->loadCountryNames($conn, 'country', 'country', 'country_id');
$countryNames = $tp->countryNames;

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$tp->buildFilters();
$filters = $tp->filters;

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['city_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
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

    <?php $activeMenu = 'city.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/city.png" alt="" /> Город</h1>

    <?php
      $baseQs = function($p) use ($search, $countryFilter, $searchActive, $searchCols, $searchCond, $sortQs) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($countryFilter !== '') $qs['country_id'] = $countryFilter;
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'city.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs);

      $exportQs = http_build_query(array_filter([
          'q'         => $searchActive && $search !== '' ? $search : null,
          'cols'      => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond'      => $searchActive ? $searchCond : null,
          'sf'        => $searchActive ? '1' : null,
          'country_id'=> $countryFilter !== '' ? $countryFilter : null,
          'sort'      => $sortQs !== '' ? $sortQs : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => 'Города.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => 'Города.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'city_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml =
          '<a class="dropdown-item" href="city_print.php' . $printQs . '" target="_blank">Печать страницы</a>' .
          '<a class="dropdown-item" href="city_print.php?all=1' . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Печать всего</a>' .
          '<a class="dropdown-item" href="city_print.php?page=' . (int)$page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Печать текущей</a>';
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
    render_toolbar_left('city_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'city.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'city' => '200px', 'country' => '160px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($countryIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'country') {
                    $param = $cm['param'] ?? ($cn . '_id');
                    return ' data-col="' . h($cn) . '" data-param="' . h($param) . '" data-values="' . h(implode(',', $countryIds)) . '"';
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
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'city_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':      $raw = (string)(int)$r['city_id']; return [$raw, $raw];
                case 'city':    $raw = (string)$r['city']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'country': $rawId = (string)(int)($r['country_id'] ?? 0); $name = (string)($r['country_name'] ?? $rawId); return [$rawId, $doHilight ? hilight($name, $search, $searchCond) : $name];
                case 'note':    $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
    window.__countries = <?= json_encode($allCountries, JSON_UNESCAPED_UNICODE) ?>;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'city' => 200, 'country' => 160, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
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
        location.href = 'city.php?action=toggleSelectAll' + (search ? '&q=' + encodeURIComponent(search) : '');
      });

      rowChecks.forEach(cb => cb.addEventListener('change', function () {
        const id  = parseInt(cb.value, 10);
        const to  = cb.checked;
        cb.disabled = true;
        fetch('city.php?action=toggleSelect', {
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
        pageUrl: 'city.php',
        search: search,
        getExportUrl: function () {
          if (markedSet.size === 0) return null;
          return 'city_export.php?format=csv&all=1';
        },
        getPrintUrl: function () {
          if (markedSet.size === 0) return null;
          return 'city_print.php?all=1';
        }
      });

      ColumnFilter.init({ thSelector: '.col-country' });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',      label: 'ID' },
          { key: 'city',    label: 'Город' },
          { key: 'country', label: 'Страна' },
          { key: 'note',    label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'city.php',
        preserveParams: ['country_id', 'sort'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',      label: 'ID' },
          { key: 'city',    label: 'Город' },
          { key: 'country', label: 'Страна' },
          { key: 'note',    label: 'Примечание' },
        ],
        pageUrl: 'city.php',
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
      'form_prefix'   => 'city_form',
      'base_url'      => 'city.php',
      'lookup_tables' => ['country'],
    ]); ?>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
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
            window.__openFormModal('city_form.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('city_form.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'city.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'city_form',
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
            $label = json_encode($vc['label'], JSON_UNESCAPED_UNICODE);
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            if ($isLookup) {
              $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
          }
        ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
        getLookupData: function () { return window.__countries || []; },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: window.__openFormModal
      });
    })();

    ColumnResize.init({ saveUrl: 'city_column_width_save.php', tbl: 'city' });
  </script>
<?php render_page_footer();
