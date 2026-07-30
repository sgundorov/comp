<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/sale_columns.php';
require_once __DIR__ . '/config/sale_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/EmbeddedTable.php';

$accessFlags = render_access_control($conn, 'Docum');

$typeop = (int)($_GET['typeop'] ?? 120);
if (!in_array($typeop, [40, 20, 100, 110, 120, 127], true)) $typeop = 120;

$prihodFlag = 0;
$pfRow = $conn->query("SELECT COALESCE(prihod_flag,0) AS pf FROM typeop WHERE typeop_id = $typeop")->fetch_assoc();
if ($pfRow) $prihodFlag = (int)$pfRow['pf'];

$DOC_LABELS = [40 => 'Возврат от покупателя', 20 => 'Приход', 100 => 'Внутреннее перемещение', 110 => 'Возврат поставщику', 120 => 'Продажа', 127 => 'Списание'];
$DOC_ICONS  = [40 => 'sale.png', 20 => 'prihod.png', 100 => 'move.png', 110 => 'prihod.png', 120 => 'sale.png', 127 => 'spisan.png'];
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
                    LEFT JOIN typeop tp ON tp.typeop_id = d.typeop
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
$tp->processColumnFilters($conn);

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

$clearQs = $tp->buildClearQs();

$tp->buildFilters();
$filters = $tp->filters;

$exportQs = $tp->buildExportQs(['typeop' => $typeop]);

$paginationHtml = '';

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

// ---- d2 (Товары) columns ----
$_d2cols = [
    ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'dbField' => 'product_id'],
    ['key' => 'quant',        'label' => 'Кол-во', 'align' => 'right'],
    ['key' => 'price',        'label' => 'Цена', 'align' => 'right'],
];
if (!in_array($typeop, [127, 100], true)) {
    $_d2cols[] = ['key' => 'discount', 'label' => 'Скидка', 'align' => 'right'];
}
$_d2cols[] = ['key' => 'sum', 'label' => 'Сумма', 'align' => 'right'];
$saleNdsRate = (int)($appSettings['nds_rate'] ?? 22);
if ($saleNdsRate > 0) {
    $_d2cols[] = ['key' => 'sum_nds', 'label' => 'НДС', 'align' => 'right'];
}
$_d2cols[] = ['key' => 'note', 'label' => 'Примечание'];

$_d2w = ['product_name' => 'auto', 'quant' => '80px', 'price' => '90px', 'discount' => '80px', 'sum' => '100px', 'note' => '250px'];
if ($saleNdsRate > 0) {
    $_d2w['sum_nds'] = '80px';
}

$d2Table = new EmbeddedTable([
    'prefix'       => 'd2',
    'columns'      => $_d2cols,
    'colWidths'    => $_d2w,
    'saveUrl'      => 'docum2_field_save.php',
    'parentField'  => 'docum_id',
    'childFormUrl' => 'docum2_form.php',
    'childFormName'=> 'docum2',
    'columnResizeUrl' => 'docum2_column_width_save.php',
    'columnResizeTbl' => 'docum2',
    'hasExport'    => true,
    'hasImport'    => true,
    'hasPrint'     => true,
    'hasSearch'    => true,
    'totalsCallback' => 'applyDocum2Totals',
    'accessFlags'  => $accessFlags,
]);

// ---- plat (Оплата) columns ----
$_pcols = [
    ['key' => 'datetime',    'label' => 'Дата/Время', 'readonly' => true],
    ['key' => 'client_name', 'label' => 'Контрагент', 'readonly' => true],
    ['key' => 'zat_name',    'label' => 'Вид операции', 'readonly' => true],
    ['key' => 'sum',         'label' => 'Сумма', 'align' => 'right'],
    ['key' => 'plat_type',   'label' => 'Вид платежа'],
    ['key' => 'note',        'label' => 'Примечание'],
];
$_pw = ['datetime' => '140px', 'client_name' => 'auto', 'zat_name' => '150px', 'sum' => '100px', 'plat_type' => '100px', 'note' => 'auto'];

