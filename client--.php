<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/config/client_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/marks-actions.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

ensure_marks_table($conn);
ensure_client_tag_table($conn);

$tp = new TablePage($conn, $clientPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'client');

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

handle_marks_actions($conn, $tp, 'client', function($action) use ($conn, $tp) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
        exit;
    }
    return null;
});

$tp->buildFilters();
$filters = $tp->filters;
$clearQs = function($drop) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
    $drop = is_array($drop) ? $drop : [$drop];
    $qs = [];
    if (!in_array('q', $drop, true) && $searchActive && $search !== '') $qs['q'] = $search;
    if (!in_array('cols', $drop, true) && $searchActive && count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
    if (!in_array('cond', $drop, true) && $searchActive) $qs['cond'] = $searchCond;
    if (!in_array('sf', $drop, true) && $searchActive) $qs['sf'] = '1';
    if (!in_array('sort', $drop, true) && $sortQs !== '') $qs['sort'] = $sortQs;
    if (!in_array('cli_categ_id', $drop, true) && !empty($_GET['cli_categ_id'])) $qs['cli_categ_id'] = $_GET['cli_categ_id'];
    if (!in_array('city_id', $drop, true) && !empty($_GET['city_id'])) $qs['city_id'] = $_GET['city_id'];
    if (!in_array('country_id', $drop, true) && !empty($_GET['country_id'])) $qs['country_id'] = $_GET['country_id'];
    if (!in_array('tag_id', $drop, true) && !empty($_GET['tag_id'])) $qs['tag_id'] = $_GET['tag_id'];
    return 'client.php' . ($qs ? '?' . http_build_query($qs) : '');
};

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

$cliCategFilter = (string)($_GET['cli_categ_id'] ?? '');
$cliCategFilterIds = []; $cliCategFilterNames = [];
if ($cliCategFilter !== '') {
    $cliCategFilterIds = array_values(array_filter(array_map('intval', explode(',', $cliCategFilter)), fn($v) => $v > 0));
}
if (count($cliCategFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($cliCategFilterIds), '?'));
    $cliCategFilterNames = loadFilterNames($conn, 'cli_categ', 'cli_categ_id', 'categ', $cliCategFilterIds);
    $tp->appendWhere("c.cli_categ_id IN ($ph)", $cliCategFilterIds, str_repeat('i', count($cliCategFilterIds)));
}

$cityFilter = (string)($_GET['city_id'] ?? '');
$cityFilterIds = []; $cityFilterNames = [];
if ($cityFilter !== '') {
    $cityFilterIds = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
}
if (count($cityFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($cityFilterIds), '?'));
    $cityFilterNames = loadFilterNames($conn, 'city', 'city_id', 'city', $cityFilterIds);
    $tp->appendWhere("c.city_id IN ($ph)", $cityFilterIds, str_repeat('i', count($cityFilterIds)));
}

$countryFilter = (string)($_GET['country_id'] ?? '');
$countryFilterIds = []; $countryFilterNames = [];
if ($countryFilter !== '') {
    $countryFilterIds = array_values(array_filter(array_map('intval', explode(',', $countryFilter)), fn($v) => $v > 0));
}
if (count($countryFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($countryFilterIds), '?'));
    $countryFilterNames = loadFilterNames($conn, 'country', 'country_id', 'country', $countryFilterIds);
    $tp->appendWhere("c.country_id IN ($ph)", $countryFilterIds, str_repeat('i', count($countryFilterIds)));
}

if (count($cliCategFilterNames) > 0) {
    $filters[] = ['kind' => 'cli_categ', 'text' => 'Р С™Р В°РЎвЂљР ВµР С–Р С•РЎР‚Р С‘РЎРЏ = ' . implode(', ', $cliCategFilterNames), 'clear' => 'cli_categ_id'];
}
if (count($cityFilterNames) > 0) {
    $filters[] = ['kind' => 'city', 'text' => 'Р вЂњР С•РЎР‚Р С•Р Т‘ = ' . implode(', ', $cityFilterNames), 'clear' => 'city_id'];
}
if (count($countryFilterNames) > 0) {
    $filters[] = ['kind' => 'country', 'text' => 'Р РЋРЎвЂљРЎР‚Р В°Р Р…Р В° = ' . implode(', ', $countryFilterNames), 'clear' => 'country_id'];
}

