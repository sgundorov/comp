<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$TBL = 'product';
$PAGE_URL = 'tmc.php';
$PAGE_TITLE = 'Товары';
$PAGE_TITLE_FORM = 'Товар';
$FORM_PREFIX = 'tmc_form';

ensure_marks_table($conn);

$categList = [];
$stmt = @$conn->prepare("SELECT categ_id, categ AS name FROM categ ORDER BY categ");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $categList[] = ['id' => (int)$r['categ_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$groupList = [];
$stmt = @$conn->prepare("SELECT group_id, name FROM `group` ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $groupList[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$sgroupList = [];
$stmt = @$conn->prepare("SELECT sgroup_id, name FROM sgroup ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $sgroupList[] = ['id' => (int)$r['sgroup_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$countryList = [];
$stmt = @$conn->prepare("SELECT country_id, country AS name FROM country ORDER BY country");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $countryList[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$izgotList = [];
$stmt = @$conn->prepare("SELECT izgot_id, izgot AS name FROM izgot ORDER BY izgot");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $izgotList[] = ['id' => (int)$r['izgot_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$unitList = [];
$stmt = @$conn->prepare("SELECT unit_id, unit AS name FROM unit ORDER BY unit");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $unitList[] = ['id' => (int)$r['unit_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$tp = new TablePage($conn, $tmcPageConfig);

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

$tp->appendWhere("p.service_flag = ?", ['0'], 's');

$filterDefs = [
    'categ_id'   => ['col_expr' => 'p.categ_id',   'param' => 'categ_id',   'label' => 'Категория'],
    'group_id'   => ['col_expr' => 'p.group_id',   'param' => 'group_id',   'label' => 'Группа'],
    'sgroup_id'  => ['col_expr' => 'p.sgroup_id',  'param' => 'sgroup_id',  'label' => 'Подгруппа'],
    'country_id' => ['col_expr' => 'p.country_id',  'param' => 'country_id', 'label' => 'Страна'],
];

$activeFilters = [];
foreach ($filterDefs as $key => $fd) {
    $raw = (string)($_GET[$fd['param']] ?? '');
    $ids = [];
    if ($raw !== '') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
    }
    $activeFilters[$key] = ['raw' => $raw, 'ids' => $ids];
    if (count($ids) > 0) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $tp->appendWhere($fd['col_expr'] . " IN ($place)", $ids, str_repeat('i', count($ids)));
    }
}
extract($activeFilters);

handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        $out = [];
        if ($col === 'categ') {
            $stmt = @$conn->prepare("SELECT categ_id, categ AS name FROM categ ORDER BY categ");
            if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id' => (int)$r['categ_id'], 'name' => (string)$r['name']]; $stmt->close(); }
        } elseif ($col === 'group') {
            $stmt = @$conn->prepare("SELECT group_id, name FROM `group` ORDER BY name");
            if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']]; $stmt->close(); }
        } elseif ($col === 'sgroup') {
            $stmt = @$conn->prepare("SELECT sgroup_id, name FROM sgroup ORDER BY name");
            if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id' => (int)$r['sgroup_id'], 'name' => (string)$r['name']]; $stmt->close(); }
        } elseif ($col === 'country') {
            $stmt = @$conn->prepare("SELECT country_id, country AS name FROM country ORDER BY country");
            if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $out[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['name']]; $stmt->close(); }
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    return null;
});

$where  = $tp->where;
$params = $tp->params;
$types  = $tp->types;
$whereSql = $tp->whereSql();

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$filterNames = [];
foreach ($filterDefs as $key => $fd) {
    $raw = $activeFilters[$key]['raw'];
    $ids = $activeFilters[$key]['ids'];
    $names = [];
    if (count($ids) > 0) {
        $table = $key === 'categ_id' ? 'categ' : ($key === 'country_id' ? 'country' : ($key === 'izgot_id' ? 'izgot' : substr($key, 0, -3)));
        $nameCol = $key === 'categ_id' ? 'categ' : ($key === 'country_id' ? 'country' : ($key === 'izgot_id' ? 'izgot' : 'name'));
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = @$conn->prepare("SELECT $nameCol AS name FROM $table WHERE {$table}_id IN ($place) ORDER BY $nameCol");
        if ($stmt) {
            stmt_bind($stmt, str_repeat('i', count($ids)), $ids);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
            $stmt->close();
        }
    }
    $filterNames[$key] = $names;
}

$tp->buildFilters();
$filters = $tp->filters;

$idColMap = [
    'categ_id'   => 'categ_id',
    'group_id'   => 'group_id',
    'sgroup_id'  => 'sgroup_id',
    'country_id' => 'country_id',
];
foreach ($idColMap as $key => $param) {
    $raw = $activeFilters[$key]['raw'];
    $ids = $activeFilters[$key]['ids'];
    if (count($ids) > 0) {
        $label = $filterDefs[$key]['label'];
        $names = $filterNames[$key];
        $nameStr = [];
        foreach ($ids as $gid) $nameStr[] = $names[array_search($gid, $ids)] ?? ("#$gid");
        $filters[] = ['kind' => $key, 'text' => "$label = " . implode(', ', $nameStr), 'clear' => [$param]];
    }
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['product_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-quant,
    .data-table tbody td.col-price_in,
    .data-table tbody td.col-price_out { text-align: right; }
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .table-wrap { overflow: auto !important; max-height: 70vh; }
    .data-table thead th { position: sticky; top: 0; z-index: 1; background: #1e2a38; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

    .data-table tbody tr:hover td.col-name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-name { background: #3a5a8a; }

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

    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/tmc.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $activeFilters) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          foreach ($activeFilters as $k => $af) { if ($af['raw'] !== '') $qs[$k] = $af['raw']; }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'tmc.php?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = http_build_query(array_filter([
          'q'          => $searchActive && $search !== '' ? $search : null,
          'cols'       => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
          'cond'       => $searchActive ? $searchCond : null,
          'sf'         => $searchActive ? '1' : null,
          'categ_id'   => $categ_id['raw'] !== '' ? $categ_id['raw'] : null,
          'group_id'   => $group_id['raw'] !== '' ? $group_id['raw'] : null,
          'sgroup_id'  => $sgroup_id['raw'] !== '' ? $sgroup_id['raw'] : null,
          'country_id' => $country_id['raw'] !== '' ? $country_id['raw'] : null,
          'sort'       => $sortQs !== '' ? $sortQs : null,
      ], function ($v) { return $v !== null && $v !== ''; }));

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'tmc_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printQs = $exportQs !== '' ? '?' . $exportQs : '';
      $printDropdownHtml = render_print_dropdown_items('tmc', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'tmc.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '300px', 'article' => '80px', 'categ' => '150px', 'group' => '150px', 'sgroup' => '150px', 'country' => '120px', 'quant' => '80px', 'price_in' => '100px', 'price_out' => '100px', 'note' => '400px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($activeFilters) {
                if ($cm && !empty($cm['filter'])) {
                    $param = $cm['param'] ?? ($cn . '_id');
                    $ids = [];
                    if (isset($activeFilters[$param])) $ids = $activeFilters[$param]['ids'];
                    return ' data-col="' . h($cn) . '" data-param="' . h($param) . '" data-values="' . h(implode(',', $ids)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter'])) {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'product_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $s = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':        $raw = (string)(int)$r['product_id']; return [$raw, $raw];
                case 'name':      $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'article':   $raw = (string)$r['article']; return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'categ':     $rawId = (string)(int)($r['categ_id'] ?? 0); $name = (string)($r['categ_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'group':     $rawId = (string)(int)($r['group_id'] ?? 0); $name = (string)($r['group_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'sgroup':    $rawId = (string)(int)($r['sgroup_id'] ?? 0); $name = (string)($r['sgroup_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'country':   $rawId = (string)(int)($r['country_id'] ?? 0); $name = (string)($r['country_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'quant':     $v = (float)($r['quant'] ?? 0); $raw = $v == (int)$v ? (string)(int)$v : number_format($v, 3, '.', ''); $disp = ($v == 0) ? '' : ($v < 0 ? '<span style="color:#e57373">' . $raw . '</span>' : $raw); return [$raw, $disp];
                case 'price_in':  $v = (float)($r['price_in'] ?? 0); $raw = number_format($v, 2, '.', ''); $disp = ($v == 0) ? '' : $raw; return [$raw, $disp];
                case 'price_out': $v = (float)($r['price_out'] ?? 0); $raw = number_format($v, 2, '.', ''); $disp = ($v == 0) ? '' : $raw; return [$raw, $disp];
                case 'note':      $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/lookup.js', 'assets/export-modal.js', 'assets/column-filter.js']]);
?>
  <script>
    window.__categList = <?= json_encode($categList, JSON_UNESCAPED_UNICODE) ?>;
    window.__groupList = <?= json_encode($groupList, JSON_UNESCAPED_UNICODE) ?>;
    window.__sgroupList = <?= json_encode($sgroupList, JSON_UNESCAPED_UNICODE) ?>;
    window.__countryList = <?= json_encode($countryList, JSON_UNESCAPED_UNICODE) ?>;
    window.__izgotList = <?= json_encode($izgotList, JSON_UNESCAPED_UNICODE) ?>;
    window.__unitList = <?= json_encode($unitList, JSON_UNESCAPED_UNICODE) ?>;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 300, 'article' => 80, 'categ' => 150, 'group' => 150, 'sgroup' => 150, 'country' => 120, 'quant' => 80, 'price_in' => 100, 'price_out' => 100, 'note' => 400], JSON_UNESCAPED_UNICODE) ?>;
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
        location.href = 'tmc.php?action=toggleSelectAll' + (search ? '&q=' + encodeURIComponent(search) : '');
      });

      rowChecks.forEach(cb => cb.addEventListener('change', function () {
        const id  = parseInt(cb.value, 10);
        const to  = cb.checked;
        cb.disabled = true;
        fetch('tmc.php?action=toggleSelect', {
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
        pageUrl: 'tmc.php',
        search: search,
        getExportUrl: function () {
          if (markedSet.size === 0) return null;
          return 'tmc_export.php?format=csv&all=1';
        },
        getPrintUrl: function () {
          if (markedSet.size === 0) return null;
          return 'tmc_print.php?all=1';
        }
      });

      ColumnFilter.init({ thSelector: '.col-categ', pageUrl: 'tmc.php' });
      ColumnFilter.init({ thSelector: '.col-group', pageUrl: 'tmc.php' });
      ColumnFilter.init({ thSelector: '.col-sgroup', pageUrl: 'tmc.php' });
      ColumnFilter.init({ thSelector: '.col-country', pageUrl: 'tmc.php' });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',      label: 'ID' },
          { key: 'name',    label: 'Наименование' },
          { key: 'article', label: 'Артикул' },
          { key: 'categ',   label: 'Категория' },
          { key: 'group',   label: 'Группа' },
          { key: 'sgroup',  label: 'Подгруппа' },
          { key: 'country', label: 'Страна' },
          { key: 'note',    label: 'Примечание' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'tmc.php',
        preserveParams: ['categ_id', 'group_id', 'sgroup_id', 'country_id', 'sort'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',       label: 'ID' },
          { key: 'name',     label: 'Наименование' },
          { key: 'article',  label: 'Артикул' },
          { key: 'categ',    label: 'Категория' },
          { key: 'group',    label: 'Группа' },
          { key: 'sgroup',   label: 'Подгруппа' },
          { key: 'country',  label: 'Страна' },
          { key: 'quant',    label: 'Количество' },
          { key: 'price_in', label: 'Закупочная цена' },
          { key: 'price_out',label: 'Розничная цена' },
          { key: 'note',     label: 'Примечание' },
        ],
        pageUrl: 'tmc.php',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });
    })();

    (function () {
      var addBtn = document.querySelector('button[data-form-open="tmc_form.php?mode=new"]');
      if (addBtn) {
        addBtn.dataset.formOpen = 'tmc_form.php?mode=new';
      }
    })();

    ExportModal.init();
</script>
<?php render_form_modal_script([
    'form_prefix' => $FORM_PREFIX,
    'base_url' => $PAGE_URL,
    'lookup_tables' => ['categ', 'group', 'sgroup', 'country', 'izgot', 'unit'],
]); ?>
<script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'tmc_columns_save.php',
      tbl: 'product',
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
            window.__openFormModal('tmc_form.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('tmc_form.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'tmc.php?' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'tmc_form',
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
        saveUrl: 'tmc_field_save.php',
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
          var map = { categ: window.__categList, group: window.__groupList, sgroup: window.__sgroupList, country: window.__countryList };
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
    })();

    ColumnResize.init({ saveUrl: 'tmc_column_width_save.php', tbl: 'product' });
  </script>
<?php render_page_footer(); ?>
