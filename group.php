<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$accessFlags = render_access_control($conn, 'Group');

$TBL = 'group';
$serviceMode = (string)($_GET['type'] ?? 'product');
if (!in_array($serviceMode, ['product', 'service'], true)) $serviceMode = 'product';
$isService = ($serviceMode === 'service');
$serviceFlag = $isService ? '1' : '0';
$PAGE_TITLE = $isService ? 'Группы услуг' : 'Группы товаров';
$PAGE_URL   = 'group.php';
$FORM_PREFIX = 'group_form';

ensure_marks_table($conn);

$tp = new TablePage($conn, $groupPageConfig);

// Reset 'note' column_visibility entry — use full defaults if DB row is corrupt
$st = $conn->prepare("DELETE FROM column_visibility WHERE tbl = 'group' AND column_name = 'note'");
if ($st) { $st->execute(); $st->close(); }
$tp->loadColumnsConfig($conn);
$tp->loadColumnWidths($conn);

$tp->appendWhere("g.service_flag = ?", [$serviceFlag], 's');

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

    .toolbar-separator { display: inline-block; width: 1px; height: 24px; background: var(--line); margin: 0 6px; vertical-align: middle; }
    .toolbar-radio { display: inline-flex; align-items: center; gap: 4px; color: #ccc; font-size: 13px; cursor: pointer; padding: 0 4px; vertical-align: middle; user-select: none; }
    .toolbar-radio input[type="radio"] { margin: 0; cursor: pointer; }
    .toolbar-radio:hover { color: #fff; }
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
    'type' => $isService ? 'service' : null,
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

$serviceRadioHtml = '<span class="toolbar-separator"></span>'
    . '<label class="toolbar-radio"><input type="radio" name="service_mode" value="product"' . (!$isService ? ' checked' : '') . ' /> Группы товаров</label>'
    . '<label class="toolbar-radio"><input type="radio" name="service_mode" value="service"' . ($isService ? ' checked' : '') . ' /> Группы услуг</label>';

$tp->renderToolbar([
    'formPrefix' => $FORM_PREFIX,
    'exportFormats' => $exportFormats,
    'afterPrintHtml' => $serviceRadioHtml,
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
    'defaultWidths' => ['id' => 46, 'name' => 125, 'pos' => 60, 'note' => 500],
    'rows' => $rows,
]); ?>
  </table>
</div>

<?php $tp->renderPagination(); ?>

<?php $tp->renderPageEnd(); ?>

<?php
    render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js', 'assets/embedded-subtable.js', 'assets/inline-edit.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($tp->columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 125, 'pos' => 60, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
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
      'accessFlags'     => $accessFlags,
      'exportUrl'       => 'group_export.php',
      'printUrl'        => 'group_print.php',
      'preserveParams'  => ['sort', 'type'],
      'marksTbl'        => $TBL,
      'formModalConfig' => [
          'autoInitTables' => ['sg'],
          'extra_open' => 'if (typeof initSgTable === "function") initSgTable(); if (window.__sgTable) window.__sgTable.refresh();',
          'extra_restore' => 'var _sg = window.__sgTable; var _sgPage = _sg ? _sg.currentPage : 1; var _sgData = _sg ? _sg.data.slice() : []; var _sgPS = _sg ? _sg.pageSize : 15; window.__sgTable = null; initSgTable(); if (window.__sgTable) { window.__sgTable.parentId = parseInt((document.querySelector("input[name=id]") || {}).value || "0", 10); if (data && data.sgroup_list) { window.__sgTable.data = data.sgroup_list; var _pages = Math.ceil(data.sgroup_list.length / _sgPS) || 1; if (window.__sgTable.currentPage > _pages) window.__sgTable.currentPage = _pages; if ((data.mode === "new" || data.mode === "copy") && data.id) { var _newId = Number(data.id); window.__sgTable.selectedId = _newId; for (var _sgi = 0; _sgi < window.__sgTable.data.length; _sgi++) { if (Number(window.__sgTable.data[_sgi].id) === _newId) { window.__sgTable.currentPage = Math.floor(_sgi / _sgPS) + 1; break; } } } else if (data._deleted) { var _delIdx = -1; for (var _sgi = 0; _sgi < _sgData.length; _sgi++) { if (Number(_sgData[_sgi].id) === Number(data.id)) { _delIdx = _sgi; break; } } if (_delIdx >= 0 && window.__sgTable.data.length > 0) { var _nextIdx = _delIdx < window.__sgTable.data.length ? _delIdx : _delIdx - 1; if (_nextIdx < 0) _nextIdx = 0; window.__sgTable.selectedId = Number(window.__sgTable.data[_nextIdx].id); window.__sgTable.currentPage = Math.floor(_nextIdx / _sgPS) + 1; } else { window.__sgTable.selectedId = 0; } } window.__sgTable.render(); } else if (data && data._deleted) { window.__sgTable.refresh({ desiredIdx: -1, currentPage: _sgPage, totalPages: Math.ceil((_sgData.length - 1) / _sgPS) || 1 }); } else if (data && data.id) { window.__sgTable.refresh({ focusId: data.id }); } } if (data && data.pos !== undefined) { var posEl = document.querySelector("[name=pos]"); if (posEl) posEl.value = data.pos; } savedTableSelections = {};',
      ],
      'extraCode' => '
        (function() {
          var sf = "' . $serviceFlag . '";
          var addBtn = document.querySelector("button[data-form-open]");
          if (addBtn && addBtn.getAttribute("data-form-open").indexOf("service_flag") < 0) {
            addBtn.setAttribute("data-form-open", addBtn.getAttribute("data-form-open") + "&service_flag=" + sf);
          }
          var modeRadios = document.querySelectorAll("input[name=service_mode]");
          modeRadios.forEach(function(radio) {
            radio.addEventListener("change", function() {
              var p = new URLSearchParams(location.search);
              p.set("type", radio.value);
              p.delete("page");
              location.href = "group.php?" + p.toString();
            });
          });
        })();
      ',
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
    'accessFlags'     => $accessFlags,
]);
?>
  <script>
    <?php $sgTable->renderScripts(); ?>
  </script>
<?php render_page_footer();