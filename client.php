<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/client_columns.php';
require_once __DIR__ . '/config/client_page.php';
require_once __DIR__ . '/lib/marks-actions.php';

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

// --- Extra filters ---
$cliCategFilter = (string)($_GET['cli_categ_id'] ?? '');
$cliCategFilterIds = [];
$cliCategFilterNames = [];
if ($cliCategFilter !== '') {
    $cliCategFilterIds = array_values(array_filter(array_map('intval', explode(',', $cliCategFilter)), fn($v) => $v > 0));
}
if (count($cliCategFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($cliCategFilterIds), '?'));
    $cliCategFilterNames = loadFilterNames($conn, 'cli_categ', 'cli_categ_id', 'categ', $cliCategFilterIds);
    $tp->appendWhere("c.cli_categ_id IN ($ph)", $cliCategFilterIds, str_repeat('i', count($cliCategFilterIds)));
}

$cityFilter = (string)($_GET['city_id'] ?? '');
$cityFilterIds = [];
$cityFilterNames = [];
if ($cityFilter !== '') {
    $cityFilterIds = array_values(array_filter(array_map('intval', explode(',', $cityFilter)), fn($v) => $v > 0));
}
if (count($cityFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($cityFilterIds), '?'));
    $cityFilterNames = loadFilterNames($conn, 'city', 'city_id', 'city', $cityFilterIds);
    $tp->appendWhere("c.city_id IN ($ph)", $cityFilterIds, str_repeat('i', count($cityFilterIds)));
}

$countryFilter = (string)($_GET['country_id'] ?? '');
$countryFilterIds = [];
$countryFilterNames = [];
if ($countryFilter !== '') {
    $countryFilterIds = array_values(array_filter(array_map('intval', explode(',', $countryFilter)), fn($v) => $v > 0));
}
if (count($countryFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($countryFilterIds), '?'));
    $countryFilterNames = loadFilterNames($conn, 'country', 'country_id', 'country', $countryFilterIds);
    $tp->appendWhere("c.country_id IN ($ph)", $countryFilterIds, str_repeat('i', count($countryFilterIds)));
}

$tagFilter = (string)($_GET['tag_id'] ?? '');
$tagFilterIds = [];
$tagFilterNames = [];
if ($tagFilter !== '') {
    $tagFilterIds = array_values(array_filter(array_map('intval', explode(',', $tagFilter)), fn($v) => $v > 0));
}
if (count($tagFilterIds) > 0) {
    $tagFilterNames = loadFilterNames($conn, 'tag', 'tag_id', 'tag', $tagFilterIds);
    $ph = implode(',', array_fill(0, count($tagFilterIds), '?'));
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

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);

// --- Build filters ---
$tp->buildFilters();
$filters = $tp->filters;

if (count($cliCategFilterNames) > 0) {
    $filters[] = ['kind' => 'cli_categ', 'text' => 'Категория = ' . implode(', ', $cliCategFilterNames), 'clear' => 'cli_categ_id'];
}
if (count($cityFilterNames) > 0) {
    $filters[] = ['kind' => 'city', 'text' => 'Город = ' . implode(', ', $cityFilterNames), 'clear' => 'city_id'];
}
if (count($countryFilterNames) > 0) {
    $filters[] = ['kind' => 'country', 'text' => 'Страна = ' . implode(', ', $countryFilterNames), 'clear' => 'country_id'];
}
if (count($tagFilterNames) > 0) {
    $filters[] = ['kind' => 'tag', 'text' => 'Вид деятельности = ' . implode(', ', $tagFilterNames), 'clear' => 'tag_id'];
}

