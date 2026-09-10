<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/ainv_columns.php';
require_once __DIR__ . '/config/ainv_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$TBL = 'ainv';
$PAGE_TITLE = 'Инвентаризация';
$PAGE_URL   = 'ainv.php';
$FORM_PREFIX = 'ainv_form';

ensure_marks_table($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $tpTemp = new TablePage($conn, $ainvPageConfig);
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($tpTemp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'acceptToggle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT accept_flag FROM ainv WHERE number = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) {
            $newFlag = (int)(!((int)$r['accept_flag']));
            $upd = $conn->prepare("UPDATE ainv SET accept_flag = ? WHERE number = ?");
            $upd->bind_param('ii', $newFlag, $id);
            $upd->execute();
            $upd->close();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

$tp = new TablePage($conn, $ainvPageConfig);

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
$showOnly     = $tp->showOnly;
$marks        = $tp->marks;
$marksCount   = $tp->marksCount;
$COLUMN_DEFAULTS = $tp->columns;
$COL_META        = $tp->colMeta;
$columnWidths    = load_columns_widths($conn, $TBL);
$urlCols = $searchCols;

$mestoIds = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['mesto_id'] ?? ''))), fn($v) => $v > 0));
$firmIds = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['firm_id'] ?? ''))), fn($v) => $v > 0));
$sotrIds = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['sotr_id'] ?? ''))), fn($v) => $v > 0));

$tp->applyFilterWithLabel($conn, 'mesto_id', 'a.mesto_id', 'Место', 'mesto', 'mesto_id', 'name');
$tp->applyFilterWithLabel($conn, 'firm_id', 'a.firm_id', 'Фирма', 'firm', 'firm_id', 'name');
$tp->applyFilterWithLabel($conn, 'sotr_id', 'a.sotr_id', 'Сотрудник', 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))");

handle_marks_actions($conn, $tp, $TBL);

$marks = $tp->marks;
$marksCount = $tp->marksCount;

$clearQs = function ($drop) use ($tp) { return $tp->clearQs((array)$drop); };

$tp->buildFilters();
$filters = $tp->filters;

[$rows, $pagination] = $tp->fetchPage($conn);
$total = $pagination['totalCount'];
$page = $pagination['pageNum'];
$pages = $pagination['pageCount'];

