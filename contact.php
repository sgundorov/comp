<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/contact_columns.php';
require_once __DIR__ . '/config/contact_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Contact');

ensure_marks_table($conn);

$tp = new TablePage($conn, $contactPageConfig);

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'contact');

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
handle_marks_actions($conn, $tp, 'contact', function($action) use ($conn) {
    if ($action === 'toggleMarkRow' || $action === 'delMarksRow') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($action === 'delMarksRow') {
                $stmt = @$conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id = ?");
                if ($stmt) { stmt_bind($stmt, 'si', ['contact', $id]); $stmt->execute(); $stmt->close(); }
            } else {
                $marksNow = load_marks_set($conn, 'contact');
                if (isset($marksNow[$id])) {
                    $stmt = $conn->prepare("DELETE FROM marks WHERE tbl = ? AND row_id = ?");
                    stmt_bind($stmt, 'si', ['contact', $id]);
                } else {
                    $stmt = $conn->prepare("INSERT IGNORE INTO marks (tbl, row_id) VALUES (?, ?)");
                    stmt_bind($stmt, 'si', ['contact', $id]);
                }
                $stmt->execute(); $stmt->close();
            }
        }
        $newCount = count_marks($conn, 'contact');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'count' => $newCount], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return null;
});
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'columnFilterOptions') {
    $col = (string)($_GET['col'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($tp->colFilterOptions($conn, $col), JSON_UNESCAPED_UNICODE);
    exit;
}

// ------ FILTERS ------
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
    if (!in_array('client_id', $drop, true) && !empty($_GET['client_id'])) $qs['client_id'] = $_GET['client_id'];
    if (!in_array('contype_id', $drop, true) && !empty($_GET['contype_id'])) $qs['contype_id'] = $_GET['contype_id'];
    if (!in_array('sotr_id', $drop, true) && !empty($_GET['sotr_id'])) $qs['sotr_id'] = $_GET['sotr_id'];
    return 'contact.php' . ($qs ? '?' . http_build_query($qs) : '');
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

$clientFilter = (string)($_GET['client_id'] ?? '');
$clientFilterIds = []; $clientFilterNames = [];
if ($clientFilter !== '') {
    $clientFilterIds = array_values(array_filter(array_map('intval', explode(',', $clientFilter)), fn($v) => $v > 0));
}
if (count($clientFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($clientFilterIds), '?'));
    $clientFilterNames = loadFilterNames($conn, 'client', 'client_id', 'name', $clientFilterIds);
    $tp->appendWhere("ct.client_id IN ($ph)", $clientFilterIds, str_repeat('i', count($clientFilterIds)));
}

$contypeFilter = (string)($_GET['contype_id'] ?? '');
$contypeFilterIds = []; $contypeFilterNames = [];
if ($contypeFilter !== '') {
    $contypeFilterIds = array_values(array_filter(array_map('intval', explode(',', $contypeFilter)), fn($v) => $v > 0));
}
if (count($contypeFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($contypeFilterIds), '?'));
    $contypeFilterNames = loadFilterNames($conn, 'contype', 'contype_id', 'contype', $contypeFilterIds);
    $tp->appendWhere("ct.contype_id IN ($ph)", $contypeFilterIds, str_repeat('i', count($contypeFilterIds)));
}

$sotrFilter = (string)($_GET['sotr_id'] ?? '');
$sotrFilterIds = []; $sotrFilterNames = [];
if ($sotrFilter !== '') {
    $sotrFilterIds = array_values(array_filter(array_map('intval', explode(',', $sotrFilter)), fn($v) => $v > 0));
}
if (count($sotrFilterIds) > 0) {
    $ph = implode(',', array_fill(0, count($sotrFilterIds), '?'));
    $sotrFilterNames = loadFilterNames($conn, 'sotr', 'sotr_id', "CONCAT(COALESCE(last_name,''), ' ', COALESCE(first_name,''))", $sotrFilterIds);
    $tp->appendWhere("ct.sotr_id IN ($ph)", $sotrFilterIds, str_repeat('i', count($sotrFilterIds)));
}

