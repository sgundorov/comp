<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';
require_once __DIR__ . '/config/invoice_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/controls.php';

ensure_marks_table($conn);

$tp = new TablePage($conn, $invoicePageConfig);
$tp->appendWhere("i.doctype_id = ?", [10], "i");

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'invoice');

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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'toggleSelect') {
    $id = (int)($_POST['id'] ?? 0);
    $to = ((string)($_POST['to'] ?? '0')) === '1';
    if ($id > 0) {
        if ($to) {
            $stmt = @$conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) VALUES (?, ?)");
            if ($stmt) { stmt_bind($stmt, 'si', ['invoice', $id]); $stmt->execute(); $stmt->close(); }
        } else {
            $stmt = @$conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id = ?");
            if ($stmt) { stmt_bind($stmt, 'si', ['invoice', $id]); $stmt->execute(); $stmt->close(); }
        }
    }
    $newCount = count_marks($conn, 'invoice');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => $newCount], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'invertSelection') {
    $filteredIds = $tp->getFilteredIds($conn);
    $marksNow = load_marks_set($conn, 'invoice');
    $conn->begin_transaction();
    try {
        foreach ($filteredIds as $id) {
            if (isset($marksNow[$id])) {
                $stmt = $conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id = ?");
                stmt_bind($stmt, 'si', ['invoice', $id]);
            } else {
                $stmt = $conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) VALUES (?, ?)");
                stmt_bind($stmt, 'si', ['invoice', $id]);
            }
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); }
    $newCount = count_marks($conn, 'invoice');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => $newCount], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'clearSelection') {
    $stmt = @$conn->prepare("DELETE FROM marks WHERE tbl = ?");
    if ($stmt) { stmt_bind($stmt, 's', ['invoice']); $stmt->execute(); $stmt->close(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'toggleShowOnly') {
    $_SESSION['invoice_select']['show_only'] = !$showOnly;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'show_only' => (bool)$_SESSION['invoice_select']['show_only']], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'toggleSelectAll') {
    $filteredIds = $tp->getFilteredIds($conn);
    $totalInFilter = count($filteredIds);
    $markedInFilter = 0;
    if ($totalInFilter > 0) {
        $place = implode(',', array_fill(0, $totalInFilter, '?'));
        $cStmt = @$conn->prepare("SELECT COUNT(*) AS cnt FROM marks WHERE tbl = ? AND row_id IN ($place)");
        if ($cStmt) {
            $cParams = array_merge(['invoice'], $filteredIds);
            $cTypes = 's' . str_repeat('i', $totalInFilter);
            stmt_bind($cStmt, $cTypes, $cParams);
            $cStmt->execute();
            $cr = $cStmt->get_result()->fetch_assoc();
            $markedInFilter = (int)($cr['cnt'] ?? 0);
            $cStmt->close();
        }
    }
    $conn->begin_transaction();
    try {
        if ($totalInFilter > 0) {
            $place = implode(',', array_fill(0, $totalInFilter, '?'));
            if ($markedInFilter >= $totalInFilter) {
                $stmt = $conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id IN ($place)");
                $params = array_merge(['invoice'], $filteredIds);
                $types = 's' . str_repeat('i', $totalInFilter);
            } else {
                $stmt = $conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) SELECT 'invoice', ? " . str_repeat('UNION SELECT ? ', max(0, $totalInFilter - 1)));
                $params = $filteredIds;
                $types = str_repeat('i', $totalInFilter);
            }
            stmt_bind($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); }
    $qs = $_GET;
    unset($qs['action']);
    header('Location: invoice.php' . ($qs ? '?' . http_build_query($qs) : ''));
    exit;
}

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
            $clean[] = ['name' => $name, 'visible' => !empty($row['visible']) ? 1 : 0, 'order' => (int)($row['order'] ?? $i)];
        }
    }
    foreach ($COLUMN_DEFAULTS as $i => $c) {
        if (!isset($seen[$c['name']])) $clean[] = ['name' => $c['name'], 'visible' => 1, 'order' => count($clean) + $i];
    }
    usort($clean, function ($a, $b) { return $a['order'] - $b['order']; });
    $ok = save_columns_config($conn, 'invoice', $clean);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    if ($col === 'state') {
        echo json_encode([
            ['id' => 1, 'name' => 'Черновик'],
            ['id' => 2, 'name' => 'Выставлен'],
            ['id' => 3, 'name' => 'Оплачен'],
            ['id' => 4, 'name' => 'Отменен'],
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($col === 'payment_type') {
        echo json_encode([
            ['id' => 1, 'name' => 'Наличные'],
            ['id' => 2, 'name' => 'Безнал.'],
            ['id' => 3, 'name' => 'Карта'],
            ['id' => 4, 'name' => 'Прочее'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$urlCols = $searchCols;

// ---- column filters ----
$clientFilter = (string)($_GET['client_id'] ?? '');
$clientFilterIds = []; $clientFilterNames = [];
$storeFilter = (string)($_GET['store_id'] ?? '');
$storeFilterIds = []; $storeFilterNames = [];
$sotrFilter = (string)($_GET['sotr_id'] ?? '');
$sotrFilterIds = []; $sotrFilterNames = [];

$stateFilter = (string)($_GET['state'] ?? '');
$stateFilterIds = []; $stateFilterNames = [];
$stateIdToValue = [1 => 'Черновик', 2 => 'Выставлен', 3 => 'Оплачен', 4 => 'Отменен'];

function loadFilterNames(mysqli $conn, string $table, string $idCol, string $labelExpr, array $ids): array {
    if (empty($ids)) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT $idCol AS id, $labelExpr AS name FROM $table WHERE $idCol IN ($ph) ORDER BY $labelExpr";
    $stmt = @$conn->prepare($sql);
    if (!$stmt) return [];
    stmt_bind($stmt, str_repeat('i', count($ids)), $ids);
    $stmt->execute();
    $r = $stmt->get_result();
    $out = [];
    while ($row = $r->fetch_assoc()) $out[(int)$row['id']] = (string)$row['name'];
    $stmt->close();
    return $out;
}

if ($clientFilter !== '') {
    $clientFilterIds = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
}
if (count($clientFilterIds) > 0) {
    $clientFilterNames = loadFilterNames($conn, 'client', 'client_id', 'name', $clientFilterIds);
    $ph = implode(',', array_fill(0, count($clientFilterIds), '?'));
    $tp->appendWhere("i.client_id IN ($ph)", $clientFilterIds, str_repeat('i', count($clientFilterIds)));
}

if ($storeFilter !== '') {
    $storeFilterIds = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
}
if (count($storeFilterIds) > 0) {
    $storeFilterNames = loadFilterNames($conn, 'store', 'store_id', 'name', $storeFilterIds);
    $ph = implode(',', array_fill(0, count($storeFilterIds), '?'));
    $tp->appendWhere("i.store_id IN ($ph)", $storeFilterIds, str_repeat('i', count($storeFilterIds)));
}

if ($sotrFilter !== '') {
    $sotrFilterIds = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
}
if (count($sotrFilterIds) > 0) {
    $sotrFilterNames = loadFilterNames($conn, 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))", $sotrFilterIds);
    $ph = implode(',', array_fill(0, count($sotrFilterIds), '?'));
    $tp->appendWhere("i.sotr_id IN ($ph)", $sotrFilterIds, str_repeat('i', count($sotrFilterIds)));
}

if ($stateFilter !== '') {
    $stateFilterIds = array_values(array_filter(array_map('intval', explode(',', $stateFilter)), fn($v) => $v >= 1 && $v <= 4));
}
if (count($stateFilterIds) > 0) {
    $stateFilterNames = [];
    foreach ($stateFilterIds as $sid) { if (isset($stateIdToValue[$sid])) $stateFilterNames[] = $stateIdToValue[$sid]; }
    $ph = implode(',', array_fill(0, count($stateFilterNames), '?'));
    $tp->appendWhere("i.state IN ($ph)", $stateFilterNames, str_repeat('s', count($stateFilterNames)));
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['invoice_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$clearQs = function($drop) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
    $drop = is_array($drop) ? $drop : [$drop];
    $qs = [];
    if (!in_array('q', $drop, true) && $searchActive && $search !== '') $qs['q'] = $search;
    if (!in_array('cols', $drop, true) && $searchActive && count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
    if (!in_array('cond', $drop, true) && $searchActive) $qs['cond'] = $searchCond;
    if (!in_array('sf', $drop, true) && $searchActive) $qs['sf'] = '1';
    if (!in_array('sort', $drop, true) && $sortQs !== '') $qs['sort'] = $sortQs;
    if (!in_array('client_id', $drop, true) && !empty($_GET['client_id'])) $qs['client_id'] = $_GET['client_id'];
    if (!in_array('store_id', $drop, true) && !empty($_GET['store_id'])) $qs['store_id'] = $_GET['store_id'];
    if (!in_array('sotr_id', $drop, true) && !empty($_GET['sotr_id'])) $qs['sotr_id'] = $_GET['sotr_id'];
    if (!in_array('state', $drop, true) && !empty($_GET['state'])) $qs['state'] = $_GET['state'];
    return 'invoice.php' . ($qs ? '?' . http_build_query($qs) : '');
};

$tp->buildFilters();
$filters = $tp->filters;
if (count($clientFilterNames) > 0) {
    $filters[] = ['kind' => 'client', 'text' => 'Контрагент = ' . implode(', ', $clientFilterNames), 'clear' => 'client_id'];
}
if (count($storeFilterNames) > 0) {
    $filters[] = ['kind' => 'store', 'text' => 'Участок = ' . implode(', ', $storeFilterNames), 'clear' => 'store_id'];
}
if (count($sotrFilterNames) > 0) {
    $filters[] = ['kind' => 'sotr', 'text' => 'Сотрудник = ' . implode(', ', $sotrFilterNames), 'clear' => 'sotr_id'];
}
if (count($stateFilterNames) > 0) {
    $filters[] = ['kind' => 'state', 'text' => 'Состояние = ' . implode(', ', $stateFilterNames), 'clear' => 'state'];
}

$exportQs = http_build_query(array_filter([
    'q'    => $searchActive && $search !== '' ? $search : null,
    'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
    'cond' => $searchActive ? $searchCond : null,
    'sf'   => $searchActive ? '1' : null,
    'sort' => $sortQs !== '' ? $sortQs : null,
    'client_id' => $clientFilter !== '' ? $clientFilter : null,
    'store_id' => $storeFilter !== '' ? $storeFilter : null,
    'sotr_id' => $sotrFilter !== '' ? $sotrFilter : null,
    'state' => $stateFilter !== '' ? $stateFilter : null,
], function ($v) { return $v !== null && $v !== ''; }));

$clientFilterOptions = [];
$clr = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($clr) while ($cr = $clr->fetch_assoc()) $clientFilterOptions[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$storeFilterOptions = [];
$str = $conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($str) while ($sr = $str->fetch_assoc()) $storeFilterOptions[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrFilterOptions = [];
$sotrr = $conn->query("SELECT sotr_id, TRIM(CONCAT_WS(' ', last_name, first_name)) AS name FROM sotr ORDER BY name");
if ($sotrr) while ($sr = $sotrr->fetch_assoc()) $sotrFilterOptions[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

function cellValue($row, $colName, $vc) {
    switch ($colName) {
        case 'number':       return [(int)$row['number'] > 0 ? (int)$row['number'] : '', (int)$row['number'] > 0 ? (int)$row['number'] : ''];
        case 'date':
            $dt = strtotime((string)$row['date']);
            $display = $dt ? date('Y-m-d', $dt) : ((string)$row['date']);
            return [$row['date'], $display];
        case 'time':     $raw = (string)$row['time']; $dt = $raw ? date('H:i', strtotime($raw)) : ''; return [$raw, $dt];
        case 'client':       return [(int)$row['client_id'], h($row['client_name'])];
        case 'state':        return [$row['state'], h($row['state'])];
        case 'store':        return [(int)$row['store_id'], h($row['store_name'])];
        case 'discount':     $dv = (float)$row['discount']; $disp = $dv == 0 ? '' : ($dv == (int)$dv ? (string)(int)$dv : rtrim(rtrim(sprintf('%.3f', $dv), '0'), '.')); return [$row['discount'], $disp];
        case 'sum':          $sv = (float)$row['sum']; return [$row['sum'], $sv == 0 ? '' : number_format($sv, 2, ',', ' ')];
        case 'sotr':         return [(int)$row['sotr_id'], h($row['sotr_name'])];
        case 'pos':          $pv = (int)$row['pos']; return [$row['pos'], $pv > 0 ? (string)$pv : ''];
        case 'note':         return [$row['note'], h($row['note'])];
    }
    return ['', ''];
}

$baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
    $qs = ['page' => (int)$p];
    if ($searchActive) {
        if ($search !== '') $qs['q'] = $search;
        if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
        $qs['cond'] = $searchCond;
        $qs['sf'] = '1';
    }
    if ($sortQs !== '') $qs['sort'] = $sortQs;
    if (!empty($_GET['client_id'])) $qs['client_id'] = $_GET['client_id'];
    if (!empty($_GET['store_id'])) $qs['store_id'] = $_GET['store_id'];
    if (!empty($_GET['sotr_id'])) $qs['sotr_id'] = $_GET['sotr_id'];
    if (!empty($_GET['state'])) $qs['state'] = $_GET['state'];
    return 'invoice.php?' . http_build_query($qs);
};
$paginationHtml = render_pagination($page, $pages, $baseQs, true);

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => 'Счета.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => 'Счета.xls', 'format' => 'XLS (Excel)'],
] as $item) {
    $fullUrl = 'invoice_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}
$printQs = $exportQs !== '' ? '?' . $exportQs : '';
$printDropdownHtml =
    '<a class="dropdown-item" href="invoice_print.php' . $printQs . '" target="_blank">Все записи</a>' .
    '<a class="dropdown-item" href="invoice_print.php?all=1' . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Выбранные</a>' .
    '<a class="dropdown-item" href="invoice_print.php?onlyPage=1&pageNum=' . (int)$page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Текущая страница</a>' .
    '<a class="dropdown-item" href="#" onclick="printSelectedInvoice();return false;">Печать счет</a>';

render_head_start('Счета');
?>
  <style>
    .col-sum { text-align: right; white-space: nowrap; }
    .col-date { white-space: nowrap; }
    .search-highlight { background: #e8812a; color: #fff; padding: 0 1px; border-radius: 2px; }
  </style>
<?php
render_export_modal();
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'invoice.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/schet.png" alt="" /> Счета</h1>

    <?php render_toolbar_wrapper_open([
        'total'        => $total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => $marksCount,
        'search'       => $search,
        'page'         => $page,
        'pages'        => $pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]); ?>

    <?php render_toolbar_left('invoice_form', $marksCount, $exportDropdownHtml, $printDropdownHtml); ?>

    <?php render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'invoice.php'); ?>

    <?php render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

    <div class="table-wrap">
    <table class="data-table" data-table="invoice">
      <?php render_table_colgroup($visibleColumns, $columnWidths, invoice_columns_widths_print()); ?>
      <?php render_table_thead(
          $visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0,
          ['thAttrsCallback' => function($cn, $cm, $i) {
              if ($cn === 'state') {
                  $attrs = ' data-param="state"';
                  $raw = (string)($_GET['state'] ?? '');
                  if ($raw !== '') {
                      $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v >= 1 && $v <= 4));
                      if (count($ids) > 0) $attrs .= ' data-values="' . implode(',', $ids) . '"';
                  }
                  return $attrs;
              }
              return '';
          }]
      ); ?>
      <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'invoice_id', 'cellValue', [
          'searchActive' => $searchActive,
          'searchCols' => $searchCols,
      ]); ?>
    </table>
    </div>

    <?= $paginationHtml ?>

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/lookup.js', 'assets/column-filter.js', 'assets/export-modal.js', 'assets/columns-panel.js', 'assets/column-resize.js', 'assets/embedded-table.js']]);
?>
  <script>
  (function () {
    const toolbar = document.querySelector('.toolbar');
    const tbody   = document.querySelector('table tbody');
    const rowOpenBtn   = document.getElementById('rowOpenBtn');
    const rowCopyBtn   = document.getElementById('rowCopyBtn');
    const rowDeleteBtn = document.getElementById('rowDeleteBtn');
    const sortBtn   = document.getElementById('sortBtn');
    const columnsBtn = document.getElementById('columnsBtn');
    const searchForm  = document.getElementById('searchForm');
    const searchCondBtn  = document.getElementById('searchCondBtn');
    const searchToggleBtn= document.getElementById('searchToggleBtn');
    const formModal = document.getElementById('formModal');
    const formBody  = document.getElementById('formModalBody');
    const selWrap   = document.getElementById('selectedActions');
    const selCount  = document.getElementById('selectedCount');

    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);
    const currentTotal = parseInt(toolbar.dataset.total || '0', 10);
    let stashed = null;

    const initialMarks = [];
    document.querySelectorAll('.row-check').forEach(function (cb) {
      if (cb.checked) initialMarks.push(parseInt(cb.dataset.id, 10));
    });
    const selected = new Set(initialMarks);

    function updateSelectionUI() {
      const any = parseInt(toolbar.dataset.marksCount || '0', 10) > 0;
      selWrap.classList.toggle('visible', any);
      selCount.textContent = 'Выбрано: ' + (toolbar.dataset.marksCount || '0');
    }

    function updateRowActionButtons() {
      const id = rowSel.getSelectedId();
      rowOpenBtn.disabled = id === 0;
      rowCopyBtn.disabled = id === 0;
      rowDeleteBtn.disabled = id === 0;
    }

    const rowSel = RowSelect.init({
      tbody: tbody,
      rowClass: 'selected',
      onChange: updateRowActionButtons,
      currentPage: currentPage,
      totalPages: currentPages,
      navigate: navigate
    });

    function selectRow(rowId) { rowSel.selectById(rowId, true); }

    function navigate(params) {
      const url = new URL(window.location.href);
      params(url.searchParams);
      window.location.href = url.pathname + '?' + url.searchParams.toString();
    }

    const SORT_COLS = <?= json_encode(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) { return $c['search'] ? ['key' => $c['name'], 'label' => $c['label']] : null; }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.init({
      pageUrl: 'invoice.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'invoice.php?action=invertSelection&' + other.toString();
      },
      getExportUrl: function () { return 'invoice_export.php?format=csv&all=1&' + new URLSearchParams(location.search).toString(); },
      getPrintUrl: function () { return 'invoice_print.php?all=1&' + new URLSearchParams(location.search).toString(); }
    });

    function toggleMark(id, to) {
      fetch('invoice.php?action=toggleSelect', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
        body: 'id=' + id + '&to=' + (to ? '1' : '0')
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) {
          toolbar.dataset.marksCount = d.count;
          updateSelectionUI();
        }
      });
    }

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('change', function () {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selected.add(id); else selected.delete(id);
        var cur = parseInt(toolbar.dataset.marksCount || '0', 10);
        toolbar.dataset.marksCount = String(cb.checked ? cur + 1 : Math.max(0, cur - 1));
        updateSelectionUI();
        toggleMark(id, cb.checked);
      });
      cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }

    SearchPanel.init({
      form: searchForm,
      condBtn: searchCondBtn,
      toggleBtn: searchToggleBtn,
      columns: SEARCH_COLS,
      pageUrl: 'invoice.php',
      popupCheckboxes: true,
      emptyClass: 'search-cond-placeholder',
      closeAllPanels: closeAllPanels,
      labels: { cols: 'Колонки', cond: 'Условие', emptyCols: 'Нет столбцов для поиска' },
      onApply: function (state) {
        var params = new URLSearchParams(location.search);
        if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
        else params.delete('cols');
        params.set('cond', state.cond);
        params.set('sf', '1');
        var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim();
        if (q !== '') params.set('q', q); else params.delete('q');
        params.delete('page');
        location.href = 'invoice.php?' + params.toString();
      },
      onToggle: function (state) {
        var params = new URLSearchParams(location.search);
        var q = searchForm.querySelector('input[name="q"]').value.trim();
        if (q !== '') {
          params.set('q', q);
          if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
          params.set('cond', state.cond);
          params.set('sf', '1');
        } else {
          params.delete('q'); params.delete('cols'); params.delete('cond'); params.delete('sf');
        }
        params.delete('page');
        location.href = 'invoice.php?' + params.toString();
      },
      onSubmit: function () { searchToggleBtn.click(); }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'invoice.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'invoice_columns_save.php',
      tbl: 'invoice',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    function initFormLookups() {
      var formEl = formBody.querySelector('form');
      var isDeleteMode = formEl && formEl.classList.contains('form--delete');
      formBody.querySelectorAll('[data-lookup]').forEach(function (root) {
        if (root.dataset.lookupInited) return;
        var rawJson = root.getAttribute('data-countries');
        if (!rawJson) return;
        var data;
        try { data = JSON.parse(rawJson); } catch (e) { return; }
        if (!Array.isArray(data)) return;
        if (isDeleteMode) return;
        var nameInputId = root.getAttribute('data-name-input');
        var nameInput = nameInputId ? formBody.querySelector('#' + nameInputId) : null;
        var handler = nameInput ? function(id, name) { if (nameInput) nameInput.value = name; } : null;

        if (root.getAttribute('data-lookup') === 'product') {
          var productHandler = function(id, name) {
            if (handler) handler(id, name);
            var item = data.find(function(p) { return p.id === id; });
            if (!item) return;
            var priceInput = formBody.querySelector('#inv2-price');
            var quantInput = formBody.querySelector('#inv2-quant');
            var discountInput = formBody.querySelector('#inv2-discount');
            if (priceInput) priceInput.value = item.price_out || '0';
            if (quantInput && !quantInput.value) quantInput.value = '1';
            if (typeof __recalcInvoice2 === 'function') __recalcInvoice2();
          };
          try { bindLookup({ root: root, data: data, readonly: false, onSelect: productHandler }); root.dataset.lookupInited = '1'; } catch (e) {}
          return;
        }

        if (root.getAttribute('data-lookup') === 'zat') {
          var zatHandler = function(id, name) {
            if (handler) handler(id, name);
            var item = data.find(function(z) { return z.id === id; });
            var outFlagEl = formBody.querySelector('#plat-out-flag');
            if (item && outFlagEl) outFlagEl.value = item.out_flag === 1 ? 'Расход' : 'Приход';
          };
          try { bindLookup({ root: root, data: data, readonly: false, onSelect: zatHandler }); root.dataset.lookupInited = '1'; } catch (e) {}
          var zatIdHidden = root.querySelector('.lookup-id');
          if (zatIdHidden) {
            var zid = parseInt(zatIdHidden.value, 10);
            var zitem = data.find(function(z) { return z.id === zid; });
            var outFlagEl = formBody.querySelector('#plat-out-flag');
            if (zitem && outFlagEl) outFlagEl.value = zitem.out_flag === 1 ? 'Расход' : 'Приход';
          }
          return;
        }

        try { bindLookup({ root: root, data: data, readonly: false, onSelect: handler }); root.dataset.lookupInited = '1'; } catch (e) {}
      });
    }

    function initInvoice2Form() {
      var quantInput = formBody.querySelector('#inv2-quant');
      var priceInput = formBody.querySelector('#inv2-price');
      var discountInput = formBody.querySelector('#inv2-discount');
      if (!quantInput && !priceInput && !discountInput) return;
      var sumInput = formBody.querySelector('#inv2-sum');
      var sumDiscInput = formBody.querySelector('#inv2-sum-discount');
      window.__recalcInvoice2 = function () {
        var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
        var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
        var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
        if (sumInput) sumInput.value = (q * p * (1 - d / 100)).toFixed(2).replace('.', ',');
        if (sumDiscInput) sumDiscInput.value = (q * p * (d / 100)).toFixed(2).replace('.', ',');
      };
      [quantInput, priceInput, discountInput].forEach(function (el) {
        if (el) el.addEventListener('input', window.__recalcInvoice2);
      });
      window.__recalcInvoice2();
    }

    function autoSaveInvoice() {
      if (window.__autoSaveInProgress) return;
      var invIdEl = formBody.querySelector('input[name="id"]');
      var invoiceId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
      if (!invoiceId) return;
      var fd = new FormData();
      fd.set('field', '_recalc_totals');
      fd.set('invoice_id', String(invoiceId));
      window.__autoSaveInProgress = true;
      fetch('invoice2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(r) { return r.json(); }).then(function(data) {
          window.__autoSaveInProgress = false;
          if (data && data.ok) { window.invoice2Dirty = false; var cb = formBody.querySelector('.form-actions a.btn-secondary'); if (cb) { cb.style.pointerEvents = ''; cb.style.opacity = ''; } }
        }).catch(function() { window.__autoSaveInProgress = false; });
    }

    function initPlatFormSumCalc() {
      var sumInEl = document.getElementById('plat-sum-in');
      if (!sumInEl) return;
      function updatePlatSum() {
        var si = parseFloat(sumInEl.value.replace(',', '.')) || 0;
        var soEl = document.getElementById('plat-sum-out');
        var so = soEl ? parseFloat(soEl.value.replace(',', '.')) || 0 : 0;
        var sumEl = document.getElementById('plat-sum');
        if (sumEl) sumEl.value = (si - so).toFixed(2);
      }
      sumInEl.addEventListener('input', updatePlatSum);
      var sumOutEl = document.getElementById('plat-sum-out');
      if (sumOutEl) sumOutEl.addEventListener('input', updatePlatSum);
      updatePlatSum();
    }

    function initPlatTable() {
      var container = formBody.querySelector('.tab-pane[data-tab-index="2"]');
      if (!container) return;
      if (container.dataset.platInited) return;
      container.dataset.platInited = '1';
      var tableEl = container.querySelector('[data-plat-table]');
      if (!tableEl || typeof EmbeddedTable === 'undefined') return;
      var platData = [];
      try { platData = JSON.parse(tableEl.dataset.items || '[]'); } catch(e) {}
      var platShowOnlyFilter = false;
      var PLAT_TYPE_OPTIONS = [];
      try { PLAT_TYPE_OPTIONS = JSON.parse(tableEl.dataset.platTypes || '[]'); } catch(e) {}
      var addBtn = formBody.querySelector('#plat-add-btn');
      var editBtn = formBody.querySelector('#plat-edit-btn');
      var delBtn = formBody.querySelector('#plat-del-btn');
      var refreshBtn = formBody.querySelector('#plat-refresh-btn');
      var searchInput = formBody.querySelector('#plat-search-input');
      var clearBtn = formBody.querySelector('#plat-clear-filter-btn');
      var searchBtn = formBody.querySelector('#plat-search-btn');
      var condBtn = formBody.querySelector('#plat-search-cond-btn');
      var filterBanner = formBody.querySelector('#plat-filter-banner');

      var invIdEl = formBody.querySelector('input[name="id"]');
      var invId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
      var searchActive = false;
      var searchText = '';
      var searchCond = 'contains';
      var PLAT_SEARCH_COLS = [
        { key: 'datetime', label: 'Дата/Время' },
        { key: 'client_name', label: 'Контрагент' },
        { key: 'zat_name', label: 'Вид операции' },
        { key: 'sum', label: 'Сумма' },
        { key: 'plat_type', label: 'Вид платежа' },
        { key: 'out_flag', label: 'Тип' },
        { key: 'note', label: 'Примечание' }
      ];

      var platTable = EmbeddedTable.create({
        tableEl: tableEl,
        data: platData,
        columns: [
          { key: 'check', label: '' },
          { key: 'datetime', label: 'Дата/Время' },
          { key: 'client_name', label: 'Контрагент' },
          { key: 'zat_name', label: 'Вид операции' },
          { key: 'sum', label: 'Сумма' },
          { key: 'plat_type', label: 'Вид платежа' },
          { key: 'out_flag', label: 'Тип' },
          { key: 'note', label: 'Примечание' }
        ],
        searchCols: PLAT_SEARCH_COLS,
        filterBannerEl: filterBanner,
        selWrapEl: formBody.querySelector('#plat-sel-wrap'),
        selCountEl: formBody.querySelector('#plat-sel-count'),
        checkAllEl: '#plat-check-all',
        onSelectionChange: function (id) { if (editBtn) editBtn.disabled = !(id > 0); if (delBtn) delBtn.disabled = !(id > 0); },
        onRowDblClick: function (id) { if (id > 0) openPlatModal('plat_form.php?mode=edit&id=' + id); },
        renderRow: function (item, sel, h) {
          var sum = parseFloat(String(item.sum != null ? item.sum : '0').replace(',','.'));
          var sumStr = isNaN(sum) ? '-' : sum.toFixed(2);
          return '<tr class="plat-row" data-row-id="' + item.id + sel + '">'
            + '<td><input type="checkbox" class="plat-check" /></td>'
            + '<td>' + h.hl(item.datetime, h) + '</td>'
            + '<td>' + h.hl(item.client_name, h) + '</td>'
            + '<td>' + h.hl(item.zat_name, h) + '</td>'
            + '<td class="cell-editable" data-field="sum" data-value="' + (item.sum || '0') + '" style="text-align:right"><span class="cell-value">' + h.hl(sumStr, h) + '</span></td>'
            + '<td class="cell-editable" data-field="plat_type" data-value="' + h.esc(item.plat_type) + '"><span class="cell-value">' + h.esc(item.plat_type) + '</span></td>'
            + '<td>' + (item.out_flag === 1 ? 'Расход' : 'Приход') + '</td>'
            + '<td class="cell-editable" data-field="note" data-value="' + h.esc(item.note) + '"><span class="cell-value">' + h.hl(item.note, h) + '</span></td></tr>';
        }
      });

      window.__platData = platData;

      function refreshPlatData() {
        var ie = formBody.querySelector('input[name="id"]');
        var rid = ie ? parseInt(ie.value, 10) : 0;
        if (!rid) return;
        fetch('invoice_form.php?mode=edit&id=' + rid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d && d.html) {
              var tmp = document.createElement('div');
              tmp.innerHTML = d.html;
              var nt = tmp.querySelector('[data-plat-table]');
              if (nt) {
                var current = window.__platTable || platTable;
                try { current.setData(JSON.parse(nt.dataset.items || '[]')); window.__platData = current.getData(); } catch(e) {}
              }
            }
          });
      }

      window.__refreshPlatData = refreshPlatData;

      function afterPlatSave(data) {
        if (data && data.ok && data.sum_plat !== undefined) {
          window.applyInvoice2Totals(data);
          window.invoice2Dirty = true;
          autoSaveInvoice();
        }
        (window.__refreshPlatData || refreshPlatData)();
      }

      function openPlatModal(url) {
        stashed = { html: formBody.innerHTML, onRestore: afterPlatSave, activeId: document.activeElement ? document.activeElement.id : null, invoice2Dirty: window.invoice2Dirty, invoice2Data: window.__invoice2Data ? window.__invoice2Data.slice() : [] };
        openFormModal(url);
      }

      function updatePlatBanner() {
        if (!filterBanner) return;
        var parts = [];
        if (searchActive) {
          var colLabels = PLAT_SEARCH_COLS.filter(function(c) { return c.key !== 'check'; }).map(function(c) { return c.label; });
          var condLabel = ({ contains: 'Содержит', not_contains: 'Не содержит', starts_with: 'Начинается с', ends_with: 'Заканчивается на', equals: 'Равно', not_equals: 'Не равно' })[searchCond] || 'Содержит';
          parts.push('<span class="filter-chip"><span class="filter-chip-text">(' + colLabels.join(', ') + ' ' + condLabel + '  «' + searchText + '»)</span><button type="button" class="filter-chip-close" id="plat-banner-clear" title="Снять фильтр">✕</button></span>');
        }
        if (platShowOnlyFilter) {
          parts.push('<span class="filter-chip" style="margin-left:6px"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="plat-banner-showoff" title="Показать все">✕</button></span>');
        }
        if (parts.length > 0) {
          filterBanner.innerHTML = '<img src="img/filter.png" alt="" />' + parts.join('');
          filterBanner.style.display = 'flex';
          var clearB = filterBanner.querySelector('#plat-banner-clear');
          if (clearB) clearB.addEventListener('click', clearPlatSearch);
          var showOff = filterBanner.querySelector('#plat-banner-showoff');
          if (showOff) showOff.addEventListener('click', function() {
            platShowOnlyFilter = false;
            refreshPlatData();
            updatePlatBanner();
          });
        } else {
          filterBanner.style.display = 'none';
        }
      }

      function doPlatSearch() {
        searchText = searchInput ? searchInput.value.trim() : '';
        searchActive = searchText !== '';
        if (clearBtn) clearBtn.style.display = searchActive ? '' : 'none';
        platTable.setSearch(searchActive, searchText, searchCond);
        if (searchBtn) searchBtn.classList.toggle('active', searchActive);
        updatePlatBanner();
      }

      if (addBtn) {
        addBtn.addEventListener('click', function () {
          var url = addBtn.getAttribute('data-plat-url');
          if (url) openPlatModal(url);
        });
      }
      if (editBtn) {
        editBtn.addEventListener('click', function () {
          var id = platTable.getSelectedId();
          if (id > 0) openPlatModal('plat_form.php?mode=edit&id=' + id);
        });
      }
      if (delBtn) {
        delBtn.addEventListener('click', function () {
          var id = platTable.getSelectedId();
          if (id > 0) openPlatModal('plat_form.php?mode=delete&id=' + id);
        });
      }
      window.__platTable = platTable;

      if (!window.__platHotkeysInited) {
        window.__platHotkeysInited = true;
        document.addEventListener('keydown', function(e) {
          if (!formModal || !formModal.classList.contains('open')) return;
          var pt = window.__platTable;
          if (!pt || !pt.tbody) return;
          if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
          var rows = Array.from(pt.tbody.querySelectorAll('[data-row-id]'));
          if (rows.length === 0) return;
          var curId = pt.getSelectedId();
          var curIdx = -1;
          for (var k = 0; k < rows.length; k++) {
            if (parseInt(rows[k].dataset.rowId, 10) === curId) { curIdx = k; break; }
          }
          switch (e.key) {
            case 'Insert':
              e.preventDefault();
              var ab = document.querySelector('#plat-add-btn');
              if (ab) ab.click();
              break;
            case 'Enter':
              e.preventDefault();
              if (curId > 0) { var eb = document.querySelector('#plat-edit-btn'); if (eb && !eb.disabled) eb.click(); }
              break;
            case 'Delete':
              e.preventDefault();
              if (curId > 0) { var db = document.querySelector('#plat-del-btn'); if (db && !db.disabled) db.click(); }
              break;
            case 'ArrowDown':
              e.preventDefault();
              if (curIdx < 0) { pt.selectFirst(); break; }
              if (curIdx < rows.length - 1) {
                var nd = parseInt(rows[curIdx + 1].dataset.rowId, 10);
                pt._selectRow(nd, rows[curIdx + 1]);
                rows[curIdx + 1].scrollIntoView({ block: 'nearest' });
              }
              break;
            case 'ArrowUp':
              e.preventDefault();
              if (curIdx > 0) {
                var pv = parseInt(rows[curIdx - 1].dataset.rowId, 10);
                pt._selectRow(pv, rows[curIdx - 1]);
                rows[curIdx - 1].scrollIntoView({ block: 'nearest' });
              }
              break;
            case 'Home':
              e.preventDefault();
              pt.selectFirst();
              var ft = pt.tbody.querySelector('[data-row-id].selected');
              if (ft) ft.scrollIntoView({ block: 'nearest' });
              break;
            case 'End':
              e.preventDefault();
              if (rows.length > 0) {
                var lt = rows[rows.length - 1];
                pt._selectRow(parseInt(lt.dataset.rowId, 10), lt);
                lt.scrollIntoView({ block: 'nearest' });
              }
              break;
          }
        });
      }

      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
          if (invId > 0) {
            var savedIds = Array.from(platTable.getCheckedIds());
            window.invoice2Dirty = false;
            var activeIdx = 0;
            var activeTab = formBody.querySelector('.tab-header.active');
            if (activeTab) activeIdx = parseInt(activeTab.dataset.tabIndex, 10);
            fetch('invoice_form.php?mode=edit&id=' + invId + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(data) {
                if (data && data.html) {
                  formBody.innerHTML = data.html;
                  initFormLookups();
                  initFormTabs();
                  try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); }
                  initInvoice2Form();
                  try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
                  var nt = formBody.querySelector('[data-plat-table]');
                  if (nt && typeof platTable !== 'undefined') {
                    savedIds.forEach(function(id) { platTable.getCheckedIds().add(id); });
                    platTable._syncChecks();
                    platTable._updateBatchUI();
                  }
                  var f = formBody.querySelector('form[data-form-modal]');
                  bindForm(f);
                  FormModalCore.bindFormTabTrap(f);
                  var hdr = formBody.querySelector('.tab-header[data-tab-index="' + activeIdx + '"]');
                  var pane = formBody.querySelector('.tab-pane[data-tab-index="' + activeIdx + '"]');
                  if (hdr && pane) {
                    formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); });
                    formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
                    hdr.classList.add('active');
                    pane.classList.add('active');
                  }
                }
              });
          }
        });
      }

      if (searchInput) searchInput.addEventListener('input', doPlatSearch);
      function clearPlatSearch() {
        if (searchInput) searchInput.value = '';
        searchActive = false; searchText = '';
        if (clearBtn) clearBtn.style.display = 'none';
        if (searchBtn) searchBtn.classList.remove('active');
        platTable.setSearch(false, '', searchCond);
        updatePlatBanner();
      }
      if (clearBtn) clearBtn.addEventListener('click', clearPlatSearch);

      function closePlatPanels() {
        document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        formBody.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
      }

      if (searchBtn && condBtn && typeof SearchPanel !== 'undefined') {
        var ns = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(ns, searchBtn);
        searchBtn = ns;
        var nc = condBtn.cloneNode(true);
        condBtn.parentNode.replaceChild(nc, condBtn);
        condBtn = nc;
        SearchPanel.init({
          form: formBody.querySelector('#plat-search-form'),
          condBtn: condBtn,
          toggleBtn: searchBtn,
          columns: PLAT_SEARCH_COLS,
          pageUrl: location.href,
          popupCheckboxes: true,
          emptyClass: 'search-cond-placeholder',
          closeAllPanels: closePlatPanels,
          labels: {
            cols: 'Столбцы',
            cond: 'Условие',
            emptyCols: 'Выберите столбцы…'
          },
          onApply: function(state) {
            searchCond = state.cond;
            searchActive = true;
            if (searchBtn) searchBtn.classList.add('active');
            doPlatSearch();
            closePlatPanels();
          },
          onClear: function() {
            searchCond = 'contains';
            doPlatSearch();
            closePlatPanels();
          },
          onToggle: function(state) {
            if (searchActive) {
              searchActive = false; searchText = '';
              if (searchInput) searchInput.value = '';
              if (clearBtn) clearBtn.style.display = 'none';
              if (searchBtn) searchBtn.classList.remove('active');
              platTable.setSearch(false, '', searchCond);
              updatePlatBanner();
            } else {
              searchActive = true;
              searchText = searchInput ? searchInput.value.trim() : '';
              if (clearBtn) clearBtn.style.display = searchText ? '' : 'none';
              if (searchBtn) searchBtn.classList.add('active');
              platTable.setSearch(true, searchText, searchCond);
              updatePlatBanner();
            }
          }
        });
      }

      InlineEdit.init({
        tbody: tableEl.querySelector('tbody'),
        saveUrl: 'plat_field_save.php',
        fields: {
          sum:       { dbField: 'sum', type: 'text', label: 'Сумма' },
          plat_type: { dbField: 'plat_type', type: 'select', label: 'Вид платежа', options: PLAT_TYPE_OPTIONS },
          note:      { dbField: 'note', type: 'textarea', label: 'Примечание' }
        },
        onSaveSuccess: function(data, field) {
          if (data && data.ok && data.sum_plat !== undefined) {
            window.applyInvoice2Totals(data);
            autoSaveInvoice();
          }
          refreshPlatData();
        }
      });

      if (formBody.querySelector('#plat-sel-clear')) {
        formBody.querySelector('#plat-sel-clear').addEventListener('click', function(e) { e.preventDefault(); platTable.clearChecked(); });
      }
      if (formBody.querySelector('#plat-sel-invert')) {
        formBody.querySelector('#plat-sel-invert').addEventListener('click', function(e) { e.preventDefault(); platTable.invertChecked(); });
      }

      /* Batch actions */
      var batchShowBtn = formBody.querySelector('#plat-sel-show');
      if (batchShowBtn) batchShowBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        if (platShowOnlyFilter) {
          platShowOnlyFilter = false;
          refreshPlatData();
          updatePlatBanner();
          return;
        }
        platShowOnlyFilter = true;
        window.__platOriginalData = platTable.getData().slice();
        var savedSel = platTable.getSelectedId();
        var filtered = window.__platOriginalData.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        platTable.setData(filtered);
        if (savedSel && !filtered.some(function(i) { return i.id === savedSel; })) {
          platTable.selectFirst();
        }
        updatePlatBanner();
      });
      var batchExportBtn = formBody.querySelector('#plat-sel-export');
      if (batchExportBtn) batchExportBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        var data = platTable.getData();
        var items = data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var csv = '\uFEFF';
        csv += 'Дата/Время;Контрагент;Вид операции;Сумма;Вид платежа;Тип;Примечание\n';
        items.forEach(function(i) {
          csv += (i.datetime || '') + ';' + (i.client_name || '') + ';' + (i.zat_name || '') + ';' + (i.sum != null ? i.sum : '0') + ';' + (i.plat_type || '') + ';' + (i.out_flag === 1 ? 'Расход' : 'Приход') + ';' + (i.note || '') + '\n';
        });
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'payments.csv'; a.click();
        URL.revokeObjectURL(a.href);
      });
      var batchPrintBtn = formBody.querySelector('#plat-sel-print');
      if (batchPrintBtn) batchPrintBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        var data = platTable.getData();
        var items = data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var w = window.open('', '_blank', 'width=800,height=600');
        var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Печать</title><style>body{font:14px sans-serif;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 10px;text-align:left}th{background:#eee}</style></head><body><table><thead><tr><th>Дата/Время</th><th>Контрагент</th><th>Вид операции</th><th>Сумма</th><th>Вид платежа</th><th>Тип</th><th>Примечание</th></tr></thead><tbody>';
        items.forEach(function(i) {
          h += '<tr><td>' + (i.datetime || '') + '</td><td>' + (i.client_name || '') + '</td><td>' + (i.zat_name || '') + '</td><td>' + (i.sum != null ? i.sum : '0') + '</td><td>' + (i.plat_type || '') + '</td><td>' + (i.out_flag === 1 ? 'Расход' : 'Приход') + '</td><td>' + (i.note || '') + '</td></tr>';
        });
        h += '</tbody></table></body></html>';
        w.document.write(h);
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
      });
      var batchDelBtn = formBody.querySelector('#plat-sel-delete');
      if (batchDelBtn) batchDelBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var ids = Array.from(platTable.getCheckedIds());
        if (ids.length === 0) return;
        if (!confirm('Удалить ' + ids.length + ' отмеченных платежей?')) return;
        var lastSumPlat = '';
        (function next(i) {
          if (i >= ids.length) {
            platTable.clearChecked();
            if (lastSumPlat !== undefined && lastSumPlat !== '') {
              window.applyInvoice2Totals({ sum_plat: lastSumPlat });
              window.invoice2Dirty = true;
            }
            autoSaveInvoice();
            refreshPlatData();
            return;
          }
          var fd = new FormData();
          fd.set('field', '_delete');
          fd.set('plat_id', String(ids[i]));
          fetch('plat_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d && d.ok && d.sum_plat !== undefined) lastSumPlat = d.sum_plat; next(i + 1); })
            .catch(function() { next(i + 1); });
        })(0);
      });
    }

    function tryInitInv2ColResize() {
      var pane = formBody.querySelector('.tab-pane[data-tab-index="1"]');
      if (!pane || !pane.classList.contains('active')) return;
      var tbl = pane.querySelector('.invoice2-table');
      if (!tbl || tbl.dataset.colResizeInited) return;
      if (window.ColumnResize) ColumnResize.init({ saveUrl: 'invoice_column_width_save.php', tbl: 'invoice2', selector: '.invoice2-table' });
    }
    function initFormTabs() {
      var container = formBody.querySelector('.tab-container');
      if (!container) return;
      var headers = container.querySelectorAll('.tab-header');
      var panes = container.querySelectorAll('.tab-pane');
      headers.forEach(function (hdr) {
        hdr.addEventListener('click', function () {
          var idx = parseInt(hdr.dataset.tabIndex, 10);
          headers.forEach(function (h) { h.classList.remove('active'); });
          panes.forEach(function (p) { p.classList.remove('active'); });
          hdr.classList.add('active');
          var pane = container.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
          if (pane) pane.classList.add('active');
          if (idx === 1) tryInitInv2ColResize();
        });
      });
      setTimeout(tryInitInv2ColResize, 100);
    }

    window.invoice2Dirty = false;

    var autoSaveInProgress = false;
    var autoSaveNeeded = false;

    function autoSaveInvoice() {
      if (autoSaveInProgress) { autoSaveNeeded = true; return; }
      var invIdEl = formBody.querySelector('input[name="id"]');
      var invoiceId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
      if (!invoiceId) return;
      var fd = new FormData();
      fd.set('field', '_recalc_totals');
      fd.set('invoice_id', String(invoiceId));
      autoSaveInProgress = true;
      fetch('invoice2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(r) { return r.json(); }).then(function(data) {
          autoSaveInProgress = false;
          if (data && data.ok) {
            window.invoice2Dirty = false;
            var cancelBtn = formBody.querySelector('.form-actions a.btn-secondary');
            if (cancelBtn) { cancelBtn.style.pointerEvents = ''; cancelBtn.style.opacity = ''; }
          }
          if (autoSaveNeeded) { autoSaveNeeded = false; autoSaveInvoice(); }
        }).catch(function(err) {
          autoSaveInProgress = false;
          if (autoSaveNeeded) { autoSaveNeeded = false; autoSaveInvoice(); }
        });
    }

    function applyInvoice2Totals(d) {
      var s = d && d.total_sum !== undefined ? d.total_sum : (d && d.sum !== undefined ? d.sum : undefined);
      if (s !== undefined) {
        var el = formBody.querySelector('#inv-sum');
        if (el) el.value = s || '';
      }
      var sd = d && d.total_sum_discount !== undefined ? d.total_sum_discount : (d && d.sum_discount !== undefined ? d.sum_discount : undefined);
      if (sd !== undefined) {
        var el = formBody.querySelector('#inv-sum-discount');
        if (el) el.value = sd || '';
      }
      var snds = d && d.total_sum_nds !== undefined ? d.total_sum_nds : (d && d.sum_nds !== undefined ? d.sum_nds : undefined);
      if (snds !== undefined) {
        var el = formBody.querySelector('#inv-sum-nds');
        if (el) el.value = snds || '';
      }
        if (d && d.pos !== undefined) {
          var el = formBody.querySelector('#inv-pos');
          if (el) el.value = d.pos > 0 ? String(d.pos) : '';
        }
        if (d && d.sum_plat !== undefined) {
          var el = formBody.querySelector('#inv-sum-plat');
          if (el) {
            el.value = d.sum_plat || '';
            var sumVal = d && d.sum !== undefined ? d.sum : (formBody.querySelector('#inv-sum') ? formBody.querySelector('#inv-sum').value : 0);
            el.style.color = (parseFloat(d.sum_plat) || 0) < (parseFloat(sumVal) || 0) ? '#c0392b' : '';
            el.style.fontWeight = (parseFloat(d.sum_plat) || 0) < (parseFloat(sumVal) || 0) ? 'bold' : '';
          }
        }
        if (d && d.ok) { window.invoice2Dirty = true; autoSaveInvoice(); }
      var cancelBtn = formBody.querySelector('.form-actions a.btn-secondary');
      if (cancelBtn) {
        if (window.invoice2Dirty) { cancelBtn.style.pointerEvents = 'none'; cancelBtn.style.opacity = '0.5'; }
        else { cancelBtn.style.pointerEvents = ''; cancelBtn.style.opacity = ''; }
      }
    }

      window.__invoice2SelectedId = window.__invoice2SelectedId || 0;
      if (!window.__inv2CheckedIds) window.__inv2CheckedIds = new Set();

      function initInvoice2Items() {
        var formEl = formBody.querySelector('form');
        if (formEl && formEl.classList.contains('form--delete')) return;
        var table = formBody.querySelector('.invoice2-table');
        if (!table) return;

        /* Hotkeys — register once at document level */
        if (!window.__inv2HotkeysInited) {
          window.__inv2HotkeysInited = true;
          document.addEventListener('keydown', function(e) {
            if (!formModal || !formModal.classList.contains('open')) return;
            if (!formBody.querySelector('.invoice2-table')) return;
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            var rows = Array.from(formBody.querySelectorAll('.inv2-row'));
            if (rows.length === 0) return;
            var curIdx = -1;
            var curId = window.__invoice2SelectedId || 0;
            for (var k = 0; k < rows.length; k++) {
              if (parseInt(rows[k].dataset.id, 10) === curId) { curIdx = k; break; }
            }
            switch (e.key) {
              case 'Insert':
                e.preventDefault();
                var addBtn = formBody.querySelector('#inv2-add-btn');
                if (addBtn) addBtn.click();
                break;
              case 'Enter':
                e.preventDefault();
                if (curId > 0) { var editBtn = formBody.querySelector('#inv2-edit-btn'); if (editBtn) editBtn.click(); }
                break;
              case 'Delete':
                e.preventDefault();
                if (curId > 0) { var delBtn = formBody.querySelector('#inv2-del-btn'); if (delBtn) delBtn.click(); }
                break;
              case 'ArrowDown':
                e.preventDefault();
                if (curIdx < rows.length - 1) {
                  var nextId = parseInt(rows[curIdx + 1].dataset.id, 10);
                  rows.forEach(function(r) { r.classList.remove('selected'); });
                  window.__invoice2SelectedId = nextId;
                  rows[curIdx + 1].classList.add('selected');
                  updateInv2Buttons();
                }
                break;
              case 'ArrowUp':
                e.preventDefault();
                if (curIdx > 0) {
                  var prevId = parseInt(rows[curIdx - 1].dataset.id, 10);
                  rows.forEach(function(r) { r.classList.remove('selected'); });
                  window.__invoice2SelectedId = prevId;
                  rows[curIdx - 1].classList.add('selected');
                  updateInv2Buttons();
                }
                break;
              case 'Home':
                e.preventDefault();
                var firstId = parseInt(rows[0].dataset.id, 10);
                rows.forEach(function(r) { r.classList.remove('selected'); });
                window.__invoice2SelectedId = firstId;
                rows[0].classList.add('selected');
                updateInv2Buttons();
                break;
              case 'End':
                e.preventDefault();
                var lastId = parseInt(rows[rows.length - 1].dataset.id, 10);
                rows.forEach(function(r) { r.classList.remove('selected'); });
                window.__invoice2SelectedId = lastId;
                rows[rows.length - 1].classList.add('selected');
                updateInv2Buttons();
                break;
            }
          });
        }

        var saveUrl = table.getAttribute('data-save-url') || 'invoice2_field_save.php';
        var invIdEl = formBody.querySelector('input[name="id"]');
        var invId = invIdEl ? parseInt(invIdEl.value, 10) : 0;

        if (!window.__invoice2Data || window.__invoice2Data.length === 0) {
          window.__invoice2Data = [];
          try { window.__invoice2Data = JSON.parse(table.dataset.items || '[]'); } catch(e) {}
        }

      var PAGE_SIZE = 15;
      var currentPage = 1;
      var searchText = '';
      var searchActive = false;
      var sortCol = -1;
      var sortDir = 'asc';
      var INV2_SEARCH_COLS = [
        { key: 'product_name', label: 'Товар' },
        { key: 'quant', label: 'Кол-во' },
        { key: 'price', label: 'Цена' },
        { key: 'discount', label: 'Скидка' },
        { key: 'sum', label: 'Сумма' },
        { key: 'note', label: 'Примечание' }
      ];
      var INV2_COL_KEYS = INV2_SEARCH_COLS.map(function(c) { return c.key; });
      var searchCols = new Set(INV2_COL_KEYS);
      var searchCond = 'contains';

      function cn(v) { return (v === '' || v === null || v === undefined) ? '-' : v; }
      function nv(v) { var n = parseFloat(String(v !== null && v !== undefined ? v : '0').replace(',','.')); return isNaN(n) || n === 0 ? '-' : v; }
      function fmtInt(v) {
        if (v === null || v === undefined || v === '') return '-';
        var s = String(v).replace(',', '.');
        var n = parseFloat(s);
        if (isNaN(n) || n === 0) return '-';
        return n % 1 === 0 ? String(Math.round(n)) : String(v);
      }

      function hl(v) {
        if (!searchActive || !searchText) return cn(v);
        var s = String(v !== null && v !== undefined ? v : '');
        if (s === '') return '-';
        var st = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var re = new RegExp('(' + st + ')', 'gi');
        return s.replace(re, '<span class="hl">$1</span>');
      }

      function renderInvoice2() {
        var tbody = formBody.querySelector('.invoice2-table tbody');
        if (!tbody) return;
        var filtered = window.__invoice2Data;
        if (inv2ShowOnlyFilter && window.__inv2CheckedIds && window.__inv2CheckedIds.size > 0) {
          filtered = filtered.filter(function(item) { return window.__inv2CheckedIds.has(item.id); });
        }
        if (searchActive && searchText) {
          var st = searchText.toLowerCase();
          var cond = searchCond || 'contains';
          var colSet = (searchCols && searchCols.size > 0) ? searchCols : new Set(INV2_COL_KEYS);
          filtered = filtered.filter(function(item) {
            return INV2_COL_KEYS.some(function(key) {
              if (!colSet.has(key)) return false;
              var val = (item[key] || '').toString().toLowerCase();
              if (cond === 'contains') return val.indexOf(st) >= 0;
              if (cond === 'not_contains') return val.indexOf(st) < 0;
              if (cond === 'starts_with') return val.indexOf(st) === 0;
              if (cond === 'ends_with') return val.indexOf(st) === val.length - st.length;
              if (cond === 'equals') return val === st;
              if (cond === 'not_equals') return val !== st;
              return false;
            });
          });
        }
        if (sortCol >= 0) {
          var key = ['product_name','quant','price','discount','sum','note'][sortCol] || 'id';
          filtered.sort(function(a, b) {
            var va = (a[key] || '').toString().toLowerCase();
            var vb = (b[key] || '').toString().toLowerCase();
            var na = parseFloat(va.replace(',','.'));
            var nb = parseFloat(vb.replace(',','.'));
            if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
            if (va < vb) return sortDir === 'asc' ? -1 : 1;
            if (va > vb) return sortDir === 'asc' ? 1 : -1;
            return 0;
          });
        }
        var total = filtered.length;
        var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (currentPage > pages) currentPage = pages;
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageItems = filtered.slice(start, start + PAGE_SIZE);

          if (total === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="col-inv2-empty">Нет товаров</td></tr>';
          } else {
            var html = '';
            for (var i = 0; i < pageItems.length; i++) {
              var item = pageItems[i];
              var sel = (item.id === window.__invoice2SelectedId) ? ' selected' : '';
              var chk = window.__inv2CheckedIds && window.__inv2CheckedIds.has(item.id) ? ' checked' : '';
              var noteVal = item.note === '' || item.note === null || item.note === undefined ? '' : hl(item.note);
              html += '<tr data-id="' + item.id + '" data-row-id="' + item.id + '" class="inv2-row' + sel + '">'
                + '<td class="col-check"><input type="checkbox" class="inv2-row-check" data-id="' + item.id + '"' + chk + ' /></td>'
                + '<td class="cell-editable" data-field="product_id" data-value="' + (item.product_id || 0) + '"><span class="cell-value">' + hl(item.product_name) + '</span></td>'
                + '<td class="cell-editable" data-field="quant" data-value="' + (item.quant || '0') + '" style="text-align:center"><span class="cell-value">' + hl(fmtInt(item.quant)) + '</span></td>'
                + '<td class="cell-editable" data-field="price" data-value="' + (item.price || '0') + '" style="text-align:right"><span class="cell-value">' + hl(nv(item.price)) + '</span></td>'
                + '<td class="cell-editable" data-field="discount" data-value="' + (item.discount || '0') + '" style="text-align:right"><span class="cell-value">' + hl(fmtInt(item.discount)) + '</span></td>'
                + '<td data-field="sum" style="text-align:right">' + hl(fmtInt(item.sum)) + '</td>'
                + '<td class="cell-editable" data-field="note" data-value="' + (item.note || '') + '"><span class="cell-value">' + noteVal + '</span></td></tr>';
          }
          tbody.innerHTML = html;
        }

        /* Checkbox handlers */
        tbody.querySelectorAll('.inv2-row-check').forEach(function(cb) {
          cb.addEventListener('change', function(e) {
            e.stopPropagation();
            var id = parseInt(cb.dataset.id, 10);
            if (!window.__inv2CheckedIds) window.__inv2CheckedIds = new Set();
            if (cb.checked) { window.__inv2CheckedIds.add(id); }
            else { window.__inv2CheckedIds.delete(id); }
            updateInv2CheckedUI();
          });
        });

        var checkAll = formBody.querySelector('#inv2-check-all');
        if (checkAll) {
          checkAll.addEventListener('change', function(e) {
            var pageIds = formBody.querySelectorAll('.inv2-row-check');
            if (!window.__inv2CheckedIds) window.__inv2CheckedIds = new Set();
            if (checkAll.checked) {
              pageIds.forEach(function(cb) { window.__inv2CheckedIds.add(parseInt(cb.dataset.id, 10)); });
            } else {
              pageIds.forEach(function(cb) { window.__inv2CheckedIds.delete(parseInt(cb.dataset.id, 10)); });
            }
            pageIds.forEach(function(cb) { cb.checked = checkAll.checked; });
            updateInv2CheckedUI();
          });
        }

        tbody.querySelectorAll('.inv2-row').forEach(function(tr) {
          tr.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            var id = parseInt(tr.dataset.id, 10);
            tbody.querySelectorAll('.inv2-row.selected').forEach(function(r) { r.classList.remove('selected'); });
            window.__invoice2SelectedId = id;
            tr.classList.add('selected');
            updateInv2Buttons();
          });
          tr.addEventListener('dblclick', function() {
            var id = parseInt(tr.dataset.id, 10);
            if (id > 0) onEdit(id);
          });
        });

        var pagEl = formBody.querySelector('#inv2-pagination');
        if (pagEl) {
          if (pages <= 1) { pagEl.innerHTML = ''; } else {
            var ph = '';
            var firstDisabled = currentPage <= 1 ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
            var lastDisabled = currentPage >= pages ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
            ph += '<a class="page-btn" href="#" data-page="1"' + firstDisabled + '>«</a>';
            var startP = Math.max(1, currentPage - 2);
            var endP = Math.min(pages, currentPage + 2);
            for (var p = startP; p <= endP; p++) {
              var active = p === currentPage ? ' active' : '';
              ph += '<a class="page-btn' + active + '" href="#" data-page="' + p + '">' + p + '</a>';
            }
            ph += '<a class="page-btn" href="#" data-page="' + pages + '"' + lastDisabled + '>»</a>';
            ph += '<span class="page-info">' + currentPage + ' из ' + pages + '</span>';
            pagEl.innerHTML = ph;
            pagEl.querySelectorAll('a.page-btn').forEach(function(a) {
              a.addEventListener('click', function(e) {
                e.preventDefault();
                var pg = parseInt(a.dataset.page, 10);
                if (pg > 0 && pg !== currentPage) { currentPage = pg; renderInvoice2(); }
              });
            });
          }
        }

        var bannerEl = formBody.querySelector('#inv2-filter-banner');
        var clearBtn = formBody.querySelector('#inv2-clear-filter-btn');
        var showOnlyFilter = inv2ShowOnlyFilter && window.__inv2CheckedIds && window.__inv2CheckedIds.size > 0;
        if (searchActive && searchText) {
          if (bannerEl) {
            bannerEl.style.display = '';
            var bannerParts = [];
            var colLabels = INV2_SEARCH_COLS.filter(function(c) { return !searchCols || searchCols.has(c.key); }).map(function(c) { return c.label; });
            if (colLabels.length === 0) colLabels = INV2_SEARCH_COLS.map(function(c) { return c.label; });
            var condLabel = ({ contains: 'Содержит', not_contains: 'Не содержит', starts_with: 'Начинается с', ends_with: 'Заканчивается на', equals: 'Равно', not_equals: 'Не равно' })[searchCond] || 'Содержит';
            bannerParts.push('<img src="img/filter.png" alt="" /><span class="filter-chip"><span class="filter-chip-text">(' + colLabels.join(', ') + ' ' + condLabel + '  «' + searchText + '»)</span><button type="button" class="filter-chip-close" id="inv2-banner-clear" title="Снять фильтр">✕</button></span>');
            if (showOnlyFilter) bannerParts.push('<span class="filter-chip" style="margin-left:6px"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="inv2-banner-showoff" title="Показать все">✕</button></span>');
            bannerEl.innerHTML = bannerParts.join('');
            var bc = bannerEl.querySelector('#inv2-banner-clear'); if (bc) bc.addEventListener('click', function () { var sb = formBody.querySelector('#inv2-search-btn'); if (sb) sb.click(); });
            var so = bannerEl.querySelector('#inv2-banner-showoff'); if (so) so.addEventListener('click', function () { inv2ShowOnlyFilter = false; renderInvoice2(); });
          }
          if (clearBtn) clearBtn.style.display = '';
        } else if (showOnlyFilter) {
          if (bannerEl) {
            bannerEl.style.display = '';
            bannerEl.innerHTML = '<span class="filter-chip"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="inv2-banner-showoff" title="Показать все">✕</button></span>';
            var so = bannerEl.querySelector('#inv2-banner-showoff'); if (so) so.addEventListener('click', function () { inv2ShowOnlyFilter = false; renderInvoice2(); });
          }
          if (clearBtn) clearBtn.style.display = 'none';
        } else {
          if (bannerEl) bannerEl.style.display = 'none';
          if (clearBtn) clearBtn.style.display = 'none';
        }

        syncInv2CheckAll();
      }

      window.__invoice2Render = function() { renderInvoice2(); };

      function updateInv2Buttons() {
        var editBtn = formBody.querySelector('#inv2-edit-btn');
        var delBtn = formBody.querySelector('#inv2-del-btn');
        var copyBtn = formBody.querySelector('#inv2-copy-btn');
        var productBtn = formBody.querySelector('#inv2-product-btn');
        var disabled = !window.__invoice2SelectedId;
        if (editBtn) editBtn.disabled = disabled;
        if (delBtn) delBtn.disabled = disabled;
        if (copyBtn) copyBtn.disabled = disabled;
        if (productBtn) productBtn.disabled = disabled;
      }

      function refreshInvoice2Data() {
        var fd = new FormData();
        fd.set('field', '_list');
        fd.set('invoice_id', String(invId));
        fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data && Array.isArray(data)) {
              window.__invoice2Data = data;
              renderInvoice2();
            }
          });
      }

      function syncInv2CheckAll() {
        var checkAll = formBody.querySelector('#inv2-check-all');
        if (!checkAll) return;
        var cbs = formBody.querySelectorAll('.inv2-row-check');
        if (cbs.length === 0) { checkAll.checked = false; checkAll.indeterminate = false; return; }
        var checked = 0;
        cbs.forEach(function(cb) { if (cb.checked) checked++; });
        checkAll.checked = checked === cbs.length;
        checkAll.indeterminate = checked > 0 && checked < cbs.length;
      }

      function updateInv2CheckedUI() {
        var n = window.__inv2CheckedIds ? window.__inv2CheckedIds.size : 0;
        var countEl = document.querySelector('#inv2-sel-count');
        var selWrap = document.querySelector('#inv2-sel-wrap');
        if (countEl) countEl.textContent = n;
        if (selWrap) selWrap.classList.toggle('visible', n > 0);
        syncInv2CheckAll();
      }

      function clearChecked() {
        window.__inv2CheckedIds = new Set();
        inv2ShowOnlyFilter = false;
        updateInv2CheckedUI();
        renderInvoice2();
      }
      function invertChecked() {
        var all = window.__invoice2Data.map(function(i) { return i.id; });
        var cur = window.__inv2CheckedIds;
        window.__inv2CheckedIds = new Set(all.filter(function(id) { return !cur.has(id); }));
        updateInv2CheckedUI();
        renderInvoice2();
      }

      var inv2ShowOnlyFilter = false;
      function toggleInv2ShowOnly() {
        inv2ShowOnlyFilter = !inv2ShowOnlyFilter;
        renderInvoice2();
      }
      function exportInv2Checked() {
        var ids = window.__inv2CheckedIds ? Array.from(window.__inv2CheckedIds) : [];
        if (ids.length === 0) return;
        var items = window.__invoice2Data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var csv = '\uFEFF';
        csv += 'Товар;Кол-во;Цена;Скидка;Сумма;Примечание\n';
        items.forEach(function(item) {
          csv += (item.product_name || '') + ';' + (item.quant || '1') + ';' + (item.price || '0') + ';' + (item.discount || '0') + ';' + (item.sum || '0') + ';' + (item.note || '') + '\n';
        });
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'invoice_items.csv'; a.click();
        URL.revokeObjectURL(a.href);
      }
      function printInv2Checked() {
        var ids = window.__inv2CheckedIds ? Array.from(window.__inv2CheckedIds) : [];
        if (ids.length === 0) return;
        var items = window.__invoice2Data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var w = window.open('', '_blank', 'width=800,height=600');
        var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Печать</title><style>body{font:14px sans-serif;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 10px;text-align:left}th{background:#eee}</style></head><body><table><thead><tr><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Скидка</th><th>Сумма</th><th>Примечание</th></tr></thead><tbody>';
        items.forEach(function(item) {
          h += '<tr><td>' + (item.product_name || '') + '</td><td>' + (item.quant || '1') + '</td><td>' + (item.price || '0') + '</td><td>' + (item.discount || '0') + '</td><td>' + (item.sum || '0') + '</td><td>' + (item.note || '') + '</td></tr>';
        });
        h += '</tbody></table></body></html>';
        w.document.write(h);
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
      }

      /* Batch operations on checked items */
      function batchDeleteChecked() {
        var ids = window.__inv2CheckedIds ? Array.from(window.__inv2CheckedIds) : [];
        if (ids.length === 0) return;
        if (!confirm('Удалить ' + ids.length + ' отмеченных товаров?')) return;
        (function next(i) {
          if (i >= ids.length) {
            window.__invoice2SelectedId = 0;
            window.__inv2CheckedIds = new Set();
            updateInv2CheckedUI();
            renderInvoice2();
            return;
          }
          var fd = new FormData();
          fd.set('field', '_delete');
          fd.set('invoice2_id', String(ids[i]));
          fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (d && d.ok) {
                window.__invoice2Data = window.__invoice2Data.filter(function(item) { return item.id !== ids[i]; });
                if (d.sum !== undefined) applyInvoice2Totals(d);
              }
              next(i + 1);
            })
            .catch(function() { next(i + 1); });
        })(0);
      }

      function onEdit(id) {
        var url = 'invoice2_form.php?mode=edit&id=' + id + '&invoice_id=' + invId;
        openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
      }

      function onItemSaved(data) {
        if (!data || !data.ok) return;
        applyInvoice2Totals(data);
        if (data.id) {
          var idx = -1;
          for (var i = 0; i < window.__invoice2Data.length; i++) {
            if (window.__invoice2Data[i].id == data.id) { idx = i; break; }
          }
          if (idx >= 0) {
            window.__invoice2Data[idx] = {
              id: data.id, product_id: data.product_id || 0,
              code: data.code || '', product_name: data.product_name || '',
              quant: data.quant || '', price: data.price || '',
              discount: data.discount || '', sum: data.sum || '',
              sum_discount: data.sum_discount || '', sum_nds: data.sum_nds || '',
              note: data.note || ''
            };
          } else {
            window.__invoice2Data.push({
              id: data.id, product_id: data.product_id || 0,
              code: data.code || '', product_name: data.product_name || '',
              quant: data.quant || '', price: data.price || '',
              discount: data.discount || '', sum: data.sum || '',
              sum_discount: data.sum_discount || '', sum_nds: data.sum_nds || '',
              note: data.note || ''
            });
          }
          renderInvoice2();
        }
      }

      function onItemDeleted(data) {
        if (!data || !data.ok || !data._deleted) return;
        applyInvoice2Totals(data);
        var deletedId = data.id;
        var idx = -1;
        for (var i = 0; i < window.__invoice2Data.length; i++) {
          if (window.__invoice2Data[i].id == deletedId) { idx = i; break; }
        }
        window.__invoice2Data = window.__invoice2Data.filter(function(item) { return item.id !== deletedId; });
        if (window.__inv2CheckedIds) window.__inv2CheckedIds.delete(deletedId);
        if (window.__invoice2Data.length > 0) {
          var newIdx = Math.min(idx, window.__invoice2Data.length - 1);
          window.__invoice2SelectedId = window.__invoice2Data[newIdx].id;
        } else {
          window.__invoice2SelectedId = 0;
        }
        renderInvoice2();
        updateInv2CheckedUI();
      }

      renderInvoice2();
      if (window.__invoice2SelectedId === 0 && window.__invoice2Data.length > 0) {
        window.__invoice2SelectedId = window.__invoice2Data[0].id;
        renderInvoice2();
      }
      updateInv2CheckedUI();

      var table = formBody.querySelector('.invoice2-table');
      var inv2ProductData = [];
      try { inv2ProductData = JSON.parse((table && table.dataset.products) || '[]'); } catch(e) {}
      InlineEdit.init({
        tbody: formBody.querySelector('.invoice2-table tbody'),
        saveUrl: saveUrl,
        fields: {
          product_id: { dbField: 'product_id', type: 'lookup', label: 'Товар' },
          quant:      { dbField: 'quant', type: 'text', label: 'Кол-во' },
          price:      { dbField: 'price', type: 'text', label: 'Цена' },
          discount:   { dbField: 'discount', type: 'text', label: 'Скидка' },
          note:       { dbField: 'note', type: 'textarea', label: 'Примечание' }
        },
        getLookupData: function (field) {
          if (field === 'product_id') return inv2ProductData;
          return [];
        },
        onSaveSuccess: function (data, field) {
          if (data && data.item && data.item.id) {
            var item = data.item;
            var found = false;
            window.__invoice2Data = window.__invoice2Data.map(function(it) {
              if (it.id == item.id) { found = true; return item; }
              return it;
            });
            if (!found) window.__invoice2Data.push(item);
            renderInvoice2();
          }
          applyInvoice2Totals(data);
        }
      });

      var addBtn = formBody.querySelector('#inv2-add-btn');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          if (!invId) return;
          var url = 'invoice2_form.php?mode=new&invoice_id=' + invId;
          openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
        });
      }

      var editBtn = formBody.querySelector('#inv2-edit-btn');
      if (editBtn) editBtn.addEventListener('click', function() { if (window.__invoice2SelectedId) onEdit(window.__invoice2SelectedId); });

      var delBtn = formBody.querySelector('#inv2-del-btn');
      if (delBtn) {
        delBtn.addEventListener('click', function() {
          var sid = window.__invoice2SelectedId;
          if (!sid) return;
          var url = 'invoice2_form.php?mode=delete&id=' + sid + '&invoice_id=' + invId;
          openFormModal(url, { onRestore: onItemDeleted, activeId: null });
        });
      }

      var copyBtn = formBody.querySelector('#inv2-copy-btn');
      if (copyBtn) {
        copyBtn.addEventListener('click', function() {
          var sid = window.__invoice2SelectedId;
          if (!sid) return;
          var url = 'invoice2_form.php?mode=copy&id=' + sid + '&invoice_id=' + invId;
          openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
        });
      }

      var productBtn = formBody.querySelector('#inv2-product-btn');
      if (productBtn) {
        productBtn.addEventListener('click', function() {
          var sid = window.__invoice2SelectedId;
          if (!sid) return;
          var item = window.__invoice2Data.find(function(it) { return it.id === sid; });
          var productId = item ? (item.product_id || 0) : 0;
          if (!productId) return;
          openFormModal('tmc_form.php?mode=edit&id=' + productId, { onRestore: function() { refreshInvoice2Data(); } });
        });
      }

      var refreshBtn = formBody.querySelector('#inv2-refresh-btn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
          var fd = new FormData();
          fd.set('field', '_list');
          fd.set('invoice_id', String(invId));
          fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data && Array.isArray(data)) {
                window.__invoice2Data = data;
                renderInvoice2();
              }
            });
        });
      }

      var importBtn = formBody.querySelector('#inv2-import-btn');
      if (importBtn) {
        importBtn.addEventListener('click', function() {
          var fd = new FormData();
          fd.set('field', '_import_marked_count');
          fd.set('invoice_id', String(invId));
          fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (!data || !data.ok) return;
              var cnt = data.count || 0;
              if (cnt === 0) {
                var indicator = document.createElement('div');
                indicator.className = 'flash flash--error';
                indicator.textContent = 'Нет отмеченных товаров';
                var form = formBody.querySelector('form');
                if (form) form.insertBefore(indicator, form.firstChild);
                setTimeout(function() { if (indicator.parentNode) indicator.parentNode.removeChild(indicator); }, 3000);
                return;
              }
              if (!confirm('Вставить в счёт ' + cnt + ' отмеченных товаров?')) return;
              var fd2 = new FormData();
              fd2.set('field', '_import_marked');
              fd2.set('invoice_id', String(invId));
              fetch(saveUrl, { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                  if (data && data.ok) {
                    var msg = 'Импортировано товаров: ' + (data.inserted || 0);
                    refreshBtn.click();
                    applyInvoice2Totals(data);
                    var indicator = document.createElement('div');
                    indicator.className = 'flash flash--success';
                    indicator.textContent = msg;
                    var form = formBody.querySelector('form');
                    if (form) form.insertBefore(indicator, form.firstChild);
                    setTimeout(function() { if (indicator.parentNode) indicator.parentNode.removeChild(indicator); }, 3000);
                  }
                });
            });
        });
      }

      /* Batch actions */
      var selClearBtn = formBody.querySelector('#inv2-sel-clear');
      if (selClearBtn) selClearBtn.addEventListener('click', function(e) { e.preventDefault(); clearChecked(); });
      var selInvertBtn = formBody.querySelector('#inv2-sel-invert');
      if (selInvertBtn) selInvertBtn.addEventListener('click', function(e) { e.preventDefault(); invertChecked(); });
      var selShowBtn = formBody.querySelector('#inv2-sel-show');
      if (selShowBtn) selShowBtn.addEventListener('click', function(e) { e.preventDefault(); toggleInv2ShowOnly(); });
      var selExportBtn = formBody.querySelector('#inv2-sel-export');
      if (selExportBtn) selExportBtn.addEventListener('click', function(e) { e.preventDefault(); exportInv2Checked(); });
      var selPrintBtn = formBody.querySelector('#inv2-sel-print');
      if (selPrintBtn) selPrintBtn.addEventListener('click', function(e) { e.preventDefault(); printInv2Checked(); });
      var selDelBtn = formBody.querySelector('#inv2-sel-delete');
      if (selDelBtn) selDelBtn.addEventListener('click', function(e) { e.preventDefault(); batchDeleteChecked(); });

      /* Search */
      var searchInput = formBody.querySelector('#inv2-search-input');
      var searchBtn = formBody.querySelector('#inv2-search-btn');
      var clearFilterBtn = formBody.querySelector('#inv2-clear-filter-btn');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          if (!searchActive) return;
          searchText = searchInput.value;
          currentPage = 1;
          renderInvoice2();
        });
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            searchActive = true;
            searchText = searchInput.value.trim();
            searchBtn.classList.add('active');
            currentPage = 1;
            renderInvoice2();
          }
        });
      }
      if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', function() {
          searchActive = false;
          searchText = '';
          searchCols = new Set(INV2_COL_KEYS);
          searchCond = 'contains';
          if (searchBtn) searchBtn.classList.remove('active');
          if (searchInput) searchInput.value = '';
          currentPage = 1;
          renderInvoice2();
          document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        });
      }
      function updateClearFilterBtn() {
        if (clearFilterBtn) {
          clearFilterBtn.style.display = (searchActive && searchText) ? '' : 'none';
        }
      }
      var _origRender = renderInvoice2;
      renderInvoice2 = function() {
        _origRender();
        updateClearFilterBtn();
      };
      function closeInv2Panels() {
        document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        document.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
      }
      var searchCondBtn = formBody.querySelector('#inv2-search-cond-btn');
      if (searchBtn && searchCondBtn && typeof SearchPanel !== 'undefined') {
        var ns = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(ns, searchBtn);
        searchBtn = ns;
        var nc = searchCondBtn.cloneNode(true);
        searchCondBtn.parentNode.replaceChild(nc, searchCondBtn);
        searchCondBtn = nc;
        SearchPanel.init({
          form: formBody.querySelector('#inv2-search-form'),
          condBtn: searchCondBtn,
          toggleBtn: searchBtn,
          columns: INV2_SEARCH_COLS,
          pageUrl: location.href,
          popupCheckboxes: true,
          emptyClass: 'search-cond-placeholder',
          closeAllPanels: closeInv2Panels,
          labels: {
            cols: 'Столбцы',
            cond: 'Условие',
            emptyCols: 'Выберите столбцы…'
          },
          onApply: function(state) {
            searchCols = state.cols;
            searchCond = state.cond;
            searchActive = true;
            searchText = searchInput ? searchInput.value.trim() : '';
            searchBtn.classList.add('active');
            currentPage = 1;
            renderInvoice2();
            closeInv2Panels();
          },
          onToggle: function(state) {
            if (searchActive) {
              searchActive = false;
              searchText = '';
              if (searchInput) searchInput.value = '';
              searchBtn.classList.remove('active');
            } else {
              searchActive = true;
              searchText = searchInput ? searchInput.value.trim() : '';
              searchBtn.classList.add('active');
            }
            currentPage = 1;
            renderInvoice2();
          }
        });
      }
 
      /* Sort */
      var updateSortIndicators = function() {
        table.querySelectorAll('thead th[data-col] .sort-indicator').forEach(function(s) { s.remove(); });
        if (sortCol >= 0) {
          var th = table.querySelectorAll('thead th[data-col]')[sortCol];
          if (th) {
            var ind = document.createElement('span');
            ind.className = 'sort-indicator';
            ind.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
            th.appendChild(ind);
          }
        }
      };
      table.querySelectorAll('thead th[data-col]').forEach(function(th) {
        th.addEventListener('click', function(e) {
          var colIdx = Array.from(th.parentNode.querySelectorAll('th[data-col]')).indexOf(th);
          if (sortCol === colIdx) {
            sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
          } else {
            sortCol = colIdx;
            sortDir = 'asc';
          }
          currentPage = 1;
          updateSortIndicators();
          renderInvoice2();
        });
      });
      var sortBtn = formBody.querySelector('#inv2-sort-btn');
      if (sortBtn) {
        sortBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          closeInv2Panels();
          var cols = INV2_SEARCH_COLS;
          var directions = [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }];
          var levels = sortCol >= 0
            ? [{ col: INV2_COL_KEYS[sortCol], dir: sortDir }]
            : [{ col: 'product_name', dir: 'asc' }];

          function colLabel(k) { for (var i = 0; i < cols.length; i++) if (cols[i].key === k) return cols[i].label; return k; }
          function dirLabel(k) { for (var i = 0; i < directions.length; i++) if (directions[i].key === k) return directions[i].label; return k; }
          function serialize(lvs) { return lvs.map(function(l) { return l.col + ':' + l.dir; }).join(','); }
          function usedCols(lvs, exceptIdx) { var u = []; lvs.forEach(function(l, i) { if (i !== exceptIdx) u.push(l.col); }); return u; }

          var backdrop = document.createElement('div');
          backdrop.className = 'sort-modal-backdrop';
          backdrop.innerHTML =
            '<div class="sort-modal" role="dialog" aria-labelledby="inv2SortTitle">' +
              '<div class="sort-modal-header">' +
                '<span id="inv2SortTitle">Сортировка</span>' +
                '<button class="sort-modal-close" type="button" title="Закрыть">✕</button>' +
              '</div>' +
              '<div class="sort-modal-body">' +
                '<div class="sort-level-actions">' +
                  '<button type="button" class="sort-add-level" id="inv2SortAddBtn">+ Добавить уровень</button>' +
                  '<button type="button" class="sort-remove-level" id="inv2SortRemoveBtn">− Удалить уровень</button>' +
                '</div>' +
                '<div class="sort-levels">' +
                  '<div class="sort-cols">' +
                    '<div class="sort-cols-header">Столбец</div>' +
                    '<div class="sort-cols-list" id="inv2SortColsList"></div>' +
                  '</div>' +
                  '<div class="sort-dirs">' +
                    '<div class="sort-dirs-header">Направление</div>' +
                    '<div class="sort-dirs-list" id="inv2SortDirsList"></div>' +
                  '</div>' +
                '</div>' +
                '<div class="sort-modal-actions">' +
                   '<button type="button" class="sort-apply" id="inv2SortApplyBtn"><img src="img/ok.png" alt="" />Сортировать</button>' +
                   '<button type="button" class="sort-cancel" id="inv2SortCancelBtn"><img src="img/cancel.png" alt="" />Отменить</button>' +
                '</div>' +
              '</div>' +
            '</div>';
          document.body.appendChild(backdrop);

          var modal = backdrop.querySelector('.sort-modal');
          var colsList = backdrop.querySelector('#inv2SortColsList');
          var dirsList = backdrop.querySelector('#inv2SortDirsList');
          var addBtn = backdrop.querySelector('#inv2SortAddBtn');
          var removeBtn = backdrop.querySelector('#inv2SortRemoveBtn');
          var cancelBtn = backdrop.querySelector('#inv2SortCancelBtn');
          var applyBtn = backdrop.querySelector('#inv2SortApplyBtn');
          var closeBtn = backdrop.querySelector('.sort-modal-close');

          var pop = document.createElement('div');
          pop.className = 'sort-pop';
          document.body.appendChild(pop);

          function positionPopup(target, p) {
            var r = target.getBoundingClientRect();
            var pw = p.offsetWidth || 320;
            var ph = p.offsetHeight;
            var left = r.left;
            if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
            var top = r.bottom + 4;
            if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
            p.style.left = left + 'px';
            p.style.top = top + 'px';
          }

          function closePop() { pop.classList.remove('open'); pop.dataset.kind = ''; }
          function closeModal() { backdrop.classList.remove('open'); closePop(); }

          function render() {
            colsList.innerHTML = '';
            dirsList.innerHTML = '';
            addBtn.disabled = levels.length >= cols.length;
            removeBtn.disabled = levels.length <= 1;
            levels.forEach(function(l, i) {
              var cRow = document.createElement('div');
              cRow.className = 'sort-row';
              var cLabel = document.createElement('span');
              cLabel.className = 'sort-label';
              cLabel.textContent = i === 0 ? 'Сначала по' : 'Затем по';
              var cSel = document.createElement('div');
              cSel.className = 'sort-select';
              cSel.tabIndex = 0;
              cSel.innerHTML = '<span class="sort-select-label">' + colLabel(l.col) + '</span>';
              cSel.addEventListener('click', function(idx) {
                return function(e) { e.stopPropagation(); openColPop(idx, cSel); };
              }(i));
              cRow.appendChild(cLabel);
              cRow.appendChild(cSel);
              colsList.appendChild(cRow);

              var dRow = document.createElement('div');
              dRow.className = 'sort-row';
              dRow.style.gap = '0';
              var dSel = document.createElement('div');
              dSel.className = 'sort-select';
              dSel.tabIndex = 0;
              dSel.innerHTML = '<span class="sort-select-label">' + dirLabel(l.dir) + '</span>';
              dSel.addEventListener('click', function(idx) {
                return function(e) { e.stopPropagation(); openDirPop(idx, dSel); };
              }(i));
              dRow.appendChild(dSel);
              dirsList.appendChild(dRow);
            });
          }

          function openColPop(idx, target) {
            var used = usedCols(levels, idx);
            pop.innerHTML = '';
            cols.forEach(function(c) {
              var isSel = levels[idx].col === c.key;
              var isUsed = used.indexOf(c.key) !== -1;
              var item = document.createElement('div');
              item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
              item.textContent = c.label;
              if (isUsed && !isSel) {
                item.style.opacity = '0.45';
                item.style.cursor = 'default';
              } else {
                item.addEventListener('click', function(colKey) {
                  return function(e) {
                    e.stopPropagation();
                    levels[idx].col = colKey;
                    closePop();
                    render();
                  };
                }(c.key));
              }
              pop.appendChild(item);
            });
            pop.classList.add('open');
            pop.dataset.kind = 'col';
            positionPopup(target, pop);
          }

          function openDirPop(idx, target) {
            pop.innerHTML = '';
            directions.forEach(function(d) {
              var isSel = levels[idx].dir === d.key;
              var item = document.createElement('div');
              item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
              item.textContent = d.label;
              item.addEventListener('click', function(dirKey) {
                return function(e) {
                  e.stopPropagation();
                  levels[idx].dir = dirKey;
                  closePop();
                  render();
                };
              }(d.key));
              pop.appendChild(item);
            });
            pop.classList.add('open');
            pop.dataset.kind = 'dir';
            positionPopup(target, pop);
          }

          addBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (levels.length >= cols.length) return;
            var used = usedCols(levels, -1);
            var available = [];
            cols.forEach(function(c) { if (used.indexOf(c.key) === -1) available.push(c); });
            if (available.length === 0) return;
            levels.push({ col: available[0].key, dir: 'asc' });
            render();
          });

          removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (levels.length <= 1) return;
            levels.pop();
            render();
          });

          cancelBtn.addEventListener('click', function(e) { e.stopPropagation(); closeModal(); });
          closeBtn.addEventListener('click', function(e) { e.stopPropagation(); closeModal(); });

          applyBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (levels.length > 0) {
              var col = levels[0].col;
              var idx = INV2_COL_KEYS.indexOf(col);
              sortCol = idx >= 0 ? idx : 0;
              sortDir = levels[0].dir;
              currentPage = 1;
              renderInvoice2();
              table.querySelectorAll('thead th[data-col] .sort-indicator').forEach(function(s) { s.remove(); });
              var ths = table.querySelectorAll('thead th[data-col]');
              if (ths[sortCol]) {
                var ind = document.createElement('span');
                ind.className = 'sort-indicator';
                ind.textContent = sortDir === 'asc' ? ' ↑' : ' ↓';
                ths[sortCol].appendChild(ind);
              }
            }
            closeModal();
          });

          document.addEventListener('click', function(e) {
            if (!backdrop.classList.contains('open')) return;
            if (e.target.closest('.sort-modal, .sort-pop.open')) return;
            e.stopPropagation();
            closeModal();
          });

          document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
          });

          render();
          backdrop.classList.add('open');
        });
      }

      updateInv2Buttons();
      /* ColumnsPanel for invoice2 */
      var inv2ColBtn = formBody.querySelector('#inv2-columns-btn');
      if (inv2ColBtn && window.ColumnsPanel) {
        var inv2ColData = [];
        var inv2ColDefData = [];
        try { inv2ColData = JSON.parse(table.dataset.columns || '[]'); } catch(e) {}
        try { inv2ColDefData = JSON.parse(table.dataset.columnsDefaults || '[]'); } catch(e) {}
        ColumnsPanel.init({
          btn: inv2ColBtn,
          saveUrl: 'invoice2_columns_save.php',
          tbl: 'invoice2',
          closeAllPanels: closeInv2Panels,
          initialColumns: inv2ColData,
          defaultColumns: inv2ColDefData.length > 0 ? inv2ColDefData : inv2ColData,
          onSave: function(state) {
            var fieldMap = { product_name: 'product_id' };
            state.forEach(function(c) {
              var visible = c.visible !== false;
              formBody.querySelectorAll('.invoice2-table .col-' + c.name + ', .invoice2-table th[data-col="' + c.name + '"]').forEach(function(el) {
                el.style.display = visible ? '' : 'none';
              });
              var bodyField = fieldMap[c.name] || c.name;
              formBody.querySelectorAll('.invoice2-table tbody [data-field="' + bodyField + '"]').forEach(function(td) {
                td.style.display = visible ? '' : 'none';
              });
            });
            closeInv2Panels();
          },
          onResetWidth: function() {
            closeInv2Panels();
            var ie = formBody.querySelector('input[name="id"]');
            var iid = ie ? parseInt(ie.value, 10) : 0;
            if (!iid) return;
            fetch('invoice_form.php?mode=edit&id=' + iid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d || !d.html) return;
                var ai = 0; var at = formBody.querySelector('.tab-header.active');
                if (at) ai = parseInt(at.dataset.tabIndex, 10);
                formBody.innerHTML = d.html;
                initFormLookups(); initFormTabs();
                try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); }
                initInvoice2Form();
                try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
                var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f);
                var hdr = formBody.querySelector('.tab-header[data-tab-index="' + ai + '"]');
                var pane = formBody.querySelector('.tab-pane[data-tab-index="' + ai + '"]');
                if (hdr && pane) { formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); }); formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); }); hdr.classList.add('active'); pane.classList.add('active'); }
              });
          },
          onResetOrder: function() {
            closeInv2Panels();
            var ie = formBody.querySelector('input[name="id"]');
            var iid = ie ? parseInt(ie.value, 10) : 0;
            if (!iid) return;
            fetch('invoice_form.php?mode=edit&id=' + iid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d || !d.html) return;
                var ai = 0; var at = formBody.querySelector('.tab-header.active');
                if (at) ai = parseInt(at.dataset.tabIndex, 10);
                formBody.innerHTML = d.html;
                initFormLookups(); initFormTabs();
                try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); }
                initInvoice2Form();
                try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
                var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f);
                var hdr = formBody.querySelector('.tab-header[data-tab-index="' + ai + '"]');
                var pane = formBody.querySelector('.tab-pane[data-tab-index="' + ai + '"]');
                if (hdr && pane) { formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); }); formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); }); hdr.classList.add('active'); pane.classList.add('active'); }
              });
          }
        });
      }
    }

    function restoreStashedForm(data) {
      if (!stashed) return;
      formBody.innerHTML = stashed.html;
      if (stashed.invoice2Data) { window.__invoice2Data = stashed.invoice2Data; }
      initFormLookups();
      initFormTabs();
      try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); }
      initInvoice2Form();
      var pc = formBody.querySelector('.tab-pane[data-tab-index="2"]');
      if (pc) delete pc.dataset.platInited;
      try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
      var old = stashed;
      stashed = null;
      window.invoice2Dirty = old.invoice2Dirty || false;
      var f = formBody.querySelector('form[data-form-modal]');
      bindForm(f);
      FormModalCore.bindFormTabTrap(f);
      if (data) try { old.onRestore(data, formBody); } catch (e) {}
      if (old.activeId) {
        var el = formBody.querySelector('#' + old.activeId);
        if (el && !el.readOnly) { el.focus(); if (el.select) el.select(); }
      } else {
        FormModalCore.focusFirstField(formBody);
        setTimeout(function() {
          if (!formBody.contains(document.activeElement)) {
            var sb = formBody.querySelector('button[type="submit"]:not([tabindex="-1"]):not([disabled])');
            if (sb) sb.focus();
          }
        }, 0);
      }
    }

    function openFormModal(url, stash) {
      closeAllPanels();
      if (stash && formBody.innerHTML) { stashed = { html: formBody.innerHTML, onRestore: stash.onRestore || null, activeId: stash.activeId || null, invoice2Dirty: window.invoice2Dirty, invoice2Data: window.__invoice2Data ? window.__invoice2Data.slice() : [] }; } else if (!formBody.innerHTML) { stashed = null; }
      formModal.classList.add('open');
      document.body.style.overflow = 'hidden';
      fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || typeof data.html !== 'string') throw new Error('bad response');
          formBody.innerHTML = data.html;
          initFormLookups();
          initFormTabs();
          try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); }
          initInvoice2Form();
          try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
          initPlatFormSumCalc();
          var form = formBody.querySelector('form[data-form-modal]');
          bindForm(form);
          FormModalCore.bindFormTabTrap(form);
          FormModalCore.focusFirstField(formBody);
          setTimeout(function() {
            if (!formBody.contains(document.activeElement)) {
              var sb = formBody.querySelector('button[type="submit"]:not([tabindex="-1"]):not([disabled])');
              if (sb) sb.focus();
            }
          }, 0);
        })
        .catch(function (err) {
          formBody.innerHTML = '<div class="flash flash--error">Ошибка подключения к БД</div>';
        });
    }
    function closeFormModal() {
      formModal.classList.remove('open');
      document.body.style.overflow = '';
      formBody.innerHTML = '';
      stashed = null;
      window.invoice2Dirty = false;
      window.__invoice2SelectedId = 0;
      window.__inv2CheckedIds = new Set();
    }
    function bindForm(form) {
      if (!form) return;
      form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          if (document.activeElement && document.activeElement.closest('[data-row-id]')) return;
          var submitBtn = form.querySelector('button[type="submit"]');
          if (submitBtn && !submitBtn.disabled) { e.preventDefault(); submitBtn.click(); }
        }
      });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(form);
        fd.set('ajax', '1');
        const submitBtn = e.submitter || form.querySelector('button[type="submit"]');
        if (submitBtn && submitBtn.name) fd.set(submitBtn.name, submitBtn.value || '1');
        fetch(form.getAttribute('action') || 'invoice_form.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
              window.invoice2Dirty = false;
              if (stashed) { restoreStashedForm(data); } else {
                if (submitBtn && submitBtn.name === 'action' && submitBtn.value === 'apply') {
                  applyInvoice2Totals(data);
                } else {
                  closeFormModal();
                  const params = new URLSearchParams(location.search);
                  FormModalCore.setFocusAfterSave(params, form, data);
                  location.href = 'invoice.php?' + params.toString();
                }
              }
            } else { window.invoice2Dirty = false; formBody.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>'; initFormLookups(); initFormTabs(); try { initInvoice2Items(); } catch(e) { console.error('initInvoice2Items', e); } initInvoice2Form(); try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); } initPlatFormSumCalc(); var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f); if (data && data.focusField) { var el = formBody.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } } else { FormModalCore.focusFirstField(formBody); } }
          }).catch(function (err) {
            const flash = document.createElement('div'); flash.className = 'flash flash--error'; flash.textContent = 'Ошибка подключения к БД: ' + (err && err.message ? err.message : 'unknown'); form.insertBefore(flash, form.firstChild);
          });
      });
      var discBtn = form.querySelector('#apply-discount-btn');
      if (discBtn) {
        discBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var discountEl = form.querySelector('#inv-discount');
          var discount = discountEl ? parseFloat(discountEl.value.replace(',', '.')) : 0;
          if (isNaN(discount)) discount = 0;
          if (!confirm('Изменить скидку у всех товаров счета на ' + discount + ' %?')) return;
          var invIdEl = form.querySelector('input[name="id"]');
          var invoiceId = invIdEl ? parseInt(invIdEl.value, 10) : 0;
          if (!invoiceId) return;
          var fd = new FormData();
          fd.set('field', '_apply_discount');
          fd.set('invoice_id', String(invoiceId));
          fd.set('discount', String(discount));
          fetch('invoice2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data && data.ok) {
                applyInvoice2Totals(data);
                var f2 = new FormData();
                f2.set('field', '_list');
                f2.set('invoice_id', String(invoiceId));
                fetch('invoice2_field_save.php', { method: 'POST', body: f2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                  .then(function(r) { return r.json(); })
                  .then(function(list) {
                    if (Array.isArray(list)) {
                      window.__invoice2Data = list;
                      if (window.__invoice2Render) window.__invoice2Render();
                    }
                  });
              }
            });
        });
      }
    }

    window.openFormModal = openFormModal;
    window.__openFormModal = openFormModal;

    if (!window.__inv2ProductEditBound) {
      window.__inv2ProductEditBound = true;
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('#inv2-product-edit-btn');
        if (!btn) return;
        e.preventDefault();
        var pidInput = formBody.querySelector('[name="product_id"]');
        var pid = pidInput ? parseInt(pidInput.value, 10) : 0;
        if (!pid) return;
        openFormModal('tmc_form.php?mode=edit&id=' + pid, {
          onRestore: function (data, bodyEl) {
            if (!data || !data.ok) return;
            var root = bodyEl || formBody;
            setTimeout(function () {
              var h = root.querySelector('[name="product_id"]');
              if (h && data.id) h.value = data.id;
              var li = root.querySelector('.lookup-input');
              if (li && data.product_name) li.value = data.product_name;
            }, 100);
          }
        });
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && formModal.classList.contains('open')) {
        e.preventDefault();
        window.invoice2Dirty = false;
        if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); }
      }
    });

    document.addEventListener('click', function (e) {
      if (formModal.classList.contains('open')) {
        const cancelA = e.target.closest('a.btn-secondary');
        if (cancelA && cancelA.closest('.form-actions')) {
          e.preventDefault(); e.stopImmediatePropagation();
          window.invoice2Dirty = false;
          if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); } return;
        }
      }
      let a = e.target.closest('a[href*="invoice_form.php"]');
      if (a) { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return; e.preventDefault(); e.stopImmediatePropagation(); openFormModal(a.getAttribute('href')); return; }
      const trig = e.target.closest('[data-form-open]');
      if (trig) { e.preventDefault(); openFormModal(trig.getAttribute('data-form-open')); return; }
      if (e.target.closest('[data-form-close]')) { e.preventDefault(); window.invoice2Dirty = false; if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); } return; }
      if (e.target.closest('[data-lookup-add]')) { e.preventDefault(); var openFn = openFormModal; FormModalCore.handleLookupAdd(e.target.closest('[data-lookup-add]'), function (fn) { stashed = { html: formBody.innerHTML, onRestore: fn, activeId: document.activeElement ? document.activeElement.id : null, invoice2Dirty: window.invoice2Dirty, invoice2Data: window.__invoice2Data ? window.__invoice2Data.slice() : [] }; }, openFn); return; }
    });

    document.querySelectorAll('tbody tr').forEach(function (tr) {
      const id = parseInt(tr.dataset.rowId, 10);
      tr.addEventListener('click', function (e) { if (e.target.closest('input.row-check')) return; selectRow(id); });
      tr.addEventListener('dblclick', function () { openFormModal('invoice_form.php?mode=edit&id=' + id); });
    });

    document.getElementById('rowOpenBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) openFormModal('invoice_form.php?mode=edit&id=' + id); });
    document.getElementById('rowCopyBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) openFormModal('invoice_form.php?mode=copy&id=' + id); });
    document.getElementById('rowDeleteBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) openFormModal('invoice_form.php?mode=delete&id=' + id); });

    window.printSelectedInvoice = function () {
      const id = rowSel.getSelectedId();
      if (id === 0) { alert('Выберите строку для печати'); return; }
      window.open('invoice_print_template.php?id=' + id, '_blank');
    };

    const tableWrapEl = document.querySelector('.table-wrap');
    if (typeof bindTableKeyboardShortcuts === 'function') {
      bindTableKeyboardShortcuts({ formPrefix: 'invoice_form', rowSel: rowSel, currentPage: currentPage, currentPages: currentPages, navigate: navigate, tableWrapEl: tableWrapEl });
    }

    (function applyInitialFocus() {
      const rows = rowSel.getRows(); if (rows.length === 0) return;
      const raw = (toolbar.dataset.focus || '').toString();
      if (raw === 'first') { rowSel.selectByIndex(0); return; }
      if (raw === 'last') { rowSel.selectByIndex(rows.length - 1); return; }
      const id = parseInt(raw, 10);
      if (id > 0 && rowSel.selectById(id, false)) return;
      rowSel.selectByIndex(0);
    })();

    updateSelectionUI();
  })();

  InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'invoice_field_save.php',
    fields: <?php
      $inlineFields = [];
      $valCases = '';
      foreach ($visibleColumns as $vc) {
        $cn = $vc['name'];
        if (!empty($vc['readonly']) || $cn === 'id' || $cn === 'number' || $cn === 'sum') continue;
        if ($cn === 'date') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'text', 'label' => $vc['label']];
        } elseif ($cn === 'note') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'textarea', 'label' => $vc['label']];
        } elseif ($cn === 'state') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'select', 'label' => $vc['label'], 'options' => ['Черновик', 'Выставлен', 'Оплачен', 'Отменен']];
        } elseif ($cn === 'payment_type') {
          $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'select', 'label' => $vc['label'], 'options' => ['Наличные', 'Безнал.', 'Карта', 'Прочее']];
        } else {
          $isLookup = !empty($vc['param']);
          $inlineFields[$cn] = ['dbField' => $isLookup ? $vc['param'] : $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
          if ($isLookup) $valCases .= "    case " . json_encode($cn, JSON_UNESCAPED_UNICODE) . ": if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
      }
    ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
    validate: function (field, value) { switch (field) { <?= $valCases ?> } return null; },
    onOpenForm: window.__openFormModal,
    getLookupData: function (field) {
      switch (field) { case 'client': return __clientLookupData; case 'store': return __storeLookupData; case 'sotr': return __sotrLookupData; }
      return [];
    }
  });

  var __clientLookupData = <?= json_encode($clientFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
  var __storeLookupData = <?= json_encode($storeFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
  var __sotrLookupData = <?= json_encode($sotrFilterOptions, JSON_UNESCAPED_UNICODE) ?>;

  ColumnResize.init({ saveUrl: 'invoice_column_width_save.php', tbl: 'invoice' });
  ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'invoice.php' });
  ColumnFilter.init({ thSelector: '.col-store', pageUrl: 'invoice.php' });
  ColumnFilter.init({ thSelector: '.col-sotr', pageUrl: 'invoice.php' });
  ColumnFilter.init({ thSelector: '.col-state', pageUrl: 'invoice.php', param: 'state' });
  ExportModal.init();

  (function() {
    var cp = new URLSearchParams(window.location.search).get('client_id');
    if (cp) {
      var addBtn = document.querySelector('[data-form-open*="invoice_form.php?mode=new"]');
      if (addBtn) addBtn.setAttribute('data-form-open', 'invoice_form.php?mode=new&client_id=' + cp);
    }
  })();
  </script>
</body>
</html>