// --- clearQs (preserves extra filter params) ---
$preservedExtraParams = ['cli_categ_id', 'city_id', 'country_id', 'tag_id'];
$clearQs = function($drop) use ($tp, $preservedExtraParams) {
    $drop = is_array($drop) ? $drop : [$drop];
    $qs = [];
    if (!in_array('q', $drop, true) && $tp->searchActive && $tp->search !== '') $qs['q'] = $tp->search;
    if (!in_array('cols', $drop, true) && $tp->searchActive && count($tp->searchCols) > 0) $qs['cols'] = implode(',', $tp->searchCols);
    if (!in_array('cond', $drop, true) && $tp->searchActive) $qs['cond'] = $tp->searchCond;
    if (!in_array('sf', $drop, true) && $tp->searchActive) $qs['sf'] = '1';
    if (!in_array('sort', $drop, true) && $tp->sortQs !== '') $qs['sort'] = $tp->sortQs;
    foreach ($preservedExtraParams as $ep) {
        if (!in_array($ep, $drop, true) && !empty($_GET[$ep])) $qs[$ep] = $_GET[$ep];
    }
    return 'client.php' . ($qs ? '?' . http_build_query($qs) : '');
};

// --- Render ---
$tp->renderHead('Контрагенты');
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
<?php
$tp->renderHeadEnd();
$tp->renderPageStart();
$activeMenu = 'client.php'; include 'menu.php';
$tp->renderTitle('customer.png', 'Контрагенты');

// Export formats (with extra filter params)
$exportQs = http_build_query(array_filter([
    'q'    => $tp->searchActive && $tp->search !== '' ? $tp->search : null,
    'cols' => $tp->searchActive && count($tp->searchCols) > 0 ? implode(',', $tp->searchCols) : null,
    'cond' => $tp->searchActive ? $tp->searchCond : null,
    'sf'   => $tp->searchActive ? '1' : null,
    'sort' => $tp->sortQs !== '' ? $tp->sortQs : null,
    'cli_categ_id' => $cliCategFilter !== '' ? $cliCategFilter : null,
    'city_id' => $cityFilter !== '' ? $cityFilter : null,
    'country_id' => $countryFilter !== '' ? $countryFilter : null,
    'tag_id' => $tagFilter !== '' ? $tagFilter : null,
], function ($v) { return $v !== null && $v !== ''; }));

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

$tp->renderToolbar([
    'formPrefix' => 'client_form',
    'extraLeftHtml' => $extraBtnHtml,
    'exportFormats' => $exportFormats,
    'extraExportParams' => array_filter([
        'cli_categ_id' => $cliCategFilter !== '' ? $cliCategFilter : null,
        'city_id' => $cityFilter !== '' ? $cityFilter : null,
        'country_id' => $countryFilter !== '' ? $countryFilter : null,
        'tag_id' => $tagFilter !== '' ? $tagFilter : null,
    ]),
]);

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
<?php
$tp->renderTable(function($r, $cn, $vc) use ($tp) {
    $search = $tp->search;
    $searchCond = $tp->searchCond;
    $searchCols = $tp->searchCols;
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
            $raw = (string)($r['tags_concat'] ?? '');
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'note':
            $raw = (string)$r['note'];
            return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
    }
    return ['', ''];
}, [
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
    'defaultWidths' => [
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
    ],
]);
?>
  </table>
</div>
<?php
$tp->renderPagination();
$tp->renderPageEnd();

