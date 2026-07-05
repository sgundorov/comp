<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/group_columns.php';
require_once __DIR__ . '/config/group_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/embedded-subtable-template.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$TBL = 'group';
$PAGE_TITLE = 'Группы товаров';
$PAGE_URL   = 'group.php';
$FORM_PREFIX = 'group_form';

ensure_marks_table($conn);

$tp = new TablePage($conn, $groupPageConfig);

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

if (!function_exists('build_filter')) {
function build_filter(string $search, array $cols = [], string $cond = 'contains'): array {
    $colToExpr = [
        'name' => 'g.name',
        'pos'  => 'g.pos',
        'note' => 'g.note',
    ];
    $condToOp = [
        'contains'     => function ($e) { return "$e LIKE ?"; },
        'not_contains' => function ($e) { return "$e NOT LIKE ?"; },
        'starts_with'  => function ($e) { return "$e LIKE ?"; },
        'ends_with'    => function ($e) { return "$e LIKE ?"; },
        'equals'       => function ($e) { return "$e = ?"; },
        'not_equals'   => function ($e) { return "$e <> ?"; },
    ];
    $where = '';
    $params = [];
    $types  = '';
    if ($search !== '' && count($cols) > 0 && isset($condToOp[$cond])) {
        $op = $condToOp[$cond];
        $parts = [];
        foreach ($cols as $col) {
            if (!isset($colToExpr[$col])) continue;
            $parts[] = $op($colToExpr[$col]);
            switch ($cond) {
                case 'contains':     $params[] = '%' . $search . '%'; break;
                case 'not_contains': $params[] = '%' . $search . '%'; break;
                case 'starts_with':  $params[] = $search . '%'; break;
                case 'ends_with':    $params[] = '%' . $search; break;
                case 'equals':       $params[] = $search; break;
                case 'not_equals':   $params[] = $search; break;
            }
            $types .= 's';
        }
        if (count($parts) > 0) {
            $where = 'WHERE (' . implode(' OR ', $parts) . ')';
        }
    }
    return [$where, $params, $types];
}

function fetch_filtered_ids(mysqli $conn, string $search, array $cols = [], string $cond = 'contains'): array {
    [$where, $params, $types] = build_filter($search, $cols, $cond);
    $sql = "SELECT g.group_id FROM `group` g WHERE g.service_flag = 0 $where";
    $ids = [];
    if ($types === '') {
        $res = @$conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['group_id'];
    } else {
        $stmt = @mysqli_prepare($conn, $sql);
        if ($stmt) {
            $whereTypes = $types;
            $whereParams = $params;
            $allTypes = $whereTypes;
            $allParams = $whereParams;
            stmt_bind($stmt, $allTypes, $allParams);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['group_id'];
            $stmt->close();
        }
    }
    return $ids;
}
}

handle_marks_actions($conn, $tp, $TBL);

$marks = load_marks_set($conn, $TBL);
$marksCount = count_marks($conn, $TBL);

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
foreach ($rows as $r) { if (isset($marks[(int)$r['group_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
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

    <h1 class="page-title"><img src="img/group.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

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
          return $PAGE_URL . '?' . http_build_query($qs);
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
          ['fmt' => 'csv', 'filename' => $PAGE_TITLE . '.csv', 'format' => 'CSV'],
          ['fmt' => 'xls', 'filename' => $PAGE_TITLE . '.xls', 'format' => 'XLS (Excel)'],
      ] as $item) {
          $fullUrl = 'group_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $printDropdownHtml = render_print_dropdown_items('group', $exportQs, (int)$page, $marksCount > 0);
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
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, $PAGE_URL);
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'name' => '250px', 'note' => '500px']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'group_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'id':   $raw = (string)(int)$r['group_id']; return [$raw, $raw];
                case 'name': $raw = (string)$r['name']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'pos':  $raw = (string)(int)$r['pos']; return [$raw, $r['pos'] ? $raw : ''];
                case 'note': $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
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
    render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/embedded-subtable.js', 'assets/inline-edit.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'name' => 250, 'note' => 500], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <?php render_table_page_scripts([
      'pageUrl'         => $PAGE_URL,
      'formPrefix'      => $FORM_PREFIX,
      'tableKey'        => $TBL,
      'fieldSaveUrl'    => 'group_field_save.php',
      'columnsSaveUrl'  => 'group_columns_save.php',
      'columnResizeUrl' => 'group_column_width_save.php',
      'visibleColumns'  => $columnsConfig,
      'defaultColumns'  => $COLUMN_DEFAULTS,
      'exportUrl'       => 'group_export.php',
      'printUrl'        => 'group_print.php',
      'preserveParams'  => ['sort'],
      'formModalConfig' => [
          'extra_open' => 'if (typeof initSgTable === "function") initSgTable();',
          'extra_restore' => 'var _sg = window.__sgTable; var _sgPage = _sg ? _sg.currentPage : 1; var _sgData = _sg ? _sg.data : []; var _sgPS = _sg ? _sg.pageSize : 15; initSgTable(); if (window.__sgTable) { window.__sgTable.parentId = parseInt((document.querySelector("input[name=id]") || {}).value || "0", 10); if (data && data._deleted) { var _sgIdx = -1; for (var _sgi = 0; _sgi < _sgData.length; _sgi++) { if (_sgData[_sgi].id == data.id) { _sgIdx = _sgi; break; } } window.__sgTable.refresh({ desiredIdx: _sgIdx, currentPage: _sgPage, totalPages: Math.ceil(_sgData.length / _sgPS) || 1 }); } else { window.__sgTable.refresh({ focusId: data && data.id ? data.id : 0 }); } } if (data && data.pos !== undefined) { var posEl = document.querySelector("[name=pos]"); if (posEl) posEl.value = data.pos; }',
      ],
  ]); ?>
  <script>
    <?php render_embedded_subtable_scripts([
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
        'columnResizeUrl'  => 'sgroup_column_width_save.php',
        'columnResizeTbl'  => 'sgroup',
    ]); ?>
  </script>
<?php render_page_footer();
