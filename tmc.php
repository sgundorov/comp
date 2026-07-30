<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/tmc_columns.php';
require_once __DIR__ . '/config/tmc_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Product');

$TBL = 'product';
$PAGE_URL = 'tmc.php';
$serviceMode = (string)($_GET['type'] ?? 'product');
if (!in_array($serviceMode, ['product', 'service'], true)) $serviceMode = 'product';
$isService = ($serviceMode === 'service');
$PAGE_TITLE = $isService ? 'Услуги' : 'Товары';
$PAGE_TITLE_FORM = $isService ? 'Услуга' : 'Товар';
$FORM_PREFIX = 'tmc_form';

ensure_marks_table($conn);

$categList = [];
$stmt = @$conn->prepare("SELECT categ_id, categ AS name FROM categ ORDER BY categ");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $categList[] = ['id' => (int)$r['categ_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$groupList = [];
$stmt = @$conn->prepare("SELECT group_id, name FROM `group` ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $groupList[] = ['id' => (int)$r['group_id'], 'name' => (string)$r['name']]; $stmt->close(); }

$sgroupList = [];
$stmt = @$conn->prepare("SELECT sgroup_id, group_id, name FROM sgroup ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) $sgroupList[] = ['id' => (int)$r['sgroup_id'], 'group_id' => (int)$r['group_id'], 'name' => (string)$r['name']]; $stmt->close(); }

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

$tp->appendWhere("p.service_flag = ?", [$isService ? '1' : '0'], 's');

if (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') {
    $tp->appendWhereRaw("(p.hide_flag = 0 OR p.hide_flag IS NULL)");
}

$filterDefs = [
    'categ_id'   => ['col_expr' => 'p.categ_id',   'param' => 'categ_id',   'label' => 'Категория'],
    'group_id'   => ['col_expr' => 'p.group_id',   'param' => 'group_id',   'label' => 'Группа'],
    'sgroup_id'  => ['col_expr' => 'p.sgroup_id',  'param' => 'sgroup_id',  'label' => 'Подгруппа'],
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
        $table = $key === 'categ_id' ? 'categ' : ($key === 'izgot_id' ? 'izgot' : substr($key, 0, -3));
        $nameCol = $key === 'categ_id' ? 'categ' : ($key === 'izgot_id' ? 'izgot' : 'name');
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

    .toolbar-separator { display: inline-block; width: 1px; height: 24px; background: var(--line); margin: 0 6px; vertical-align: middle; }
    .toolbar-radio { display: inline-flex; align-items: center; gap: 4px; color: #ccc; font-size: 13px; cursor: pointer; padding: 0 4px; vertical-align: middle; user-select: none; }
    .toolbar-radio input[type="radio"] { margin: 0; cursor: pointer; }
    .toolbar-radio:hover { color: #fff; }

  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">

    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/<?= $isService ? 'uslug' : 'tmc' ?>.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $activeFilters, $serviceMode) {
          $qs = ['page' => $p];
          if ($serviceMode !== 'product') $qs['type'] = $serviceMode;
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
          'type'       => $isService ? 'service' : null,
          'categ_id'   => $categ_id['raw'] !== '' ? $categ_id['raw'] : null,
          'group_id'   => $group_id['raw'] !== '' ? $group_id['raw'] : null,
          'sgroup_id'  => $sgroup_id['raw'] !== '' ? $sgroup_id['raw'] : null,
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
    $serviceRadioHtml = '<span class="toolbar-separator"></span>'
        . '<label class="toolbar-radio"><input type="radio" name="service_mode" value="product"' . (!$isService ? ' checked' : '') . ' /> Товары</label>'
        . '<label class="toolbar-radio"><input type="radio" name="service_mode" value="service"' . ($isService ? ' checked' : '') . ' /> Услуги</label>';
    render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml, '', $serviceRadioHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'tmc.php');
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '300px', 'code' => '120px', 'article' => '80px', 'categ' => '150px', 'group' => '150px', 'sgroup' => '150px', 'quant' => '80px', 'price_out' => '100px', 'price_in' => '100px', 'note' => '400px']); ?>
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
                case 'code':      $raw = (string)($r['code'] ?? ''); return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'article':   $raw = (string)$r['article']; return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'categ':     $rawId = (string)(int)($r['categ_id'] ?? 0); $name = (string)($r['categ_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'group':     $rawId = (string)(int)($r['group_id'] ?? 0); $name = (string)($r['group_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'sgroup':    $rawId = (string)(int)($r['sgroup_id'] ?? 0); $name = (string)($r['sgroup_name'] ?? ''); $disp = ($name !== '' && $name !== '0') ? $name : ''; return [$rawId, $doHilight ? hilight($disp, $s, $searchCond) : $disp];
                case 'quant':     $v = (float)($r['quant'] ?? 0); $raw = $v == (int)$v ? (string)(int)$v : number_format($v, 3, '.', ''); $disp = ($v == 0) ? '' : ($v < 0 ? '<span style="color:#e57373">' . $raw . '</span>' : $raw); return [$raw, $disp];
                case 'price_in':  $v = (float)($r['price_in'] ?? 0); $raw = number_format($v, 2, '.', ''); $disp = ($v == 0) ? '' : $raw; return [$raw, $disp];
                case 'price_out': $v = (float)($r['price_out'] ?? 0); $raw = number_format($v, 2, '.', ''); $disp = ($v == 0) ? '' : $raw; return [$raw, $disp];
                case 'note':      $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/access.js', 'assets/lookup.js', 'assets/export-modal.js', 'assets/column-filter.js']]);
?>
  <script>
    window.__serviceMode = <?= json_encode($serviceMode) ?>;
    window.__categList = <?= json_encode($categList, JSON_UNESCAPED_UNICODE) ?>;
    window.__groupList = <?= json_encode($groupList, JSON_UNESCAPED_UNICODE) ?>;
    window.__sgroupList = <?= json_encode($sgroupList, JSON_UNESCAPED_UNICODE) ?>;
    window.__izgotList = <?= json_encode($izgotList, JSON_UNESCAPED_UNICODE) ?>;
    window.__unitList = <?= json_encode($unitList, JSON_UNESCAPED_UNICODE) ?>;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 300, 'code' => 120, 'article' => 80, 'categ' => 150, 'group' => 150, 'sgroup' => 150, 'quant' => 80, 'price_out' => 100, 'price_in' => 100, 'note' => 400], JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    (function () {
      SelectionToolbar.initTableSelection('tmc.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

      SelectionToolbar.init({
        pageUrl: 'tmc.php',
        search: document.querySelector('.toolbar').getAttribute('data-search') || '',
        getExportUrl: function () {
          return null;
        },
        getPrintUrl: function () {
          return null;
        }
      });

      var modeRadios = document.querySelectorAll('input[name="service_mode"]');
      modeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
          var p = new URLSearchParams(location.search);
          p.set('type', radio.value);
          p.delete('page');
          location.href = 'tmc.php?' + p.toString();
        });
      });

      ColumnFilter.init({ thSelector: '.col-categ', pageUrl: 'tmc.php' });
      ColumnFilter.init({ thSelector: '.col-group', pageUrl: 'tmc.php' });
      ColumnFilter.init({ thSelector: '.col-sgroup', pageUrl: 'tmc.php' });

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: [
          { key: 'id',           label: 'ID' },
          { key: 'name',         label: 'Наименование' },
          { key: 'code',         label: 'Штрихкод' },
          { key: 'article',      label: 'Артикул' },
          { key: 'categ',        label: 'Категория' },
          { key: 'group',        label: 'Группа' },
          { key: 'sgroup',       label: 'Подгруппа' },
          { key: 'quant',        label: 'Количество' },
          { key: 'price_in',     label: 'Закупочная цена' },
          { key: 'price_out',    label: 'Розничная цена' },
          { key: 'note',         label: 'Примечание' },
          { key: 'description',  label: 'Описание' },
          { key: 'hide_flag',    label: 'Не показывать' },
          { key: 'noquant_flag', label: 'Без количества' },
        ],
        closeAllPanels: closeAllPanels,
        pageUrl: 'tmc.php',
        preserveParams: ['categ_id', 'group_id', 'sgroup_id', 'sort', 'type'],
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: [
          { key: 'id',       label: 'ID' },
          { key: 'name',     label: 'Наименование' },
          { key: 'code',     label: 'Штрихкод' },
          { key: 'article',  label: 'Артикул' },
          { key: 'categ',    label: 'Категория' },
          { key: 'group',    label: 'Группа' },
          { key: 'sgroup',   label: 'Подгруппа' },
          { key: 'quant',    label: 'Количество' },
          { key: 'price_out',label: 'Розничная цена' },
          { key: 'price_in', label: 'Закупочная цена' },
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
        addBtn.dataset.formOpen = 'tmc_form.php?mode=new&type=<?= h($serviceMode) ?>';
      }
    })();

    ExportModal.init();
</script>
<?php render_form_modal_script([
    'form_prefix' => $FORM_PREFIX,
    'base_url' => $PAGE_URL,
    'lookup_tables' => ['categ', 'group', 'sgroup', 'country', 'izgot', 'unit'],
    'extra_open' => '(function(){
      var groupRoot = document.querySelector(\'[data-lookup="group"]\');
      var sgroupRoot = document.querySelector(\'[data-lookup="sgroup"]\');
      if (!groupRoot || !sgroupRoot) return;
      var allSgroups = ' . json_encode($sgroupList, JSON_UNESCAPED_UNICODE) . ';
      function getFiltered() {
        var gi = groupRoot.querySelector("input[data-lookup-id]");
        var gid = gi ? parseInt(gi.value, 10) : 0;
        return gid > 0 ? allSgroups.filter(function(s){return s.group_id===gid;}) : allSgroups;
      }
      function syncApi() {
        var f = getFiltered();
        sgroupRoot.setAttribute("data-countries", JSON.stringify(f));
        if (sgroupRoot.__lookupApi) sgroupRoot.__lookupApi.updateData(f);
      }
      if (groupRoot.__lookupApi) {
        var origChoose = groupRoot.__lookupApi.choose;
        groupRoot.__lookupApi.choose = function(id,name) { origChoose.call(groupRoot.__lookupApi,id,name); syncApi(); };
      }
      var sgroupBtn = sgroupRoot.querySelector("[data-lookup-open]");
      if (sgroupBtn) sgroupBtn.addEventListener("mousedown", function(){ syncApi(); }, true);
      syncApi();
    })();',
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
            window.__openFormModal('tmc_form.php?mode=edit&id=' + id + '&type=<?= h($serviceMode) ?>');
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof window.__openFormModal === 'function') {
          window.__openFormModal('tmc_form.php?mode=' + mode + '&id=' + id + '&type=<?= h($serviceMode) ?>');
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
        tableWrapEl: tableWrapEl,
        accessFlags: window.__accessFlags,
        formExtraParams: '&type=<?= h($serviceMode) ?>'
      });
    })();

    (function () {
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;

      if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
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
          var map = { categ: window.__categList, group: window.__groupList, sgroup: window.__sgroupList };
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
    })();

    ColumnResize.init({ saveUrl: 'tmc_column_width_save.php', tbl: 'product' });
  </script>
<?php render_page_footer(); ?>