$platTable = new EmbeddedTable([
    'prefix'       => 'plat',
    'columns'      => $_pcols,
    'colWidths'    => $_pw,
    'saveUrl'      => 'plat_field_save.php',
    'parentField'  => 'doc_id',
    'childFormUrl' => 'plat_form.php?doc_type=' . $typeop,
    'childFormName'=> 'plat',
    'columnResizeUrl' => 'plat_column_width_save.php',
    'columnResizeTbl' => 'plat',
    'hasExport'    => false,
    'hasPrint'     => false,
    'hasSearch'    => true,
    'totalsCallback' => 'applyDocum2Totals',
    'accessFlags'  => $accessFlags,
]);

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
              'thAttrsCallback' => function($cn, $cm, $i) {
                  if ($cm && !empty($cm['filter'])) {
                      $param = $cm['param'] ?? ($cn . '_id');
                      $raw = (string)($_GET[$param] ?? '');
                      $values = $raw !== '' ? array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0)) : [];
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
          'trExtraAttrs' => function($r) { return (int)($r['accept_flag'] ?? 0) === 0 ? ' style="color:#ffe9a8"' : ''; },
      ]); ?>
    </table>
    </div>

    <?php $tp->renderPagination(['typeop' => $typeop]); ?>

    <?php render_form_modal(); ?>
  </div>

