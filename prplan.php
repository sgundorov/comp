<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/prplan_columns.php';
require_once __DIR__ . '/config/prplan_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Product');

/** Отображение даты без ведущего нуля дня: 01.05.2026 -> 1.05.2026 */
function prplan_disp_date(string $v): string {
    return preg_replace('/^0(\d\.)/', '$1', trim($v));
}

$TBL = 'prplan';

ensure_marks_table($conn);

$tp = new TablePage($conn, $prplanPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, $TBL);

/* Даты сезона видны только при включённом SezonFlag */
$sezonFlag = (int)($appSettings['SezonFlag'] ?? 0) === 1;
if (!$sezonFlag) {
    $visibleColumns = array_values(array_filter($visibleColumns, function ($c) {
        return !in_array($c['name'], ['bdate', 'edate'], true);
    }));
}

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
handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
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
    $ok = save_columns_config($conn, 'prplan', $clean);
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
foreach ($rows as $r) { if (isset($marks[(int)$r['prplan_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Тарифные планы');
?>
  <style>
    .data-table tbody tr:hover td.col-prplan { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-prplan { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-prplan { background: #3a5a8a; }
  </style>
<?php
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'prplan.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/price.png" alt="" /> Тарифные планы</h1>

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
          return 'prplan.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);
    ?>

    <?php
      $exportDropdownHtml = '<a class="dropdown-item" href="prplan_export.php?format=csv' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Тарифные планы.csv">Экспорт в CSV</a>'
        . '<a class="dropdown-item" href="prplan_export.php?format=xls' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Тарифные планы.xls">Экспорт в Excel</a>';
      $printDropdownHtml = render_print_dropdown_items('prplan', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('prplan_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'prplan.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'prplan' => '220px', 'bdate' => '110px', 'edate' => '110px', 'note' => '400px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, []); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'prplan_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':     $raw = (string)(int)$r['prplan_id']; return [$raw, $raw];
                case 'prplan': $raw = (string)$r['prplan']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'bdate':  $raw = (string)$r['bdate']; $disp = prplan_disp_date($raw); return [$raw, $doHilight ? hilight($disp, $search, $searchCond) : $disp];
                case 'edate':  $raw = (string)$r['edate']; $disp = prplan_disp_date($raw); return [$raw, $doHilight ? hilight($disp, $search, $searchCond) : $disp];
                case 'note':   $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'prplan' => 220, 'bdate' => 110, 'edate' => 110, 'note' => 400], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
require_once __DIR__ . '/lib/table-page-scripts.php';
render_table_page_scripts([
    'pageUrl'         => 'prplan.php',
    'formPrefix'      => 'prplan_form',
    'tableKey'        => 'prplan',
    'fieldSaveUrl'    => 'prplan_field_save.php',
    'columnsSaveUrl'  => 'prplan_columns_save.php',
    'columnResizeUrl' => 'prplan_column_width_save.php',
    'visibleColumns'  => $visibleColumns,
    'defaultColumns'  => $COLUMN_DEFAULTS,
    'accessFlags'     => $accessFlags,
    'exportUrl'       => 'prplan_export.php',
    'printUrl'        => 'prplan_print.php',
    'marksTbl'        => 'prplan',
    'searchPanelConfig' => [
        'popupCheckboxes' => true,
        'emptyClass' => 'search-cond-placeholder',
        'labels' => [
            'cols' => 'Колонки',
            'cond' => 'Условие',
            'emptyCols' => 'Выберите колонки…',
        ],
    ],
    'formModalConfig' => [
        'lookup_tables' => [],
    ],
    'lookupData'      => [],
]);
render_page_footer();
