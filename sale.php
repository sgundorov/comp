<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sale_columns.php';
require_once __DIR__ . '/config/sale_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$typeop = (int)($_GET['typeop'] ?? 120);
if (!in_array($typeop, [10, 20, 100, 110, 120, 127], true)) $typeop = 120;

$prihodFlag = 0;
$pfRow = $conn->query("SELECT COALESCE(prihod_flag,0) AS pf FROM typeop WHERE typeop_id = $typeop")->fetch_assoc();
if ($pfRow) $prihodFlag = (int)$pfRow['pf'];

$DOC_LABELS = [10 => 'Возврат от покупателя', 20 => 'Приход', 100 => 'Внутреннее перемещение', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];
$DOC_ICONS  = [10 => 'sale.png', 20 => 'prihod.png', 100 => 'move.png', 110 => 'prihod.png', 120 => 'sale.png', 127 => 'spisan.png'];
$PAGE_TITLE  = $DOC_LABELS[$typeop];
$PAGE_ICON   = $DOC_ICONS[$typeop];

$salePageConfig['marks_session'] = 'sale_select_' . $typeop;
$salePageConfig['marks_tbl'] = 'docum_' . $typeop;

ensure_marks_table($conn);
@mysqli_query($conn, "DELETE m FROM marks m LEFT JOIN docum d ON d.docum_id = m.row_id WHERE m.tbl = 'docum_$typeop' AND d.docum_id IS NULL");
@mysqli_query($conn, "DELETE FROM marks WHERE tbl = 'sale'");

