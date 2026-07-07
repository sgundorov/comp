<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/city_columns.php';
require_once __DIR__ . '/config/city_page.php';

$TBL = 'city';

ensure_marks_table($conn);

$allCountries = [];
$res = @$conn->query("SELECT country_id, country FROM country ORDER BY country");
if ($res) while ($r = $res->fetch_assoc()) {
    $allCountries[] = ['id' => (int)$r['country_id'], 'name' => (string)$r['country']];
}

$tp = new TablePage($conn, $cityPageConfig);
$tp->loadColumnWidths($conn);
$tp->loadCountryNames($conn, 'country', 'country', 'country_id');

require_once __DIR__ . '/lib/marks-actions.php';
handle_marks_actions($conn, $tp, $TBL, function($action) use ($conn, $tp) {
    if ($action === 'columnFilterOptions') {
        $col = (string)($_GET['col'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if ($col === 'country') {
            $out = $tp->colFilterOptions($conn, 'country');
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([]);
        }
        exit;
    }
    return null;
});

$tp->getTotalCount($conn);
$tp->getRows($conn);

$tp->renderHead('Города'); ?>
  <style>
    .data-table tbody td.col-note { white-space: normal; word-break: break-word; }
    .cell-edit-panel .cell-edit-actions { position: relative; z-index: 2; }
    .data-table tbody tr:hover td.col-city { background: #2c3a4d; }
    .data-table tbody tr.selected td.col-city { background: #3a5a8a; }
    .data-table tbody tr.selected:hover td.col-city { background: #3a5a8a; }
    .data-table thead th[data-sort-col]:hover { background: #3d5468; }
    .col-filter-panel {
      display: none; position: fixed; z-index: 1000; min-width: 240px; max-width: 320px;
      padding: 8px; background: #2f3e4e; border: 2px solid #6b7785;
      border-radius: 2px; box-shadow: 0 8px 18px rgba(0,0,0,.35); text-align: left;
    }
    .col-filter-panel.open { display: block; }
    .col-filter-search { width: 100%; height: 28px; padding: 0 8px; margin: 0 0 6px; box-sizing: border-box; border: 1px solid var(--line); background: #fff; color: #2b2b2b; border-radius: 2px; font-size: 13px; outline: none; }
    .col-filter-list { margin-bottom: 8px; }
    .col-filter-row { display: flex; align-items: center; gap: 6px; padding: 4px; cursor: pointer; color: #fff; font-size: 13px; user-select: none; }
    .col-filter-row:hover { background: var(--btn-hover); }
    .col-filter-row input[type="checkbox"] { width: 14px; height: 14px; flex: 0 0 auto; }
    .col-filter-row .col-filter-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .col-filter-empty { padding: 6px 4px; color: var(--muted); font-size: 12px; font-style: italic; }
    .col-filter-more { padding: 4px; color: var(--muted); font-size: 12px; font-style: italic; border-top: 1px solid var(--line); }
    .col-filter-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 10px 8px; border-top: 1px solid var(--line); }
    .col-filter-actions button { display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 8px; background: var(--btn); color: #fff; border: 1px solid var(--line); border-radius: 2px; cursor: pointer; font-size: 14px; }
    .col-filter-actions button img { width: 28px; height: 28px; flex: 0 0 auto; display: block; }
    .col-filter-actions button:hover { background: var(--btn-hover); }
    .col-filter-actions .col-filter-apply { background: var(--accent); border-color: var(--accent); }
    .col-filter-actions .col-filter-apply:hover { background: #cf6d1a; border-color: #cf6d1a; }
  </style>
<?php
$tp->renderHeadEnd();
$tp->renderPageStart();

$activeMenu = 'city.php'; include 'menu.php';
$tp->renderTitle('city.png', 'Город');

$tp->renderToolbar(['formPrefix' => 'city_form']);
$tp->renderFilterBanner();
?>
  <div class="table-wrap">
    <table class="data-table">
<?php
$tp->renderTable(function($r, $cn, $vc) use ($tp) {
    $search = $tp->search;
    $searchCols = $tp->searchCols;
    $searchCond = $tp->searchCond;
    $doHilight = $search !== '' && in_array($cn, $searchCols, true);
    switch ($cn) {
        case 'id':      $raw = (string)(int)$r['city_id']; return [$raw, $raw];
        case 'city':    $raw = (string)$r['city']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        case 'country': $rawId = (string)(int)($r['country_id'] ?? 0); $name = (string)($r['country_name'] ?? $rawId); return [$rawId, $doHilight ? hilight($name, $search, $searchCond) : $name];
        case 'note':    $raw = (string)$r['note']; return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
    }
    return ['', ''];
}, [
    'defaultWidths' => ['id' => 46, 'city' => 200, 'country' => 160, 'note' => 500],
    'tdExtraAttrs' => function($cn, $vc, $r, $i) {
        return ' data-col-idx="' . (int)$i . '"';
    },
    'thAttrsCallback' => function($cn, $cm) use ($tp) {
        if ($cm && !empty($cm['filter']) && $cn === 'country') {
            return ' data-col="' . h($cn) . '" data-param="country_id" data-values="' . h(implode(',', $tp->countryIds)) . '"';
        }
        return '';
    },
    'thHtmlCallback' => function($cn, $cm) {
        if ($cm && !empty($cm['filter']) && $cn === 'country') {
            return '<button type="button" class="col-filter-btn" title="Фильтр по колонке"><img src="img/look.png" alt="" /></button>';
        }
        return '';
    },
]);
?>
    </table>
  </div>
<?php
$tp->renderPagination();
$tp->renderPageEnd();

$tp->renderScripts([
    'formPrefix' => 'city_form',
    'lookupData' => ['country' => $allCountries],
    'colFilters' => [['thSelector' => '.col-country']],
    'formModalConfig' => ['lookup_tables' => ['country']],
]);
$tp->renderFooter();
