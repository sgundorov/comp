<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/country_columns.php';
require_once __DIR__ . '/config/country_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Country');

$TBL = 'country';

ensure_marks_table($conn);

$tp = new TablePage($conn, $countryPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, $TBL);

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
    $ok = save_columns_config($conn, 'country', $clean);
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
foreach ($rows as $r) { if (isset($marks[(int)$r['country_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs();
?><?php
render_head_start('Страны');
?>
  <style>
    /* Column collapsing via arrow keys вЂ” third column */
    .data-table tbody tr:hover td.col-country { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-country { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-country { background: #3a5a8a; }

    .col-filter-panel {
      position: absolute; top: 100%; left: 0; margin-top: 0; background: var(--head);
      border: 2px solid #6b7785; border-radius: 3px; padding: 0; min-width: 240px; max-width: 320px;
      box-shadow: 0 4px 12px rgba(0,0,0,.3); z-index: 50;
    }
    .col-filter-panel-header { padding: 8px 10px; border-bottom: 1px solid var(--line); font-weight: 700; font-size: 16px; }
    .col-filter-panel-search { padding: 6px 8px; border-bottom: 1px solid var(--line); }
    .col-filter-panel-search input { width: 100%; height: 24px; padding: 0 8px; background: #2a3a4b; border: 1px solid #4a5561; color: var(--text); border-radius: 2px; font-size: 12px; outline: none; }
    .col-filter-panel-list { max-height: 200px; overflow-y: auto; padding: 4px 0; }
    .col-filter-panel-list label { display: flex; align-items: center; gap: 6px; padding: 4px 12px; cursor: pointer; font-size: 13px; }
    .col-filter-panel-list label:hover { background: var(--btn-hover); }
    .col-filter-panel-list input[type="checkbox"] { cursor: pointer; }
    .col-filter-panel-hint { padding: 4px 12px; color: var(--muted); font-size: 11px; }
    .col-filter-panel-empty { padding: 12px; color: var(--muted); text-align: center; font-size: 12px; }
    .col-filter-panel-footer { padding: 8px; border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; }
    .col-filter-panel-footer button { height: 24px; padding: 0 10px; background: var(--btn); color: var(--text); border: 1px solid #2a3a4b; border-radius: 2px; cursor: pointer; font-size: 12px; }
    .col-filter-panel-footer button:hover { background: var(--btn-hover); }
    .col-filter-panel-footer button.primary { background: var(--accent); border-color: var(--accent); }
    .col-filter-panel-footer button.primary:hover { background: var(--accent-hover); }

  </style>
<?php
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'country.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/country.png" alt="" /> Страна</h1>

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
          return 'country.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);
    ?>

    <?php
      $exportDropdownHtml = '<a class="dropdown-item" href="country_export.php?format=csv' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Страны.csv">Экспорт в CSV</a>'
        . '<a class="dropdown-item" href="country_export.php?format=xls' . ($exportQs !== '' ? '&' . $exportQs : '') . '" data-export-filename="Страны.xls">Экспорт в Excel</a>';
      $printDropdownHtml = render_print_dropdown_items('country', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_left('country_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'country.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'country' => '200px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'country_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':      $raw = (string)(int)$r['country_id']; return [$raw, $raw];
                case 'country': $raw = (string)$r['country']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note':    $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'country' => 200, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
require_once __DIR__ . '/lib/table-page-scripts.php';
render_table_page_scripts([
    'pageUrl'         => 'country.php',
    'formPrefix'      => 'country_form',
    'tableKey'        => 'country',
    'fieldSaveUrl'    => 'country_field_save.php',
    'columnsSaveUrl'  => 'country_columns_save.php',
    'columnResizeUrl' => 'country_column_width_save.php',
    'visibleColumns'  => $visibleColumns,
    'defaultColumns'  => $COLUMN_DEFAULTS,
    'accessFlags'     => $accessFlags,
    'exportUrl'       => 'country_export.php',
    'printUrl'        => 'country_print.php',
    'marksTbl'        => 'country',
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

