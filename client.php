<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/config/client_page.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';
require_once __DIR__ . '/lib/marks-actions.php';

$accessFlags = render_access_control($conn, 'Client');

ensure_marks_table($conn);
ensure_client_tag_table($conn);

$tp = new TablePage($conn, $clientPageConfig);
$tp->loadColumnWidths($conn);

handle_marks_actions($conn, $tp, 'client', function($action) use ($conn, $tp) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
        exit;
    }
    return null;
});

// --- Column filters ---
$tp->processColumnFilters($conn);

// --- Hide hidden clients ---
if (empty($appSettings['show_hidden']) || $appSettings['show_hidden'] !== '1') {
    $tp->appendWhereRaw("(c.hide_flag = 0 OR c.hide_flag IS NULL)");
}

// --- Tags filter (subquery — not handled by processColumnFilters) ---
$tagFilter = (string)($_GET['tag_id'] ?? '');
$tagFilterIds = [];
$tagFilterNames = [];
if ($tagFilter !== '') {
    $tagFilterIds = array_values(array_filter(array_map('intval', explode(',', $tagFilter)), fn($v) => $v > 0));
}
if (count($tagFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($tagFilterIds), '?'));
    $stmt = @$conn->prepare("SELECT tag_id, tag AS name FROM tag WHERE tag_id IN ($ph)");
    if ($stmt) {
        stmt_bind($stmt, str_repeat('i', count($tagFilterIds)), $tagFilterIds);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) while ($r = $res->fetch_assoc()) $tagFilterNames[] = (string)$r['name'];
        $stmt->close();
    }
    $tp->appendWhere("c.client_id IN (SELECT client_id FROM client_tag WHERE tag_id IN ($ph))", $tagFilterIds, str_repeat('i', count($tagFilterIds)));
}

// --- Lookup data ---
$cliCategLookup = [];
$crs = $conn->query("SELECT cli_categ_id, categ FROM cli_categ ORDER BY categ");
if ($crs) while ($cr = $crs->fetch_assoc()) $cliCategLookup[] = ['id' => (int)$cr['cli_categ_id'], 'name' => (string)$cr['categ']];

$cityLookup = [];
$crs = $conn->query("SELECT city_id, city, country_id FROM city ORDER BY city");
if ($crs) while ($cr = $crs->fetch_assoc()) $cityLookup[] = ['id' => (int)$cr['city_id'], 'name' => (string)$cr['city'], 'country_id' => (int)$cr['country_id']];

$countryLookup = [];
$crs = $conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($crs) while ($cr = $crs->fetch_assoc()) $countryLookup[] = ['id' => (int)$cr['country_id'], 'name' => (string)$cr['country']];

$promoLookup = [];
$crs = $conn->query("SELECT promo_id, promo FROM promo ORDER BY promo");
if ($crs) while ($cr = $crs->fetch_assoc()) $promoLookup[] = ['id' => (int)$cr['promo_id'], 'name' => (string)$cr['promo']];

$tagLookup = [];
$crs = $conn->query("SELECT tag_id, tag FROM tag WHERE client_flag = '1' ORDER BY tag");
if ($crs) while ($cr = $crs->fetch_assoc()) $tagLookup[] = ['id' => (int)$cr['tag_id'], 'name' => (string)$cr['tag']];

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);