// --- Build inline fields for custom InlineEdit ---
$inlineFields = [];
$valCases = '';
foreach ($tp->visibleColumns as $vc) {
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
$inlineFieldsJson = json_encode($inlineFields, JSON_UNESCAPED_UNICODE);

// All columns (including hidden) for SearchPanel/SortPanel
$allLabeledColumns = array_map(function($c) {
    return ['key' => $c['name'], 'label' => $c['label']];
}, $tp->columns);

// Default columns for ColumnsPanel (preserves original visibility)
$defaultColumnsForPanel = array_map(function($c) {
    return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
}, $tp->columns);

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

$extraCode = <<<JS
    window.closeAllPanels = function () {
      document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    };

    (function () {
      var contactsBtn = document.getElementById('rowContactsBtn');
      var invoiceBtn = document.getElementById('rowInvoiceBtn');
      var platBtn = document.getElementById('rowPlatBtn');
      contactsBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('contact.php?client_id=' + id, '_blank'); });
      invoiceBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('invoice.php?client_id=' + id, '_blank'); });
      platBtn.addEventListener('click', function () { var id = window.rowSel.getSelectedId(); if (id !== 0) window.open('plat.php?client_id=' + id, '_blank'); });
    })();

    window.__columnWidths = {$columnWidthsJson};
    window.__columnDefaultWidths = {$columnDefaultWidthsJson};

    InlineEdit.init({
        tbody: document.querySelector('table tbody'),
        saveUrl: 'client_field_save.php',
        fields: {$inlineFieldsJson},
        validate: function (field, value) {
          switch (field) {
{$valCases}
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
            }
            return [];
        }
    });

    var __cliCategLookup = {$cliCategLookupJson};
    var __cityLookup = {$cityLookupJson};
    var __countryLookup = {$countryLookupJson};
    var __promoLookup = {$promoLookupJson};

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
JS;
$tp->renderScripts([
    'extraScripts' => ['assets/embedded-subtable.js'],
    'formPrefix' => 'client_form',
    'lookupData' => [
        'cli_categ_id' => $cliCategLookup,
        'city_id' => $cityLookup,
        'country_id' => $countryLookup,
        'promo_id' => $promoLookup,
    ],
    'colFilters' => [
        ['thSelector' => '.col-cli_categ_id', 'pageUrl' => 'client.php'],
        ['thSelector' => '.col-city_id', 'pageUrl' => 'client.php'],
        ['thSelector' => '.col-country_id', 'pageUrl' => 'client.php'],
        ['thSelector' => '.col-tags', 'pageUrl' => 'client.php'],
    ],
    'searchPanelConfig' => [
        'popupCheckboxes' => true,
        'emptyClass' => 'search-cond-placeholder',
        'labels' => ['cols' => 'Столбцы', 'cond' => 'Условие', 'emptyCols' => 'Выберите столбцы…'],
        'onApply' => 'function(state) { var p = new URLSearchParams(location.search); if (state.cols.size > 0) p.set("cols", Array.from(state.cols).join(",")); else p.delete("cols"); p.set("cond", state.cond); p.set("sf", "1"); var q = (document.getElementById("searchForm").querySelector("input[name=\'q\']") || { value: "" }).value.trim(); if (q !== "") p.set("q", q); else p.delete("q"); p.delete("page"); location.href = "client.php?" + p.toString(); }',
        'onToggle' => 'function(state) { var p = new URLSearchParams(location.search); var q = document.getElementById("searchForm").querySelector("input[name=\'q\']").value.trim(); if (q !== "") { p.set("q", q); if (state.cols.size > 0) p.set("cols", Array.from(state.cols).join(",")); p.set("cond", state.cond); p.set("sf", "1"); } else { p.delete("q"); p.delete("cols"); p.delete("cond"); p.delete("sf"); } p.delete("page"); location.href = "client.php?" + p.toString(); }',
        'onSubmit' => 'function() { document.getElementById("searchToggleBtn").click(); }',
    ],
    'currentSort' => $tp->sortLevels,
    'searchColumns' => $allLabeledColumns,
    'sortColumns' => $allLabeledColumns,
    'defaultColumns' => $defaultColumnsForPanel,
    'skipInlineEdit' => true,
    'extraRowSelectBtns' => ['rowContactsBtn', 'rowInvoiceBtn', 'rowPlatBtn'],
    'formModalConfig' => [
        'autoInitTables' => ['rc'],
        'extra_open' => 'initFormLookups(); initTagPicker(); window.initFormTabs(); (function(){var j=document.getElementById("jur-name"),l=document.getElementById("last-name"),f=document.getElementById("first-name"),d=document.getElementById("name-display"),g=document.getElementById("juridical-flag");function u(){var jv=j?j.value:"",lv=l?l.value:"",fv=f?f.value:"";if(d)d.value=jv||(lv+" "+fv).trim();if(g)g.checked=!!jv;}if(j)j.addEventListener("input",u);if(l)l.addEventListener("input",u);if(f)f.addEventListener("input",u);u();})();',
        'extra_restore' => 'initFormLookups();',
    ],
    'extraCode' => $extraCode,
]);
$tp->renderFooter();
