<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repgroup_columns.php';
require_once __DIR__ . '/config/repgroup_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/marks-actions.php';

ensure_marks_table($conn);

$TBL = 'repgroup';
$PAGE_URL = 'repgroup.php';
$PAGE_TITLE = 'Группы шаблонов документов';
$FORM_PREFIX = 'repgroup_form';

$tp = new TablePage($conn, $repgroupPageConfig);

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
$columnWidths    = load_columns_widths($conn, 'repgroup');
$urlCols = $searchCols;

handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn) {
    return null;
});

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['gr_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$exportQs = $tp->buildExportQs();

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
] as $item) {
    $fullUrl = 'repgroup_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}
$printDropdownHtml = render_print_dropdown_items('repgroup', $exportQs, (int)$page, $marksCount > 0);

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
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }
    .data-table tbody tr:hover td { background: #2c3a4d; }
    .data-table tbody tr.selected td { background: #3a5a8a !important; }
    .data-table tbody tr.selected:hover td { background: #3a5a8a !important; }
    .data-table thead th[data-sort-col]:hover { background: #3d5468; }
  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">
    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>
    <h1 class="page-title"><img src="img/repgroup.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>
    <?php render_toolbar_wrapper_open([
        'total'        => (int)$total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => (int)$marksCount,
        'search'       => $search,
        'page'         => (int)$page,
        'pages'        => (int)$pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]); ?>
    <?php render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml); ?>
    <?php render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, $PAGE_URL); ?>
    <?php render_toolbar_wrapper_close(); ?>
    <?php render_filter_banner($filters, $clearQs); ?>
    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '60px', 'name' => '250px', 'note' => '300px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'gr_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $s = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':   $raw = (string)(int)$r['gr_id']; return [$raw, $raw];
                case 'name': $raw = (string)($r['name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'note': $raw = (string)($r['note'] ?? ''); return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
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
render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/column-filter.js']]);
?>
<?php render_table_page_scripts([
    'pageUrl'        => $PAGE_URL,
    'formPrefix'     => $FORM_PREFIX,
    'tableKey'       => 'repgroup',
    'fieldSaveUrl'   => 'repgroup_field_save.php',
    'columnsSaveUrl' => 'repgroup_columns_save.php',
    'columnResizeUrl'=> 'repgroup_column_width_save.php',
    'visibleColumns' => $visibleColumns,
    'defaultColumns' => $COLUMN_DEFAULTS,
    'exportUrl'      => 'repgroup_export.php',
    'printUrl'       => 'repgroup_print.php',
    'preserveParams' => [],
]); ?>
<script>
ColumnsPanel.init({
    btn: document.getElementById('columnsBtn'),
    saveUrl: 'repgroup_columns_save.php',
    tbl: 'repgroup',
    closeAllPanels: closeAllPanels,
    initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
    }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
    defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
    }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
});
    ColumnResize.init({ saveUrl: 'repgroup_column_width_save.php', tbl: 'repgroup' });
    ExportModal.init();
</script>
<?php render_page_footer();