$tagFilter = (string)($_GET['tag_id'] ?? '');
$tagFilterIds = []; $tagFilterNames = [];
if ($tagFilter !== '') {
    $tagFilterIds = array_values(array_filter(array_map('intval', explode(',', $tagFilter)), fn($v) => $v > 0));
}
if (count($tagFilterIds) > 0) {
    $tagFilterNames = loadFilterNames($conn, 'tag', 'tag_id', 'tag', $tagFilterIds);
    $ph = implode(',', array_fill(0, count($tagFilterIds), '?'));
    $tp->appendWhere("c.client_id IN (SELECT client_id FROM client_tag WHERE tag_id IN ($ph))", $tagFilterIds, str_repeat('i', count($tagFilterIds)));
}
if (count($tagFilterNames) > 0) {
    $filters[] = ['kind' => 'tag', 'text' => 'Р вЂ™Р С‘Р Т‘ Р Т‘Р ВµРЎРЏРЎвЂљР ВµР В»РЎРЉР Р…Р С•РЎРѓРЎвЂљР С‘ = ' . implode(', ', $tagFilterNames), 'clear' => 'tag_id'];
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['client_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$exportQs = http_build_query(array_filter([
    'q'    => $searchActive && $search !== '' ? $search : null,
    'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
    'cond' => $searchActive ? $searchCond : null,
    'sf'   => $searchActive ? '1' : null,
    'sort' => $sortQs !== '' ? $sortQs : null,
    'cli_categ_id' => $cliCategFilter !== '' ? $cliCategFilter : null,
    'city_id' => $cityFilter !== '' ? $cityFilter : null,
    'country_id' => $countryFilter !== '' ? $countryFilter : null,
    'tag_id' => $tagFilter !== '' ? $tagFilter : null,
], function ($v) { return $v !== null && $v !== ''; }));

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => 'Р С™Р С•Р Р…РЎвЂљРЎР‚Р В°Р С–Р ВµР Р…РЎвЂљРЎвЂ№.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => 'Р С™Р С•Р Р…РЎвЂљРЎР‚Р В°Р С–Р ВµР Р…РЎвЂљРЎвЂ№.xls', 'format' => 'XLS (Excel)'],
    ['fmt' => 'pdf', 'filename' => 'Р С™Р С•Р Р…РЎвЂљРЎР‚Р В°Р С–Р ВµР Р…РЎвЂљРЎвЂ№.csv', 'format' => 'PDF'],
] as $item) {
    $fullUrl = 'client_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Р В­Р С”РЎРѓР С—Р С•РЎР‚РЎвЂљ Р Р† CSV' : ($item['format'] === 'PDF' ? 'Р В­Р С”РЎРѓР С—Р С•РЎР‚РЎвЂљ Р Р† Excel' : 'Р В­Р С”РЎРѓР С—Р С•РЎР‚РЎвЂљ Р Р† Excel')) . '</a>';
}

$printDropdownHtml = render_print_dropdown_items('client', $exportQs, (int)$page, $marksCount > 0);

// Lookup data for inline editor
$cliCategLookup = [];
$crs = $conn->query("SELECT cli_categ_id, categ FROM cli_categ ORDER BY categ");
if ($crs) while ($cr = $crs->fetch_assoc()) $cliCategLookup[] = ['id' => (int)$cr['cli_categ_id'], 'name' => (string)$cr['categ']];
$cityLookup = [];
$crs = $conn->query("SELECT city_id, city FROM city ORDER BY city");
if ($crs) while ($cr = $crs->fetch_assoc()) $cityLookup[] = ['id' => (int)$cr['city_id'], 'name' => (string)$cr['city']];
$countryLookup = [];
$crs = $conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($crs) while ($cr = $crs->fetch_assoc()) $countryLookup[] = ['id' => (int)$cr['country_id'], 'name' => (string)$cr['country']];
$promoLookup = [];
$crs = $conn->query("SELECT promo_id, promo FROM promo ORDER BY promo");
if ($crs) while ($cr = $crs->fetch_assoc()) $promoLookup[] = ['id' => (int)$cr['promo_id'], 'name' => (string)$cr['promo']];

