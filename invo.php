<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/invo_columns.php';
require_once __DIR__ . '/config/invo_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/embedded-subtable-template.php';
require_once __DIR__ . '/lib/table-page-scripts.php';

$TBL = 'invoice';
$PAGE_TITLE = 'Счета';
$PAGE_URL   = 'invo.php';
$FORM_PREFIX = 'invo_form';

ensure_marks_table($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $tpTemp = new TablePage($conn, $invoPageConfig);
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    if ($col === 'state') {
        echo json_encode([
            ['id' => 1, 'name' => 'Черновик'],
            ['id' => 2, 'name' => 'Выставлен'],
            ['id' => 3, 'name' => 'Оплачен'],
            ['id' => 4, 'name' => 'Отменен'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($tpTemp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$tp = new TablePage($conn, $invoPageConfig);

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

$tp->appendWhere("i.doctype_id = ?", [10], 'i');

$clientFilter = (string)($_GET['client_id'] ?? '');
$clientIds = [];
if ($clientFilter !== '') {
    $clientIds = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
}
if (count($clientIds) > 0) {
    $place = implode(',', array_fill(0, count($clientIds), '?'));
    $tp->appendWhere("i.client_id IN ($place)", $clientIds, str_repeat('i', count($clientIds)));
}

$storeFilter = (string)($_GET['store_id'] ?? '');
$storeIds = [];
if ($storeFilter !== '') {
    $storeIds = array_values(array_filter(array_map('intval', explode(',', $storeFilter)), fn($v) => $v > 0));
}
if (count($storeIds) > 0) {
    $place = implode(',', array_fill(0, count($storeIds), '?'));
    $tp->appendWhere("i.store_id IN ($place)", $storeIds, str_repeat('i', count($storeIds)));
}

$sotrFilter = (string)($_GET['sotr_id'] ?? '');
$sotrIds = [];
if ($sotrFilter !== '') {
    $sotrIds = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
}
if (count($sotrIds) > 0) {
    $place = implode(',', array_fill(0, count($sotrIds), '?'));
    $tp->appendWhere("i.sotr_id IN ($place)", $sotrIds, str_repeat('i', count($sotrIds)));
}

if (!function_exists('build_filter')) {
function build_filter(string $search, array $cols = [], string $cond = 'contains'): array {
    $colToExpr = [
        'number'   => 'i.number',
        'date'     => 'i.date',
        'client'   => 'c.name',
        'state'    => 'i.state',
        'store'    => 'st.name',
        'discount' => 'i.discount',
        'sum'      => 'i.sum',
        'sotr'     => 's.doc_name',
        'note'     => 'i.note',
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

if (count($clientIds) > 0) {
    $names = [];
    $stmt = @$conn->prepare("SELECT client_id, name FROM client WHERE client_id IN (" . implode(',', array_fill(0, count($clientIds), '?')) . ") ORDER BY name");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($clientIds)), $clientIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
        $stmt->close();
    }
    $filters[] = ['kind' => 'client', 'text' => 'Контрагент = ' . implode(', ', $names), 'clear' => ['client_id']];
}

if (count($storeIds) > 0) {
    $names = [];
    $stmt = @$conn->prepare("SELECT store_id, name FROM store WHERE store_id IN (" . implode(',', array_fill(0, count($storeIds), '?')) . ") ORDER BY name");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($storeIds)), $storeIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
        $stmt->close();
    }
    $filters[] = ['kind' => 'store', 'text' => 'Участок = ' . implode(', ', $names), 'clear' => ['store_id']];
}

if (count($sotrIds) > 0) {
    $names = [];
    $stmt = @$conn->prepare("SELECT sotr_id, CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,'')) AS name FROM sotr WHERE sotr_id IN (" . implode(',', array_fill(0, count($sotrIds), '?')) . ") ORDER BY last_name, first_name");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($sotrIds)), $sotrIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
        $stmt->close();
    }
    $filters[] = ['kind' => 'sotr', 'text' => 'Сотрудник = ' . implode(', ', $names), 'clear' => ['sotr_id']];
}

$total = $tp->getTotalCount($conn);
$pages = $tp->pages;
$page  = $tp->page;
$offset= $tp->offset;

$rows = $tp->getRows($conn);

$clientLookupOptions = [];
$cr = @$conn->query("SELECT client_id AS id, name FROM client ORDER BY name");
if ($cr) while ($r = $cr->fetch_assoc()) $clientLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$storeLookupOptions = [];
$sr = @$conn->query("SELECT store_id AS id, name FROM store ORDER BY name");
if ($sr) while ($r = $sr->fetch_assoc()) $storeLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$sotrLookupOptions = [];
$so = @$conn->query("SELECT sotr_id AS id, CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,'')) AS name FROM sotr ORDER BY last_name, first_name");
if ($so) while ($r = $so->fetch_assoc()) $sotrLookupOptions[] = ['id' => (int)$r['id'], 'name' => trim((string)$r['name'])];

$productLookupOptions = [];
$po = @$conn->query("SELECT product_id AS id, product_name AS name FROM product ORDER BY product_name");
if ($po) while ($r = $po->fetch_assoc()) $productLookupOptions[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['invoice_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;
?>
<?php
render_head_start($PAGE_TITLE); ?>
  <style>
    .data-table tbody td.col-note { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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

    <h1 class="page-title"><img src="img/invoice.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

    <?php
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
          $fullUrl = 'invo_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
          $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
      }

      $repmenuTemplates = [];
      $stmtTpl = @$conn->prepare("SELECT rp_id, number, name, fname FROM repmenu WHERE gr_id = 1 AND (HIDE_FLAG IS NULL OR HIDE_FLAG = 0) AND fname != '' ORDER BY number");
      if ($stmtTpl) { $stmtTpl->execute(); $resTpl = $stmtTpl->get_result(); if ($resTpl) while ($rt = $resTpl->fetch_assoc()) $repmenuTemplates[] = $rt; $stmtTpl->close(); }

      $printDropdownHtml = '';
      foreach ($repmenuTemplates as $tpl) {
          $printDropdownHtml .= '<a class="dropdown-item" href="#" onclick="printWithTemplate(' . (int)$tpl['rp_id'] . ',\'' . h(addslashes($tpl['fname'])) . '\');return false;">' . h($tpl['name']) . '</a>';
      }
      if (count($repmenuTemplates) > 0) {
          $printDropdownHtml .= '<div class="dropdown-divider"></div>';
      }
      $printDropdownHtml .= render_print_dropdown_items('invo', $exportQs, (int)$page, $marksCount > 0);
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
    $saleBtn = '<button class="icon-btn" id="createSaleBtn" title="Создать Продажу" type="button"><img src="img/sale.png" alt="" /></button>';
    render_toolbar_left($FORM_PREFIX, $marksCount, $exportDropdownHtml, $printDropdownHtml, $saleBtn);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, $PAGE_URL);
    render_toolbar_wrapper_close();
    ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
      <table class="data-table">
        <?php render_table_colgroup($visibleColumns, $columnWidths, ['number' => '80px', 'date' => '80px', 'client' => '200px', 'state' => '100px', 'store' => '150px', 'discount' => '80px', 'sum' => '100px', 'sotr' => '150px', 'pos' => '60px', 'note' => 'auto']); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
            'thAttrsCallback' => function($cn, $cm) use ($clientIds, $storeIds, $sotrIds) {
                if ($cm && !empty($cm['filter']) && $cn === 'client') {
                    return ' data-col="' . h($cn) . '" data-param="client_id" data-values="' . h(implode(',', $clientIds)) . '"';
                }
                if ($cm && !empty($cm['filter']) && $cn === 'store') {
                    return ' data-col="' . h($cn) . '" data-param="store_id" data-values="' . h(implode(',', $storeIds)) . '"';
                }
                if ($cm && !empty($cm['filter']) && $cn === 'sotr') {
                    return ' data-col="' . h($cn) . '" data-param="sotr_id" data-values="' . h(implode(',', $sotrIds)) . '"';
                }
                return '';
            },
            'thHtmlCallback' => function($cn, $cm) {
                if ($cm && !empty($cm['filter']) && in_array($cn, ['client', 'store', 'sotr'])) {
                    return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
                }
                return '';
            },
        ]); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'invoice_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
            $doHilight = in_array($cn, $searchCols, true);
            switch ($cn) {
                case 'number':   $raw = (string)$r['number']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'date':     $raw = (string)$r['date']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'time':     $raw = (string)$r['time']; $dt = $raw ? date('H:i', strtotime($raw)) : ''; return [$raw, $dt];
                case 'client':   $raw = (string)($r['client_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'state':    $raw = (string)$r['state']; $states = ['1'=>'Черновик','2'=>'Выставлен','3'=>'Оплачен','4'=>'Отменен']; return [$raw, $states[$raw] ?? $raw];
                case 'store':    $raw = (string)($r['store_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'discount': $raw = (string)(int)$r['discount']; $nd = (int)$r['discount']; return [$raw, $nd ? $raw : ''];
                case 'sum':      $raw = (string)(float)$r['sum']; $ns = (float)$r['sum']; return [$raw, $ns ? number_format($ns, 2, '.', ' ') : ''];
                case 'sum_plat': $raw = (string)(float)$r['sum_plat']; $ns = (float)$r['sum_plat']; return [$raw, $ns ? number_format($ns, 2, '.', ' ') : ''];
                case 'sotr':     $raw = (string)($r['sotr_name'] ?? ''); return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'pos':      $raw = (string)(int)$r['pos']; $np = (int)$r['pos']; return [$raw, $np ? $raw : ''];
                case 'note':     $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, [
            'tdExtraAttrs' => function($cn, $vc, $r, $i) {
                $a = ' data-col-idx="' . (int)$i . '"';
                if ($cn === 'sum_plat' && (float)($r['sum_plat'] ?? 0) < (float)($r['sum'] ?? 0)) {
                    $a .= ' style="color:#c0392b;font-weight:bold"';
                }
                return $a;
            },
        ]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

  </div>

<?php render_script_includes(['scripts' => ['assets/lookup.js', 'assets/export-modal.js', 'assets/column-filter.js', 'assets/embedded-subtable.js']]); ?>
<?php render_table_page_scripts([
    'pageUrl'         => $PAGE_URL,
    'formPrefix'      => $FORM_PREFIX,
    'tableKey'        => $TBL,
    'fieldSaveUrl'    => 'invo_field_save.php',
    'columnsSaveUrl'  => 'invo_columns_save.php',
    'columnResizeUrl' => 'invo_column_width_save.php',
    'visibleColumns'  => $columnsConfig,
    'defaultColumns'  => $COLUMN_DEFAULTS,
    'exportUrl'       => 'invo_export.php',
    'printUrl'        => 'invo_print.php',
    'preserveParams'  => ['sort'],
    'lookupData' => [
        'client'   => $clientLookupOptions,
        'store'    => $storeLookupOptions,
        'sotr'     => $sotrLookupOptions,
    ],
    'formModalConfig' => [
        'autoInitTables' => ['d2', 'plat'],
        'extra_restore' => '
            if (data && data.id) {
                var idEl = body.querySelector("input[name=\"id\"]");
                if (idEl) {
                    var curId = parseInt(idEl.value, 10);
                    if (curId === 0) idEl.value = data.id;
                }
            }
            initD2Table();
            if (window.__d2Table) {
                if (typeof window.applyInvoice2Totals === "function") window.applyInvoice2Totals(data);
                window.__d2Table.refresh({ focusId: data && data.id ? data.id : 0 });
            }
            initPlatTable();
            if (window.__platTable) {
                if (typeof window.applyInvoice2Totals === "function") window.applyInvoice2Totals(data);
                window.__platTable.refresh({ focusId: data && data.id ? data.id : 0 });
            }
        ',
        'extra_open' => '
            body.querySelectorAll("[data-lookup=\"product\"]").forEach(function(el) {
                if (el.__lookupBound) return;
                try {
                    var _data = JSON.parse(el.getAttribute("data-countries") || "[]");
                    window.bindLookup({
                        root: el, data: _data, readonly: false,
                        onSelect: function(id, name) {
                            var _item = _data.find(function(p){return p.id===id;});
                            if (!_item) return;
                            var _pi = body.querySelector("#inv2-price");
                            if (_pi) _pi.value = _item.price_out || "0";
                            var _qi = body.querySelector("#inv2-quant");
                            var _di = body.querySelector("#inv2-discount");
                            var _si = body.querySelector("#inv2-sum");
                            var _sd = body.querySelector("#inv2-sum-discount");
                            var _q = parseFloat((_qi?_qi.value:"1").replace(",","."))||0;
                            var _p = parseFloat((_pi?_pi.value:"0").replace(",","."))||0;
                            var _d = parseFloat((_di?_di.value:"0").replace(",","."))||0;
                            var _sum = _q*_p*(1-_d/100);
                            var _sdv = _q*_p*(_d/100);
                            if(_si) _si.value = _sum.toFixed(2).replace(".",",");
                            if(_sd) _sd.value = _sdv.toFixed(2).replace(".",",");
                            var _frm = body.querySelector("form[data-form-modal]");
                            if(_frm) {
                                var _fd = new FormData(_frm);
                                fetch(_frm.getAttribute("action"), {method:"POST",body:_fd,headers:{"X-Requested-With":"XMLHttpRequest"},credentials:"same-origin"})
                                .then(function(r){return r.json();}).then(function(d){
                                    if(d&&d.ok){
                                        if(d.id){
                                            body.querySelectorAll("[name=invoice2_id]").forEach(function(e){e.value=d.id;});
                                            var _af=body.querySelector("form[data-form-modal]");
                                            if(_af){
                                                var _act=_af.getAttribute("action");
                                                _af.setAttribute("action",_act.replace(/[?&]id=\d*/,"")+(_act.includes("?")?"&":"?")+"id="+d.id);
                                            }
                                        }
                                        if(window.applyInvoice2Totals) window.applyInvoice2Totals(d);
                                    }
                                }).catch(function(){});
                            }
                        }
                    });
                } catch(ex) {}
            });
            (function(){
                var _qi2 = body.querySelector("#inv2-quant");
                var _pi2 = body.querySelector("#inv2-price");
                var _di2 = body.querySelector("#inv2-discount");
                if (_qi2 || _pi2 || _di2) {
                    function _recalc() {
                        var q = parseFloat((_qi2?_qi2.value:"1").replace(",","."))||0;
                        var p = parseFloat((_pi2?_pi2.value:"0").replace(",","."))||0;
                        var d = parseFloat((_di2?_di2.value:"0").replace(",","."))||0;
                        var s = q*p*(1-d/100), sd = q*p*(d/100);
                        var si = body.querySelector("#inv2-sum"), sdi = body.querySelector("#inv2-sum-discount");
                        if(si) si.value = s.toFixed(2).replace(".",",");
                        if(sdi) sdi.value = sd.toFixed(2).replace(".",",");
                    }
                    if (_qi2) _qi2.addEventListener("input", _recalc);
                    if (_pi2) _pi2.addEventListener("input", _recalc);
                    if (_di2) _di2.addEventListener("input", _recalc);
                }
            })();
        ',
    ],
    'extraCode' => "
        window.applyInvoice2Totals = function(d) {
            var body = document.getElementById('formModalBody') || document;
            var s = d && d.total_sum !== undefined ? d.total_sum : (d && d.sum !== undefined ? d.sum : undefined);
            if (s !== undefined) {
                var el = body.querySelector('#invo-sum');
                if (el) el.value = s || '';
            }
            var sd = d && d.total_sum_discount !== undefined ? d.total_sum_discount : (d && d.sum_discount !== undefined ? d.sum_discount : undefined);
            if (sd !== undefined) {
                var elSd = body.querySelector('#invo-sum-discount');
                if (elSd) elSd.value = sd || '';
            }
            var snds = d && d.total_sum_nds !== undefined ? d.total_sum_nds : (d && d.sum_nds !== undefined ? d.sum_nds : undefined);
            if (snds !== undefined) {
                var elNds = body.querySelector('#invo-sum-nds');
                if (elNds) elNds.value = snds || '';
            }
            if (d && d.pos !== undefined) {
                var elPos = body.querySelector('#invo-pos');
                if (elPos) elPos.value = d.pos > 0 ? String(d.pos) : '';
            }
            if (d && d.sum_plat !== undefined) {
                var elPlat = body.querySelector('#invo-sum-plat');
                if (elPlat) {
                    elPlat.value = d.sum_plat || '';
                    var sumVal = d && d.sum !== undefined ? d.sum : (body.querySelector('#invo-sum') ? body.querySelector('#invo-sum').value : 0);
                    elPlat.style.color = (parseFloat(d.sum_plat) || 0) < (parseFloat(sumVal) || 0) ? '#c0392b' : '';
                    elPlat.style.fontWeight = (parseFloat(d.sum_plat) || 0) < (parseFloat(sumVal) || 0) ? 'bold' : '';
                }
            }
        };
        ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'invo.php' });
        ColumnFilter.init({ thSelector: '.col-store', pageUrl: 'invo.php' });
        ColumnFilter.init({ thSelector: '.col-sotr', pageUrl: 'invo.php' });
        ColumnFilter.init({ thSelector: '.col-state', pageUrl: 'invo.php', param: 'state' });
    ",
]); ?>
<script>
    <?php render_embedded_subtable_scripts([
        'prefix'           => 'd2',
        'columns'          => [
            ['key' => 'product_name', 'label' => 'Товар', 'type' => 'lookup', 'dbField' => 'product_id'],
            ['key' => 'quant',        'label' => 'Кол-во', 'align' => 'right'],
            ['key' => 'price',        'label' => 'Цена', 'align' => 'right'],
            ['key' => 'discount',     'label' => 'Скидка', 'align' => 'right'],
            ['key' => 'sum',          'label' => 'Сумма', 'align' => 'right'],
            ['key' => 'note',         'label' => 'Примечание'],
        ],
        'saveUrl'          => 'invoice2_field_save.php',
        'parentField'      => 'invoice_id',
        'childFormUrl'     => 'invoice2_form.php',
        'childFormName'    => 'invoice2',
        'hasExport'        => false,
        'hasPrint'         => false,
        'hasSearch'        => true,
        'columnResizeUrl'  => 'invoice2_column_width_save.php',
        'columnResizeTbl'  => 'invoice2',
        'lookupData' => [
            'product_name' => $productLookupOptions,
        ],
        'totalsCallback' => 'applyInvoice2Totals',
    ]); ?>
  </script>
  <script>
    <?php render_embedded_subtable_scripts([
        'prefix'           => 'plat',
        'columns'          => [
            ['key' => 'datetime',    'label' => 'Дата/Время', 'readonly' => true],
            ['key' => 'client_name', 'label' => 'Контрагент', 'readonly' => true],
            ['key' => 'zat_name',    'label' => 'Вид операции', 'readonly' => true],
            ['key' => 'sum',         'label' => 'Сумма', 'align' => 'right'],
            ['key' => 'plat_type',   'label' => 'Вид платежа'],
            ['key' => 'out_flag',    'label' => 'Тип', 'readonly' => true],
            ['key' => 'note',        'label' => 'Примечание'],
        ],
        'saveUrl'          => 'plat_field_save.php',
        'parentField'      => 'doc_id',
        'childFormUrl'     => 'plat_form.php?doc_type=10',
        'childFormName'    => 'plat',
        'hasExport'        => false,
        'hasPrint'         => false,
        'hasSearch'        => true,
        'totalsCallback' => 'applyInvoice2Totals',
    ]); ?>
  </script>
<?php render_page_footer(); ?>
<script>
(function(){
  var btn = document.getElementById('createSaleBtn');
  if (btn) {
    var dd = btn.parentNode.querySelectorAll('.dropdown');
    if (dd.length >= 2) btn.parentNode.insertBefore(btn, dd[2] || null);
  }
})();
document.getElementById('createSaleBtn')?.addEventListener('click', function () {
    var sel = document.querySelector('table tbody tr.selected');
    if (!sel) { alert('Выберите счёт для создания продажи'); return; }
    var invId = parseInt(sel.getAttribute('data-row-id') || '0', 10);
    if (!invId) { alert('Выберите счёт для создания продажи'); return; }
    var btn = this;
    btn.disabled = true;
    fetch('invo_to_sale.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: 'invoice_id=' + invId })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.ok) {
                window.__openFormModal('sale_form.php?typeop=120&mode=edit&id=' + d.id);
            } else {
                alert(d && d.error ? d.error : 'Ошибка создания продажи');
            }
        })
        .catch(function () { alert('Ошибка создания продажи'); })
        .then(function () { btn.disabled = false; });
});
window.printSelectedInvoice = function () {
    var sel = document.querySelector('table tbody tr.selected');
    if (!sel) { alert('Выберите строку для печати'); return; }
    var id = parseInt(sel.getAttribute('data-row-id') || '0', 10);
    if (!id) { alert('Выберите строку для печати'); return; }
    window.open('invoice_print_template.php?id=' + id, '_blank');
};
window.printWithTemplate = function (rpId, fname) {
    var sel = document.querySelector('table tbody tr.selected');
    if (!sel) { alert('Выберите строку для печати'); return; }
    var invId = parseInt(sel.getAttribute('data-row-id') || '0', 10);
    if (!invId) { alert('Выберите строку для печати'); return; }
    window.open('invoice_print_template.php?id=' + invId + '&template=' + encodeURIComponent(fname), '_blank');
};
</script>
