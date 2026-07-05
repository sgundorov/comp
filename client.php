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
    $filters[] = ['kind' => 'cli_categ', 'text' => 'Категория = ' . implode(', ', $cliCategFilterNames), 'clear' => 'cli_categ_id'];
}
if (count($cityFilterNames) > 0) {
    $filters[] = ['kind' => 'city', 'text' => 'Город = ' . implode(', ', $cityFilterNames), 'clear' => 'city_id'];
}
if (count($countryFilterNames) > 0) {
    $filters[] = ['kind' => 'country', 'text' => 'Страна = ' . implode(', ', $countryFilterNames), 'clear' => 'country_id'];
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
    $filters[] = ['kind' => 'tag', 'text' => 'Вид деятельности = ' . implode(', ', $tagFilterNames), 'clear' => 'tag_id'];
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
    ['fmt' => 'csv', 'filename' => 'Контрагенты.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => 'Контрагенты.xls', 'format' => 'XLS (Excel)'],
    ['fmt' => 'pdf', 'filename' => 'Контрагенты.csv', 'format' => 'PDF'],
] as $item) {
    $fullUrl = 'client_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : ($item['format'] === 'PDF' ? 'Экспорт в Excel' : 'Экспорт в Excel')) . '</a>';
}

$printDropdownHtml = render_print_dropdown_items('client', $exportQs, (int)$page, $marksCount > 0);

// Lookup data for inline editor
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

render_head_start('Контрагенты');
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
    <h1 class="page-title"><img src="img/customer.png" alt="" /> Контрагенты</h1>

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
        <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'client_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
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

