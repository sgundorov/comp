<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/store_columns.php';
require_once __DIR__ . '/config/store_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Store');

$TBL = 'store';

ensure_marks_table($conn);

$tp = new TablePage($conn, $storePageConfig);

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

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$tp->buildFilters();
$filters = $tp->filters;

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['store_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start('Участки'); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

    .data-table tbody tr:hover td.col-name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-name { background: #3a5a8a; }

    .data-table thead th[data-sort-col]:hover { background: #3d5468; }
  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">

    <?php $activeMenu = 'store.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/store.png" alt="" /> Участок</h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'store.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = http_build_query(array_filter([
          'q'    => $searchActive && $search !== '' ? $search : null,
          'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond' => $searchActive ? $searchCond : null,
          'sf'   => $searchActive ? '1' : null,
          'sort' => $sortQs !== '' ? $sortQs : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => 'Участки.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => 'Участки.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'store_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('store', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('store_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'store.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '200px', 'address' => '250px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'store_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':      $raw = (string)(int)$r['store_id']; return [$raw, $raw];
                case 'name':    $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'address': $raw = (string)$r['address']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 200, 'address' => 250, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    (function () {
      SelectionToolbar.initTableSelection('store.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

      SelectionToolbar.init({
        pageUrl: 'store.php',
        search: document.querySelector('.toolbar').getAttribute('data-search') || '',
        getExportUrl: function () {
          var sp = new URLSearchParams(location.search);
          ['action', 'page'].forEach(function (k) { sp.delete(k); });
          return 'store_export.php?format=csv&selected=1&' + sp.toString();
        },
        getPrintUrl: function () {
          var sp = new URLSearchParams(location.search);
          ['action', 'page'].forEach(function (k) { sp.delete(k); });
          return 'store_print.php?selected=1&' + sp.toString();
        }
      });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',      label: 'ID' },
          { key: 'name',    label: 'Участок' },
          { key: 'address', label: 'Адрес' },
          { key: 'note',    label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'store.php',
        preserveParams: ['sort'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',      label: 'ID' },
          { key: 'name',    label: 'Участок' },
          { key: 'address', label: 'Адрес' },
          { key: 'note',    label: 'Примечание' },
        ],
        pageUrl: 'store.php',
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
      'form_prefix'   => 'store_form',
      'base_url'      => 'store.php',
      'lookup_tables' => [],
    ]); ?>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'store_columns_save.php',
      tbl: 'store',
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
            window.__openFormModal('store_form.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('store_form.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'store.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'store_form',
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
        saveUrl: 'store_field_save.php',
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

    ColumnResize.init({ saveUrl: 'store_column_width_save.php', tbl: 'store' });
  </script>
<?php render_page_footer();