<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/repmenu_columns.php';
require_once __DIR__ . '/config/repmenu_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$accessFlags = render_access_control($conn, 'RepMenu');

ensure_marks_table($conn);

$TBL = 'repmenu';
$PAGE_URL = 'repmenu.php';
$PAGE_TITLE = 'Настройка документов';
$FORM_PREFIX = 'repmenu_form';

$tp = new TablePage($conn, $repmenuPageConfig);

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
$columnWidths    = load_columns_widths($conn, 'repmenu');
$urlCols = $searchCols;

$repgroupList = [];
$defaultGrId = 0;
$stmt = @$conn->prepare("SELECT gr_id AS id, name FROM repgroup ORDER BY name");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) while ($r = $res->fetch_assoc()) { $repgroupList[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']]; if ((string)$r['name'] === 'Счета') $defaultGrId = (int)$r['id']; } $stmt->close(); }

handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn, $tp) {
    return null;
});

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$grFilter = (string)($_GET['gr_id'] ?? '');
if ($grFilter === '' && $defaultGrId > 0) {
    $grFilter = (string)$defaultGrId;
}
$grFilterIds = [];
if ($grFilter !== '') {
    $grFilterIds = array_values(array_filter(array_map('intval', explode(',', $grFilter)), fn($v) => $v > 0));
}
if (count($grFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($grFilterIds), '?'));
    $tp->appendWhere("m.gr_id IN ($ph)", $grFilterIds, str_repeat('i', count($grFilterIds)));
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['rp_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$activeGrId = count($grFilterIds) > 0 ? $grFilterIds[0] : $defaultGrId;

$extraExportParams = [];
if ($activeGrId > 0) $extraExportParams['gr_id'] = $activeGrId;
$exportQs = $tp->buildExportQs($extraExportParams);

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
] as $item) {
    $fullUrl = 'repmenu_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}
$printDropdownHtml = render_print_dropdown_items('repmenu', $exportQs, (int)$page, $marksCount > 0);

$baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $PAGE_URL, $activeGrId) {
    $qs = ['page' => $p];
    if ($activeGrId > 0) $qs['gr_id'] = $activeGrId;
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

$groupSelectHtml = '<select id="grFilterSelect" onchange="var v=this.value;var u=new URLSearchParams(location.search);if(v){u.set(\'gr_id\',v);}else{u.delete(\'gr_id\');}u.delete(\'page\');location.href=\'repmenu.php?\'+u.toString();" style="height:30px;font-size:13px;">';
foreach ($repgroupList as $gr) {
    $sel = ((int)$gr['id'] === $activeGrId) ? ' selected' : '';
    $groupSelectHtml .= '<option value="' . (int)$gr['id'] . '"' . $sel . '>' . h($gr['name']) . '</option>';
}
$groupSelectHtml .= '</select>';
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .data-table tbody td.col-fname { white-space: nowrap; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }
    .data-table tbody tr:hover td { background: #2c3a4d; }
    .data-table tbody tr.selected td { background: #3a5a8a !important; }
    .data-table tbody tr.selected:hover td { background: #3a5a8a !important; }
    .data-table tbody tr.row-hidden td { color: #e57373; }
    .data-table thead th[data-sort-col]:hover { background: #3d5468; }
    .toolbar-group-filter { display:flex; align-items:center; gap:4px; margin-left:8px; }
    .toolbar-group-filter label { font-size:12px; color:var(--muted); }
  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">
    <?php $activeMenu = $PAGE_URL; include 'menu.php'; ?>
    <h1 class="page-title"><img src="img/repmenu.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>
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
    <script>document.querySelector('[data-form-open]').setAttribute('data-form-open','repmenu_form.php?mode=new&gr_id=<?= (int)$activeGrId ?>');</script>
    <div class="toolbar-group-filter">
      <label for="grFilterSelect">Группа:</label>
      <?= $groupSelectHtml ?>
    </div>
    <?php render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, $PAGE_URL); ?>
    <?php render_toolbar_wrapper_close(); ?>
    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '60px', 'name' => '200px', 'fname' => '150px', 'note' => '250px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'rp_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $s = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':    $raw = (string)(int)$r['number']; return [$raw, $raw];
                case 'name':  $raw = (string)($r['name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
                case 'fname': $raw = (string)($r['fname'] ?? ''); $html = '<a href="repmenu_template_edit.php?id=' . (int)$r['rp_id'] . '" title="Редактировать шаблон">' . h($raw) . '</a>'; return [$raw, $html];
                case 'note':  $raw = (string)($r['note'] ?? ''); return [$raw, $doHilight ? hilight($raw, $s, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'searchActive' => $searchActive,
            'searchCols' => $searchCols,
            'tdExtraAttrs' => function($cn, $vc, $r, $i) {
                return ' data-col-idx="' . (int)$i . '"';
            },
            'trExtraAttrs' => function($r) {
                if (!empty($r['HIDE_FLAG']) && $r['HIDE_FLAG'] == '1') {
                    return 'class="row-hidden"';
                }
                return '';
            },
        ]); ?>
      </table>
    </div>
    <?= $paginationHtml ?>
  </div>
<?php
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js']]); ?>
<?php render_table_page_scripts([
    'pageUrl'        => $PAGE_URL,
    'formPrefix'     => $FORM_PREFIX,
    'tableKey'       => 'repmenu',
    'fieldSaveUrl'   => 'repmenu_field_save.php',
    'columnsSaveUrl' => 'repmenu_columns_save.php',
    'columnResizeUrl'=> 'repmenu_column_width_save.php',
    'accessFlags'    => $accessFlags,
    'visibleColumns' => $visibleColumns,
    'defaultColumns' => $COLUMN_DEFAULTS,
    'exportUrl'      => 'repmenu_export.php',
    'printUrl'       => 'repmenu_print.php',
    'preserveParams' => ['gr_id'],
    'lookupData'     => [],
    'formModalConfig'=> ['lookup_tables' => ['repgroup'], 'lookup_data' => ['repgroup' => '__repgroupList']],
]); ?>
<script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'repmenu_columns_save.php',
      tbl: 'repmenu',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });
</script>
<script>
window.__repgroupList = <?= json_encode($repgroupList, JSON_UNESCAPED_UNICODE) ?>;
    ExportModal.init();
</script>
<?php render_page_footer();