$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'client');
$search           = $tp->search;
$searchActive     = $tp->searchActive;
$searchCols       = $tp->searchCols;
$searchCond       = $tp->searchCond;
$sortLevels       = $tp->sortLevels;
$sortQs           = $tp->sortQs;
$showOnly         = $tp->showOnly;
$marks            = $tp->marks;
$marksCount       = $tp->marksCount;
$page             = $tp->page;
$pages            = $tp->pages;
$total            = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['client_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

// --- Build filters ---
$tp->buildFilters();
$filters = $tp->filters;

if (count($tagFilterNames) > 0) {
    $filters[] = ['kind' => 'tag', 'text' => 'Вид деятельности = ' . implode(', ', $tagFilterNames), 'clear' => 'tag_id'];
}

$clearQs = $tp->buildClearQs();

// --- Render ---
render_head_start('Контрагенты'); ?>
  <style>
    .data-table tbody tr:hover td.col-name { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-name { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-name { background: #3a5a8a; }
    .col-supplier_flag .cell-value svg,
    .col-problem_flag .cell-value svg,
    .col-juridical_flag .cell-value svg,
    .col-hide_flag .cell-value svg { display: inline-block; vertical-align: middle; }
    .data-table tbody tr.row-hidden td { color: #999; }
    .data-table tbody tr.row-problem td { color: #e74c3c; }
    .data-table tbody tr.row-juridical td.col-name { color: #ffe9a8; }
    .data-table tbody td.col-sum_nach,
    .data-table tbody td.col-sum_plat,
    .data-table tbody td.col-sum_balans,
    .data-table tbody td.col-disc_goods { text-align: right; }
  </style>
<?php
render_head_end();
render_export_modal();
render_form_modal(); ?>
  <div class="page">
    <?php $activeMenu = 'client.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/customer.png" alt="" /> Контрагенты</h1>

<?php
$exportExtra = [];
if ($tagFilter !== '') $exportExtra['tag_id'] = $tagFilter;
$exportQs = $tp->buildExportQs($exportExtra);

$extraBtnHtml =
  '<button class="icon-btn" id="rowContactsBtn" title="Контакты" disabled><img src="img/contact.png" alt="" /></button>' .
  '<button class="icon-btn" id="rowInvoiceBtn" title="Счета" disabled><img src="img/invoice.png" alt="" /></button>' .
  '<button class="icon-btn" id="rowPlatBtn" title="Оплата" disabled><img src="img/plat.png" alt="" /></button>';

$exportFormats = [];
foreach ([
    ['fmt' => 'csv', 'filename' => 'Контрагенты.csv', 'format' => 'CSV', 'label' => 'Экспорт в CSV'],
    ['fmt' => 'xls', 'filename' => 'Контрагенты.xls', 'format' => 'XLS (Excel)', 'label' => 'Экспорт в Excel'],
] as $item) {
    $fullUrl = 'client_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportFormats[] = $item + ['url' => $fullUrl];
}

?>
    <?php
      $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
          $qs = ['page' => (int)$p];
          if ($searchActive) {
              if ($search !== '') $qs['q'] = $search;
              if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
              $qs['cond'] = $searchCond;
              $qs['sf'] = '1';
          }
          if ($sortQs !== '') $qs['sort'] = $sortQs;
          return 'client.php?' . http_build_query($qs);
      };
      $paginationHtml = render_pagination($page, $pages, $baseQs);

      $exportDropdownHtml = render_export_dropdown_items($exportFormats, $exportQs);
      $printDropdownHtml = render_print_dropdown_items('client', $exportQs, (int)$page, $marksCount > 0);
    ?>
    <?php render_toolbar_wrapper_open([
        'total'        => (int)$total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => (int)$marksCount,
        'search'       => $search,
        'page'         => (int)$page,
        'pages'        => (int)$pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]);
    render_toolbar_left('client_form', $marksCount, $exportDropdownHtml, $printDropdownHtml, $extraBtnHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'client.php');
    render_toolbar_wrapper_close(); ?>

<?php
// Filter banner (custom, with extra filters)
if (count($filters) > 0) {
?>
<div class="mode-banner" id="filterBanner">
    <img src="img/filter.png" alt="" />
    <?php foreach ($filters as $fi => $f): ?>
      <?php if ($fi > 0): ?><span class="filter-sep">и</span><?php endif; ?>
      <span class="filter-chip">
        <span class="filter-chip-text">(<?= h($f['text']) ?>)</span>
        <?php if ($f['clear'] === null): ?>
          <button class="filter-chip-close" type="button" title="Снять фильтр" onclick="toggleShowOnly()">✕</button>
        <?php else: ?>
          <a class="filter-chip-close" href="<?= h($clearQs($f['clear'])) ?>" title="Снять фильтр">✕</a>
        <?php endif; ?>
      </span>
    <?php endforeach; ?>
  </div>
<?php
}
?>
<div class="table-wrap">
  <table class="data-table">
<?php render_table_colgroup($visibleColumns, $columnWidths, [
    'id' => '60px', 'name' => '200px', 'last_name' => '120px', 'first_name' => '150px',
    'title' => '120px', 'cli_categ_id' => '120px',
    'supplier_flag' => '60px', 'problem_flag' => '60px',
    'juridical_flag' => '60px', 'hide_flag' => '60px',
    'phone' => '120px', 'cphone' => '120px', 'email' => '160px', 'site' => '160px',
    'city_id' => '120px', 'country_id' => '120px', 'postindex' => '80px',
    'address_jur' => '200px', 'address' => '200px',
    'pasport' => '140px', 'pasp_date' => '100px', 'pasp_vydan' => '200px', 'birthday' => '100px',
    'promo_id' => '120px',
    'inn' => '120px', 'kpp' => '100px', 'ogrn' => '120px', 'jur_name' => '200px',
    'director' => '150px', 'glavbuh' => '150px',
    'bank' => '200px', 'bik' => '80px', 'schet' => '140px', 'kschet' => '140px',
    'okonh' => '100px', 'okpo' => '100px',
    'disc_goods' => '60px', 'sum_nach' => '80px', 'sum_plat' => '80px', 'sum_balans' => '80px',
    'bdate' => '100px', 'dop1' => '200px', 'tags' => '200px', 'note' => '500px',
]); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, []); ?>
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'client_id', function($r, $cn, $vc) use ($search, $searchCols, $searchCond) {
    $doHilight = in_array($cn, $searchCols, true);
    switch ($cn) {
        case 'id':
            $raw = (string)(int)$r['client_id'];
            return [$raw, $raw];
        case 'name':
            $raw = (string)($r['name'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'last_name':
            $raw = (string)($r['last_name'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'first_name':
            $raw = (string)($r['first_name'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'title':
            $raw = (string)($r['title'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'cli_categ_id':
            $categName = (string)($r['cli_categ_name'] ?? '');
            $colorInt = (int)($r['cli_categ_color'] ?? 0);
            $style = $colorInt > 0 ? ' style="background-color:' . sprintf('#%06x', $colorInt) . ';padding:2px 6px;border-radius:2px;display:inline-block;"' : '';
            $raw = (string)(int)($r['cli_categ_id'] ?? 0);
            $display = $categName !== '' ? '<span' . $style . '>' . h($categName) . '</span>' : '';
            return [$raw, $doHilight ? hilight($categName, $search, $searchCond) : $display];
        case 'supplier_flag':
            $v = (string)($r['supplier_flag'] ?? '0');
            return [$v, $v === '1' ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : ''];
        case 'problem_flag':
            $v = (string)($r['problem_flag'] ?? '0');
            return [$v, $v === '1' ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#e74c3c" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : ''];
        case 'juridical_flag':
            $v = (string)($r['juridical_flag'] ?? '0');
            return [$v, $v === '1' ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : ''];
        case 'hide_flag':
            $v = (string)($r['hide_flag'] ?? '0');
            return [$v, $v === '1' ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#e67e22" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : ''];
        case 'phone':
            $raw = (string)($r['phone'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'cphone':
            $raw = (string)($r['cphone'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'email':
            $raw = (string)($r['email'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'site':
            $raw = (string)($r['site'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'city_id':
            $raw = (string)(int)($r['city_id'] ?? 0);
            return [$raw, $doHilight ? hilight((string)($r['city_name'] ?? ''), $search, $searchCond) : (string)($r['city_name'] ?? '')];
        case 'country_id':
            $raw = (string)(int)($r['country_id'] ?? 0);
            return [$raw, $doHilight ? hilight((string)($r['country_name'] ?? ''), $search, $searchCond) : (string)($r['country_name'] ?? '')];
        case 'postindex':
            $raw = (string)($r['postindex'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'address_jur':
            $raw = (string)($r['address_jur'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'address':
            $raw = (string)($r['address'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'pasport':
            $raw = (string)($r['pasport'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'pasp_date':
            $raw = (string)($r['pasp_date'] ?? '');
            $dt = $raw !== '' ? date('d.m.Y', strtotime($raw)) : '';
            return [$raw, $dt];
        case 'pasp_vydan':
            $raw = (string)($r['pasp_vydan'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'birthday':
            $raw = (string)($r['birthday'] ?? '');
            $dt = $raw !== '' ? date('d.m.Y', strtotime($raw)) : '';
            return [$raw, $dt];
        case 'promo_id':
            $raw = (string)(int)($r['promo_id'] ?? 0);
            return [$raw, (string)($r['promo_name'] ?? '')];
        case 'inn':
            $raw = (string)($r['inn'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'kpp':
            $raw = (string)($r['kpp'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'ogrn':
            $raw = (string)($r['ogrn'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'jur_name':
            $raw = (string)($r['jur_name'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'director':
            $raw = (string)($r['director'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'glavbuh':
            $raw = (string)($r['glavbuh'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'bank':
            $raw = (string)($r['bank'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'bik':
            $raw = (string)($r['bik'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'schet':
            $raw = (string)($r['schet'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'kschet':
            $raw = (string)($r['kschet'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'okonh':
            $raw = (string)($r['okonh'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'okpo':
            $raw = (string)($r['okpo'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'disc_goods':
            $raw = (string)($r['disc_goods'] ?? '');
            $display = $raw !== '' ? $raw . '%' : '';
            return [$raw, $display];
        case 'sum_nach':
            $v = (string)($r['sum_nach'] ?? '0');
            $num = (float)$v;
            return [$v, $num ? number_format($num, 2, '.', ' ') : '0.00'];
        case 'sum_plat':
            $v = (string)($r['sum_plat'] ?? '0');
            $num = (float)$v;
            return [$v, $num ? number_format($num, 2, '.', ' ') : '0.00'];
        case 'sum_balans':
            $v = (string)($r['sum_balans'] ?? '0');
            $num = (float)$v;
            return [$v, $num ? number_format($num, 2, '.', ' ') : '0.00'];
        case 'bdate':
            $raw = (string)($r['bdate'] ?? '');
            $dt = $raw !== '' ? date('d.m.Y', strtotime($raw)) : '';
            return [$raw, $dt];
        case 'dop1':
            $raw = (string)($r['dop1'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'tags':
            $raw = (string)($r['tag_ids'] ?? '');
            $display = (string)($r['tags_concat'] ?? '');
            return [$raw, $doHilight ? hilight($display, $search, $searchCond) : $display];
        case 'note':
            $raw = (string)$r['note'];
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
    }
    return ['', ''];
}, [
    'searchActive' => $searchActive,
    'searchCols' => $searchCols,
    'trExtraAttrs' => function($r) {
        $classes = [];
        if (!empty($r['hide_flag']) && $r['hide_flag'] == '1') {
            $classes[] = 'row-hidden';
        } elseif (!empty($r['problem_flag']) && $r['problem_flag'] == '1') {
            $classes[] = 'row-problem';
        }
        if (!empty($r['juridical_flag']) && $r['juridical_flag'] == '1') {
            $classes[] = 'row-juridical';
        }
        return $classes ? 'class="' . implode(' ', $classes) . '"' : '';
    },
    'tdExtraAttrs' => function($cn, $vc, $r, $i) {
        return ' data-col-idx="' . (int)$i . '"';
    },
]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>

  </div>

<?php
// --- Build inline fields for custom InlineEdit ---
$inlineFields = [];
$valCases = '';
foreach ($tp->visibleColumns as $vc) {
    $cn = $vc['name'];
    if (!empty($vc['readonly']) || $cn === 'id' || $cn === 'name') continue;
    if ($cn === 'tags') {
        $inlineFields[$cn] = ['dbField' => 'tag_ids', 'type' => 'tags', 'label' => $vc['label']];
    } elseif (in_array($cn, ['supplier_flag', 'problem_flag', 'juridical_flag', 'hide_flag'], true)) {
        $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'checkbox', 'label' => $vc['label']];
    } elseif (in_array($cn, ['cli_categ_id', 'city_id', 'country_id', 'promo_id'], true)) {
        $isLookup = !empty($vc['param']);
        $inlineFields[$cn] = ['dbField' => $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
        if ($isLookup) {
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
    } else {
        $isLookup = !empty($vc['param']);
        $inlineFields[$cn] = ['dbField' => $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
        if ($isLookup) {
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
    }
}
$inlineFieldsJson = json_encode($inlineFields, JSON_UNESCAPED_UNICODE);

// All columns (including hidden) for SearchPanel/SortPanel
$allLabeledColumns = array_map(function($c) {
    return ['key' => $c['name'], 'label' => $c['label']];
}, $tp->columns);

// Default columns for ColumnsPanel
$defaultColumnsForPanel = array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
}, $tp->columnsConfig);

// JSON values for extra JS
$columnWidthsJson = json_encode($tp->columnWidths, JSON_NUMERIC_CHECK);
$columnDefaultWidthsJson = json_encode([
    'id' => 60, 'name' => 200, 'last_name' => 120, 'first_name' => 150,
    'title' => 120, 'cli_categ_id' => 120,
    'supplier_flag' => 60, 'problem_flag' => 60,
    'juridical_flag' => 60, 'hide_flag' => 60,
    'phone' => 120, 'cphone' => 120, 'email' => 160, 'site' => 160,
    'city_id' => 120, 'country_id' => 120, 'postindex' => 80,
    'address_jur' => 200, 'address' => 200,
    'pasport' => 140, 'pasp_date' => 100, 'pasp_vydan' => 200, 'birthday' => 100,
    'promo_id' => 120,
    'inn' => 120, 'kpp' => 100, 'ogrn' => 120, 'jur_name' => 200,
    'director' => 150, 'glavbuh' => 150,
    'bank' => 200, 'bik' => 80, 'schet' => 140, 'kschet' => 140,
    'okonh' => 100, 'okpo' => 100,
    'disc_goods' => 60, 'sum_nach' => 80, 'sum_plat' => 80, 'sum_balans' => 80,
    'bdate' => 100, 'dop1' => 200, 'tags' => 200, 'note' => 500,
], JSON_UNESCAPED_UNICODE);
$cliCategLookupJson = json_encode($cliCategLookup, JSON_UNESCAPED_UNICODE);
$cityLookupJson = json_encode($cityLookup, JSON_UNESCAPED_UNICODE);
$countryLookupJson = json_encode($countryLookup, JSON_UNESCAPED_UNICODE);
$promoLookupJson = json_encode($promoLookup, JSON_UNESCAPED_UNICODE);

render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js', 'assets/column-filter.js', 'assets/embedded-subtable.js']]);
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

    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

    SelectionToolbar.initTableSelection('client.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

    function closeAllPanels() {
      document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }

    const SORT_COLS = <?= json_encode($allLabeledColumns, JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode($allLabeledColumns, JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.init({
      pageUrl: 'client.php',
      getInvertUrl: function () {
        var other = new URLSearchParams(location.search);
        other.delete('ids');
        return 'client.php?action=invertSelection&' + other.toString();
      },
      getExportUrl: function () {
        var sp = new URLSearchParams(location.search);
        ['action', 'page'].forEach(function (k) { sp.delete(k); });
        return 'client_export.php?format=csv&selected=1&' + sp.toString();
      },
      getPrintUrl: function () {
        var sp = new URLSearchParams(location.search);
        ['action', 'page'].forEach(function (k) { sp.delete(k); });
        return 'client_print.php?selected=1&' + sp.toString();
      }
    });

    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    SearchPanel.init({
      form: searchForm,
      condBtn: searchCondBtn,
      toggleBtn: searchToggleBtn,
      columns: SEARCH_COLS,
      pageUrl: 'client.php',
      popupCheckboxes: true,
      emptyClass: 'search-cond-placeholder',
      labels: { cols: 'Столбцы', cond: 'Условие', emptyCols: 'Выберите столбцы…' },
      closeAllPanels: closeAllPanels,
      preserveParams: ['sort'],
      onApply: function (state) {
        var p = new URLSearchParams(location.search);
        if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(','));
        else p.delete('cols');
        p.set('cond', state.cond);
        p.set('sf', '1');
        var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim();
        if (q !== '') p.set('q', q); else p.delete('q');
        p.delete('page');
        location.href = 'client.php?' + p.toString();
      },
      onToggle: function (state) {
        var p = new URLSearchParams(location.search);
        var q = searchForm.querySelector('input[name="q"]').value.trim();
        if (q !== '') {
          p.set('q', q);
          if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(','));
          p.set('cond', state.cond);
          p.set('sf', '1');
        } else {
          p.delete('q');
          p.delete('cols');
          p.delete('cond');
          p.delete('sf');
        }
        p.delete('page');
        location.href = 'client.php?' + p.toString();
      },
      onSubmit: function () { searchToggleBtn.click(); }
    });

    SortPanel.init({
      btn: sortBtn,
      columns: SORT_COLS,
      pageUrl: 'client.php',
      mode: 'modal',
      currentSort: currentSortLevels,
      directions: [
        { key: 'asc',  label: 'По возрастанию' },
        { key: 'desc', label: 'По убыванию' },
      ],
    });

    ColumnsPanel.init({
      btn: columnsBtn,
      saveUrl: 'client_columns_save.php',
      tbl: 'client',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= json_encode($defaultColumnsForPanel, JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) {
        return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
      }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
    });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= $columnDefaultWidthsJson ?>;

    ExportModal.init();
  })();
  </script>
  <?php render_form_modal_script([
    'form_prefix'   => 'client_form',
    'base_url'      => 'client.php',
    'autoInitTables' => ['rc', 'cd'],
    'extra_open'    => 'initFormLookups(); initTagPicker(); window.initFormTabs(); (function(){var j=document.getElementById("jur-name"),l=document.getElementById("last-name"),f=document.getElementById("first-name"),d=document.getElementById("name-display"),g=document.getElementById("juridical-flag");function u(){var jv=j?j.value:"",lv=l?l.value:"",fv=f?f.value:"";if(d)d.value=jv||(lv+" "+fv).trim();if(g)g.checked=!!jv;}if(j)j.addEventListener("input",u);if(l)l.addEventListener("input",u);if(f)f.addEventListener("input",u);u();})();',
    'extra_restore' => 'initFormLookups();',
  ]); ?>
  <script>
    window.closeAllPanels = function () {
      document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    };

    window.__columnWidths = <?= $columnWidthsJson ?>;
    window.__columnDefaultWidths = <?= $columnDefaultWidthsJson ?>;

    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);

    (function () {
      const toolbar = document.querySelector('.toolbar');
      const tbody   = document.querySelector('table tbody');
      const rowOpenBtn   = document.getElementById('rowOpenBtn');
      const rowCopyBtn   = document.getElementById('rowCopyBtn');
      const rowDeleteBtn = document.getElementById('rowDeleteBtn');
      const contactsBtn = document.getElementById('rowContactsBtn');
      const invoiceBtn = document.getElementById('rowInvoiceBtn');
      const platBtn = document.getElementById('rowPlatBtn');

      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          const af = window.__accessFlags || {};
          if (rowOpenBtn)   rowOpenBtn.disabled   = !enabled || !!af.change_flag;
          if (rowCopyBtn)   rowCopyBtn.disabled   = !enabled || !!af.insert_flag;
          if (rowDeleteBtn) rowDeleteBtn.disabled = !enabled || !!af.delete_flag;
          if (contactsBtn)  contactsBtn.disabled  = !enabled;
          if (invoiceBtn)   invoiceBtn.disabled   = !enabled;
          if (platBtn)      platBtn.disabled      = !enabled;
        },
        currentPage: currentPage,
        totalPages: currentPages,
        navigate: navigate
      });
      window.rowSel = rowSel;

      function selectRow(rowId) { rowSel.selectById(rowId, true); }

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = 'client.php?' + p.toString();
      }

      document.querySelectorAll('tbody tr').forEach(function (tr) {
        const id = parseInt(tr.dataset.rowId, 10);
        tr.addEventListener('click', function (e) {
          if (e.target.closest('input.row-check')) return;
          selectRow(id);
        });
        tr.addEventListener('dblclick', function () {
          if (window.__accessFlags && window.__accessFlags.change_flag) return;
          window.__openFormModal('client_form.php?mode=edit&id=' + id);
        });
      });

      rowOpenBtn.addEventListener('click', function () {
        const id = rowSel.getSelectedId();
        if (id !== 0) window.__openFormModal('client_form.php?mode=edit&id=' + id);
      });
      rowCopyBtn.addEventListener('click', function () {
        const id = rowSel.getSelectedId();
        if (id !== 0) window.__openFormModal('client_form.php?mode=copy&id=' + id);
      });
      rowDeleteBtn.addEventListener('click', function () {
        const id = rowSel.getSelectedId();
        if (id !== 0) window.__openFormModal('client_form.php?mode=delete&id=' + id);
      });

      if (contactsBtn) {
        contactsBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('contact.php?client_id=' + id, '_blank'); });
      }
      if (invoiceBtn) {
        invoiceBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('invoice.php?client_id=' + id, '_blank'); });
      }
      if (platBtn) {
        platBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('plat.php?client_id=' + id, '_blank'); });
      }

      const tableWrapEl = document.querySelector('.table-wrap');

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

      bindTableKeyboardShortcuts({
        formPrefix: 'client_form',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: tableWrapEl,
        onOpenForm: window.__openFormModal,
        accessFlags: window.__accessFlags
      });
    })();

    if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
    InlineEdit.init({
        tbody: document.querySelector('table tbody'),
        saveUrl: 'client_field_save.php',
        fields: <?= $inlineFieldsJson ?>,
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onSaveSuccess: function (data, field) {
            if (data.name != null) {
                var tr = (document.querySelector('table tbody tr.selected') || document.querySelector('table tbody tr[data-row-id]'));
                if (tr) {
                    var nameTd = tr.querySelector('td.col-name');
                    if (nameTd) {
                        var span = nameTd.querySelector('span');
                        if (span) span.textContent = data.name;
                        nameTd.dataset.value = data.name;
                    }
                }
            }
        },
        getLookupData: function (field) {
            switch (field) {
                case 'cli_categ_id': return window.__cliCategLookup || [];
                case 'city_id': return window.__cityLookup || [];
                case 'country_id': return window.__countryLookup || [];
                case 'promo_id': return window.__promoLookup || [];
                case 'tags': return window.__tagLookup || [];
            }
            return [];
        }
    });
    }

    var __cliCategLookup = <?= $cliCategLookupJson ?>;
    var __cityLookup = <?= $cityLookupJson ?>;
    var __countryLookup = <?= $countryLookupJson ?>;
    var __promoLookup = <?= $promoLookupJson ?>;
    var __tagLookup = <?= json_encode($tagLookup, JSON_UNESCAPED_UNICODE) ?>;

    window.initFormTabs = function () {
      var container = document.querySelector('.tab-container');
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
    };

    window.updateNameDisplay = function () {
      var jur = (document.getElementById('jur-name') || {}).value || '';
      var last = (document.getElementById('last-name') || {}).value || '';
      var first = (document.getElementById('first-name') || {}).value || '';
      var display = document.getElementById('name-display');
      if (display) display.value = jur || (last + ' ' + first).trim();
      var flag = document.getElementById('juridical-flag');
      if (flag) flag.checked = jur !== '';
    };

    window.bindFormLookups = function () {
      document.getElementById('jur-name')?.addEventListener('input', window.updateNameDisplay);
      document.getElementById('last-name')?.addEventListener('input', window.updateNameDisplay);
      document.getElementById('first-name')?.addEventListener('input', window.updateNameDisplay);
      window.updateNameDisplay();
    };

    function initFormLookups() {
      var modalBody = document.getElementById('formModalBody');
      if (!modalBody) return;
      var formEl = modalBody.querySelector('form');
      var isDeleteMode = formEl && formEl.classList.contains('form--delete');
      modalBody.querySelectorAll('[data-lookup]').forEach(function (root) {
        if (root.dataset.lookupInited) return;
        var rawJson = root.getAttribute('data-countries');
        if (!rawJson) return;
        var data;
        try { data = JSON.parse(rawJson); } catch (e) { return; }
        if (!Array.isArray(data)) return;
        if (isDeleteMode) return;
        var nameInputId = root.getAttribute('data-name-input');
        var nameInput = nameInputId ? modalBody.querySelector('#' + nameInputId) : null;
        var handler = nameInput ? function(id, name) { if (nameInput) nameInput.value = name; } : null;
        var countryInputId = root.getAttribute('data-country-input');
        if (countryInputId) {
          var tabPane = root.closest('.tab-pane');
          var countryRoot = tabPane
            ? tabPane.querySelector('[data-lookup="country"]')
            : modalBody.querySelector('[data-lookup="country"]');
          if (countryRoot) {
            var countryData;
            try { countryData = JSON.parse(countryRoot.getAttribute('data-countries') || '[]'); } catch(e) {}
            if (Array.isArray(countryData)) {
              var prevHandler = handler;
              handler = function(id, name) {
                if (prevHandler) prevHandler(id, name);
                var city = data.find(function(c) { return String(c.id) === String(id); });
                if (city && city.country_id) {
                  var country = countryData.find(function(c) { return String(c.id) === String(city.country_id); });
                  if (!country) country = countryData.find(function(c) { return c.id == city.country_id; });
                  if (country && countryRoot.__lookupApi) {
                    countryRoot.__lookupApi.choose(country.id, country.country || country.name || '');
                  }
                }
              };
            }
          }
        }
        try { bindLookup({ root: root, data: data, readonly: false, onSelect: handler }); root.dataset.lookupInited = '1'; root.setAttribute('data-lookup-bound', '1'); } catch (e) { console.warn('[form] bindLookup error', e); }
      });
    }

    function initTagPicker() {
      var container = document.getElementById('tag-picker-container');
      if (!container || container.dataset.inited) return;
      container.dataset.inited = '1';
      var hidden = document.getElementById('tag-ids-hidden');
      if (!hidden) return;
      var allTags = [];
      try { allTags = JSON.parse(container.getAttribute('data-items') || '[]'); } catch(e) {}
      var selected = new Set((hidden.value || '').split(',').filter(Boolean));
      var pop = document.createElement('div');
      pop.className = 'search-cond-pop';
      pop.style.zIndex = '2100';
      document.body.appendChild(pop);
      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
          return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
      }
      function renderChips() {
        container.innerHTML = '';
        if (selected.size === 0) {
          var e = document.createElement('span');
          e.className = 'search-cond-empty';
          e.textContent = 'Не выбрано';
          container.appendChild(e);
          return;
        }
        container.innerHTML = '';
        selected.forEach(function(id) {
          var tag = allTags.find(function(t) { return String(t.id) === id; });
          var name = tag ? tag.name : id;
          var chip = document.createElement('span');
          chip.className = 'search-cond-chip';
          chip.innerHTML = '<span>' + escapeHtml(name) + '</span><button type="button" class="search-cond-chip-remove" data-chip-id="' + id + '" title="Убрать">✕</button>';
          chip.querySelector('.search-cond-chip-remove').addEventListener('click', function(e) {
            e.stopPropagation();
            selected.delete(id);
            renderChips();
          });
          container.appendChild(chip);
        });
      }
      function openPop() {
        pop.innerHTML = '';
        allTags.forEach(function(tag) {
          var item = document.createElement('label');
          item.className = 'search-cond-pop-item';
          var checked = selected.has(String(tag.id));
          item.innerHTML = '<input type="checkbox"' + (checked ? ' checked' : '') + ' data-tag-id="' + tag.id + '" /><span>' + escapeHtml(tag.name) + '</span>';
          item.addEventListener('click', function(ev) {
            if (ev.target.tagName !== 'INPUT') {
              var cb = item.querySelector('input');
              cb.checked = !cb.checked;
              cb.dispatchEvent(new Event('change'));
              ev.preventDefault();
              ev.stopPropagation();
            }
          });
          item.querySelector('input').addEventListener('change', function(ev) {
            var id = String(tag.id);
            if (ev.target.checked) { selected.add(id); } else { selected.delete(id); }
            hidden.value = Array.from(selected).join(',');
            renderChips();
            ev.stopPropagation();
          });
          pop.appendChild(item);
        });
        pop.classList.add('open');
        var r = container.getBoundingClientRect();
        var left = r.left;
        var pw = pop.offsetWidth;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
        pop.style.left = left + 'px';
        var top = r.bottom + 4;
        pop.style.top = top + 'px';
      }
      function closePop() { pop.classList.remove('open'); }
      container.addEventListener('click', function(e) {
        if (e.target.classList.contains('search-cond-chip-remove')) return;
        if (pop.classList.contains('open')) { closePop(); return; }
        openPop();
      });
      document.addEventListener('click', function(e) {
        if (!container.contains(e.target) && !pop.contains(e.target)) closePop();
      });
      renderChips();
    }

    ColumnResize.init({ saveUrl: 'client_column_width_save.php', tbl: 'client' });

    ColumnFilter.init({"thSelector":".col-cli_categ_id","pageUrl":"client.php"});
    ColumnFilter.init({"thSelector":".col-city_id","pageUrl":"client.php"});
    ColumnFilter.init({"thSelector":".col-country_id","pageUrl":"client.php"});
    ColumnFilter.init({"thSelector":".col-tags","pageUrl":"client.php","param":"tag_id"});
  </script>
<?php render_page_footer();
