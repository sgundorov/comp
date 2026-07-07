<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invoice_columns.php';
require_once __DIR__ . '/config/invoice_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';

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
$stateFilterIds = [];
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
    $stateFilterNames = array_map(fn($id) => $stateIdToValue[$id] ?? ('#' . $id), $stateFilterIds);
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
if (count($stateFilterIds) > 0) {
    $stateNames = array_map(fn($id) => $stateIdToValue[$id] ?? ('#' . $id), $stateFilterIds);
    $filters[] = ['kind' => 'state', 'text' => 'Состояние = ' . implode(', ', $stateNames), 'clear' => 'state'];
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
render_script_includes(['scripts' => ['assets/lookup.js', 'assets/column-filter.js', 'assets/export-modal.js', 'assets/columns-panel.js', 'assets/column-resize.js', 'assets/embedded-table.js', 'assets/embedded-subtable.js']]);
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
        });
      });
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

     function restoreStashedForm(data) {
      if (!stashed) return;
      formBody.innerHTML = stashed.html;
      initFormLookups();
      initFormTabs();
      try { initInv2Table(); } catch(e) { console.error('initInv2Table', e); }
      initInvoice2Form();
      try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
      evalFormScripts();
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
      if (stash && formBody.innerHTML) { stashed = { html: formBody.innerHTML, onRestore: stash.onRestore || null, activeId: stash.activeId || null, invoice2Dirty: window.invoice2Dirty }; } else if (!formBody.innerHTML) { stashed = null; }
      formModal.classList.add('open');
      document.body.style.overflow = 'hidden';
      fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || typeof data.html !== 'string') throw new Error('bad response');
          formBody.innerHTML = data.html;
          initFormLookups();
          initFormTabs();
          try { initInv2Table(); } catch(e) { console.error('initInv2Table', e); }
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
            } else { window.invoice2Dirty = false; formBody.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>'; initFormLookups(); initFormTabs(); try { initInv2Table(); } catch(e) { console.error('initInv2Table', e); } initInvoice2Form(); try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); } initPlatFormSumCalc(); var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f); if (data && data.focusField) { var el = formBody.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } } else { FormModalCore.focusFirstField(formBody); } }
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
                      var inv2Table = window['__inv2Table'];
                      if (inv2Table) inv2Table.setData(list);
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
      if (e.target.closest('[data-lookup-add]')) { e.preventDefault(); var openFn = openFormModal; FormModalCore.handleLookupAdd(e.target.closest('[data-lookup-add]'), function (fn) { stashed = { html: formBody.innerHTML, onRestore: fn, activeId: document.activeElement ? document.activeElement.id : null, invoice2Dirty: window.invoice2Dirty }; }, openFn); return; }
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
  ColumnFilter.init({ thSelector: '.col-state', pageUrl: 'invoice.php', param: 'state', options: [{id:1,name:'Черновик'},{id:2,name:'Выставлен'},{id:3,name:'Оплачен'},{id:4,name:'Отменен'}] });
  ExportModal.init();

  (function() {
    var cp = new URLSearchParams(window.location.search).get('client_id');
    if (cp) {
      var addBtn = document.querySelector('[data-form-open*="invoice_form.php?mode=new"]');
      if (addBtn) addBtn.setAttribute('data-form-open', 'invoice_form.php?mode=new&client_id=' + cp);
    }
  })();

<?php
$inv2Table = new EmbeddedTable([
    'prefix' => 'inv2',
    'saveUrl' => 'invoice2_field_save.php',
    'parentField' => 'invoice_id',
    'childFormUrl' => 'invoice2_form.php',
    'childFormName' => 'Invoice2Form',
    'pageSize' => 15,
    'hasExport' => true,
    'hasPrint' => true,
    'hasImport' => true,
    'hasSearch' => true,
    'readonly' => false,
    'totalsCallback' => 'applyInvoice2Totals',
    'columns' => [
        ['name' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'param' => 'product_id'],
        ['name' => 'quant', 'label' => 'Кол-во', 'type' => 'text'],
        ['name' => 'price', 'label' => 'Цена', 'type' => 'text'],
        ['name' => 'discount', 'label' => 'Скидка', 'type' => 'text'],
        ['name' => 'sum', 'label' => 'Сумма', 'type' => 'text', 'readonly' => true],
        ['name' => 'note', 'label' => 'Примечание', 'type' => 'textarea'],
    ],
    'columnLabels' => [
        'product_name' => 'Товар',
        'quant' => 'Кол-во',
        'price' => 'Цена',
        'discount' => 'Скидка',
        'sum' => 'Сумма',
        'note' => 'Примечание',
    ],
]);
$platTable = new EmbeddedTable([
    'prefix' => 'plat',
    'saveUrl' => 'plat_field_save.php',
    'parentField' => 'doc_id',
    'childFormUrl' => 'plat_form.php?doc_type=10',
    'childFormName' => 'PlatForm',
    'pageSize' => 15,
    'hasExport' => true,
    'hasPrint' => true,
    'hasSearch' => true,
    'readonly' => false,
    'totalsCallback' => 'applyInvoice2Totals',
    'columns' => [
        ['name' => 'datetime', 'label' => 'Дата/Время'],
        ['name' => 'client_name', 'label' => 'Контрагент'],
        ['name' => 'zat_name', 'label' => 'Вид операции'],
        ['name' => 'sum', 'label' => 'Сумма'],
        ['name' => 'out_flag', 'label' => 'Тип'],
        ['name' => 'note', 'label' => 'Примечание', 'type' => 'textarea'],
    ],
    'columnLabels' => [
        'datetime' => 'Дата/Время',
        'client_name' => 'Контрагент',
        'zat_name' => 'Вид операции',
        'sum' => 'Сумма',
        'out_flag' => 'Тип',
        'note' => 'Примечание',
    ],
]);
$inv2Table->renderScripts();
$platTable->renderScripts();
?>
  </script>
</body>
</html>