if (count($clientFilterNames) > 0) {
    $filters[] = ['kind' => 'client', 'text' => 'Контрагент = ' . implode(', ', $clientFilterNames), 'clear' => 'client_id'];
}
if (count($contypeFilterNames) > 0) {
    $filters[] = ['kind' => 'contype', 'text' => 'Вид контакта = ' . implode(', ', $contypeFilterNames), 'clear' => 'contype_id'];
}
if (count($sotrFilterNames) > 0) {
    $filters[] = ['kind' => 'sotr', 'text' => 'Сотрудник = ' . implode(', ', $sotrFilterNames), 'clear' => 'sotr_id'];
}

$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
$page  = $tp->page;
$pages = $tp->pages;
$total = $tp->total;

$rowsMarkedCount = 0;
foreach ($rows as $r) { if (isset($marks[(int)$r['contact_id']])) $rowsMarkedCount++; }
$rowsTotalCount  = count($rows);
$allRowsMarked   = $rowsTotalCount > 0 && $rowsMarkedCount === $rowsTotalCount;

$urlCols = $searchCols;

$clientLookupData = [];
$clr = $conn->query("SELECT client_id, name FROM client ORDER BY name");
if ($clr) while ($cr = $clr->fetch_assoc()) $clientLookupData[] = ['id' => (int)$cr['client_id'], 'name' => (string)$cr['name']];

$contypeLookupData = [];
$clr2 = $conn->query("SELECT contype_id, contype FROM contype ORDER BY contype");
if ($clr2) while ($cr = $clr2->fetch_assoc()) $contypeLookupData[] = ['id' => (int)$cr['contype_id'], 'name' => (string)$cr['contype']];

$sotrLookupData = [];
$clr3 = $conn->query("SELECT sotr_id, TRIM(CONCAT_WS(' ', last_name, first_name)) AS name FROM sotr ORDER BY name");
if ($clr3) while ($cr = $clr3->fetch_assoc()) $sotrLookupData[] = ['id' => (int)$cr['sotr_id'], 'name' => (string)$cr['name']];

$exportQs = http_build_query(array_filter([
    'q'    => $searchActive && $search !== '' ? $search : null,
    'cols' => $searchActive && count($searchCols) > 0 ? implode(',', $searchCols) : null,
    'cond' => $searchActive ? $searchCond : null,
    'sf'   => $searchActive ? '1' : null,
    'sort' => $sortQs !== '' ? $sortQs : null,
    'client_id' => $clientFilter !== '' ? $clientFilter : null,
    'contype_id' => $contypeFilter !== '' ? $contypeFilter : null,
    'sotr_id' => $sotrFilter !== '' ? $sotrFilter : null,
], function ($v) { return $v !== null && $v !== ''; }));

