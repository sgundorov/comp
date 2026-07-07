<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$TBL = 'group';
$PAGE_TITLE = 'Группы товаров';
$PAGE_URL   = 'group.php';
$FORM_PREFIX = 'group_form';

ensure_marks_table($conn);

$tp = new TablePage($conn, $groupPageConfig);

handle_marks_actions($conn, $tp, $TBL);

$total = $tp->getTotalCount($conn);
$rows = $tp->getRows($conn);

render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }

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
$tp->renderHeadEnd();
render_export_modal();
render_form_modal();
$tp->renderPageStart();
$activeMenu = $PAGE_URL; include 'menu.php';
$tp->renderTitle('group.png', $PAGE_TITLE);

$exportQs = http_build_query(array_filter([
    'q'    => $tp->searchActive && $tp->search !== '' ? $tp->search : null,
    'cols' => $tp->searchActive && count($tp->searchCols) > 0 ? implode(',', $tp->searchCols) : null,
    'cond' => $tp->searchActive ? $tp->searchCond : null,
    'sf'   => $tp->searchActive ? '1' : null,
    'sort' => $tp->sortQs !== '' ? $tp->sortQs : null,
], function ($v) { return $v !== null && $v !== ''; }));

$exportFormats = [];
foreach ([
    ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV', 'label' => 'Экспорт в CSV'],
    ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)', 'label' => 'Экспорт в Excel'],
] as $item) {
    $fullUrl = 'group_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportFormats[] = $item + ['url' => $fullUrl];
}

$tp->renderToolbar([
    'formPrefix' => $FORM_PREFIX,
    'exportFormats' => $exportFormats,
]);

$tp->renderFilterBanner();

?>
<div class="table-wrap">
  <table class="data-table">
<?php $tp->renderTable(function($r, $cn, $vc) use ($tp) {
    $search = $tp->search;
    $searchCond = $tp->searchCond;
    $searchCols = $tp->searchCols;
    $doHilight = in_array($cn, $searchCols, true);
    switch ($cn) {
        case 'id':   $raw = (string)(int)$r['group_id']; return [$raw, $raw];
        case 'name': $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'pos':  $raw = (string)(int)$r['pos']; return [$raw, $r['pos'] ? $raw : ''];
        case 'note': $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
    }
    return ['', ''];
}, [
    'defaultWidths' => ['id' => 46, 'name' => 250, 'note' => 500],
    'rows' => $rows,
]); ?>
  </table>
</div>

<?php $tp->renderPagination(); ?>

<?php $tp->renderPageEnd(); ?>

<?php
    render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/embedded-subtable.js', 'assets/inline-edit.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($tp->columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 250, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <?php render_table_page_scripts([
      'pageUrl'         => $PAGE_URL,
      'formPrefix'      => $FORM_PREFIX,
      'tableKey'        => $TBL,
      'fieldSaveUrl'    => 'group_field_save.php',
      'columnsSaveUrl'  => 'group_columns_save.php',
      'columnResizeUrl' => 'group_column_width_save.php',
      'visibleColumns'  => $tp->columnsConfig,
      'defaultColumns'  => $tp->columns,
      'exportUrl'       => 'group_export.php',
      'printUrl'        => 'group_print.php',
      'preserveParams'  => ['sort'],
      'marksTbl'        => $TBL,
      'formModalConfig' => [
          'extra_open' => 'if (typeof initSgTable === "function") initSgTable();',
          'extra_restore' => 'var _sg = window.__sgTable; var _sgPage = _sg ? _sg.currentPage : 1; var _sgData = _sg ? _sg.data : []; var _sgPS = _sg ? _sg.pageSize : 15; initSgTable(); if (window.__sgTable) { window.__sgTable.parentId = parseInt((document.querySelector("input[name=id]") || {}).value || "0", 10); if (data && data._deleted) { var _sgIdx = -1; for (var _sgi = 0; _sgi < _sgData.length; _sgi++) { if (_sgData[_sgi].id == data.id) { _sgIdx = _sgi; break; } } window.__sgTable.refresh({ desiredIdx: _sgIdx, currentPage: _sgPage, totalPages: Math.ceil(_sgData.length / _sgPS) || 1 }); } else { window.__sgTable.refresh({ focusId: data && data.id ? data.id : 0 }); } } if (data && data.pos !== undefined) { var posEl = document.querySelector("[name=pos]"); if (posEl) posEl.value = data.pos; }',
      ],
  ]); ?>
<?php
$sgTable = new EmbeddedTable([
    'prefix'           => 'sg',
    'columns'          => [
        ['key' => 'name', 'label' => 'Название подгруппы', 'align' => 'left'],
        ['key' => 'note', 'label' => 'Примечание', 'align' => 'left'],
    ],
    'saveUrl'          => 'sgroup_field_save.php',
    'parentField'      => 'group_id',
    'childFormUrl'     => 'sgroup_form.php',
    'childFormName'    => 'sgroup',
    'hasExport'        => true,
    'hasPrint'         => true,
    'exportUrl'        => 'sgroup_export.php',
    'printUrl'         => 'sgroup_print.php',
    'parentParam'      => 'group_id=',
]);
?>
  <script>
    <?php $sgTable->renderScripts(); ?>
  </script>
<?php render_page_footer();