<?php
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js', 'assets/embedded-subtable.js', 'assets/column-filter.js', 'assets/embedded-table.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(sale_columns_widths_print(), JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script>
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
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
      var _tb = document.querySelector('.toolbar');
      SelectionToolbar.initTableSelection('sale.php', _tb.getAttribute('data-search') || '');

      SelectionToolbar.init({
        pageUrl: 'sale.php?typeop=<?= $typeop ?>',
        search: _tb.getAttribute('data-search') || '',
        getInvertUrl: function () {
          var other = new URLSearchParams(location.search);
          other.delete('ids');
          return 'sale.php?action=invertSelection&' + other.toString();
        },
        getExportUrl: function () { return 'sale_export.php?format=csv&all=1&' + new URLSearchParams(location.search).toString(); },
        getPrintUrl: function () { return 'sale_print.php?all=1&' + new URLSearchParams(location.search).toString(); }
      });

      ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'sale.php' });
      ColumnFilter.init({ thSelector: '.col-store', pageUrl: 'sale.php' });
      ColumnFilter.init({ thSelector: '.col-store2', pageUrl: 'sale.php' });

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

      __refreshSelectionUI();
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
          const af = window.__accessFlags || {};
          if (openBtn)   openBtn.disabled   = !enabled || !!af.change_flag;
          if (copyBtn)   copyBtn.disabled   = !enabled || !!af.insert_flag;
          if (deleteBtn) deleteBtn.disabled = !enabled || !!af.delete_flag;
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
          if (window.__accessFlags && window.__accessFlags.change_flag) return;
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
        formExtraParams: '&typeop=<?= $typeop ?>',
        accessFlags: window.__accessFlags
      });
    })();

    if (!window.__accessFlags || !window.__accessFlags.change_flag) {
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
    }

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
    var formModal = document.getElementById('formModal');
    var formBody  = document.getElementById('formModalBody');
    var stashed   = null;

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
      var snds = d && d.total_sum_nds !== undefined ? d.total_sum_nds : (d && d.sum_nds !== undefined ? d.sum_nds : undefined);
      if (snds !== undefined) {
        var el = document.getElementById('sale-sum-nds');
        if (el) el.value = snds || '';
      }
    }

    <?php $d2Table->renderScripts(); ?>

    function restoreStashedForm(data) {
      if (!stashed) return;
      var savedTableSelections = stashed._tableSelections || {};
      var html = stashed.html;
      var scripts = [];
      html = html.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function(m, code) { if (code.trim()) scripts.push(code); return ''; });
      formBody.innerHTML = html;
      scripts.forEach(function(code) { try { eval(code); } catch(e) { console.error('form script', e); } });
      initFormLookups();
      try { initD2Table(); } catch(e) { console.error('initD2Table', e); }
      initSaleFormTabSwitch();
      try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
      var old = stashed;
      stashed = null;
      if (savedTableSelections) {
        Object.keys(savedTableSelections).forEach(function(k) {
          var tbl = window[k];
          if (tbl && typeof tbl.selectedId === 'number' && savedTableSelections[k]) {
            var id = savedTableSelections[k];
            var found = false;
            if (tbl.data) { for (var i = 0; i < tbl.data.length; i++) { if (tbl.data[i].id == id) { found = true; break; } } }
            if (found) {
              tbl.selectedId = id;
              tbl.render();
              if (tbl.tbody) {
                var row = tbl.tbody.querySelector('tr[data-id="' + id + '"]');
                if (row) row.scrollIntoView({ block: 'nearest' });
              }
            }
          }
        });
      }
      if (old.tabIndex && old.tabIndex !== 0 && typeof window.__switchSaleTab === 'function') { window.__switchSaleTab(old.tabIndex); }
      var f = formBody.querySelector('form[data-form-modal]');
      bindForm(f);
      FormModalCore.bindFormTabTrap(f);
      if (data) try { old.onRestore(data, formBody); } catch (e) {}
      if (old.activeId) {
        var el = formBody.querySelector('[id="' + old.activeId.replace(/"/g, '\\"') + '"]');
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
        var savedTableSelections = {};
        Object.keys(window).forEach(function(k) {
          if (k.indexOf('__') === 0 && k.indexOf('Table') === k.length - 5 && window[k] && typeof window[k].selectedId === 'number') {
            savedTableSelections[k] = window[k].selectedId;
          }
        });
        stashed = { html: formBody.innerHTML, onRestore: stash.onRestore || null, activeId: stash.activeId || null, tabIndex: activeTabIndex, _tableSelections: savedTableSelections };
      } else if (!formBody.innerHTML) { stashed = null; }
      formModal.classList.add('open');
      document.body.style.overflow = 'hidden';
      fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || typeof data.html !== 'string') throw new Error('bad response');
          var html = data.html;
          var scripts = [];
          html = html.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function(m, code) { if (code.trim()) scripts.push(code); return ''; });
          formBody.innerHTML = html;
          scripts.forEach(function(code) { try { eval(code); } catch(e) { console.error('form script', e); } });
          initFormLookups();
          try { initD2Table(); } catch(e) { console.error('initD2Table', e); }
          initSaleFormTabSwitch();
          try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
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
    }
    function bindForm(form) {
      if (!form) return;
      var _mode = form.querySelector('input[name="mode"]');
      var _isDelete = _mode && _mode.value === 'delete';
      if (window.__accessFlags && window.__accessFlags.save_flag && !_isDelete) {
        form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
      }
      form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          if (document.activeElement && document.activeElement.closest('[data-row-id]')) return;
          var submitBtn = form.querySelector('button[type="submit"]');
          if (submitBtn && !submitBtn.disabled) { e.preventDefault(); submitBtn.click(); }
        }
      });
      form.addEventListener('submit', function (e) {
        if (window.__accessFlags && window.__accessFlags.save_flag && !_isDelete) return;
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
            } else { formBody.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>'; initFormLookups(); try { initD2Table(); } catch(e) { console.error('initD2Table', e); }
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
                    if (Array.isArray(list) && window.__d2Table) {
                      window.__d2Table.data = list;
                      window.__d2Table.render();
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
          var tbl = window.__d2Table;
          if (tbl && d.id) {
            for (var i = 0; i < tbl.data.length; i++) {
              if (tbl.data[i].product_id == d.id) tbl.data[i].product_name = d.product_name;
            }
            tbl.render();
          }
        }
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && formModal.classList.contains('open')) {
        e.preventDefault();
        if (stashed) { restoreStashedForm(null); } else { closeFormModal(); var p = new URLSearchParams(location.search); var st = document.querySelector('table.data-table tbody tr.selected'); if (st) p.set('focus', st.getAttribute('data-row-id')); location.href = location.pathname + '?' + p.toString(); }
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
          if (stashed) { restoreStashedForm(null); } else { closeFormModal(); var p = new URLSearchParams(location.search); var st = document.querySelector('table.data-table tbody tr.selected'); if (st) p.set('focus', st.getAttribute('data-row-id')); location.href = location.pathname + '?' + p.toString(); } return;
        }
      }
      let a = e.target.closest('a[href*="sale_form.php"]');
      if (a) { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return; e.preventDefault(); e.stopImmediatePropagation(); openFormModal(a.getAttribute('href')); return; }
      const trig = e.target.closest('[data-form-open]');
      if (trig) { e.preventDefault(); openFormModal(trig.getAttribute('data-form-open')); return; }
      if (e.target.closest('[data-form-close]')) { e.preventDefault(); if (stashed) { restoreStashedForm(null); } else { closeFormModal(); var p = new URLSearchParams(location.search); var st = document.querySelector('table.data-table tbody tr.selected'); if (st) p.set('focus', st.getAttribute('data-row-id')); location.href = location.pathname + '?' + p.toString(); } return; }
      if (e.target.closest('[data-lookup-add]')) { e.preventDefault(); syncFormValues(); var openFn = openFormModal; FormModalCore.handleLookupAdd(e.target.closest('[data-lookup-add]'), function (fn) { stashed = { html: formBody.innerHTML, onRestore: fn, activeId: document.activeElement ? document.activeElement.id : null }; }, openFn); return; }
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
        h.addEventListener('click', function() {
          switchTab(parseInt(this.getAttribute('data-tab-index'), 10));
        });
      });

      switchTab(0);
      window.__switchSaleTab = switchTab;
    }

    window.initSaleForm = function() { initFormLookups(); initSaleFormTabSwitch(); };



    <?php $platTable->renderScripts(); ?>

    // Init on page load for direct access (will be a no-op if modal not open)
    if (document.querySelector('.tab-container')) { try { initD2Table(); } catch(e) { console.error('initD2Table', e); } initSaleForm(); try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); } }
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
          var sndsInput = formBody.querySelector('#d2-snds');
          var ndsForm = formBody.querySelector('form[data-form-modal]');
          var ndsRate = parseInt(ndsForm ? ndsForm.getAttribute('data-nds-rate') : '22', 10) || 0;
          var noNds = (ndsForm ? ndsForm.getAttribute('data-no-nds') : '0') === '1';
          function recalc() {
            var q = parseFloat((quantInput ? quantInput.value : '1').replace(',', '.')) || 0;
            var p = parseFloat((priceInput ? priceInput.value : '0').replace(',', '.')) || 0;
            var d = parseFloat((discountInput ? discountInput.value : '0').replace(',', '.')) || 0;
            var sum = q * p * (1 - d / 100);
            var sumD = q * p * (d / 100);
            var snds = noNds ? (sum * ndsRate / (100 + ndsRate)) : (sum * ndsRate / 100);
            function _rf(v) {
              if (v === 0) return '';
              return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ',');
            }
            if (sumInput) sumInput.value = _rf(sum);
            if (sumDiscInput) sumDiscInput.value = _rf(sumD);
            if (sndsInput) { var ndsf = snds.toFixed(2); sndsInput.value = ndsf === '0.00' ? '' : ndsf.replace('.', ','); }
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
  </script>
  <script>
    if (!window.__accessFlags || !window.__accessFlags.change_flag) {
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
    }

    ColumnResize.init({ saveUrl: 'sale_column_width_save.php', tbl: 'sale' });
    ExportModal.init();
  </script>
<?php render_page_footer(); ?>