$exportDropdownHtml = '';
foreach ([
    ['fmt' => 'csv', 'filename' => 'Контакты.csv', 'format' => 'CSV'],
    ['fmt' => 'xls', 'filename' => 'Контакты.xls', 'format' => 'XLS (Excel)'],
] as $item) {
    $fullUrl = 'contact_export.php?format=' . $item['fmt'] . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($item['filename']) . '" data-export-format="' . h($item['format']) . '">' . h($item['format'] === 'CSV' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}

$printQs = $exportQs !== '' ? '?' . $exportQs : '';
$printDropdownHtml = render_print_dropdown_items('contact', $exportQs, (int)$page, $marksCount > 0);

render_head_start('Контакты');
?>
  <style>
    .data-table tbody tr.selected td { background: #3a5a8a !important; color: #fff !important; }
    .data-table tbody tr.selected:hover td { background: #3a5a8a !important; }
    .col-impotant_flag .cell-value svg { display: inline-block; vertical-align: middle; }
    textarea.field-input { height: auto; resize: vertical; }
  </style>
<?php render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'contact.php'; include 'menu.php'; ?>
    <h1 class="page-title"><img src="img/contact.png" alt="" /> Контакты</h1>

<?php
    $baseQs = function($p) use ($search, $searchActive, $searchCols, $searchCond, $sortQs) {
        $qs = ['page' => (int)$p];
        if ($searchActive) {
            if ($search !== '') $qs['q'] = $search;
            if (count($searchCols) > 0) $qs['cols'] = implode(',', $searchCols);
            $qs['cond'] = $searchCond; $qs['sf'] = '1';
        }
        if ($sortQs !== '') $qs['sort'] = $sortQs;
        return 'contact.php?' . http_build_query($qs);
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
render_toolbar_left('contact_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'contact.php');
render_toolbar_wrapper_close(); ?>

<?php render_filter_banner($filters, $clearQs); ?>

<div class="table-wrap">
  <table class="data-table">
    <?php render_table_colgroup($visibleColumns, $columnWidths, ['id' => '46px', 'client' => '200px', 'datetime' => '160px', 'impotant_flag' => '80px', 'contype' => '150px', 'sotr' => '180px', 'note' => '900px']); ?>
    <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0); ?>
    <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'contact_id', function($r, $cn, $vc) use ($searchCond, $searchCols) {
        $search = $GLOBALS['search'] ?? '';
        $doHilight = in_array($cn, $searchCols, true);
        switch ($cn) {
            case 'id':
                $raw = (string)(int)$r['contact_id'];
                return [$raw, $raw];
            case 'client':
                $raw = (string)(int)($r['client_id'] ?? 0);
                return [$raw, $doHilight ? hilight((string)$r['cli_name'], $search, $searchCond) : (string)$r['cli_name']];
            case 'datetime':
                $raw = (string)$r['datetime'];
                $dt = $raw !== '' ? date('d.m.Y H:i', strtotime($raw)) : '';
                return [$raw, $dt];
            case 'impotant_flag':
                $v = (string)($r['impotant_flag'] ?? '0');
                return [$v, $v === '1' ? '<svg class="check-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="#27ae60" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>' : ''];
            case 'contype':
                $raw = (string)(int)($r['contype_id'] ?? 0);
                return [$raw, $doHilight ? hilight((string)$r['contype'], $search, $searchCond) : (string)$r['contype']];
            case 'sotr':
                $raw = (string)(int)($r['sotr_id'] ?? 0);
                return [$raw, (string)($r['sotr_name'] ?? '')];
            case 'note':
                $raw = (string)$r['note'];
                return [$raw, $doHilight ? hilight($raw, $search, $searchCond) : $raw];
        }
        return ['', ''];
    }, [
        'searchActive' => $searchActive,
        'searchCols' => $searchCols,
        'checkboxCallback' => function($rid) use ($marks) {
            return '<input type="checkbox" class="row-check" value="' . $rid . '" data-id="' . $rid . '"' . (isset($marks[$rid]) ? ' checked' : '') . ' />';
        },
    ]); ?>
  </table>
</div>

<?= $paginationHtml ?>
<?php render_form_modal(); ?>
</div>

<?php render_script_includes(['scripts' => ['assets/access.js', 'assets/lookup.js', 'assets/column-filter.js', 'assets/export-modal.js']]); ?>
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
    function updateRowActionButtons() { const id = rowSel.getSelectedId(); const en = id !== 0; const af = window.__accessFlags || {}; document.getElementById('rowOpenBtn').disabled = !en || !!af.change_flag; document.getElementById('rowCopyBtn').disabled = !en || !!af.insert_flag; document.getElementById('rowDeleteBtn').disabled = !en || !!af.delete_flag; rowSel.getRows().forEach(function (tr) { tr.querySelectorAll('td').forEach(function (td) { td.style.background = parseInt(tr.dataset.rowId, 10) === id ? '#3a5a8a' : ''; }); }); }
    const rowSel = RowSelect.init({ tbody: tbody, rowClass: 'selected', onChange: updateRowActionButtons, currentPage: currentPage, totalPages: currentPages, navigate: navigate });
    function selectRow(rowId) { rowSel.selectById(rowId, true); }
    function navigate(params) { const url = new URL(window.location.href); params(url.searchParams); window.location.href = url.pathname + '?' + url.searchParams.toString(); }
    const SORT_COLS = <?= json_encode(array_map(function ($c) { return ['key' => $c['name'], 'label' => $c['label']]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>;
    const SEARCH_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) { return $c['search'] ? ['key' => $c['name'], 'label' => $c['label']] : null; }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
    const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

    SelectionToolbar.initTableSelection('contact.php', document.querySelector('.toolbar').getAttribute('data-search') || '');

    SelectionToolbar.init({
        pageUrl: 'contact.php',
        getInvertUrl: function () { var o = new URLSearchParams(location.search); o.delete('ids'); return 'contact.php?action=invertSelection&' + o.toString(); },
        getExportUrl: function () {
          var sp = new URLSearchParams(location.search);
          ['action', 'page'].forEach(function (k) { sp.delete(k); });
          return 'contact_export.php?format=csv&selected=1&' + sp.toString();
        },
        getPrintUrl: function () {
          var sp = new URLSearchParams(location.search);
          ['action', 'page'].forEach(function (k) { sp.delete(k); });
          return 'contact_print.php?selected=1&' + sp.toString();
        }
    });

    document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    function closeAllPanels() { document.querySelectorAll('.col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); }); document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(function (p) { p.remove(); }); document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); }); }

    const searchForm = document.getElementById('searchForm');
    const searchCondBtn = document.getElementById('searchCondBtn');
    const searchToggleBtn = document.getElementById('searchToggleBtn');

    SearchPanel.init({
        form: searchForm, condBtn: searchCondBtn, toggleBtn: searchToggleBtn, columns: SEARCH_COLS, pageUrl: 'contact.php', popupCheckboxes: true, emptyClass: 'search-cond-placeholder', closeAllPanels: closeAllPanels,
        labels: { cols: 'Столбцы', cond: 'Условие', emptyCols: 'Нет столбцов для поиска' },
        onApply: function (state) {
            var p = new URLSearchParams(location.search); if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(',')); else p.delete('cols');
            p.set('cond', state.cond); p.set('sf', '1');
            var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim(); if (q !== '') p.set('q', q); else p.delete('q');
            p.delete('page'); location.href = 'contact.php?' + p.toString();
        },
        onToggle: function (state) {
            var p = new URLSearchParams(location.search); var q = searchForm.querySelector('input[name="q"]').value.trim();
            if (q !== '') { p.set('q', q); if (state.cols.size > 0) p.set('cols', Array.from(state.cols).join(',')); p.set('cond', state.cond); p.set('sf', '1'); }
            else { p.delete('q'); p.delete('cols'); p.delete('cond'); p.delete('sf'); }
            p.delete('page'); location.href = 'contact.php?' + p.toString();
        },
        onSubmit: function () { searchToggleBtn.click(); }
    });

    SortPanel.init({ btn: document.getElementById('sortBtn'), columns: SORT_COLS, pageUrl: 'contact.php', mode: 'modal', currentSort: currentSortLevels, directions: [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }] });

    ColumnsPanel.init({ btn: document.getElementById('columnsBtn'), saveUrl: 'contact_columns_save.php', tbl: 'contact', closeAllPanels: closeAllPanels, initialColumns: <?= json_encode(array_map(function ($c) { return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])]; }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
      defaultColumns: <?= json_encode(array_map(function ($c) { return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true]; }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?> });

    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(['id' => 46, 'client' => 200, 'datetime' => 160, 'impotant_flag' => 80, 'contype' => 150, 'sotr' => 180, 'note' => 900], JSON_UNESCAPED_UNICODE) ?>;

    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);

    document.addEventListener('click', function (e) {
        let a = e.target.closest('a[href*="contact_form.php"]');
        if (a) { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return; e.preventDefault(); e.stopImmediatePropagation(); window.__openFormModal(a.getAttribute('href')); return; }
        const trig = e.target.closest('[data-form-open]');
        if (trig) { e.preventDefault(); window.__openFormModal(trig.getAttribute('data-form-open')); return; }
    });

    document.querySelectorAll('tbody tr').forEach(function (tr) {
        const id = parseInt(tr.dataset.rowId, 10);
        tr.addEventListener('click', function (e) { if (e.target.closest('input.row-check')) return; selectRow(id); });
        tr.addEventListener('dblclick', function () { if (window.__accessFlags && window.__accessFlags.change_flag) return; window.__openFormModal('contact_form.php?mode=edit&id=' + id); });
    });

    document.getElementById('rowOpenBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) window.__openFormModal('contact_form.php?mode=edit&id=' + id); });
    document.getElementById('rowCopyBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) window.__openFormModal('contact_form.php?mode=copy&id=' + id); });
    document.getElementById('rowDeleteBtn').addEventListener('click', function () { const id = rowSel.getSelectedId(); if (id !== 0) window.__openFormModal('contact_form.php?mode=delete&id=' + id); });

    const tableWrapEl = document.querySelector('.table-wrap');

    (function applyInitialFocus() {
        const rows = rowSel.getRows(); if (rows.length === 0) return;
        const raw = (toolbar.dataset.focus || '').toString();
        if (raw === 'first') { rowSel.selectByIndex(0); return; }
        if (raw === 'last') { rowSel.selectByIndex(rows.length - 1); return; }
        const id = parseInt(raw, 10);
        if (id > 0 && rowSel.selectById(id, false)) return;
        rowSel.selectByIndex(0);
    })();

    bindTableKeyboardShortcuts({ formPrefix: 'contact_form', rowSel: rowSel, currentPage: currentPage, currentPages: currentPages, navigate: navigate, tableWrapEl: tableWrapEl, accessFlags: window.__accessFlags });
    __refreshSelectionUI();
})();
</script>
  <?php render_form_modal_script([
    'form_prefix'   => 'contact_form',
    'base_url'      => 'contact.php',
    'lookup_tables' => [],
  ]); ?>
<script>
    if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) { /* skip */ } else {
    InlineEdit.init({
        tbody: document.querySelector('table tbody'),
        saveUrl: 'contact_field_save.php',
        fields: <?php
          $inlineFields = [];
          $valCases = '';
          foreach ($visibleColumns as $vc) {
            $cn = $vc['name'];
            if (!empty($vc['readonly']) || $cn === 'id') continue;
            if ($cn === 'impotant_flag') {
              $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'checkbox', 'label' => $vc['label']];
            } elseif ($cn === 'datetime') {
              $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'text', 'label' => $vc['label']];
            } elseif ($cn === 'note') {
              $inlineFields[$cn] = ['dbField' => $cn, 'type' => 'textarea', 'label' => $vc['label']];
            } else {
              $isLookup = !empty($vc['param']);
              $inlineFields[$cn] = ['dbField' => $cn, 'type' => $isLookup ? 'lookup' : 'text', 'label' => $vc['label']];
              if ($isLookup) $valCases .= "    case " . json_encode($cn, JSON_UNESCAPED_UNICODE) . ": if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
            }
          }
        ?><?= json_encode($inlineFields, JSON_UNESCAPED_UNICODE) ?>,
        validate: function (field, value) { switch (field) { <?= $valCases ?> } return null; },
        onOpenForm: window.__openFormModal,
        getLookupData: function (field) {
            switch (field) { case 'client': return __clientLookupData; case 'contype': return __contypeLookupData; case 'sotr': return __sotrLookupData; }
            return [];
        }
    });
    }

    var __clientLookupData = <?= json_encode($clientLookupData, JSON_UNESCAPED_UNICODE) ?>;
    var __contypeLookupData = <?= json_encode($contypeLookupData, JSON_UNESCAPED_UNICODE) ?>;
    var __sotrLookupData = <?= json_encode($sotrLookupData, JSON_UNESCAPED_UNICODE) ?>;

    ColumnResize.init({ saveUrl: 'contact_column_width_save.php', tbl: 'contact' });
    ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'contact.php' });
    ColumnFilter.init({ thSelector: '.col-contype', pageUrl: 'contact.php' });
    ColumnFilter.init({ thSelector: '.col-sotr', pageUrl: 'contact.php' });
    ExportModal.init();
</script>
<?php render_page_footer();