render_head_start('Р С™Р С•Р Р…РЎвЂљРЎР‚Р В°Р С–Р ВµР Р…РЎвЂљРЎвЂ№');
?>
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
    .data-table tbody tr.row-juridical td.col-name { color: #e6b800; }
    .data-table tbody td.col-sum_nach,
    .data-table tbody td.col-sum_plat,
    .data-table tbody td.col-sum_balans,
    .data-table tbody td.col-disc_goods { text-align: right; }
  </style>
<?php render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'client.php'; include 'menu.php'; ?>
    <h1 class="page-title"><img src="img/customer.png" alt="" /> Р С™Р С•Р Р…РЎвЂљРЎР‚Р В°Р С–Р ВµР Р…РЎвЂљРЎвЂ№</h1>

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
      $paginationHtml = render_pagination($page, $pages, $baseQs, true);
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
    render_toolbar_left('client_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'client.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs); ?>

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
            'bdate' => '100px',
            'dop1' => '200px', 'tags' => '200px', 'note' => '500px',
        ]); ?>
        <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
]); ?>

($visibleColumns, $rows, $marks, $search, 'client_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
            $search = $GLOBALS['search'] ?? '';
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
                    return [$v, $v === '1'
                        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        : ''];
                case 'problem_flag':
                    $v = (string)($r['problem_flag'] ?? '0');
                    return [$v, $v === '1'
                        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#e74c3c" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        : ''];
                case 'juridical_flag':
                    $v = (string)($r['juridical_flag'] ?? '0');
                    return [$v, $v === '1'
                        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        : ''];
                case 'hide_flag':
                    $v = (string)($r['hide_flag'] ?? '0');
                    return [$v, $v === '1'
                        ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#e67e22" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        : ''];
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
                    $raw = (string)($r['tags_concat'] ?? '');
                    return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
                case 'note':
                    $raw = (string)$r['note'];
                    return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
            }
            return ['', ''];
        }, ['checkboxCallback' => function($rid) use ($marks) {
            return '<input type="checkbox" class="row-check" data-id="' . $rid . '"' . (isset($marks[$rid]) ? ' checked' : '') . ' />';
        }, 'trExtraAttrs' => function($r) {
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
        }]); ?>
      </table>
    </div>

    <?= $paginationHtml ?>
    <?php render_form_modal(); ?>
    <?php render_export_modal(); ?>
  </div>

<?php
$appSettings = load_app_settings($conn);
$RegcodeFlag = (int)($appSettings['regcode_flag'] ?? 0);
$regcodList = [];
$productList = [];
$prs = $conn->query("SELECT product_id, product_name FROM product WHERE hide_flag = 0 ORDER BY product_name");
if ($prs) while ($pr = $prs->fetch_assoc()) $productList[] = ['id' => (int)$pr['product_id'], 'name' => (string)$pr['product_name']];
$cityList = [];
$cs = $conn->query("SELECT city_id AS id, name FROM city ORDER BY name");
if ($cs) while ($cr = $cs->fetch_assoc()) $cityList[] = $cr;
?>

<script>
var __regcodData = <?= json_encode($regcodList ?? [], JSON_UNESCAPED_UNICODE) ?>;
var __regcodClientId = <?= (int)($_GET['id'] ?? 0) ?>;
var __regcodProductList = <?= json_encode($productList ?? [], JSON_UNESCAPED_UNICODE) ?>;
var __regcodCityList = <?= json_encode($cityList ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php render_script_includes(); ?>
<script src="assets/client-form.js"></script>

<?php render_form_modal_script([
    'form_prefix' => 'client_form',
    'base_url' => 'client.php',
    'lookup_tables' => [],
    'extra_open' => 'initFormLookups(); window.initFormTabs(); (function(){var j=document.getElementById("jur-name"),l=document.getElementById("last-name"),f=document.getElementById("first-name"),d=document.getElementById("name-display"),g=document.getElementById("juridical-flag");function u(){var jv=j?j.value:"",lv=l?l.value:"",fv=f?f.value:"";if(d)d.value=jv||(lv+" "+fv).trim();if(g)g.checked=!!jv;}if(j)j.addEventListener("input",u);if(l)l.addEventListener("input",u);if(f)f.addEventListener("input",u);u();})(); initTagPicker(); initRegcodTable();',
    'extra_restore' => 'initFormLookups();',
]); ?>

<?php render_page_footer(); ?>