<?php render_script_includes(['scripts' => ['assets/lookup.js', 'assets/column-filter.js', 'assets/export-modal.js', 'assets/embedded-subtable.js']]); ?>
<script>
(function () {
    const toolbar = document.querySelector('.toolbar');
    const tbody = document.querySelector('table tbody');
    if (!toolbar || !tbody) return;
    const selWrap   = document.getElementById('selectedActions');
    const selCount  = document.getElementById('selectedCount');
    const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
    const currentPages = parseInt(toolbar.dataset.pages || '1', 10);
    const currentTotal = parseInt(toolbar.dataset.total || '0', 10);
    const initialMarks = [];
    document.querySelectorAll('.row-check').forEach(function (cb) { if (cb.checked) initialMarks.push(parseInt(cb.dataset.id, 10)); });
    const selected = new Set(initialMarks);
    function updateSelectionUI() { var total = parseInt(toolbar.dataset.marksCount || '0', 10); var any = total > 0; selWrap.classList.toggle('visible', any); selCount.textContent = 'Выбрано: ' + total; }
    function updateRowActionButtons() { const id = rowSel.getSelectedId(); const en = id !== 0; document.getElementById('rowOpenBtn').disabled = !en; document.getElementById('rowCopyBtn').disabled = !en; document.getElementById('rowDeleteBtn').disabled = !en; var cb = document.getElementById('rowContactsBtn'); if (cb) cb.disabled = !en; var ib = document.getElementById('rowInvoiceBtn'); if (ib) ib.disabled = !en; var pb = document.getElementById('rowPlatBtn'); if (pb) pb.disabled = !en; }
    const rowSel = RowSelect.init({ tbody: tbody, rowClass: 'selected', onChange: updateRowActionButtons, currentPage: currentPage, totalPages: currentPages, navigate: navigate });
    function selectRow(rowId) { rowSel.selectById(rowId, true); }
    function navigate(params) { const url = new URL(window.location.href); params(url.searchParams); window.location.href = url.pathname + '?' + url.searchParams.toString(); }
    const SORT_COLS = <?= json_encode(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode(array_values(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS)), JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.init({
        pageUrl: 'client.php',
        getInvertUrl: function () { var o = new URLSearchParams(location.search); o.delete('ids'); return 'client.php?action=invertSelection&' + o.toString(); },
        getExportUrl: function () { return 'client_export.php?format=csv&all=1&' + new URLSearchParams(location.search).toString(); },
        getPrintUrl: function () { var p = new URLSearchParams(location.search); p.delete('page'); return 'client_print.php?all=1&' + p.toString(); }
    });

    function toggleMark(id, to) {
        fetch('client.php?action=toggleSelect', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: 'id=' + id + '&to=' + (to ? '1' : '0') })
            .then(function (r) { return r.json(); }).then(function (d) { if (d.ok) { toolbar.dataset.marksCount = d.count; updateSelectionUI(); } });
    }

    document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.addEventListener('change', function () { const id = parseInt(cb.dataset.id, 10); if (cb.checked) selected.add(id); else selected.delete(id); var cur = parseInt(toolbar.dataset.marksCount || '0', 10); toolbar.dataset.marksCount = String(cb.checked ? cur + 1 : Math.max(0, cur - 1)); updateSelectionUI(); toggleMark(id, cb.checked); });
        cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    function closeAllPanels() { document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); }); document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); }); document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); }); }

    const searchForm = document.getElementById('searchForm');
    const searchCondBtn = document.getElementById('searchCondBtn');
    const searchToggleBtn = document.getElementById('searchToggleBtn');

    SearchPanel.init({
        form: searchForm, condBtn: searchCondBtn, toggleBtn: searchToggleBtn, columns: SEARCH_COLS, pageUrl: 'client.php', popupCheckboxes: true, emptyClass: 'search-cond-placeholder', closeAllPanels: closeAllPanels,
        labels: { cols: 'Столбцы', cond: 'Условие', emptyCols: 'Выберите столбцы…' },
        onApply: function (state) {
            var p = new URLSearchParams(location.search); if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(',')); else p.delete('cols');
            p.set('cond', state.cond); p.set('sf', '1');
            var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim(); if (q !== '') p.set('q', q); else p.delete('q');
            p.delete('page'); location.href = 'client.php?' + p.toString();
        },
        onToggle: function (state) {
            var p = new URLSearchParams(location.search); var q = searchForm.querySelector('input[name="q"]').value.trim();
            if (q !== '') { p.set('q', q); if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(',')); p.set('cond', state.cond); p.set('sf', '1'); }
            else { p.delete('q'); p.delete('cols'); p.delete('cond'); p.delete('sf'); }
            p.delete('page'); location.href = 'client.php?' + p.toString();
        },
        onSubmit: function () { searchToggleBtn.click(); }
    });

    SortPanel.init({ btn: document.getElementById('sortBtn'), columns: SORT_COLS, pageUrl: 'client.php', mode: 'modal', currentSort: currentSortLevels, directions: [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }] });

    window.rowSel = rowSel;
    window.closeAllPanels = closeAllPanels;
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode([
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
        'bdate' => 100,
        'dop1' => 200, 'tags' => 200, 'note' => 500,
    ], JSON_UNESCAPED_UNICODE) ?>;



    document.querySelectorAll('tbody tr').forEach(function (tr) {
        const id = parseInt(tr.dataset.rowId, 10);
        tr.addEventListener('click', function (e) { if (e.target.closest('input.row-check')) return; selectRow(id); });
        tr.addEventListener('dblclick', function () { if (typeof window.__openFormModal === 'function') window.__openFormModal('client_form.php?mode=edit&id=' + id); });
    });

    document.getElementById('rowOpenBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('client_form.php?mode=edit&id=' + id); });
    document.getElementById('rowCopyBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('client_form.php?mode=copy&id=' + id); });
    document.getElementById('rowDeleteBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0 && typeof window.__openFormModal === 'function') window.__openFormModal('client_form.php?mode=delete&id=' + id); });

    var contactsBtn = document.createElement('button');
    contactsBtn.className = 'icon-btn';
    contactsBtn.id = 'rowContactsBtn';
    contactsBtn.title = 'Контакты';
    contactsBtn.disabled = true;
    contactsBtn.innerHTML = '<img src="img/contact.png" alt="" />';
    contactsBtn.addEventListener('click', function () { var id = rowSel.getSelectedId(); if (id !== 0) window.open('contact.php?client_id=' + id, '_blank'); });
    var printDropdown = document.querySelector('.toolbar-left .dropdown:nth-child(7)');
    if (printDropdown) printDropdown.after(contactsBtn); else document.querySelector('.toolbar-left').appendChild(contactsBtn);

    var invoiceBtn = document.createElement('button');
    invoiceBtn.className = 'icon-btn';
    invoiceBtn.id = 'rowInvoiceBtn';
    invoiceBtn.title = 'Счета';
    invoiceBtn.disabled = true;
    invoiceBtn.innerHTML = '<img src="img/invoice.png" alt="" />';
    invoiceBtn.addEventListener('click', function () { var id = rowSel.getSelectedId(); if (id !== 0) window.open('invoice.php?client_id=' + id, '_blank'); });
    contactsBtn.after(invoiceBtn);

    var platBtn = document.createElement('button');
    platBtn.className = 'icon-btn';
    platBtn.id = 'rowPlatBtn';
    platBtn.title = 'Оплата';
    platBtn.disabled = true;
    platBtn.innerHTML = '<img src="img/plat.png" alt="" />';
    platBtn.addEventListener('click', function () { var id = rowSel.getSelectedId(); if (id !== 0) window.open('plat.php?client_id=' + id, '_blank'); });
    invoiceBtn.after(platBtn);

    const tableWrapEl = document.querySelector('.table-wrap');

    (function applyInitialFocus() {
        const rows = rowSel.getRows(); if (rows.length === 0) return;
        const raw = (toolbar.dataset.focus || '').toString();
        if (raw === 'first') { rowSel.selectByIndex(0); return; }
        if (raw === 'last')  { rowSel.selectByIndex(rows.length - 1); return; }
        const id = parseInt(raw, 10);
        if (id > 0 && rowSel.selectById(id, false)) return;
        rowSel.selectByIndex(0);
    })();

    bindTableKeyboardShortcuts({ formPrefix: 'client_form', rowSel: rowSel, currentPage: currentPage, currentPages: currentPages, navigate: navigate, tableWrapEl: tableWrapEl });
    updateSelectionUI();
})();

ExportModal.init();
</script>
<?php render_form_modal_script([
    'form_prefix' => 'client_form',
    'base_url' => 'client.php',
    'lookup_tables' => [],
    'autoInitTables' => ['rc'],
    'extra_open' => 'initFormLookups(); initTagPicker(); window.initFormTabs(); (function(){var j=document.getElementById("jur-name"),l=document.getElementById("last-name"),f=document.getElementById("first-name"),d=document.getElementById("name-display"),g=document.getElementById("juridical-flag");function u(){var jv=j?j.value:"",lv=l?l.value:"",fv=f?f.value:"";if(d)d.value=jv||(lv+" "+fv).trim();if(g)g.checked=!!jv;}if(j)j.addEventListener("input",u);if(l)l.addEventListener("input",u);if(f)f.addEventListener("input",u);u();})();',
    'extra_restore' => 'document.querySelectorAll("#formModalBody [data-lookup],#formModalBody [data-inited]").forEach(function(el){ delete el.dataset.lookupInited; delete el.dataset.inited; }); initFormLookups(); initRcTable(); if(window.__rcTable){ window.__rcTable.refresh({ focusId: data && data.id ? data.id : 0 }); }',
]); ?>
<script>
window.rowSel = window.rowSel || null;
ColumnsPanel.init({ btn: document.getElementById('columnsBtn'), saveUrl: 'client_columns_save.php', tbl: 'client', closeAllPanels: closeAllPanels, initialColumns: <?= json_encode(array_map(function ($c) { return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])]; }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
  defaultColumns: <?= json_encode(array_map(function ($c) { return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?> });

ColumnResize.init({ saveUrl: 'client_column_width_save.php', tbl: 'client' });

ColumnFilter.init({ thSelector: '.col-cli_categ_id', pageUrl: 'client.php' });
ColumnFilter.init({ thSelector: '.col-city_id', pageUrl: 'client.php' });
ColumnFilter.init({ thSelector: '.col-country_id', pageUrl: 'client.php' });
ColumnFilter.init({ thSelector: '.col-tags', pageUrl: 'client.php' });

InlineEdit.init({
    tbody: document.querySelector('table tbody'),
    saveUrl: 'client_field_save.php',
    fields: <?php
      $inlineFields = [];
      $valCases = '';
      foreach ($visibleColumns as $vc) {
        $cn = $vc['name'];
        if (!empty($vc['readonly']) || $cn === 'id' || $cn === 'name' || $cn === 'tags') continue;
        if (in_array($cn, ['supplier_flag', 'problem_flag', 'juridical_flag', 'hide_flag'], true)) {
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
    ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
    validate: function (field, value) {
      switch (field) {
<?= $valCases ?>
      }
      return null;
    },
    onOpenForm: function(url) { if (window.__openFormModal) window.__openFormModal(url); },
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
        }
        return [];
    }
});

var __cliCategLookup = <?= json_encode($cliCategLookup, JSON_UNESCAPED_UNICODE) ?>;
var __cityLookup = <?= json_encode($cityLookup, JSON_UNESCAPED_UNICODE) ?>;
var __countryLookup = <?= json_encode($countryLookup, JSON_UNESCAPED_UNICODE) ?>;
var __promoLookup = <?= json_encode($promoLookup, JSON_UNESCAPED_UNICODE) ?>;

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
</script>
<?php render_page_footer();