$tp = new TablePage($conn, $salePageConfig);
$tp->appendWhere("d.typeop = ?", [$typeop], "i");

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$hideForSpisanie = in_array($typeop, [127, 100]) ? ['sum_plat', 'client', 'discount'] : [];
if ($hideForSpisanie) {
    $visibleColumns = array_values(array_filter($visibleColumns, function($c) use ($hideForSpisanie) {
        return !in_array($c['name'], $hideForSpisanie);
    }));
}
if ($typeop !== 100) {
    $visibleColumns = array_values(array_filter($visibleColumns, function($c) {
        return $c['name'] !== 'store2';
    }));
}
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'sale');

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
handle_marks_actions($conn, $tp, $salePageConfig['marks_tbl'], function($action) use ($conn) {
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
            $clean[] = ['name' => $name, 'visible' => !empty($row['visible']) ? 1 : 0, 'order' => (int)($row['order'] ?? $i)];
        }
    }
    foreach ($COLUMN_DEFAULTS as $i => $c) {
        if (!isset($seen[$c['name']])) $clean[] = ['name' => $c['name'], 'visible' => 1, 'order' => count($clean) + $i];
    }
    usort($clean, function ($a, $b) { return $a['order'] - $b['order']; });
    $ok = save_columns_config($conn, 'sale', $clean);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['action'] ?? '') === 'acceptToggle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $conn->begin_transaction();
        $stmt = $conn->prepare("SELECT accept_flag, store_id, store2_id, typeop FROM docum WHERE docum_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) {
            $oldFlag   = (int)$r['accept_flag'];
            $newFlag   = (int)(!$oldFlag);
            $storeId   = (int)$r['store_id'];
            $store2Id  = (int)$r['store2_id'];
            $docTypeop = (int)$r['typeop'];

            $stmt = $conn->prepare("UPDATE docum SET accept_flag = ? WHERE docum_id = ?");
            $stmt->bind_param('ii', $newFlag, $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE docum2 SET accept_flag = ? WHERE docum_id = ?");
            $stmt->bind_param('ii', $newFlag, $id);
            $stmt->execute();
            $stmt->close();

            $itemsStmt = $conn->prepare("SELECT DISTINCT product_id FROM docum2 WHERE docum_id = ?");
            $itemsStmt->bind_param('i', $id);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            $productIds = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $productIds[] = (int)$item['product_id'];
            }
            $itemsStmt->close();

            if (!empty($productIds)) {
                $calcStmt = $conn->prepare("
                    SELECT COALESCE(SUM(d2.quant * IF(tp.prihod_flag = 1, 1, -1)), 0) AS total
                    FROM docum2 d2
                    JOIN docum d ON d.docum_id = d2.docum_id
                    LEFT JOIN typeop tp ON tp.typeop_id = d2.typeop
                    WHERE d2.accept_flag != 0
                      AND d2.product_id = ?
                      AND d.store_id = ?
                ");
                $upsertStmt = $conn->prepare("
                    INSERT INTO residue (wh_id, product_id, quant) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quant = VALUES(quant)
                ");
                foreach ($productIds as $pid) {
                    $calcStmt->bind_param('ii', $pid, $storeId);
                    $calcStmt->execute();
                    $calcRes = $calcStmt->get_result();
                    $total = (float)$calcRes->fetch_row()[0];
                    $calcRes->close();
                    $upsertStmt->bind_param('iid', $storeId, $pid, $total);
                    $upsertStmt->execute();
                }
                $calcStmt->close();
                $upsertStmt->close();

                if ($docTypeop === 100 && $store2Id > 0) {
                    $calcStmt2 = $conn->prepare("
                        SELECT COALESCE(SUM(d2.quant), 0) AS total
                        FROM docum2 d2
                        JOIN docum d ON d.docum_id = d2.docum_id
                        WHERE d2.accept_flag != 0
                          AND d2.product_id = ?
                          AND d.store2_id = ?
                          AND d.typeop = 100
                    ");
                    $upsertStmt2 = $conn->prepare("
                        INSERT INTO residue (wh_id, product_id, quant) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE quant = quant + VALUES(quant)
                    ");
                    foreach ($productIds as $pid) {
                        $calcStmt2->bind_param('ii', $pid, $store2Id);
                        $calcStmt2->execute();
                        $calcRes2 = $calcStmt2->get_result();
                        $total2 = (float)$calcRes2->fetch_row()[0];
                        $calcRes2->close();
                        $upsertStmt2->bind_param('iid', $store2Id, $pid, $total2);
                        $upsertStmt2->execute();
                    }
                    $calcStmt2->close();
                    $upsertStmt2->close();
                }

                $updProd = $conn->prepare("UPDATE product SET residue = (SELECT COALESCE(SUM(quant), 0) FROM residue WHERE product_id = ?), quantall = (SELECT COALESCE(SUM(quant), 0) FROM residue WHERE product_id = ?) WHERE product_id = ?");
                if ($updProd) {
                    foreach ($productIds as $pid) {
                        $updProd->bind_param('iii', $pid, $pid, $pid);
                        $updProd->execute();
                    }
                    $updProd->close();
                }
            }

            $conn->commit();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

// ---- column filters ----
$clientFilter = (string)($_GET['client_id'] ?? '');
$clientFilterIds = []; $clientFilterNames = [];
$storeFilter = (string)($_GET['store_id'] ?? '');
$storeFilterIds = []; $storeFilterNames = [];

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
    $tp->appendWhere("d.client_id IN ($ph)", $clientFilterIds, str_repeat('i', count($clientFilterIds)));
}

if ($storeFilter !== '') {
    $storeFilterIds = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
}
if (count($storeFilterIds) > 0) {
    $storeFilterNames = loadFilterNames($conn, 'store', 'store_id', 'name', $storeFilterIds);
    $ph = implode(',', array_fill(0, count($storeFilterIds), '?'));
    $tp->appendWhere("d.store_id IN ($ph)", $storeFilterIds, str_repeat('i', count($storeFilterIds)));
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['docum_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$clientFilterOptions = [];
$clr = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($clr) while ($cr = $clr->fetch_assoc()) $clientFilterOptions[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$storeFilterOptions = [];
$str = $conn->query("SELECT store_id, name FROM store ORDER BY name");
if ($str) while ($sr = $str->fetch_assoc()) $storeFilterOptions[] = ['id' => (int)$sr['store_id'], 'name' => (string)$sr['name']];

$sotrList = [];
$sotrs = $conn->query("SELECT sotr_id, doc_name AS name FROM sotr ORDER BY doc_name");
if ($sotrs) while ($sr = $sotrs->fetch_assoc()) $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

$urlCols = $searchCols;

$clearQs = function($drop) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $typeop) {
    $drop = is_array($drop) ? $drop : [$drop];
    $qs = [];
    if ($typeop) $qs['typeop'] = $typeop;
    if (!in_array('q', $drop, true) && $searchActive && $search !== '') $qs['q'] = $search;
    if (!in_array('cols', $drop, true) && $searchActive && count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
    if (!in_array('cond', $drop, true) && $searchActive) $qs['cond'] = $searchCond;
    if (!in_array('sf', $drop, true) && $searchActive) $qs['sf'] = '1';
    if (!in_array('sort', $drop, true) && $sortQs !== '') $qs['sort'] = $sortQs;
    if (!in_array('client_id', $drop, true) && !empty($_GET['client_id'])) $qs['client_id'] = $_GET['client_id'];
    if (!in_array('store_id', $drop, true) && !empty($_GET['store_id'])) $qs['store_id'] = $_GET['store_id'];
    return 'sale.php' . ($qs ? '?' . http_build_query($qs) : '');
};

$tp->buildFilters();
$filters = $tp->filters;
if (count($clientFilterIds) > 0) {
    $names = [];
    foreach ($clientFilterIds as $cid) {
        $names[] = $clientFilterNames[$cid] ?? ('#' . $cid);
    }
    $filters[] = ['kind' => 'client', 'text' => 'Контрагент = ' . implode(', ', $names), 'clear' => 'client_id'];
}
if (count($storeFilterIds) > 0) {
    $names = [];
    foreach ($storeFilterIds as $sid) {
        $names[] = $storeFilterNames[$sid] ?? ('#' . $sid);
    }
    $filters[] = ['kind' => 'store', 'text' => 'Участок = ' . implode(', ', $names), 'clear' => 'store_id'];
}

$exportQs = http_build_query(array_filter([
    'typeop'   => $typeop,
    'q'    => $searchActive && $search !== '' ? $search : null,
    'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
    'cond' => $searchActive ? $searchCond : null,
    'sf'   => $searchActive ? '1' : null,
    'sort' => $sortQs !== '' ? $sortQs : null,
    'client_id' => $clientFilter !== '' ? $clientFilter : null,
    'store_id' => $storeFilter !== '' ? $storeFilter : null,
], function ($v) { return $v !== null && $v !== ''; }));

$baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs, $typeop) {
    $qs = ['page' => (int)$p];
    if ($typeop) $qs['typeop'] = $typeop;
    if ($searchActive) {
        if ($search !== '') $qs['q'] = $search;
        if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
        $qs['cond'] = $searchCond;
        $qs['sf'] = '1';
    }
    if ($sortQs !== '') $qs['sort'] = $sortQs;
    if (!empty($_GET['client_id'])) $qs['client_id'] = $_GET['client_id'];
    if (!empty($_GET['store_id'])) $qs['store_id'] = $_GET['store_id'];
    return 'sale.php?' . http_build_query($qs);
};
$paginationHtml = render_pagination($page, $pages, $baseQs, true);

$exportDropdownHtml = '';
$exportTimestamp = date('d.m.Y_H.i');
foreach (['csv' => $PAGE_TITLE . ' ' . $exportTimestamp . '.csv', 'xls' => $PAGE_TITLE . ' ' . $exportTimestamp . '.xls'] as $fmt => $filename) {
    $fullUrl = 'sale_export.php?format=' . $fmt . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($fmt === 'csv' ? 'CSV' : 'XLS (Excel)') . '">' . ($fmt === 'csv' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}
$repmenuTemplates = [];
$stmtTpl = @$conn->prepare("SELECT rp_id, number, name, fname FROM repmenu WHERE gr_id = 2 AND (HIDE_FLAG IS NULL OR HIDE_FLAG = 0) AND fname != '' ORDER BY number");
if ($stmtTpl) { $stmtTpl->execute(); $resTpl = $stmtTpl->get_result(); if ($resTpl) while ($rt = $resTpl->fetch_assoc()) $repmenuTemplates[] = $rt; $stmtTpl->close(); }

$printDropdownHtml = '';
foreach ($repmenuTemplates as $tpl) {
    $printDropdownHtml .= '<a class="dropdown-item" href="#" onclick="printSaleTemplate(' . (int)$tpl['rp_id'] . ',\'' . h(addslashes($tpl['fname'])) . '\');return false;">' . h($tpl['name']) . '</a>';
}
if (count($repmenuTemplates) > 0) {
    $printDropdownHtml .= '<div class="dropdown-divider"></div>';
}
$printDropdownHtml .= render_print_dropdown_items('sale', $exportQs, (int)$page, $marksCount > 0);

render_head_start($PAGE_TITLE);
?>
  <style>
    .col-sum { text-align: right; white-space: nowrap; }
    .col-date { white-space: nowrap; }
    .search-highlight { background: #e8812a; color: #fff; padding: 0 1px; border-radius: 2px; }

    .col-filter-panel {
      display: none; position: fixed; z-index: 1000; min-width: 240px; max-width: 320px;
      padding: 8px; background: #2f3e4e; border: 2px solid #6b7785;
      border-radius: 2px; box-shadow: 0 8px 18px rgba(0,0,0,.35); text-align: left;
    }
    .col-filter-panel.open { display: block; }
    .col-filter-search {
      width: 100%; height: 28px; padding: 0 8px; margin: 0 0 6px; box-sizing: border-box;
      border: 1px solid var(--line); background: #fff; color: #2b2b2b;
      border-radius: 2px; font-size: 13px; outline: none;
    }
    .col-filter-list { margin-bottom: 8px; }
    .col-filter-row {
      display: flex; align-items: center; gap: 6px; padding: 4px; cursor: pointer;
      color: #fff; font-size: 13px; user-select: none;
    }
    .col-filter-row:hover { background: var(--btn-hover); }
    .col-filter-row input[type="checkbox"] { width: 14px; height: 14px; flex: 0 0 auto; }
    .col-filter-row .col-filter-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .col-filter-empty { padding: 6px 4px; color: var(--muted); font-size: 12px; font-style: italic; }
    .col-filter-more {
      padding: 4px; color: var(--muted); font-size: 12px; font-style: italic;
      border-top: 1px solid var(--line);
    }
    .col-filter-actions {
      display: flex; gap: 10px; justify-content: flex-end; padding: 10px 8px;
      border-top: 1px solid var(--line);
    }
    .col-filter-actions button {
      display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 8px;
      background: var(--btn); color: #fff; border: 1px solid var(--line);
      border-radius: 2px; cursor: pointer; font-size: 14px;
    }
    .col-filter-actions button img { width: 28px; height: 28px; flex: 0 0 auto; display: block; }
    .col-filter-actions button:hover { background: var(--btn-hover); }
    .col-filter-actions .col-filter-apply { background: var(--accent); border-color: var(--accent); }
    .col-filter-actions .col-filter-apply:hover { background: #cf6d1a; border-color: #cf6d1a; }

    /* === Tabs & Docum2 (form modal) === */
    .form-modal { max-width: 990px; }
    .page--form { max-width: 990px; }
    .form { max-width: 990px; }
    .tab-container { margin-bottom: 16px; width: 100%; }
    .tab-headers { display: flex; border-bottom: 2px solid var(--accent); margin-bottom: 12px; }
    .tab-header { padding: 8px 20px; cursor: pointer; font-size: 14px; font-weight: bold; color: var(--muted); border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; user-select: none; }
    .tab-header.active { color: #fff; background: var(--accent); border-color: var(--accent); }
    .tab-header:hover:not(.active) { color: #fff; background: var(--btn-hover); }
    .tab-pane { display: none; width: 100%; }
    .tab-pane.active { display: block; width: 100%; }
    .table-wrap { width: 100%; box-sizing: border-box; }
    .docum2-toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; gap:8px; flex-wrap:wrap; }
    .docum2-toolbar .toolbar-left, .docum2-toolbar .toolbar-right { display:flex; align-items:center; gap:4px; }
    .docum2-table td { cursor:default; }
    .docum2-table .cell-editable { cursor:text; }
  </style>
<?php
render_export_modal();
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'sale.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/<?= h($PAGE_ICON) ?>" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
    $acceptBtnHtml = '<button class="icon-btn" id="acceptBtn" title="Утвердить" type="button"><img src="img/lock.png" alt="" /></button>';
    ?>

    <?php render_toolbar_wrapper_open([
        'total'        => $total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => $marksCount,
        'search'       => $search,
        'page'         => $page,
        'pages'        => $pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]); ?>

    <?php render_toolbar_left('sale_form', $marksCount, $exportDropdownHtml, $printDropdownHtml, $acceptBtnHtml); ?>
    <script>(function(){var b=document.querySelector('[data-form-open="sale_form.php?mode=new"]');if(b)b.dataset.formOpen='sale_form.php?typeop=<?= $typeop ?>&mode=new';})();</script>

    <?php render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'sale.php'); ?>

    <?php render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
    <table class="data-table">
      <?php render_table_colgroup($visibleColumns, $columnWidths, sale_columns_widths_print()); ?>
      <?php render_table_thead(
          $visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0,
          [
              'thAttrsCallback' => function($cn, $cm, $i) use ($clientFilterIds, $storeFilterIds) {
                  if ($cm && !empty($cm['filter'])) {
                      $param = $cm['param'] ?? ($cn . '_id');
                      $values = [];
                      if ($cn === 'client') $values = $clientFilterIds;
                      if ($cn === 'store') $values = $storeFilterIds;
                      return ' data-col="' . h($cn) . '" data-param="' . h($param) . '" data-values="' . h(implode(',', $values)) . '"';
                  }
                  return '';
              },
              'thHtmlCallback' => function($cn, $cm) {
                  if ($cm && !empty($cm['filter'])) {
                      return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                  }
                  return '';
              },
          ]
      ); ?>
      <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'docum_id', function($r, $cn, $vc) use ($typeop, $prihodFlag) {
          switch ($cn) {
              case 'accept':   $af = (int)$r['accept_flag']; return [$af, $af ? '<img src="img/lock.png" alt="Утверждено" width="18" height="18" style="vertical-align:middle" />' : ''];
              case 'number':   return [(int)$r['number'] > 0 ? (int)$r['number'] : '', (int)$r['number'] > 0 ? (int)$r['number'] : ''];
              case 'date':
                  $dt = strtotime((string)$r['date']);
                  $display = $dt ? date('Y-m-d', $dt) : ((string)$r['date']);
                  return [$r['date'], $display];
              case 'time':         return [(string)$r['time'], (string)$r['time']];
              case 'client':       return [(int)$r['client_id'], h($r['client_name'])];
              case 'store':        return [(int)$r['store_id'], h($r['store_name'])];
              case 'store2':       return [(int)$r['store2_id'], h($r['store2_name'])];
              case 'discount':     $dv = (float)$r['discount']; $disp = $dv == 0 ? '' : ($dv == (int)$dv ? (string)(int)$dv : rtrim(rtrim(sprintf('%.3f', $dv), '0'), '.')); return [$r['discount'], $disp];
              case 'sum':          $sv = (float)$r['sum']; return [$r['sum'], $sv == 0 ? '' : number_format($sv, 2, ',', ' ')];
              case 'sum_plat':     $sv = (float)$r['sum_plat']; $ss = (float)$r['sum'];
                   $spDisp = $sv == 0 ? '' : number_format($sv, 2, ',', ' ');
                   $spColor = $prihodFlag ? ($ss > -$sv ? ' style="color:#e57373"' : '') : ($sv < $ss ? ' style="color:#e57373"' : '');
                   return [$r['sum_plat'], '<span' . $spColor . '>' . $spDisp . '</span>'];
              case 'pos':          $pv = (int)$r['pos']; return [$r['pos'], $pv > 0 ? (string)$pv : ''];
              case 'note':         return [$r['note'], h($r['note'])];
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

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/export-modal.js', 'assets/column-filter.js', 'assets/embedded-table.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(sale_columns_widths_print(), JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script>
    window.printSaleTemplate = function (rpId, fname) {
      var sel = document.querySelector('table tbody tr.selected');
      if (!sel) { alert('Выберите строку для печати'); return; }
      var rowId = parseInt(sel.getAttribute('data-row-id') || '0', 10);
      if (!rowId) { alert('Выберите строку для печати'); return; }
      window.open('sale_print_template.php?id=' + rowId + '&template=' + encodeURIComponent(fname), '_blank');
    };
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    window.__openFormModal = window.__openFormModal || function(url){ window.location.href = url; };
    (function () {
      const checkAll  = document.getElementById('checkAll');
      const rowChecks = document.querySelectorAll('.row-check');
      const selWrap   = document.getElementById('selectedActions');
      const selCount  = document.getElementById('selectedCount');
      const toolbar   = document.querySelector('.toolbar');
      const search    = toolbar.getAttribute('data-search') || '';
      const markedSet = new Set(Array.from(rowChecks).filter(cb => cb.checked).map(cb => parseInt(cb.value, 10)));
      let globalCount = parseInt(toolbar.getAttribute('data-marks-count') || '0', 10);

      function refreshCounter() {
        selCount.textContent = 'Выбрано: ' + globalCount;
        selWrap.classList.toggle('visible', globalCount > 0);
        const rowTotal = rowChecks.length;
        let rowOn = 0;
        rowChecks.forEach(cb => { if (cb.checked) rowOn++; });
        if (rowTotal === 0) { checkAll.checked = false; checkAll.indeterminate = false; }
        else { checkAll.checked = rowOn === rowTotal; checkAll.indeterminate = rowOn > 0 && rowOn < rowTotal; }
      }

      checkAll.addEventListener('change', function () {
        var qs = new URLSearchParams(location.search);
        qs.set('action', 'toggleSelectAll');
        location.href = 'sale.php?' + qs.toString();
      });

      rowChecks.forEach(cb => cb.addEventListener('change', function () {
        const id = parseInt(cb.value, 10);
        const to = cb.checked;
        cb.disabled = true;
        var qs = new URLSearchParams(location.search);
        qs.set('action', 'toggleSelect');
        fetch('sale.php?' + qs.toString(), {
          method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
          body: 'id=' + id + '&to=' + (to ? '1' : '0')
        }).then(r => r.json()).then(function (j) {
          cb.disabled = false;
          if (j.ok) {
            if (to) markedSet.add(id); else markedSet.delete(id);
            globalCount = (typeof j.count === 'number') ? j.count : globalCount;
            refreshCounter();
          }
        }).catch(function () { cb.disabled = false; });
      }));

      SelectionToolbar.init({
        pageUrl: 'sale.php?typeop=<?= $typeop ?>',
        search: search,
        getInvertUrl: function () {
          var other = new URLSearchParams(location.search);
          other.delete('ids');
          return 'sale.php?action=invertSelection&' + other.toString();
        },
        getExportUrl: function () { return 'sale_export.php?format=csv&all=1&' + new URLSearchParams(location.search).toString(); },
        getPrintUrl: function () { return 'sale_print.php?all=1&' + new URLSearchParams(location.search).toString(); }
      });

      ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'sale.php?typeop=<?= $typeop ?>' });
      ColumnFilter.init({ thSelector: '.col-store', pageUrl: 'sale.php?typeop=<?= $typeop ?>' });

      const SORT_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) { return $c['name'] === 'accept' || empty($c['sort_expr']) ? null : ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
      const SEARCH_COLS = <?= json_encode(array_values(array_map(function ($key) use ($salePageConfig) {
          $labels = $salePageConfig['search_labels'] ?? [];
          return ['key' => $key, 'label' => $labels[$key] ?? $key];
      }, array_keys($salePageConfig['search_cols']))), JSON_UNESCAPED_UNICODE) ?>;
      const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: SEARCH_COLS,
        pageUrl: 'sale.php?typeop=<?= $typeop ?>',
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
          location.href = 'sale.php?' + params.toString();
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
          location.href = 'sale.php?' + params.toString();
        },
        onSubmit: function () { searchToggleBtn.click(); }
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: SORT_COLS,
        pageUrl: 'sale.php?typeop=<?= $typeop ?>',
        mode: 'modal',
        currentSort: currentSortLevels,
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });

      refreshCounter();
    })();

    ExportModal.init();
  </script>
  <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: 'sale_columns_save.php',
      tbl: 'sale',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
      }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    (function () {
      const toolbar = document.querySelector('.toolbar');
      if (!toolbar) return;
      const tbody = document.querySelector('table tbody');
      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

      const openBtn   = document.getElementById('rowOpenBtn');
      const copyBtn   = document.getElementById('rowCopyBtn');
      const deleteBtn = document.getElementById('rowDeleteBtn');

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          if (openBtn)   openBtn.disabled   = !enabled;
          if (copyBtn)   copyBtn.disabled   = !enabled;
          if (deleteBtn) deleteBtn.disabled = !enabled;
        },
        currentPage: currentPage,
        totalPages: currentPages,
        navigate: navigate
      });

      var _ab = document.getElementById('acceptBtn');
      if (_ab) _ab.addEventListener('click', function () {
        var _id = rowSel.getSelectedId();
        if (!_id) { alert('Выберите строку'); return; }
        fetch('sale.php?action=acceptToggle&id=' + _id, {
          method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); })
          .then(function (d) { if (d.ok) location.reload(); });
      });

      (function applyInitialFocus() {
        const rows = rowSel.getRows();
        if (rows.length === 0) return;
        const raw = (toolbar.dataset.focus || '').toString();
        if (raw === 'first') { rowSel.selectByIndex(0); return; }
        if (raw === 'last')  { rowSel.selectByIndex(rows.length - 1); return; }
        const id = parseInt(raw, 10);
        if (id > 0 && rowSel.selectById(id, false)) return;
        rowSel.selectByIndex(0);
      })();

      if (tbody) {
        tbody.addEventListener('dblclick', function (e) {
          if (e.target.closest('input[type="checkbox"]')) return;
          const tr = e.target.closest('tr[data-row-id]');
          if (!tr) return;
          const id = parseInt(tr.dataset.rowId, 10) || 0;
          if (!id) return;
          rowSel.selectById(id, true);
          window.__openFormModal('sale_form.php?typeop=<?= $typeop ?>&mode=edit&id=' + id);
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        window.__openFormModal('sale_form.php?typeop=<?= $typeop ?>&mode=' + mode + '&id=' + id);
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(params) {
        var url = new URL(window.location.href);
        params(url.searchParams);
        window.location.href = url.pathname + '?' + url.searchParams.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: 'sale_form',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: document.querySelector('.table-wrap'),
        onOpenForm: window.__openFormModal,
        formExtraParams: '&typeop=<?= $typeop ?>'
      });
    })();

    InlineEdit.init({
      tbody: document.querySelector('table tbody'),
      saveUrl: 'sale_field_save.php',
      fields: <?php
        $inlineFields = [];
        foreach ($COLUMN_DEFAULTS as $c) {
            if (!empty($c['readonly']) || $c['name'] === 'accept') continue;
            $inlineFields[$c['name']] = [
                'dbField' => $c['param'] ?: $c['name'],
                'type'    => !empty($c['param']) ? 'lookup' : 'text',
                'label'   => $c['label'],
            ];
        }
        $inlineFields['note'] = ['dbField' => 'note', 'type' => 'textarea', 'label' => 'Примечание'];
        echo json_encode($inlineFields, JSON_UNESCAPED_UNICODE);
      ?>,
      getLookupData: function (field) {
        var map = { client: window.__clientLookupData, store: window.__storeLookupData, store2: window.__storeLookupData };
        return map[field] || [];
      },
      onOpenForm: window.__openFormModal
    });

    ColumnResize.init({ saveUrl: 'sale_column_width_save.php', tbl: 'sale', selector: 'table.data-table' });

    document.addEventListener('click', function (e) {
      let a = e.target.closest('a[href*="sale_form.php"]');
      if (a) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        window.__openFormModal(a.getAttribute('href'));
        return;
      }
      const trig = e.target.closest('[data-form-open]');
      if (trig) { e.preventDefault(); window.__openFormModal(trig.getAttribute('data-form-open')); return; }
    });
  </script>
  <script>
    // ---- Tab switching + docum2 items (invoice-pattern) ----
    window.renderDocum2 = window.renderDocum2 || function(){};
    window.__docum2Render = window.__docum2Render || function(){};
    var formModal = document.getElementById('formModal');
    var formBody  = document.getElementById('formModalBody');
    var stashed   = null;
    window.__docum2SelectedId = window.__docum2SelectedId || 0;
    if (!window.__d2CheckedIds) window.__d2CheckedIds = new Set();
    var d2ShowOnlyFilter = false;

    function applyDocum2Totals(d) {
      var s = d && d.total_sum !== undefined ? d.total_sum : (d && d.sum !== undefined ? d.sum : undefined);
      if (s !== undefined) {
        var el = document.getElementById('sale-sum');
        if (el) el.value = s || '';
      }
      var sd = d && d.total_sum_discount !== undefined ? d.total_sum_discount : (d && d.sum_discount !== undefined ? d.sum_discount : undefined);
      if (sd !== undefined) {
        var el = document.getElementById('sale-sum-discount');
        if (el) el.value = sd || '';
      }
      if (d && d.pos !== undefined) {
        var el = document.getElementById('sale-pos');
        if (el) el.value = d.pos > 0 ? String(d.pos) : '';
      }
      if (d && d.sum_plat !== undefined) {
        var spEl = document.getElementById('sale-sum-plat');
        if (spEl) { spEl.value = d.sum_plat; spEl.style.color = d.sum_plat_red ? '#e57373' : ''; spEl.style.fontWeight = d.sum_plat_red ? 'bold' : ''; }
      }
    }

    function initDocum2Table() {
      var formBody = document.querySelector('.form-modal-body') || document.querySelector('[data-form-modal]') || document.body;
      var formEl = formBody.querySelector('form');
      if (formEl && formEl.classList.contains('form--delete')) return;
      var table = formBody.querySelector('.docum2-table');
      if (!table) return;

      /* Delegated event handlers (attached once) */
      table.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('d2-row-check')) {
          e.stopPropagation();
          var id = parseInt(e.target.dataset.id, 10);
          if (!window.__d2CheckedIds) window.__d2CheckedIds = new Set();
          if (e.target.checked) { window.__d2CheckedIds.add(id); }
          else { window.__d2CheckedIds.delete(id); }
          updateD2CheckedUI();
        }
        if (e.target.id === 'd2-check-all') {
          var pageIds = formBody.querySelectorAll('.d2-row-check');
          if (!window.__d2CheckedIds) window.__d2CheckedIds = new Set();
          if (e.target.checked) {
            pageIds.forEach(function(cb) { window.__d2CheckedIds.add(parseInt(cb.dataset.id, 10)); });
          } else {
            pageIds.forEach(function(cb) { window.__d2CheckedIds.delete(parseInt(cb.dataset.id, 10)); });
          }
          pageIds.forEach(function(cb) { cb.checked = e.target.checked; });
          updateD2CheckedUI();
        }
      });

      table.addEventListener('click', function(e) {
        var tr = e.target.closest('.d2-row');
        if (!tr) return;
        if (e.target.type === 'checkbox') return;
        var id = parseInt(tr.dataset.id, 10);
        formBody.querySelectorAll('.d2-row.selected').forEach(function(r) { r.classList.remove('selected'); });
        window.__docum2SelectedId = id;
        tr.classList.add('selected');
        updateD2Buttons();
      });

      table.addEventListener('dblclick', function(e) {
        var tr = e.target.closest('.d2-row');
        if (!tr) return;
        var id = parseInt(tr.dataset.id, 10);
        if (id > 0) onEdit(id);
      });

      /* Hotkeys — register once at document level */
      if (!window.__d2HotkeysInited) {
        window.__d2HotkeysInited = true;
        document.addEventListener('keydown', function(e) {
          if (!formModal || !formModal.classList.contains('open')) return;
          if (!formBody.querySelector('.docum2-table')) return;
          if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
          var activeHeader = formBody.querySelector('.tab-header.active');
          var activeTab = activeHeader ? activeHeader.getAttribute('data-tab-index') : '';
          if (activeTab !== '1' && activeTab !== '2') return;
          var isPlat = activeTab === '2';
          var rowSel = isPlat ? '.plat-row' : '.d2-row';
          var rows = Array.from(formBody.querySelectorAll(rowSel));
          if (rows.length === 0) return;
          var curId = isPlat ? (window.__platSelectedId || 0) : (window.__docum2SelectedId || 0);
          var curIdx = -1;
          for (var k = 0; k < rows.length; k++) {
            if (parseInt(rows[k].dataset.id, 10) === curId) { curIdx = k; break; }
          }
          function platSetSel(id, idx) {
            rows.forEach(function(r) { r.classList.remove('selected'); });
            if (idx >= 0 && idx < rows.length) rows[idx].classList.add('selected');
            if (isPlat) window.__platSelectedId = id;
            else window.__docum2SelectedId = id;
            if (isPlat) { if (typeof window.updatePlatButtons === 'function') window.updatePlatButtons(); }
            else updateD2Buttons();
          }
          switch (e.key) {
            case 'Insert':
              e.preventDefault();
              var addBtn = formBody.querySelector(isPlat ? '#plat-add-btn' : '#d2-add-btn');
              if (addBtn) addBtn.click();
              break;
            case 'Enter':
              e.preventDefault();
              if (curId > 0) { var editBtn = formBody.querySelector(isPlat ? '#plat-edit-btn' : '#d2-edit-btn'); if (editBtn) editBtn.click(); }
              break;
            case 'Delete':
              e.preventDefault();
              if (curId > 0) { var delBtn = formBody.querySelector(isPlat ? '#plat-del-btn' : '#d2-del-btn'); if (delBtn) delBtn.click(); }
              break;
            case 'ArrowDown':
              e.preventDefault();
              if (curIdx < rows.length - 1) platSetSel(parseInt(rows[curIdx + 1].dataset.id, 10), curIdx + 1);
              break;
            case 'ArrowUp':
              e.preventDefault();
              if (curIdx > 0) platSetSel(parseInt(rows[curIdx - 1].dataset.id, 10), curIdx - 1);
              break;
            case 'Home':
              e.preventDefault();
              if (rows.length > 0) platSetSel(parseInt(rows[0].dataset.id, 10), 0);
              break;
            case 'End':
              e.preventDefault();
              if (rows.length > 0) platSetSel(parseInt(rows[rows.length - 1].dataset.id, 10), rows.length - 1);
              break;
          }
        });
      }

      var saveUrl = table.getAttribute('data-save-url') || 'docum2_field_save.php';
      var documIdEl = formBody.querySelector('input[name="id"]');
      var documId = documIdEl ? parseInt(documIdEl.value, 10) : 0;

      if (!window.__docum2Data || window.__docum2Data.length === 0) {
        window.__docum2Data = [];
        try { window.__docum2Data = JSON.parse(table.dataset.items || '[]'); } catch(e) {}
      }

      var PAGE_SIZE = 15;
      var currentPage = 1;
      var searchText = '';
      var searchActive = false;
      var sortCol = -1;
      var sortDir = 'asc';
      var D2_SEARCH_COLS = [
        { key: 'product_name', label: 'Товар' },
        { key: 'quant', label: 'Кол-во' },
        { key: 'price', label: 'Цена' },
        { key: 'discount', label: 'Скидка' },
        { key: 'sum', label: 'Сумма' },
        { key: 'note', label: 'Примечание' }
      ];
      var d2Typeop = parseInt((table && table.dataset.typeop) || '120', 10);
      if (d2Typeop === 127 || d2Typeop === 100) D2_SEARCH_COLS = D2_SEARCH_COLS.filter(function(c) { return c.key !== 'discount'; });
      var D2_COL_KEYS = D2_SEARCH_COLS.map(function(c) { return c.key; });
      var searchCols = new Set(D2_COL_KEYS);
      var searchCond = 'contains';

      function cn(v) { return (v === '' || v === null || v === undefined) ? '' : v; }
      function disp(v) { return (v == null || v === '') ? '' : String(v); }

      function hl(v) {
        if (!searchActive || !searchText) return cn(v);
        var s = String(v !== null && v !== undefined ? v : '');
        if (s === '') return '-';
        var st = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var re = new RegExp('(' + st + ')', 'gi');
        return s.replace(re, '<span class="hl">$1</span>');
      }

      function renderDocum2() {
        var tbody = formBody.querySelector('.docum2-table tbody');
        if (!tbody) return;
        var filtered = window.__docum2Data;
        if (d2ShowOnlyFilter && window.__d2CheckedIds && window.__d2CheckedIds.size > 0) {
          filtered = filtered.filter(function(item) { return window.__d2CheckedIds.has(item.id); });
        }
        if (searchActive && searchText) {
          var st = searchText.toLowerCase();
          var cond = searchCond || 'contains';
          var colSet = (searchCols && searchCols.size > 0) ? searchCols : new Set(D2_COL_KEYS);
          filtered = filtered.filter(function(item) {
            return D2_COL_KEYS.some(function(key) {
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
          tbody.innerHTML = '<tr><td colspan="' + (1 + document.querySelectorAll('.docum2-table thead th').length) + '" class="col-d2-empty">Нет товаров</td></tr>';
        } else {
          var ths = formBody.querySelectorAll('.docum2-table thead th:not(.col-check)');
          var colOrder = [];
          ths.forEach(function(th) { var c = th.dataset.col; if (c && c !== 'id') colOrder.push(c); });
          if (colOrder.length === 0) colOrder = ['product_name','quant','price','discount','sum','note'];
          var html = '';
          for (var i = 0; i < pageItems.length; i++) {
            var item = pageItems[i];
            var sel = (item.id === window.__docum2SelectedId) ? ' selected' : '';
            var chk = window.__d2CheckedIds && window.__d2CheckedIds.has(item.id) ? ' checked' : '';
            html += '<tr data-id="' + item.id + '" data-row-id="' + item.id + '" class="d2-row' + sel + '">'
              + '<td class="col-check"><input type="checkbox" class="d2-row-check" data-id="' + item.id + '"' + chk + ' /></td>';
            colOrder.forEach(function(c) {
              var val = item[c] || '';
              var align = (c === 'quant') ? 'center' : (c === 'product_name' || c === 'note') ? 'left' : 'right';
              var editable = (c !== 'sum' && !((d2Typeop === 100 || d2Typeop === 127) && c === 'price')) ? ' class="cell-editable"' : '';
              var displayVal = (c === 'product_name' || c === 'note') ? hl(val) : disp(val);
              html += '<td' + editable + ' data-field="' + c + '" data-value="' + (item[c] || '0') + '" style="text-align:' + align + '"><span class="cell-value">' + displayVal + '</span></td>';
            });
            html += '</tr>';
          }
          tbody.innerHTML = html;
          var ceCells = tbody.querySelectorAll('td.cell-editable');
          console.log('[D2] cells=' + pageItems.length + ' editable=' + ceCells.length + ' cols=' + colOrder.join(','));
        }

        var pagEl = formBody.querySelector('#d2-pagination');
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
                if (pg > 0 && pg !== currentPage) { currentPage = pg; renderDocum2(); }
              });
            });
          }
        }

        var bannerEl = formBody.querySelector('#d2-filter-banner');
        var clearBtn = formBody.querySelector('#d2-clear-filter-btn');
        var showOnlyFilter = d2ShowOnlyFilter && window.__d2CheckedIds && window.__d2CheckedIds.size > 0;
        if (searchActive && searchText) {
          if (bannerEl) {
            bannerEl.style.display = '';
            var bannerParts = [];
            var colLabels = D2_SEARCH_COLS.filter(function(c) { return !searchCols || searchCols.has(c.key); }).map(function(c) { return c.label; });
            if (colLabels.length === 0) colLabels = D2_SEARCH_COLS.map(function(c) { return c.label; });
            var condLabel = ({ contains: 'Содержит', not_contains: 'Не содержит', starts_with: 'Начинается с', ends_with: 'Заканчивается на', equals: 'Равно', not_equals: 'Не равно' })[searchCond] || 'Содержит';
            bannerParts.push('<img src="img/filter.png" alt="" /><span class="filter-chip"><span class="filter-chip-text">(' + colLabels.join(', ') + ' ' + condLabel + '  «' + searchText + '»)</span><button type="button" class="filter-chip-close" id="d2-banner-clear" title="Снять фильтр">✕</button></span>');
            if (showOnlyFilter) bannerParts.push('<span class="filter-chip" style="margin-left:6px"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="d2-banner-showoff" title="Показать все">✕</button></span>');
            bannerEl.innerHTML = bannerParts.join('');
            var bc = bannerEl.querySelector('#d2-banner-clear'); if (bc) bc.addEventListener('click', function () { var sb = formBody.querySelector('#d2-search-btn'); if (sb) sb.click(); });
            var so = bannerEl.querySelector('#d2-banner-showoff'); if (so) so.addEventListener('click', function () { d2ShowOnlyFilter = false; renderDocum2(); });
          }
          if (clearBtn) clearBtn.style.display = '';
        } else if (showOnlyFilter) {
          if (bannerEl) {
            bannerEl.style.display = '';
            bannerEl.innerHTML = '<span class="filter-chip"><span class="filter-chip-text">Показаны только выбранные</span><button type="button" class="filter-chip-close" id="d2-banner-showoff" title="Показать все">✕</button></span>';
            var so = bannerEl.querySelector('#d2-banner-showoff'); if (so) so.addEventListener('click', function () { d2ShowOnlyFilter = false; renderDocum2(); });
          }
          if (clearBtn) clearBtn.style.display = 'none';
        } else {
          if (bannerEl) bannerEl.style.display = 'none';
          if (clearBtn) clearBtn.style.display = 'none';
        }

        syncD2CheckAll();
      }

      window.__docum2Render = function() { renderDocum2(); };

      function updateD2Buttons() {
        var editBtn = formBody.querySelector('#d2-edit-btn');
        var delBtn = formBody.querySelector('#d2-del-btn');
        var copyBtn = formBody.querySelector('#d2-copy-btn');
        var disabled = !window.__docum2SelectedId;
        if (editBtn) editBtn.disabled = disabled;
        if (delBtn) delBtn.disabled = disabled;
        if (copyBtn) copyBtn.disabled = disabled;
      }

      function refreshDocum2Data() {
        var fd = new FormData();
        fd.set('field', '_list');
        fd.set('docum_id', String(documId));
        fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data && Array.isArray(data)) {
              window.__docum2Data = data;
        (window.__docum2Render || renderDocum2)();
      }
          });
      }

      function printD2Checked() {
        var ids = window.__d2CheckedIds ? Array.from(window.__d2CheckedIds) : [];
        if (ids.length === 0) return;
        var items = window.__docum2Data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
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
      function exportD2Checked() {
        var ids = window.__d2CheckedIds ? Array.from(window.__d2CheckedIds) : [];
        if (ids.length === 0) return;
        var items = window.__docum2Data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        var lines = ['Товар;Кол-во;Цена;Скидка;Сумма;Примечание'];
        items.forEach(function(item) {
          lines.push([
            '"' + (item.product_name || '').replace(/"/g, '""') + '"',
            item.quant || '1',
            item.price || '0',
            item.discount || '0',
            item.sum || '0',
            '"' + (item.note || '').replace(/"/g, '""') + '"'
          ].join(';'));
        });
        var blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Товары_документа.csv';
        a.click();
      }

      function exportAllD2() {
        var items = window.__docum2Data || [];
        if (items.length === 0) return;
        var csv = '\uFEFF';
        csv += 'Товар;Кол-во;Цена;Скидка;Сумма;Примечание\n';
        items.forEach(function(item) {
          csv += (item.product_name || '') + ';' + (item.quant || '1') + ';' + (item.price || '0') + ';' + (item.discount || '0') + ';' + (item.sum || '0') + ';' + (item.note || '') + '\n';
        });
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'docum_items.csv'; a.click();
        URL.revokeObjectURL(a.href);
      }
      function printAllD2() {
        var items = window.__docum2Data || [];
        if (items.length === 0) return;
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
      function printPageD2() {
        var pagEl = formBody.querySelector('#d2-pagination');
        var pageInfo = pagEl ? pagEl.querySelector('.page-info') : null;
        var pageText = pageInfo ? pageInfo.textContent : '';
        var items = window.__docum2Data || [];
        var w = window.open('', '_blank', 'width=800,height=600');
        var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Печать - ' + pageText + '</title><style>body{font:14px sans-serif;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 10px;text-align:left}th{background:#eee}</style></head><body><p>' + pageText + '</p><table><thead><tr><th>Товар</th><th>Кол-во</th><th>Цена</th><th>Скидка</th><th>Сумма</th><th>Примечание</th></tr></thead><tbody>';
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
        var ids = window.__d2CheckedIds ? Array.from(window.__d2CheckedIds) : [];
        if (ids.length === 0) return;
        if (!confirm('Удалить ' + ids.length + ' отмеченных товаров?')) return;
        (function next(i) {
          if (i >= ids.length) {
            window.__docum2SelectedId = 0;
            window.__d2CheckedIds = new Set();
            updateD2CheckedUI();
            renderDocum2();
            return;
          }
          var fd = new FormData();
          fd.set('field', '_delete');
          fd.set('docum2_id', String(ids[i]));
          fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (d && d.ok) {
                window.__docum2Data = window.__docum2Data.filter(function(item) { return item.id !== ids[i]; });
                applyDocum2Totals(d);
              }
              next(i + 1);
            })
            .catch(function() { next(i + 1); });
        })(0);
      }

      function onEdit(id) {
        var url = 'docum2_form.php?mode=edit&id=' + id + '&docum_id=' + documId;
        openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
      }

      function onItemSaved(data) {
        if (!data || !data.ok) return;
        applyDocum2Totals(data);
        if (data.id) {
          var idx = -1;
          for (var i = 0; i < window.__docum2Data.length; i++) {
            if (window.__docum2Data[i].id == data.id) { idx = i; break; }
          }
          if (idx >= 0) {
            window.__docum2Data[idx] = {
              id: data.id, product_id: data.product_id || 0,
              code: data.code || '', product_name: data.product_name || '',
              quant: data.quant || '', price: data.price || '',
              discount: data.discount || '', sum: data.sum || '',
              sum_discount: data.sum_discount || '',
              note: data.note || ''
            };
          } else {
            window.__docum2Data.push({
              id: data.id, product_id: data.product_id || 0,
              code: data.code || '', product_name: data.product_name || '',
              quant: data.quant || '', price: data.price || '',
              discount: data.discount || '', sum: data.sum || '',
              sum_discount: data.sum_discount || '',
              note: data.note || ''
            });
          }
          window.__docum2SelectedId = data.id;
          (window.__docum2Render || renderDocum2)();
        }
      }
      function onItemDeleted(data) {
        if (!data || !data.ok || !data._deleted) return;
        applyDocum2Totals(data);
        var deletedId = data.id;
        var idx = -1;
        for (var i = 0; i < window.__docum2Data.length; i++) {
          if (window.__docum2Data[i].id == deletedId) { idx = i; break; }
        }
        window.__docum2Data = window.__docum2Data.filter(function(item) { return item.id !== deletedId; });
        if (window.__d2CheckedIds) window.__d2CheckedIds.delete(deletedId);
        if (window.__docum2Data.length > 0) {
          var newIdx = Math.min(idx, window.__docum2Data.length - 1);
          window.__docum2SelectedId = window.__docum2Data[newIdx].id;
        } else {
          window.__docum2SelectedId = 0;
        }
        (window.__docum2Render || renderDocum2)();
        updateD2CheckedUI();
      }

      function syncD2CheckAll() {
        var formBodyEl = document.querySelector('.form-modal-body') || document.body;
        var checkAll = formBodyEl.querySelector('#d2-check-all');
        if (!checkAll) return;
        var cbs = formBodyEl.querySelectorAll('.d2-row-check');
        if (cbs.length === 0) { checkAll.checked = false; checkAll.indeterminate = false; return; }
        var checked = 0;
        cbs.forEach(function(cb) { if (cb.checked) checked++; });
        checkAll.checked = checked === cbs.length;
        checkAll.indeterminate = checked > 0 && checked < cbs.length;
      }
      function updateD2CheckedUI() {
        var n = window.__d2CheckedIds ? window.__d2CheckedIds.size : 0;
        var countEl = document.querySelector('#d2-sel-count');
        var selWrap = document.querySelector('#d2-sel-wrap');
        if (countEl) countEl.textContent = n;
        if (selWrap) selWrap.classList.toggle('visible', n > 0);
        syncD2CheckAll();
      }
      function updateD2Buttons() {
        var editBtn = formBody.querySelector('#d2-edit-btn');
        var delBtn = formBody.querySelector('#d2-del-btn');
        var copyBtn = formBody.querySelector('#d2-copy-btn');
        var disabled = !window.__docum2SelectedId;
        if (editBtn) editBtn.disabled = disabled;
        if (delBtn) delBtn.disabled = disabled;
        if (copyBtn) copyBtn.disabled = disabled;
      }
      function clearChecked() {
        window.__d2CheckedIds = new Set();
        d2ShowOnlyFilter = false;
        updateD2CheckedUI();
        renderDocum2();
      }
      function invertChecked() {
        var all = window.__docum2Data.map(function(i) { return i.id; });
        var cur = window.__d2CheckedIds;
        window.__d2CheckedIds = new Set(all.filter(function(id) { return !cur.has(id); }));
        updateD2CheckedUI();
        renderDocum2();
      }
      function toggleD2ShowOnly() {
        d2ShowOnlyFilter = !d2ShowOnlyFilter;
        renderDocum2();
      }

      renderDocum2();
      if (window.__docum2SelectedId === 0 && window.__docum2Data && window.__docum2Data.length > 0) {
        window.__docum2SelectedId = window.__docum2Data[0].id;
        renderDocum2();
      }
      updateD2CheckedUI();

      var d2ProductData = [];
      try { d2ProductData = JSON.parse((table && table.dataset.products) || '[]'); } catch(e) {}
      InlineEdit.init({
        tbody: formBody.querySelector('.docum2-table tbody'),
        saveUrl: saveUrl,
        fields: {
          product_id:   { dbField: 'product_id', type: 'lookup', label: 'Товар' },
          product_name: { dbField: 'product_id', type: 'lookup', label: 'Товар' },
          quant:        { dbField: 'quant', type: 'text', label: 'Кол-во' },
          price:        { dbField: 'price', type: 'text', label: 'Цена' },
          discount:     { dbField: 'discount', type: 'text', label: 'Скидка' },
          note:         { dbField: 'note', type: 'textarea', label: 'Примечание' }
        },
        getLookupData: function (field) {
          if (field === 'product_id' || field === 'product_name') return d2ProductData;
          return [];
        },
        onSaveSuccess: function (data, field) {
          if (data && data.item && data.item.id) {
            var item = data.item;
            var found = false;
            window.__docum2Data = window.__docum2Data.map(function(it) {
              if (it.id == item.id) { found = true; return item; }
              return it;
            });
            if (!found) window.__docum2Data.push(item);
            window.__docum2SelectedId = item.id;
            (window.__docum2Render || renderDocum2)();
          }
          applyDocum2Totals(data);
        }
      });

      var addBtn = formBody.querySelector('#d2-add-btn');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          if (!documId) return;
          var url = 'docum2_form.php?mode=new&docum_id=' + documId;
          openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
        });
      }

      var editBtn = formBody.querySelector('#d2-edit-btn');
      if (editBtn) editBtn.addEventListener('click', function() { if (window.__docum2SelectedId) onEdit(window.__docum2SelectedId); });

      var delBtn = formBody.querySelector('#d2-del-btn');
      if (delBtn) {
        delBtn.addEventListener('click', function() {
          var sid = window.__docum2SelectedId;
          if (!sid) return;
          var url = 'docum2_form.php?mode=delete&id=' + sid + '&docum_id=' + documId;
          openFormModal(url, { onRestore: onItemDeleted, activeId: null });
        });
      }

      var copyBtn = formBody.querySelector('#d2-copy-btn');
      if (copyBtn) {
        copyBtn.addEventListener('click', function() {
          var sid = window.__docum2SelectedId;
          if (!sid) return;
          var url = 'docum2_form.php?mode=copy&id=' + sid + '&docum_id=' + documId;
          openFormModal(url, { onRestore: onItemSaved, activeId: 'product-id' });
        });
      }

      var refreshBtn = formBody.querySelector('#d2-refresh-btn');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
          refreshDocum2Data();
        });
      }

      var importBtn = formBody.querySelector('#d2-import-btn');
      if (importBtn) {
        importBtn.addEventListener('click', function() {
          var fd = new FormData();
          fd.set('field', '_import_marked_count');
          fd.set('docum_id', String(documId));
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
              if (!confirm('Вставить в продажу ' + cnt + ' отмеченных товаров?')) return;
              var fd2 = new FormData();
              fd2.set('field', '_import_marked');
              fd2.set('docum_id', String(documId));
              fetch(saveUrl, { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                  if (data && data.ok) {
                    var msg = 'Импортировано товаров: ' + (data.inserted || 0);
                    refreshBtn.click();
                    applyDocum2Totals(data);
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

      /* Export dropdown */
      var exportCsvBtn = formBody.querySelector('#d2-export-csv');
      if (exportCsvBtn) exportCsvBtn.addEventListener('click', function(e) { e.preventDefault(); exportAllD2(); });
      var exportXlsBtn = formBody.querySelector('#d2-export-xls');
      if (exportXlsBtn) exportXlsBtn.addEventListener('click', function(e) { e.preventDefault(); exportAllD2(); });
      /* Print dropdown */
      var printAllBtn = formBody.querySelector('#d2-print-all');
      if (printAllBtn) printAllBtn.addEventListener('click', function(e) { e.preventDefault(); printAllD2(); });
      var printSelBtn = formBody.querySelector('#d2-print-selected');
      if (printSelBtn) printSelBtn.addEventListener('click', function(e) { e.preventDefault(); printD2Checked(); });
      var printPageBtn = formBody.querySelector('#d2-print-page');
      if (printPageBtn) printPageBtn.addEventListener('click', function(e) { e.preventDefault(); printPageD2(); });

      /* Batch actions — guard against duplicate binding */
      function bindOnce(el, fn) { if (!el || el.dataset.d2Bound) return; el.dataset.d2Bound = '1'; el.addEventListener('click', fn); }
      bindOnce(formBody.querySelector('#d2-sel-clear'), function(e) { e.preventDefault(); clearChecked(); });
      bindOnce(formBody.querySelector('#d2-sel-invert'), function(e) { e.preventDefault(); invertChecked(); });
      bindOnce(formBody.querySelector('#d2-sel-show'), function(e) { e.preventDefault(); toggleD2ShowOnly(); });
      bindOnce(formBody.querySelector('#d2-sel-export'), function(e) { e.preventDefault(); exportD2Checked(); });
      bindOnce(formBody.querySelector('#d2-sel-print'), function(e) { e.preventDefault(); printD2Checked(); });
      bindOnce(formBody.querySelector('#d2-sel-delete'), function(e) { e.preventDefault(); batchDeleteChecked(); });

      /* Search */
      var searchInput = formBody.querySelector('#d2-search-input');
      var searchBtn = formBody.querySelector('#d2-search-btn');
      var clearFilterBtn = formBody.querySelector('#d2-clear-filter-btn');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          if (!searchActive) return;
          searchText = searchInput.value;
          currentPage = 1;
          renderDocum2();
        });
        searchInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            searchActive = true;
            searchText = searchInput.value.trim();
            searchBtn.classList.add('active');
            currentPage = 1;
            renderDocum2();
          }
        });
      }
      if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', function() {
          searchActive = false;
          searchText = '';
          searchCols = new Set(D2_COL_KEYS);
          searchCond = 'contains';
          if (searchBtn) searchBtn.classList.remove('active');
          if (searchInput) searchInput.value = '';
          currentPage = 1;
          renderDocum2();
          document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        });
      }
      function updateClearFilterBtn() {
        if (clearFilterBtn) {
          clearFilterBtn.style.display = (searchActive && searchText) ? '' : 'none';
        }
      }
      var _origRender = renderDocum2;
      renderDocum2 = function() {
        _origRender();
        updateClearFilterBtn();
      };
      function closeD2Panels() {
        document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
        document.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
      }
      var searchCondBtn = formBody.querySelector('#d2-search-cond-btn');
      if (searchBtn && searchCondBtn && typeof SearchPanel !== 'undefined') {
        var ns = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(ns, searchBtn);
        searchBtn = ns;
        var nc = searchCondBtn.cloneNode(true);
        searchCondBtn.parentNode.replaceChild(nc, searchCondBtn);
        searchCondBtn = nc;
        SearchPanel.init({
          form: formBody.querySelector('#d2-search-form'),
          condBtn: searchCondBtn,
          toggleBtn: searchBtn,
          columns: D2_SEARCH_COLS,
          pageUrl: location.href,
          popupCheckboxes: true,
          emptyClass: 'search-cond-placeholder',
          closeAllPanels: closeD2Panels,
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
            renderDocum2();
            closeD2Panels();
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
            renderDocum2();
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
            ind.textContent = sortDir === 'asc' ? ' ▲' : ' ▼';
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
          renderDocum2();
        });
      });
      var sortBtn = formBody.querySelector('#d2-sort-btn');
      if (sortBtn) {
        sortBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          closeD2Panels();
          var cols = D2_SEARCH_COLS;
          var directions = [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }];
          var levels = sortCol >= 0
            ? [{ col: D2_COL_KEYS[sortCol], dir: sortDir }]
            : [{ col: 'product_name', dir: 'asc' }];

          function colLabel(k) { for (var i = 0; i < cols.length; i++) if (cols[i].key === k) return cols[i].label; return k; }
          function dirLabel(k) { for (var i = 0; i < directions.length; i++) if (directions[i].key === k) return directions[i].label; return k; }
          function serialize(lvs) { return lvs.map(function(l) { return l.col + ':' + l.dir; }).join(','); }
          function usedCols(lvs, exceptIdx) { var u = []; lvs.forEach(function(l, i) { if (i !== exceptIdx) u.push(l.col); }); return u; }

          var backdrop = document.createElement('div');
          backdrop.className = 'sort-modal-backdrop';
          backdrop.innerHTML =
            '<div class="sort-modal" role="dialog" aria-labelledby="d2SortTitle">' +
              '<div class="sort-modal-header">' +
                '<span id="d2SortTitle">Сортировка</span>' +
                '<button class="sort-modal-close" type="button" title="Закрыть">✕</button>' +
              '</div>' +
              '<div class="sort-modal-body">' +
                '<div class="sort-level-actions">' +
                  '<button type="button" class="sort-add-level" id="d2SortAddBtn">+ Добавить уровень</button>' +
                  '<button type="button" class="sort-remove-level" id="d2SortRemoveBtn">− Удалить уровень</button>' +
                '</div>' +
                '<div class="sort-levels">' +
                  '<div class="sort-cols">' +
                    '<div class="sort-cols-header">Столбец</div>' +
                    '<div class="sort-cols-list" id="d2SortColsList"></div>' +
                  '</div>' +
                  '<div class="sort-dirs">' +
                    '<div class="sort-dirs-header">Направление</div>' +
                    '<div class="sort-dirs-list" id="d2SortDirsList"></div>' +
                  '</div>' +
                '</div>' +
                '<div class="sort-modal-actions">' +
                   '<button type="button" class="sort-apply" id="d2SortApplyBtn"><img src="img/ok.png" alt="" />Сортировать</button>' +
                   '<button type="button" class="sort-cancel" id="d2SortCancelBtn"><img src="img/cancel.png" alt="" />Отменить</button>' +
                '</div>' +
              '</div>' +
            '</div>';
          document.body.appendChild(backdrop);

          var modal = backdrop.querySelector('.sort-modal');
          var colsList = backdrop.querySelector('#d2SortColsList');
          var dirsList = backdrop.querySelector('#d2SortDirsList');
          var addBtnLvl = backdrop.querySelector('#d2SortAddBtn');
          var removeBtnLvl = backdrop.querySelector('#d2SortRemoveBtn');
          var cancelBtn = backdrop.querySelector('#d2SortCancelBtn');
          var applyBtn = backdrop.querySelector('#d2SortApplyBtn');
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
            addBtnLvl.disabled = levels.length >= cols.length;
            removeBtnLvl.disabled = levels.length <= 1;
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

          addBtnLvl.addEventListener('click', function(e) {
            e.stopPropagation();
            if (levels.length >= cols.length) return;
            var used = usedCols(levels, -1);
            var available = [];
            cols.forEach(function(c) { if (used.indexOf(c.key) === -1) available.push(c); });
            if (available.length === 0) return;
            levels.push({ col: available[0].key, dir: 'asc' });
            render();
          });

          removeBtnLvl.addEventListener('click', function(e) {
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
              var idx = D2_COL_KEYS.indexOf(col);
              sortCol = idx >= 0 ? idx : 0;
              sortDir = levels[0].dir;
              currentPage = 1;
              renderDocum2();
              table.querySelectorAll('thead th[data-col] .sort-indicator').forEach(function(s) { s.remove(); });
              var ths = table.querySelectorAll('thead th[data-col]');
              if (ths[sortCol]) {
                var ind = document.createElement('span');
                ind.className = 'sort-indicator';
                ind.textContent = sortDir === 'asc' ? ' ▲' : ' ▼';
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

      updateD2Buttons();
      /* ColumnsPanel for docum2 */
      var d2ColBtn = formBody.querySelector('#d2-columns-btn');
      if (d2ColBtn && window.ColumnsPanel) {
        var d2ColData = [];
        var d2ColDefData = [];
        try { d2ColData = JSON.parse(table.dataset.columns || '[]'); } catch(e) {}
        try { d2ColDefData = JSON.parse(table.dataset.columnsDefaults || '[]'); } catch(e) {}
        ColumnsPanel.init({
          btn: d2ColBtn,
          saveUrl: 'docum2_columns_save.php',
          tbl: 'docum2',
          closeAllPanels: closeD2Panels,
          initialColumns: d2ColData,
          defaultColumns: d2ColDefData.length > 0 ? d2ColDefData : d2ColData,
          onSave: function(state) {
            closeD2Panels();
            var ie = formBody.querySelector('input[name="id"]');
            var iid = ie ? parseInt(ie.value, 10) : 0;
            if (!iid) return;
            fetch('sale_form.php?typeop=<?= $typeop ?>&mode=edit&id=' + iid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d || !d.html) return;
                var ai = 0; var at = formBody.querySelector('.tab-header.active');
                if (at) ai = parseInt(at.dataset.tabIndex, 10);
                formBody.innerHTML = d.html;
                resetD2ColResize();
                initFormLookups(); initFormTabs();
                try { initDocum2Table(); } catch(e) {}
                initSaleFormTabSwitch();
                try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
                var f = formBody.querySelector('form[data-form-modal]');
                bindForm(f);
                var hdr = formBody.querySelector('.tab-header[data-tab-index="' + ai + '"]');
                var pane = formBody.querySelector('.tab-pane[data-tab-index="' + ai + '"]');
                if (hdr && pane) {
                  formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); });
                  formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
                  hdr.classList.add('active'); pane.classList.add('active');
                }
              });
          },
          onResetWidth: function() {
            closeD2Panels();
            var ie = formBody.querySelector('input[name="id"]');
            var iid = ie ? parseInt(ie.value, 10) : 0;
            if (!iid) return;
            fetch('sale_form.php?typeop=<?= $typeop ?>&mode=edit&id=' + iid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d || !d.html) return;
                var ai = 0; var at = formBody.querySelector('.tab-header.active');
                if (at) ai = parseInt(at.dataset.tabIndex, 10);
                formBody.innerHTML = d.html;
                resetD2ColResize();
                initFormLookups(); initFormTabs();
                try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); }
                initSaleFormTabSwitch();
                var f = formBody.querySelector('form[data-form-modal]'); bindForm(f);
                var hdr = formBody.querySelector('.tab-header[data-tab-index="' + ai + '"]');
                var pane = formBody.querySelector('.tab-pane[data-tab-index="' + ai + '"]');
                if (hdr && pane) { formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); }); formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); }); hdr.classList.add('active'); pane.classList.add('active'); }
              });
          },
          onResetOrder: function() {
            closeD2Panels();
            var ie = formBody.querySelector('input[name="id"]');
            var iid = ie ? parseInt(ie.value, 10) : 0;
            if (!iid) return;
            fetch('sale_form.php?typeop=<?= $typeop ?>&mode=edit&id=' + iid + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d || !d.html) return;
                var ai = 0; var at = formBody.querySelector('.tab-header.active');
                if (at) ai = parseInt(at.dataset.tabIndex, 10);
                formBody.innerHTML = d.html;
                resetD2ColResize();
                initFormLookups(); initFormTabs();
                try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); }
                initSaleFormTabSwitch();
                var f = formBody.querySelector('form[data-form-modal]'); bindForm(f);
                var hdr = formBody.querySelector('.tab-header[data-tab-index="' + ai + '"]');
                var pane = formBody.querySelector('.tab-pane[data-tab-index="' + ai + '"]');
                if (hdr && pane) { formBody.querySelectorAll('.tab-header').forEach(function(h) { h.classList.remove('active'); }); formBody.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); }); hdr.classList.add('active'); pane.classList.add('active'); }
              });
          }
        });
      }

    } /* end initDocum2Table */

    function restoreStashedForm(data) {
      if (!stashed) return;
      formBody.innerHTML = stashed.html;
      resetD2ColResize(); { window.__docum2Data = stashed.docum2Data; }
      initFormLookups();
      initFormTabs();
      try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); }
      initSaleFormTabSwitch();
      try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
      evalFormScripts();
      var pc = formBody.querySelector('.tab-pane[data-tab-index="1"]');
      if (pc) try { initDocum2Table(); } catch(e) {}
      var old = stashed;
      stashed = null;
      if (old.tabIndex && old.tabIndex !== 0 && typeof window.__switchSaleTab === 'function') { window.__switchSaleTab(old.tabIndex); }
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

    function evalFormScripts(root) {
      (root || formBody).querySelectorAll('script:not([src])').forEach(function(s) {
        try { eval(s.textContent); } catch(ex) { console.error('[plat-form] script error', ex); }
      });
    }

    function syncFormValues(root) {
      (root || formBody).querySelectorAll('input, textarea, select').forEach(function(el) {
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.checked) el.setAttribute('checked', ''); else el.removeAttribute('checked');
        } else if (el.tagName === 'SELECT') {
          Array.from(el.options).forEach(function(o) { o.removeAttribute('selected'); });
          var sel = el.options[el.selectedIndex];
          if (sel) sel.setAttribute('selected', '');
        } else {
          el.setAttribute('value', el.value);
        }
      });
    }

    function openFormModal(url, stash) {
      closeAllPanels();
      if (stash && formBody.innerHTML) {
        syncFormValues();
        var activeTabEl = document.querySelector('.tab-header.active');
        var activeTabIndex = activeTabEl ? parseInt(activeTabEl.getAttribute('data-tab-index'), 10) : 0;
        stashed = { html: formBody.innerHTML, onRestore: stash.onRestore || null, activeId: stash.activeId || null, docum2Data: window.__docum2Data ? window.__docum2Data.slice() : [], tabIndex: activeTabIndex };
      } else if (!formBody.innerHTML) { stashed = null; }
      formModal.classList.add('open');
      document.body.style.overflow = 'hidden';
      fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || typeof data.html !== 'string') throw new Error('bad response');
          formBody.innerHTML = data.html;
          resetD2ColResize();
          initFormLookups();
          initFormTabs();
          try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); }
          initSaleFormTabSwitch();
          try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
          evalFormScripts();
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
    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel, .search-cond-panel, .search-cond-pop, .columns-panel').forEach(function(p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function(p) { p.classList.remove('open'); });
    }

    function closeFormModal() {
      formModal.classList.remove('open');
      document.body.style.overflow = '';
      formBody.innerHTML = '';
      stashed = null;
      window.__d2CheckedIds = new Set();
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
        fetch(form.getAttribute('action') || 'sale_form.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
              if (stashed) { restoreStashedForm(data); } else {
                if (submitBtn && submitBtn.name === 'action' && submitBtn.value === 'apply') {
                  applyDocum2Totals(data);
                } else {
                  closeFormModal();
                  const params = new URLSearchParams(location.search);
                  FormModalCore.setFocusAfterSave(params, form, data);
                  location.href = 'sale.php?' + params.toString();
                }
              }
            } else { formBody.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>'; resetD2ColResize(); initFormLookups(); initFormTabs();                 try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); }
                initSaleFormTabSwitch();
                try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
                evalFormScripts();
                var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f); if (data && data.focusField) { var el = formBody.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } } else { FormModalCore.focusFirstField(formBody); } }
          }).catch(function (err) {
            const flash = document.createElement('div'); flash.className = 'flash flash--error'; flash.textContent = 'Ошибка подключения к БД: ' + (err && err.message ? err.message : 'unknown'); form.insertBefore(flash, form.firstChild);
          });
      });
      var applyDiscBtn = form.querySelector('#apply-discount-btn');
      if (applyDiscBtn) {
        applyDiscBtn.addEventListener('click', function() {
          var discountEl = form.querySelector('#sale-discount');
          var discount = discountEl ? parseFloat(discountEl.value.replace(',', '.')) : 0;
          if (isNaN(discount)) discount = 0;
          if (!confirm('Изменить скидку у всех товаров продажи на ' + discount + ' %?')) return;
          var idEl = form.querySelector('input[name="id"]');
          var docId = idEl ? parseInt(idEl.value, 10) : 0;
          if (!docId) return;
          var fd = new FormData();
          fd.set('field', '_apply_discount');
          fd.set('docum_id', String(docId));
          fd.set('discount', String(discount));
          fetch('docum2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data && data.ok) {
                applyDocum2Totals(data);
                var fd2 = new FormData();
                fd2.set('field', '_list');
                fd2.set('docum_id', String(docId));
                fetch('docum2_field_save.php', { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                  .then(function(r) { return r.json(); })
                  .then(function(list) {
                    if (Array.isArray(list)) {
                      window.__docum2Data = list;
                      if (window.__docum2Render) window.__docum2Render();
                    }
                  });
              }
            });
        });
      }
    }

    window.openFormModal = openFormModal;
    window.__openFormModal = openFormModal;

    document.addEventListener('click', function(e) {
      if (e.target.closest('[data-form-close]') && formModal.classList.contains('open')) {
        e.stopImmediatePropagation();
        e.preventDefault();
        if (stashed) { restoreStashedForm(null); } else { closeFormModal(); }
      }
    });

    document.addEventListener('d2:editProduct', function(e) {
      var pid = e.detail && e.detail.pid;
      if (!pid) return;
      openFormModal('tmc_form.php?mode=edit&id=' + pid, {
        onRestore: function(d, bodyEl) {
          if (!d || !d.ok) return;
          var root = bodyEl || document;
          var ph = root.querySelector('[name="product_id"]');
          if (ph && d.id) ph.value = d.id;
          var li = root.querySelector('.lookup-input');
          if (li && d.product_name) li.value = d.product_name;
          var items = window.__docum2Data;
          if (items && d.id) {
            for (var i = 0; i < items.length; i++) {
              if (items[i].product_id == d.id) items[i].product_name = d.product_name;
            }
            if (window.__docum2Render) window.__docum2Render();
          }
        }
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && formModal.classList.contains('open')) {
        e.preventDefault();
        if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); }
      }
      if (e.key === 'Enter' && formModal.classList.contains('open') && !stashed && !e.target.closest('.cell-edit-panel') && e.target.tagName !== 'TEXTAREA') {
        var f = formBody.querySelector('form[data-form-modal]');
        if (f) { e.preventDefault(); var btn = f.querySelector('button[type="submit"]:not([tabindex="-1"]):not([disabled])'); if (btn) btn.click(); }
      }
    });

    document.addEventListener('click', function (e) {
      if (formModal.classList.contains('open')) {
        const cancelA = e.target.closest('a.btn-secondary');
        if (cancelA && cancelA.closest('.form-actions')) {
          e.preventDefault(); e.stopImmediatePropagation();
          if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); } return;
        }
      }
      let a = e.target.closest('a[href*="sale_form.php"]');
      if (a) { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return; e.preventDefault(); e.stopImmediatePropagation(); openFormModal(a.getAttribute('href')); return; }
      const trig = e.target.closest('[data-form-open]');
      if (trig) { e.preventDefault(); openFormModal(trig.getAttribute('data-form-open')); return; }
      if (e.target.closest('[data-form-close]')) { e.preventDefault(); if (stashed) { restoreStashedForm(null); } else { closeFormModal(); location.reload(); } return; }
      if (e.target.closest('[data-lookup-add]')) { e.preventDefault(); syncFormValues(); var openFn = openFormModal; FormModalCore.handleLookupAdd(e.target.closest('[data-lookup-add]'), function (fn) { stashed = { html: formBody.innerHTML, onRestore: fn, activeId: document.activeElement ? document.activeElement.id : null, docum2Data: window.__docum2Data ? window.__docum2Data.slice() : [] }; }, openFn); return; }
    });

    function initSaleFormTabSwitch() {
      var headers = document.querySelectorAll('.tab-header');
      var panes   = document.querySelectorAll('.tab-pane');
      if (!headers.length) return;

      function switchTab(idx) {
        headers.forEach(function(x) { x.classList.remove('active'); });
        panes.forEach(function(x) { x.classList.remove('active'); });
        var hd = document.querySelector('.tab-header[data-tab-index="' + idx + '"]');
        if (hd) hd.classList.add('active');
        var pane = document.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
        if (pane) pane.classList.add('active');
      }

      headers.forEach(function(h) {
        h.removeEventListener('click', h._d2TabHandler);
        h._d2TabHandler = function() {
          var idx = parseInt(this.getAttribute('data-tab-index'), 10);
          if (idx === 1) {
            var d2t = document.querySelector('.docum2-table');
            var curId = d2t ? d2t.getAttribute('data-docum-id') : '0';
            if (!curId || curId === '0') {
              var form = document.querySelector('form[data-form-modal]');
              if (!form) { switchTab(idx); return; }
              var fd = new FormData(form);
              fd.set('ajax', '1');
              fd.set('action', 'apply');
              var xhr = new XMLHttpRequest();
              xhr.open('POST', form.getAttribute('action') || 'sale_form.php', true);
              xhr.onload = function() {
                if (xhr.status === 200) {
                  try {
                    var data = JSON.parse(xhr.responseText);
                    var newId = '0';
                    if (data.html) {
                      var p = new DOMParser();
                      var d = p.parseFromString(data.html, 'text/html');
                      var inp = d.querySelector('input[name="id"]');
                      if (inp && inp.value) newId = inp.value;
                    }
                    if (newId !== '0') {
                      if (d2t) d2t.setAttribute('data-docum-id', newId);
                      var idInput = document.querySelector('input[name="id"]');
                      if (idInput) idInput.value = newId;
                      window.__docum2Data = [];
                      try { window.__docum2Data = JSON.parse(d2t.dataset.items || '[]'); } catch(e) {}
                      initDocum2Table();
                      switchTab(1);
                      return;
                    }
                  } catch(e) {}
                }
              };
              xhr.send(fd);
              return;
            }
          }
          switchTab(idx);
        };
        h.addEventListener('click', h._d2TabHandler);
      });

      switchTab(0);
      window.__switchSaleTab = switchTab;
    }

    window.initSaleForm = function() { initFormLookups(); initSaleFormTabSwitch(); };

    /* Column resize (deferred until tab visible) */
    var d2ColResizeInited = false;
    function resetD2ColResize() { d2ColResizeInited = false; }
    function tryInitD2ColResize() {
      if (d2ColResizeInited) return;
      var pane = document.querySelector('.tab-pane[data-tab-index="1"]');
      if (!pane || !pane.classList.contains('active')) return;
      var d2t = document.querySelector('.docum2-table');
      if (!d2t || d2t.dataset.colResizeInited) return;
      d2ColResizeInited = true;
      if (typeof ColumnResize !== 'undefined') {
        ColumnResize.init({ saveUrl: 'docum2_column_width_save.php', tbl: 'docum2', selector: '.docum2-table' });
      }
    }
    setTimeout(tryInitD2ColResize, 100);

    function openPlatForm(url) {
      openFormModal(url, { onRestore: function(data) {
        if (data && data.sum_plat !== undefined) {
          var el = document.getElementById('sale-sum-plat');
          if (el) { el.value = data.sum_plat || ''; el.style.color = data.sum_plat_red ? '#e57373' : ''; el.style.fontWeight = data.sum_plat_red ? 'bold' : ''; }
        }
        var deletedIdx = -1;
        if (data && data.mode === 'delete' && data.id) {
          for (var i = 0; i < window.__platData.length; i++) {
            if (window.__platData[i].id == data.id) { deletedIdx = i; break; }
          }
        }
        refreshPlatTable(function() {
          if (data && data.ok && data.id && data.mode !== 'delete') {
            var found = false;
            for (var i = 0; i < window.__platData.length; i++) {
              if (window.__platData[i].id == data.id) {
                window.__platSelectedId = window.__platData[i].id;
                found = true;
                break;
              }
            }
            if (!found) window.__platSelectedId = 0;
          } else if (deletedIdx >= 0 && window.__platData && window.__platData.length > 0) {
            var newIdx = Math.min(deletedIdx, window.__platData.length - 1);
            window.__platSelectedId = window.__platData[newIdx].id;
          } else {
            window.__platSelectedId = 0;
          }
          if (typeof window.__platRender === 'function') window.__platRender();
          if (typeof window.updatePlatButtons === 'function') window.updatePlatButtons();
        });
      }});
    }

    function refreshPlatTable(callback) {
      var container = formBody.querySelector('.tab-pane[data-tab-index="2"]');
      if (!container) return;
      delete container.__platInited;
      var tableEl = container.querySelector('[data-plat-table]');
      if (!tableEl) return;
      var docIdEl = formBody.querySelector('input[name="id"]');
      var docId = docIdEl ? parseInt(docIdEl.value, 10) : 0;
      if (docId <= 0) return;
      var typeopEl = formBody.querySelector('input[name="typeop"]');
      var typeop = typeopEl ? parseInt(typeopEl.value, 10) : 120;
      fetch('sale_form.php?ajax=1&mode=edit&id=' + docId + '&typeop=' + typeop, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (!d || !d.html) return;
          var p = new DOMParser();
          var doc = p.parseFromString(d.html, 'text/html');
          var newTableEl = doc.querySelector('[data-plat-table]');
          if (newTableEl && tableEl) {
            tableEl.dataset.items = newTableEl.dataset.items;
            window.__platSelectedId = 0;
            initPlatTable();
          }
          var sumPlatInput = doc.querySelector('#sale-sum-plat');
          if (sumPlatInput) {
            var cur = document.getElementById('sale-sum-plat');
            if (cur) { cur.value = sumPlatInput.value; cur.style.color = sumPlatInput.style.color; cur.style.fontWeight = sumPlatInput.style.fontWeight; }
          }
          if (typeof callback === 'function') callback();
        });
    }

    function initPlatTable() {
      var container = formBody.querySelector('.tab-pane[data-tab-index="2"]');
      if (!container) return;
      if (container.__platInited) return;
      container.__platInited = true;
      var tableEl = container.querySelector('[data-plat-table]');
      if (!tableEl) return;

      var platData = [];
      try { platData = JSON.parse(tableEl.dataset.items || '[]'); } catch(e) {}
      window.__platData = platData;
      if (!window.__platSelectedId) window.__platSelectedId = 0;

      if (!container.__platEventsInited) {
        container.__platEventsInited = '1';

        tableEl.addEventListener('click', function(e) {
          var tr = e.target.closest('.plat-row');
          if (!tr) return;
          var id = parseInt(tr.dataset.id, 10);
          tableEl.querySelectorAll('.plat-row.selected').forEach(function(r) { r.classList.remove('selected'); });
          window.__platSelectedId = id;
          tr.classList.add('selected');
          if (typeof window.updatePlatButtons === 'function') window.updatePlatButtons();
        });

        tableEl.addEventListener('dblclick', function(e) {
          var tr = e.target.closest('.plat-row');
          if (!tr) return;
          var id = parseInt(tr.dataset.id, 10);
          if (id > 0) openPlatForm('plat_form.php?mode=edit&id=' + id);
        });
      }

      var searchInput = formBody.querySelector('#plat-search-input');
      var searchBtn = formBody.querySelector('#plat-search-btn');
      var clearBtn = formBody.querySelector('#plat-clear-filter-btn');
      var filterBanner = formBody.querySelector('#plat-filter-banner');

      window.__platSearchActive = false;
      window.__platSearchText = '';

      window.updatePlatButtons = function() {
        var editBtn = formBody.querySelector('#plat-edit-btn');
        var delBtn = formBody.querySelector('#plat-del-btn');
        var disabled = !window.__platSelectedId;
        if (editBtn) editBtn.disabled = disabled;
        if (delBtn) delBtn.disabled = disabled;
      };

      function cn(v) { return (v === '' || v === null || v === undefined) ? '' : v; }
      function hl(v) {
        if (!window.__platSearchActive || !window.__platSearchText) return cn(v);
        var s = String(v !== null && v !== undefined ? v : '');
        if (s === '') return '-';
        var st = window.__platSearchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var re = new RegExp('(' + st + ')', 'gi');
        return s.replace(re, '<span class="hl">$1</span>');
      }

      function renderPlat() {
        var tbody = tableEl.querySelector('tbody');
        if (!tbody) return;
        var data = window.__platData;
        if (window.__platSearchActive && window.__platSearchText) {
          var st = window.__platSearchText.toLowerCase();
          data = data.filter(function(item) {
            return (item.datetime || '').toLowerCase().indexOf(st) >= 0
              || (item.client_name || '').toLowerCase().indexOf(st) >= 0
              || (item.zat_name || '').toLowerCase().indexOf(st) >= 0
              || (item.sum || '').toString().toLowerCase().indexOf(st) >= 0
              || (item.plat_type || '').toLowerCase().indexOf(st) >= 0
              || (item.note || '').toLowerCase().indexOf(st) >= 0;
          });
        }
        if (data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="col-d2-empty">Нет платежей</td></tr>';
          return;
        }
        function fmt(v) {
          var n = parseFloat(v);
          return isNaN(n) ? '' : n.toLocaleString('ru-RU', {minimumFractionDigits:2, maximumFractionDigits:2});
        }
        var html = '';
        for (var i = 0; i < data.length; i++) {
          var item = data[i];
          var sel = (item.id === window.__platSelectedId) ? ' selected' : '';
          html += '<tr data-id="' + item.id + '" data-row-id="' + item.id + '" class="plat-row' + sel + '">'
            + '<td>' + hl(item.datetime) + '</td>'
            + '<td>' + hl(item.client_name) + '</td>'
            + '<td>' + hl(item.zat_name) + '</td>'
            + '<td class="col-sum">' + hl(fmt(item.sum)) + '</td>'
            + '<td>' + hl(item.plat_type) + '</td>'
            + '<td>' + hl(item.note) + '</td>'
            + '</tr>';
        }
        tbody.innerHTML = html;
      }
      window.__platRender = function() {
        if (window.__platSelectedId === 0 && window.__platData && window.__platData.length > 0) {
          window.__platSelectedId = window.__platData[0].id;
        }
        renderPlat();
      };

      function updatePlatFilterUI() {
        if (clearBtn) clearBtn.style.display = (window.__platSearchActive && window.__platSearchText) ? '' : 'none';
        if (filterBanner) {
          if (window.__platSearchActive && window.__platSearchText) {
            filterBanner.style.display = '';
            filterBanner.innerHTML = '<span class="filter-chip"><span class="filter-chip-text">(Содержит «' + window.__platSearchText.replace(/</g,'&lt;') + '»)</span><button type="button" class="filter-chip-close" id="plat-banner-clear" title="Снять фильтр">✕</button></span>';
            var cb = filterBanner.querySelector('#plat-banner-clear');
            if (cb) cb.addEventListener('click', function() { window.__platSearchActive = false; window.__platSearchText = ''; if (searchInput) searchInput.value = ''; renderPlat(); updatePlatFilterUI(); });
          } else {
            filterBanner.style.display = 'none';
          }
        }
      }

      if (!container.__platHandlersInited) {
        container.__platHandlersInited = '1';

        var addBtn = formBody.querySelector('#plat-add-btn');
        var editBtn = formBody.querySelector('#plat-edit-btn');
        var delBtn = formBody.querySelector('#plat-del-btn');
        var refreshBtn = formBody.querySelector('#plat-refresh-btn');

        if (addBtn) addBtn.addEventListener('click', function() {
          openPlatForm(this.getAttribute('data-plat-url'));
        });
        if (editBtn) editBtn.addEventListener('click', function() {
          if (window.__platSelectedId > 0) openPlatForm('plat_form.php?mode=edit&id=' + window.__platSelectedId);
        });
        if (delBtn) delBtn.addEventListener('click', function() {
          if (window.__platSelectedId > 0) openPlatForm('plat_form.php?mode=delete&id=' + window.__platSelectedId);
        });
        if (refreshBtn) refreshBtn.addEventListener('click', refreshPlatTable);

        if (searchBtn) searchBtn.addEventListener('click', function() { window.__platSearchActive = true; window.__platSearchText = (searchInput ? searchInput.value.trim() : ''); renderPlat(); updatePlatFilterUI(); });
        if (searchInput) searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); window.__platSearchActive = true; window.__platSearchText = searchInput.value.trim(); renderPlat(); updatePlatFilterUI(); } });
        if (clearBtn) clearBtn.addEventListener('click', function() { window.__platSearchActive = false; window.__platSearchText = ''; if (searchInput) searchInput.value = ''; renderPlat(); updatePlatFilterUI(); });
      }

      renderPlat();
      if (window.__platSelectedId === 0 && window.__platData && window.__platData.length > 0) {
        window.__platSelectedId = window.__platData[0].id;
        renderPlat();
      }
      window.updatePlatButtons();
    }

    // Init on page load for direct access (will be a no-op if modal not open)
    if (document.querySelector('.tab-container')) { try { initDocum2Table(); } catch(e) { console.error('initDocum2Table', e); } initSaleForm(); try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); } }
  </script>
  <script>
    // Lookup data for form modal
    var __clientLookupData = <?= json_encode($clientFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
    var __storeLookupData = <?= json_encode($storeFilterOptions, JSON_UNESCAPED_UNICODE) ?>;
    var __sotrLookupData = <?= json_encode($sotrList, JSON_UNESCAPED_UNICODE) ?>;
    function initFormLookups() {
      if (typeof bindLookup === 'function') {
        formBody.querySelectorAll('[data-lookup="client"]').forEach(function(el) { bindLookup({ root: el, data: __clientLookupData, readonly: el.hasAttribute('data-readonly') }); });
        formBody.querySelectorAll('[data-lookup="store"]').forEach(function(el) { bindLookup({ root: el, data: __storeLookupData, readonly: el.hasAttribute('data-readonly') }); });
        formBody.querySelectorAll('[data-lookup="sotr"]').forEach(function(el) { bindLookup({ root: el, data: __sotrLookupData, readonly: el.hasAttribute('data-readonly') }); });
        formBody.querySelectorAll('[data-lookup="product"]').forEach(function(el) {
          if (el.dataset.lookupInited) return;
          var rawJson = el.getAttribute('data-countries');
          if (!rawJson) return;
          var data;
          try { data = JSON.parse(rawJson); } catch(e) { return; }
          if (!Array.isArray(data)) return;
          var priceInput = formBody.querySelector('#d2-price');
          var quantInput = formBody.querySelector('#d2-quant');
          var discountInput = formBody.querySelector('#d2-discount');
          var sumInput = formBody.querySelector('#d2-sum');
          var sumDiscInput = formBody.querySelector('#d2-sum-discount');
          function recalc() {
            var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
            var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
            var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
            function _rf(v) {
              if (v === 0) return '';
              return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
            }
            if (sumInput) sumInput.value = _rf(q * p * (1 - d / 100));
            if (sumDiscInput) sumDiscInput.value = _rf(q * p * (d / 100));
          }
          var handler = function(id, name) {
            var item = data.find(function(p) { return p.id === id; });
            if (!item) return;
            if (priceInput) priceInput.value = item.price || '0';
            if (quantInput && !quantInput.value) quantInput.value = '1';
            recalc();
          };
          try { bindLookup({ root: el, data: data, readonly: false, onSelect: handler }); el.dataset.lookupInited = '1'; } catch(e) {}
          if (quantInput) quantInput.addEventListener('input', recalc);
          if (priceInput) priceInput.addEventListener('input', recalc);
          if (discountInput) discountInput.addEventListener('input', recalc);
        });
        formBody.querySelectorAll('[data-lookup]:not([data-lookup="client"]):not([data-lookup="store"]):not([data-lookup="sotr"]):not([data-lookup="product"])').forEach(function(el) {
          if (el.dataset.lookupInited) return;
          var rawJson = el.getAttribute('data-countries');
          if (!rawJson) return;
          var data;
          try { data = JSON.parse(rawJson); } catch(e) { return; }
          if (!Array.isArray(data)) return;
          try { bindLookup({ root: el, data: data, readonly: false }); el.dataset.lookupInited = '1'; } catch(e) {}
        });
      }
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
          if (idx === 1) tryInitD2ColResize();
        });
      });
      setTimeout(tryInitD2ColResize, 100);
    }
  </script>
  <script>
    InlineEdit.init({
      tbody: document.querySelector('table tbody'),
      saveUrl: 'sale_field_save.php',
      fields: <?php
        $inlineFields = [];
        $valCases = '';
        foreach ($visibleColumns as $vc) {
          $cn = $vc['name'];
          if (!empty($vc['readonly']) || $cn === 'id' || $cn === 'number' || $cn === 'sum') continue;
          $isLookup = !empty($vc['param']);
          $inlineFields[$cn] = ['dbField' => $isLookup ? $vc['param'] : $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
          if ($isLookup) $valCases .= "    case " . json_encode($cn, JSON_UNESCAPED_UNICODE) . ": if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
      ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
      getLookupData: function (field) {
        var map = { client: window.__clientLookupData, store: window.__storeLookupData, store2: window.__storeLookupData };
        return map[field] || [];
      },
      validate: function (field, value) {
        switch (field) {
<?= $valCases ?>
        }
        return null;
      },
      onOpenForm: window.__openFormModal
    });

    ColumnResize.init({ saveUrl: 'sale_column_width_save.php', tbl: 'sale' });
    ExportModal.init();
  </script>
<?php render_page_footer(); ?>