$mestoLookupOptions = [];
$mr = @$conn->query("SELECT mesto_id AS id, name FROM mesto ORDER BY name");
if ($mr) while ($r = $mr->fetch_assoc()) $mestoLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$firmLookupOptions = [];
$fr = @$conn->query("SELECT firm_id AS id, name FROM firm ORDER BY name");
if ($fr) while ($r = $fr->fetch_assoc()) $firmLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$sotrLookupOptions = [];
$so = @$conn->query("SELECT sotr_id AS id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($so) while ($r = $so->fetch_assoc()) $sotrLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$productLookupOptions = [];
$pr = @$conn->query("SELECT product_id AS id, product_name AS name FROM product ORDER BY product_name");
if ($pr) while ($r = $pr->fetch_assoc()) $productLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['number']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }
    <?php if (count($firmLookupOptions) === 1): ?>
    .data-table thead th.col-firm_id,
    .data-table tbody td.col-firm_id { display: none; }
    <?php endif; ?>

    .data-table tbody tr:hover td { background: #2c3a4d; }
    .data-table tbody tr.selected td { background: #3a5a8a !important; }
    .data-table tbody tr.selected:hover td { background: #3a5a8a !important; }

    .data-table thead th[data-sort-col]:hover { background: #3d5468; }

    .form .tab-container { margin-bottom: 16px; width: 100%; }
    .form .tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
    .form .tab-header { padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold; color: var(--muted); border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; user-select: none; }
    .form .tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
    .form .tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
    .form .tab-pane { display: none; width: 100%; }
    .form .tab-pane.active { display: block; width: 100%; }
  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">

    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/ainv.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $PAGE_URL) {
          $qs = ['page' => $p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return $PAGE_URL . '?' . http_build_query($qs);
      };

      $paginationHtml = render_pagination($page, $pages, $baseQs, true);

      $exportQs = $tp->buildExportQs();

      $exportDropdownHtml = '';
      foreach ([
          ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'ainv_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printDropdownHtml = render_print_dropdown_items('ainv', $exportQs, (int)$page, $marksCount > 0);

      $acceptBtnHtml = '<button class="icon-btn" id="acceptBtn" title="Утверждено" type="button"><img src="img/lock.png" alt="" /></button>';
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
    render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml, $acceptBtnHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, $PAGE_URL);
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, array_merge(['accept_flag' => '90px', 'number' => '70px', 'date' => '90px', 'time' => '70px', 'mesto_id' => '150px', 'pos' => '70px', 'sum' => '110px', 'first_card' => '130px', 'last_card' => '150px', 'firm_id' => '150px', 'sotr_id' => '150px', 'note' => '1600px'], count($firmLookupOptions) === 1 ? ['firm_id' => '0px'] : [])); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($mestoIds, $firmIds, $sotrIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'mesto_id') {
                    return ' data-col="' . h($cn) . '" data-param="mesto_id" data-values="' . h(implode(',', $mestoIds)) . '"';
                }
                if ($cm && !empty($cm['filter']) && $cn === 'firm_id') {
                    return ' data-col="' . h($cn) . '" data-param="firm_id" data-values="' . h(implode(',', $firmIds)) . '"';
                }
                if ($cm && !empty($cm['filter']) && $cn === 'sotr_id') {
                    return ' data-col="' . h($cn) . '" data-param="sotr_id" data-values="' . h(implode(',', $sotrIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && in_array($cn, ['mesto_id', 'firm_id', 'sotr_id'])) {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'number', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'accept_flag': $raw = (string)(int)$r['accept_flag']; return [$raw, $raw ? '<img src="img/lock.png" alt="Утверждено" title="Утверждено" width="18" height="18" style="vertical-align:middle" />' : ''];
                case 'number':      $raw = (string)$r['number']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'date':        $raw = (string)$r['date']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'time':        $raw = (string)$r['time']; $dt = $raw ? date('H:i', strtotime($raw)) : ''; return [$raw, $dt];
                case 'mesto_id':    $raw = (string)($r['mesto_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'pos':         $raw = (string)(int)$r['poz']; $np = (int)$r['poz']; return [$raw, $np ? $raw : ''];
                case 'sum':         $raw = (string)(float)$r['sum']; $ns = (float)$r['sum']; return [$raw, $ns ? number_format($ns, 2, '.', ' ') : ''];
                case 'first_card':  $raw = (string)(int)$r['first_card']; $nf = (int)$r['first_card']; return [$raw, $nf ? $raw : ''];
                case 'last_card':   $raw = (string)(int)$r['last_card']; $nl = (int)$r['last_card']; return [$raw, $nl ? $raw : ''];
                case 'firm_id':     $raw = (string)($r['firm_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'sotr_id':     $raw = (string)($r['sotr_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note':        $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'searchActive' => $searchActive,
            'searchCols' => $searchCols,
            'rowReadonly' => function($r, $cn) { return $cn !== 'note' && (int)($r['accept_flag'] ?? 0) === 1; },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

  </div>

<?php render_script_includes(['scripts' => ['assets/lookup.js', 'assets/export-modal.js', 'assets/column-filter.js', 'assets/embedded-subtable.js']]); ?>
<?php render_table_page_scripts([
    'pageUrl'         => $PAGE_URL,
    'formPrefix'      => $FORM_PREFIX,
    'tableKey'        => $TBL,
    'fieldSaveUrl'    => 'ainv_field_save.php',
    'columnsSaveUrl'  => 'ainv_columns_save.php',
    'columnResizeUrl' => 'ainv_column_width_save.php',
    'visibleColumns'  => $columnsConfig,
    'defaultColumns'  => $COLUMN_DEFAULTS,
    'exportUrl'       => 'ainv_export.php',
    'printUrl'        => 'ainv_print.php',
    'preserveParams'  => ['sort'],
    'lookupData' => [
        'mesto_id' => $mestoLookupOptions,
        'firm_id'  => $firmLookupOptions,
        'sotr_id'  => $sotrLookupOptions,
    ],
    'formModalConfig' => [
        'autoInitTables' => ['a2'],
        'extra_restore' => 'var _a2 = window.__a2Table; var _a2Page = _a2 ? _a2.currentPage : 1; var _a2Data = _a2 ? _a2.data.slice() : []; var _a2PS = _a2 ? _a2.pageSize : 15; window.__a2Table = null; initA2Table(); if (window.__a2Table) { window.__a2Table.parentId = parseInt((document.querySelector("input[name=id]") || {}).value || "0", 10); if (data && data.sgroup_list) { window.__a2Table.data = data.sgroup_list; var _pages = Math.ceil(data.sgroup_list.length / _a2PS) || 1; if (window.__a2Table.currentPage > _pages) window.__a2Table.currentPage = _pages; if ((data.mode === "new" || data.mode === "copy") && data.id) { var _newId = Number(data.id); window.__a2Table.selectedId = _newId; for (var _ai = 0; _ai < window.__a2Table.data.length; _ai++) { if (Number(window.__a2Table.data[_ai].id) === _newId) { window.__a2Table.currentPage = Math.floor(_ai / _a2PS) + 1; break; } } } else if (data._deleted) { var _delIdx = -1; for (var _ai = 0; _ai < _a2Data.length; _ai++) { if (Number(_a2Data[_ai].id) === Number(data.id)) { _delIdx = _ai; break; } } if (_delIdx >= 0 && window.__a2Table.data.length > 0) { var _nextIdx = _delIdx < window.__a2Table.data.length ? _delIdx : _delIdx - 1; if (_nextIdx < 0) _nextIdx = 0; window.__a2Table.selectedId = Number(window.__a2Table.data[_nextIdx].id); window.__a2Table.currentPage = Math.floor(_nextIdx / _a2PS) + 1; } else { window.__a2Table.selectedId = 0; } } window.__a2Table.render(); } else if (data && data._deleted) { window.__a2Table.refresh({ desiredIdx: -1, currentPage: _a2Page, totalPages: Math.ceil((_a2Data.length - 1) / _a2PS) || 1 }); } else if (data && data.id) { window.__a2Table.refresh({ focusId: data.id }); } } if (data && data.pos !== undefined) { var posEl = document.querySelector("[name=pos]"); if (posEl) posEl.value = data.pos; } savedTableSelections = {};',
    ],
    'extraCode' => "
        window.applyAinv2Totals = function (d) {
            var body = document.getElementById('formModalBody') || document;
            if (d && d.sum !== undefined) {
                var el = body.querySelector('#ainv-sum');
                if (el) el.value = d.sum || '';
            }
            if (d && d.pos !== undefined) {
                var elPos = body.querySelector('#ainv-pos');
                if (elPos) elPos.value = d.pos > 0 ? String(d.pos) : '';
            }
        };
        var _ab = document.getElementById('acceptBtn');
        if (_ab) _ab.addEventListener('click', function () {
            var _tr = document.querySelector('tbody tr.selected');
            var _id = _tr ? parseInt(_tr.getAttribute('data-row-id'), 10) : 0;
            if (!_id) { alert('Выберите строку'); return; }
            fetch('ainv.php?action=acceptToggle&id=' + _id, {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (d) { if (d.ok) location.reload(); });
        });
        ColumnFilter.init({ thSelector: '.col-mesto_id', pageUrl: 'ainv.php', param: 'mesto_id' });
        ColumnFilter.init({ thSelector: '.col-firm_id', pageUrl: 'ainv.php', param: 'firm_id' });
        ColumnFilter.init({ thSelector: '.col-sotr_id', pageUrl: 'ainv.php', param: 'sotr_id' });
    ",
]); ?>
<?php
$a2Table = new EmbeddedTable([
    'prefix'           => 'a2',
    'columns'          => [
        ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'param' => 'product_id', 'dbField' => 'product_id'],
        ['key' => 'quant_old',    'label' => 'Документальный остаток', 'align' => 'right', 'readonly' => true],
        ['key' => 'quant',        'label' => 'Фактический остаток', 'align' => 'right'],
        ['key' => 'dif',          'label' => 'Разница', 'align' => 'right', 'readonly' => true],
        ['key' => 'price',        'label' => 'Цена', 'align' => 'right', 'readonly' => true],
        ['key' => 'sum',          'label' => 'Сумма', 'align' => 'right', 'readonly' => true],
        ['key' => 'state',        'label' => 'Состояние', 'align' => 'right'],
        ['key' => 'note',         'label' => 'Примечание'],
    ],
    'colWidths'      => ['product_name' => '250px', 'quant_old' => '100px', 'quant' => '90px', 'dif' => '80px', 'price' => '90px', 'sum' => '100px', 'state' => '70px', 'note' => '300px'],
    'saveUrl'          => 'ainv2_field_save.php',
    'parentField'      => 'number',
    'childFormUrl'     => 'ainv2_form.php',
    'childFormName'    => 'ainv2',
    'hasExport'        => false,
    'hasPrint'         => false,
    'hasSearch'        => true,
    'columnResizeUrl'  => 'ainv2_column_width_save.php',
    'columnResizeTbl'  => 'ainv2',
    'lookupData' => [
        'product_name' => $productLookupOptions,
    ],
    'totalsCallback' => 'applyAinv2Totals',
    'barcodeAdd' => true,
]);
?>
<script>
    <?php $a2Table->renderScripts(); ?>
</script>
<?php render_page_footer(); ?>
