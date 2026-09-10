<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/arenda_columns.php';
require_once __DIR__ . '/config/arenda_page.php';
require_once __DIR__ . '/lib/TablePage.php';
require_once __DIR__ . '/lib/table-template.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/form-modal-handler.php';

$accessFlags = render_access_control($conn, 'Docum');

$PAGE_TITLE = 'Аренда';

ensure_marks_table($conn);
@mysqli_query($conn, "DELETE m FROM marks m LEFT JOIN docum d ON d.docum_id = m.row_id WHERE m.tbl = 'arenda' AND d.docum_id IS NULL");

function arenda_hide_zero_time($time) {
    $t = trim((string)$time);
    if ($t === '' || $t === '00:00' || $t === '00:00:00') return true;
    return false;
}

$tp = new TablePage($conn, $arendaPageConfig);
$tp->appendWhere("d.typeop = ?", [90], "i");

$table            = $tp->table;
$key              = $tp->key;
$columnsConfig    = $tp->columnsConfig;
$visibleColumns   = $tp->visibleColumns;
$COLUMN_DEFAULTS  = $tp->columns;
$COL_META         = $tp->colMeta;
$columnWidths     = load_columns_widths($conn, 'arenda');

$search           = $tp->search;
$searchActive     = $tp->searchActive;
$searchCols       = $tp->searchCols;
$searchCond       = $tp->searchCond;
$sortLevels       = $tp->sortLevels;
$sortIsDefault    = $tp->sortIsDefault;
$sortQs           = $tp->sortQs;
$showOnly         = $tp->showOnly;
$marks            = $tp->marks;
$marksCount       = $tp->marksCount;

require_once __DIR__ . '/lib/marks-actions.php';
handle_marks_actions($conn, $tp, 'arenda', function($action) use ($conn) {
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
    $ok = save_columns_config($conn, 'arenda', $clean);
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

$sotrList = [];
$sotrs = $conn->query("SELECT sotr_id, last_name AS name FROM sotr ORDER BY last_name");
if ($sotrs) while ($sr = $sotrs->fetch_assoc()) $sotrList[] = ['id' => (int)$sr['sotr_id'], 'name' => (string)$sr['name']];

$urlCols = $searchCols;
$clearQs = $tp->buildClearQs();
$tp->buildFilters();
$filters = $tp->filters;
$exportQs = $tp->buildExportQs();

$exportDropdownHtml = '';
foreach (['csv' => 'Аренда ' . date('d.m.Y_H.i') . '.csv', 'xls' => 'Аренда ' . date('d.m.Y_H.i') . '.xls'] as $fmt => $filename) {
    $fullUrl = 'arenda_export.php?format=' . $fmt . ($exportQs !== '' ? '&' . $exportQs : '');
    $exportDropdownHtml .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h(strtoupper($fmt)) . '">' . ($fmt === 'csv' ? 'Экспорт в CSV' : 'Экспорт в Excel') . '</a>';
}

$printDropdownHtml = render_print_dropdown_items('arenda', $exportQs, (int)$page, $marksCount > 0);

render_head_start($PAGE_TITLE);
?>
<style>
    .data-table td.col-vremya, .data-table td.col-beg, .data-table td.col-vozvrat { white-space: nowrap; }
    .data-table tr.row-rez td { color: #c8f7c5; }
    .data-table td.col-status { position: relative; text-align: center; }
    .data-table td.col-status img {
        display: block;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }
    .cell-overdue { background: #e57373 !important; color: #fff; }
    .col-filter-panel { display:none; position:fixed; z-index:1000; min-width:240px; max-width:320px; padding:8px; background:#2f3e4e; border:2px solid #6b7785; border-radius:2px; box-shadow:0 8px 18px rgba(0,0,0,.35); text-align:left; }
    .col-filter-panel.open { display:block; }
    .form-modal { max-width: 1400px; }
    .page--form { max-width: 1400px; }
    .form { max-width: 1400px; }
    .tab-container { margin-bottom: 16px; width: 100%; }
</style>
<?php
render_export_modal();
render_head_end(); ?>
  <div class="page">
    <?php $activeMenu = 'arenda.php'; include 'menu.php'; ?>

    <h1 class="page-title"><img src="img/price.png" alt="" /> <?= h($PAGE_TITLE) ?></h1>

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
          return 'arenda.php?' . http_build_query($qs);
      };
    ?>
    <?php render_toolbar_wrapper_open([
        'total'        => $total,
        'show-only'    => $showOnly ? '1' : '0',
        'marks-count'  => $marksCount,
        'search'       => $search,
        'page'         => $page,
        'pages'        => $pages,
        'focus'        => (string)($_GET['focus'] ?? '0'),
    ]);
    render_toolbar_left('arenda_form', $marksCount, $exportDropdownHtml, $printDropdownHtml);
    render_toolbar_right($search, $searchActive, $urlCols, $searchCond, $clearQs, 'arenda.php');
    render_toolbar_wrapper_close(); ?>

    <?php render_filter_banner($filters, $clearQs, 'img/filter.png'); ?>

    <div class="table-wrap">
    <table class="data-table">
      <?php render_table_colgroup($visibleColumns, $columnWidths, arenda_columns_widths_print()); ?>
      <?php render_table_thead($visibleColumns, $COL_META, $sortLevels, $allRowsMarked, $rowsTotalCount === 0, [
          'thAttrsCallback' => function($cn, $cm, $i) {
              if ($cm && !empty($cm['filter'])) {
                  $param = $cn === 'plat_type' ? 'plat_type' : ($cm['param'] ?? ($cn . '_id'));
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
      ]); ?>
      <?php render_table_tbody($visibleColumns, $rows, $marks, $search, 'docum_id', function($r, $cn, $vc) {
          switch ($cn) {
              case 'status':
                  if ((int)$r['rezerv_flag'] === 1) return [1, '<img src="img/time.png" alt="Резерв" width="27" height="27" style="display:block" />'];
                  if ((int)$r['voz_flag'] === 1) return [2, '<img src="img/vozvr.png" alt="Возвращено" width="27" height="27" style="display:block" />'];
                  return [0, ''];
              case 'firm':     $fv = (int)$r['firm_id']; return [$fv > 0 ? (string)$fv : '', $fv > 0 ? (string)$fv : ''];
              case 'number':   return [(int)$r['number'], (int)$r['number']];
              case 'vremya':   $raw = trim((string)$r['date'] . ' ' . substr((string)$r['time'], 0, 5)); return [$raw, h($raw)];
              case 'client':   return [(int)$r['client_id'], h((string)($r['client_name'] ?? ''))];
              case 'beg':      $raw = trim((string)$r['date_beg'] . (arenda_hide_zero_time($r['time_beg']) ? '' : ' ' . substr((string)$r['time_beg'], 0, 5))); return [$raw, h($raw)];
              case 'vozvrat':  $raw = trim((string)$r['date_voz'] . (arenda_hide_zero_time($r['time_voz']) ? '' : ' ' . substr((string)$r['time_voz'], 0, 5))); return [$raw, h($raw)];
              case 'poz':      $pv = (int)$r['poz']; return [$pv, $pv > 0 ? (string)$pv : ''];
              case 'sum':      $sv = (float)$r['sum']; return [$r['sum'], $sv == 0 ? '' : number_format($sv, 2, ',', ' ')];
              case 'sum_plat': $sv = (float)$r['sum_plat']; return [$r['sum_plat'], $sv == 0 ? '' : number_format($sv, 2, ',', ' ')];
              case 'plat_type':return [(string)$r['plat_type'], h((string)$r['plat_type'])];
              case 'sotr':     return [(int)$r['sotr_id'], h((string)($r['sotr_name'] ?? ''))];
              case 'note':     return [$r['note'], h($r['note'])];
          }
          return ['', ''];
      }, [
          'searchActive' => $searchActive,
          'searchCols' => $searchCols,
          'trExtraAttrs' => function($r) {
              if ((int)($r['voz_flag'] ?? 0) === 1) return ' style="color:#fff9c4"';
              if ((int)($r['rezerv_flag'] ?? 0) === 1) return ' style="color:#c8f7c5"';
              return '';
          },
          'tdExtraAttrs' => function($cn, $vc, $r, $i) {
              if ($cn === 'vozvrat') {
                  $t = strtotime(trim((string)$r['date_voz'] . ' ' . (string)$r['time_voz']));
                  if ($t && (int)($r['voz_flag'] ?? 0) === 0) {
                      if ($t < time()) return ' style="background:#e57373;color:#fff"';
                      if ($t < time() + 86400) return ' style="background:#fff176;color:#333"';
                  }
              }
              if ($cn === 'sum_plat') {
                  if ((float)$r['sum_plat'] < (float)$r['sum'] && (int)($r['rezerv_flag'] ?? 0) === 0)
                      return ' style="background:#e57373;color:#fff"';
              }
              return '';
          },
      ]); ?>
    </table>
    </div>

    <?php $tp->renderPagination(); ?>

    <?php render_form_modal(); ?>
  </div>
<?php
render_script_includes(['scripts' => ['assets/access.js', 'assets/export-modal.js', 'assets/embedded-subtable.js', 'assets/column-filter.js', 'assets/embedded-table.js', 'assets/arenda-d2-renderers.js']]);
?>
  <script>
    window.__columnWidths = <?= json_encode($columnWidths, JSON_NUMERIC_CHECK) ?>;
    window.__columnDefaultWidths = <?= json_encode(arenda_columns_widths_print(), JSON_UNESCAPED_UNICODE) ?>;
    window.__accessFlags = <?= json_encode($accessFlags) ?>;
    if (typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
  </script>
  <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
      document.querySelectorAll('.sort-modal-backdrop.open').forEach(function (p) { p.classList.remove('open'); });
    }
    var formModal = document.getElementById('formModal');
    var formBody  = document.getElementById('formModalBody');
    var stashed   = null;

    function applyDocum2Totals(d) {
      var s = d && d.total_sum !== undefined ? d.total_sum : undefined;
      if (s !== undefined) { var el = document.getElementById('arenda-sum'); if (el) el.value = s || ''; }
      var sd = d && d.total_sum_discount !== undefined ? d.total_sum_discount : undefined;
      if (sd !== undefined) { var el2 = document.getElementById('arenda-sum-discount'); if (el2) el2.value = sd || ''; }
      if (d && d.pos !== undefined) { var el3 = document.getElementById('arenda-pos'); if (el3) el3.value = d.pos > 0 ? String(d.pos) : ''; }
      if (d && d.sum_plat !== undefined) { var el4 = document.getElementById('arenda-sum-plat'); if (el4) { el4.value = d.sum_plat; el4.style.color = d.sum_plat_red ? '#e57373' : ''; el4.style.fontWeight = d.sum_plat_red ? 'bold' : ''; } }
      if (d && d.total_sum_zalog !== undefined) { var elZ = document.getElementById('arenda-sum-zalog'); if (elZ) elZ.value = d.total_sum_zalog || ''; }
      if (d && d.date_voz !== undefined) { var el5 = document.getElementById('arenda-date-voz'); if (el5) el5.value = d.date_voz || ''; }
      if (d && d.time_voz !== undefined) { var el6 = document.getElementById('arenda-time-voz'); if (el6) el6.value = d.time_voz ? String(d.time_voz).substring(0, 5) : ''; }
      if (d && d.days !== undefined) { var el7 = document.getElementById('arenda-days'); if (el7) el7.value = d.days > 0 ? String(d.days) : ''; }
      if (d && d.hours !== undefined) { var el8 = document.getElementById('arenda-hours'); if (el8) el8.value = d.hours ? String(d.hours).substring(0, 5) : ''; }
    }

    function evalFormScripts(root) {
      (root || formBody).querySelectorAll('script:not([src])').forEach(function(s) {
        try { eval(s.textContent); } catch(ex) { console.error('[arenda-form] script error', ex); }
      });
    }
    function initFormLookups() {
      if (typeof bindLookup !== 'function') return;
      formBody.querySelectorAll('[data-lookup="client"]').forEach(function(el) { bindLookup({ root: el, data: <?= json_encode($clientFilterOptions, JSON_UNESCAPED_UNICODE) ?>, readonly: el.hasAttribute('data-readonly') }); });
      formBody.querySelectorAll('[data-lookup="store"]').forEach(function(el) { bindLookup({ root: el, data: <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], []), JSON_UNESCAPED_UNICODE) ?>, readonly: el.hasAttribute('data-readonly') }); });
      formBody.querySelectorAll('[data-lookup="sotr"]').forEach(function(el) { bindLookup({ root: el, data: <?= json_encode($sotrList, JSON_UNESCAPED_UNICODE) ?>, readonly: el.hasAttribute('data-readonly') }); });
      formBody.querySelectorAll('[data-lookup="product"]').forEach(function(el) {
        if (el.dataset.lookupInited) return;
        var rawJson = el.getAttribute('data-countries');
        if (!rawJson) return;
        try {
          var data = JSON.parse(rawJson);
          if (!Array.isArray(data)) return;
          bindLookup({ root: el, data: data, readonly: el.hasAttribute('data-readonly') });
          el.dataset.lookupInited = '1';
        } catch (e) {}
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

    function bindMakeButtons(form) {
    function fmtDateTimeDisp(dateEl, timeEl) {
      var d = dateEl ? dateEl.value : '';
      var t = timeEl ? timeEl.value : '';
      var m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
      var res = '';
      if (m) res = m[3] + '.' + m[2] + '.' + m[1];
      if (t) { var tm = String(t).match(/(\d{1,2}):(\d{2})/); if (tm) res += ' ' + tm[1] + ':' + tm[2]; }
      return res;
    }
    function applyToAll(field, docId, btn, getFields, done) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        if (!docId) { alert('Сначала сохраните документ'); return; }
        if (!done()) return;
        var fd = new FormData();
        fd.set('field', field);
        fd.set('docum_id', String(docId));
        var fields = typeof getFields === 'function' ? getFields() : (getFields || {});
        if (fields) { Object.keys(fields).forEach(function (k) { fd.set(k, String(fields[k] == null ? '' : fields[k])); }); }
        fetch('arenda_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!(data && data.ok)) { alert('Не удалось применить'); return; }
            applyDocum2Totals(data);
            var tbl = window.__d2Table;
            if (!tbl) return;
            var fd2 = new FormData();
            fd2.set('field', '_list');
            fd2.set('docum_id', String(docId));
            fetch(tbl.saveUrl, { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then(function (r2) { return r2.json(); })
              .then(function (list) { if (Array.isArray(list)) { tbl.data = list; tbl.render(); } });
          });
      });
    }
    function fval(sel) { var el = form.querySelector(sel); return el ? el.value : ''; }
    (function () {
      var idEl = form.querySelector('input[name="id"]');
      var docId = idEl ? parseInt(idEl.value, 10) : 0;
      var begB = form.querySelector('#apply-beg-btn');
      if (begB) applyToAll('_apply_beg', docId, begB, function () {
        return { date_beg: fval('#arenda-date-beg'), time_beg: fval('#arenda-time-beg'),
                 date_voz: fval('#arenda-date-voz'), time_voz: fval('#arenda-time-voz') };
      }, function () {
        var beg = fmtDateTimeDisp(form.querySelector('#arenda-date-beg'), form.querySelector('#arenda-time-beg'));
        return confirm('Установить время начала в ' + beg + ' ?');
      });
      var vozB = form.querySelector('#apply-voz-btn');
      if (vozB) applyToAll('_apply_voz', docId, vozB, function () {
        return { date_voz: fval('#arenda-date-voz'), time_voz: fval('#arenda-time-voz') };
      }, function () {
        var voz = fmtDateTimeDisp(form.querySelector('#arenda-date-voz'), form.querySelector('#arenda-time-voz'));
        return confirm('Установить время возврата в ' + voz + ' ?');
      });
    })();
      var discBtn = form.querySelector('#apply-discount-btn');
      if (discBtn && !discBtn.dataset.bound) {
        discBtn.dataset.bound = '1';
        discBtn.addEventListener('click', function () {
          var discountEl = form.querySelector('#arenda-discount');
          var discount = discountEl ? parseFloat(discountEl.value.replace(',', '.')) : 0;
          if (isNaN(discount)) discount = 0;
          if (!confirm('Установить скидку у всех товаров документа: ' + discount + ' %?')) return;
          var idEl = form.querySelector('input[name="id"]');
          var docId = idEl ? parseInt(idEl.value, 10) : 0;
          if (!docId) { alert('Сначала сохраните документ'); return; }
          var fd = new FormData();
          fd.set('field', '_apply_discount');
          fd.set('docum_id', String(docId));
          fd.set('discount', String(discount));
          fetch('docum2_field_save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!(data && data.ok)) return;
              applyDocum2Totals(data);
              var tbl = window.__d2Table;
              if (!tbl) return;
              var fd2 = new FormData();
              fd2.set('field', '_list');
              fd2.set('docum_id', String(docId));
              fetch(tbl.saveUrl, { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r2) { return r2.json(); })
                .then(function (list) { if (Array.isArray(list)) { tbl.data = list; tbl.render(); } });
            });
        });
      }
    }
  </script>
  <script>
    window.__openFormModal = window.__openFormModal || function(url){ location.href = url; };
    (function () {
      var _tb = document.querySelector('.toolbar');
      SelectionToolbar.initTableSelection('arenda.php', _tb.getAttribute('data-search') || '');
      SelectionToolbar.init({
        pageUrl: 'arenda.php',
        search: _tb.getAttribute('data-search') || '',
        getInvertUrl: function () {
          var other = new URLSearchParams(location.search);
          other.delete('ids');
          return 'arenda.php?action=invertSelection&' + other.toString();
        },
        getExportUrl: function () { return 'arenda_export.php?format=csv&all=1&selected=' + ((window.__marksCountGlobal||0)>0?'1':'0') + '&' + new URLSearchParams(location.search).toString(); },
        getPrintUrl: function () { return 'arenda_print.php?all=1&selected=1&' + new URLSearchParams(location.search).toString(); }
      });
      ColumnFilter.init({ thSelector: '.col-client', pageUrl: 'arenda.php' });
      ColumnFilter.init({ thSelector: '.col-plat_type', pageUrl: 'arenda.php' });
      ColumnFilter.init({ thSelector: '.col-sotr', pageUrl: 'arenda.php' });

      const SORT_COLS = <?= json_encode(array_values(array_filter(array_map(function ($c) {
          return (empty($c['sort_expr']) || in_array($c['name'], ['status'], true)) ? null : ['key' => $c['name'], 'label' => $c['label']];
      }, $COLUMN_DEFAULTS))), JSON_UNESCAPED_UNICODE) ?>;
      const SEARCH_COLS = <?= json_encode(array_values(array_map(function ($key) use ($arendaPageConfig) {
          $labels = $arendaPageConfig['search_labels'] ?? [];
          return ['key' => $key, 'label' => $labels[$key] ?? $key];
      }, array_keys($arendaPageConfig['search_cols']))), JSON_UNESCAPED_UNICODE) ?>;
      const currentSortLevels = <?= json_encode($sortLevels, JSON_UNESCAPED_UNICODE) ?>;

      const searchForm  = document.getElementById('searchForm');
      const searchCondBtn  = document.getElementById('searchCondBtn');
      const searchToggleBtn = document.getElementById('searchToggleBtn');

      function navigateSearch(params) {
        params.delete('page');
        location.href = 'arenda.php?' + params.toString();
      }

      SearchPanel.init({
        form: searchForm,
        condBtn: searchCondBtn,
        toggleBtn: searchToggleBtn,
        columns: SEARCH_COLS,
        pageUrl: 'arenda.php',
        popupCheckboxes: true,
        emptyClass: 'search-cond-placeholder',
        closeAllPanels: closeAllPanels,
        labels: { cols: 'Столбцы', cond: 'Условие', emptyCols: 'Нет столбцов для поиска' },
        onApply: function (state) {
          var params = new URLSearchParams(location.search);
          if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
          else params.delete('cols');
          params.set('cond', state.cond);
          params.set('sf', '1');
          var q = (searchForm.querySelector('input[name="q"]') || { value: '' }).value.trim();
          if (q !== '') params.set('q', q); else params.delete('q');
          navigateSearch(params);
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
          navigateSearch(params);
        },
        onSubmit: function () { searchToggleBtn.click(); }
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: SORT_COLS,
        pageUrl: 'arenda.php',
        mode: 'modal',
        currentSort: currentSortLevels,
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });

      ColumnsPanel.init({
        btn: document.getElementById('columnsBtn'),
        saveUrl: 'arenda_columns_save.php',
        tbl: 'arenda',
        closeAllPanels: closeAllPanels,
        initialColumns: <?= json_encode(array_map(function ($c) {
            return ['name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])];
        }, $columnsConfig), JSON_UNESCAPED_UNICODE) ?>,
        defaultColumns: <?= json_encode(array_map(function ($c) {
            return ['name' => $c['name'], 'label' => $c['label'], 'visible' => true];
        }, $COLUMN_DEFAULTS), JSON_UNESCAPED_UNICODE) ?>
      });
    })();
    ExportModal.init();
  </script>
  <script>
    (function () {
      const toolbar = document.querySelector('.toolbar');
      if (!toolbar) return;
      const tbody = document.querySelector('table tbody');
      const openBtn   = document.getElementById('rowOpenBtn');
      const copyBtn   = document.getElementById('rowCopyBtn');
      const deleteBtn = document.getElementById('rowDeleteBtn');
      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        currentPage: currentPage,
        currentPages: currentPages,
        onChange: function (id) {
          const enabled = id > 0;
          const af = window.__accessFlags || {};
          if (openBtn)   openBtn.disabled   = !enabled || !!af.change_flag;
          if (copyBtn)   copyBtn.disabled   = !enabled || !!af.insert_flag;
          if (deleteBtn) deleteBtn.disabled = !enabled || !!af.delete_flag;
        }
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
          window.__openFormModal('arenda_form.php?mode=edit&id=' + id);
        });
      }
      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        window.__openFormModal('arenda_form.php?mode=' + mode + '&id=' + id);
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
        formPrefix: 'arenda_form',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: document.querySelector('.table-wrap'),
        onOpenForm: window.__openFormModal,
        accessFlags: window.__accessFlags
      });

      document.addEventListener('click', function(e) {
        let a = e.target.closest('a[href*="arenda_form.php"]');
        if (a) {
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
          e.preventDefault(); e.stopImmediatePropagation();
          window.__openFormModal(a.getAttribute('href'));
          return;
        }
        const trig = e.target.closest('[data-form-open]');
        if (trig) { e.preventDefault(); window.__openFormModal(trig.getAttribute('data-form-open')); }
      });
      if (!window.__accessFlags || !window.__accessFlags.change_flag) {
        InlineEdit.init({
          tbody: tbody,
          saveUrl: 'arenda_field_save.php',
          fields: {
            note: { dbField: 'note', type: 'textarea', label: 'Примечание' },
            plat_type: { dbField: 'plat_type', type: 'text', label: 'Вид оплаты' },
            client: { dbField: 'client_id', type: 'lookup', label: 'Клиент' },
            sotr: { dbField: 'sotr_id', type: 'lookup', label: 'Сотрудник' }
          },
          getLookupData: function (field) {
            var map = {
              client: <?= json_encode($clientFilterOptions, JSON_UNESCAPED_UNICODE) ?>,
              sotr: <?= json_encode($sotrList, JSON_UNESCAPED_UNICODE) ?>
            };
            return map[field] || [];
          },
          onOpenForm: window.__openFormModal
        });
      }
      ColumnResize.init({ saveUrl: 'arenda_column_width_save.php', tbl: 'arenda', selector: 'table.data-table' });
    })();
  </script>
  <script>
    // ---- Модальная форма Аренды (шаблон sale.php) ----
    function restoreStashedFormA(data) {
      if (!stashed) return;
      var savedTableSelections = stashed._tableSelections || {};
      var html = stashed.html;
      var scripts = [];
      html = html.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function(m, code) { if (code.trim()) scripts.push(code); return ''; });
      formBody.innerHTML = html;
      scripts.forEach(function(code) { try { eval(code); } catch(e) { console.error('form script', e); } });
      initFormLookups();
      try { initD2Table(); } catch(e) { console.error('initD2Table', e); }
      try { if (window.__d2Table) { applyArendaD2Renderers(window.__d2Table); window.__d2Table.render(); } } catch(e) {}
      try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
      if (typeof window.bindArendaQuickPlat === 'function') window.bindArendaQuickPlat();
      if (window.__platTable) { try { window.__platTable.refresh(); } catch(e) {} }
      initArendaFormTabSwitch();
      var old = stashed;
      stashed = null;
      if (savedTableSelections) {
        Object.keys(savedTableSelections).forEach(function(k) {
          var tbl = window[k];
          if (tbl && typeof tbl.selectedId === 'number' && savedTableSelections[k]) {
            tbl.selectedId = savedTableSelections[k];
            tbl.render();
          }
        });
      }
      if (old.tabIndex && old.tabIndex !== 0 && typeof window.__switchArendaTab === 'function') window.__switchArendaTab(old.tabIndex);
      var f = formBody.querySelector('form[data-form-modal]');
      bindForm(f);
      FormModalCore.bindFormTabTrap(f);
      /* После сохранения товара docum2 сервер возвращает doc_voz_flag/doc_rezerv_flag —
         обновляем чекбоксы документа, иначе при последующем сохранении формы
         устаревший (необновлённый) чекбокс voz_flag воспринимается как «возврат снят»
         и сбрасывает voz_flag у ВСЕХ товаров. */
      if (data) {
        try {
          if (data.doc_voz_flag !== undefined) {
            var vChk = formBody.querySelector('input[name="voz_flag"]');
            if (vChk) vChk.checked = data.doc_voz_flag === 1;
          }
          if (data.doc_rezerv_flag !== undefined) {
            var rChk = formBody.querySelector('input[name="rezerv_flag"]');
            if (rChk) rChk.checked = data.doc_rezerv_flag === 1;
          }
        } catch (e) { console.error('syncDocFlags', e); }
      }
      if (data) try { old.onRestore(data, formBody); } catch (e) {}
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
        formBody.querySelectorAll('[data-bound]').forEach(function (n) { n.removeAttribute('data-bound'); });
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
      try { if (window.__d2Table) { applyArendaD2Renderers(window.__d2Table); window.__d2Table.render(); } } catch(e) {}
          try { initPlatTable(); } catch(e) { console.error('initPlatTable', e); }
          if (typeof window.bindArendaQuickPlat === 'function') window.bindArendaQuickPlat();
          initArendaFormTabSwitch();
          var form = formBody.querySelector('form[data-form-modal]');
          bindForm(form);
          FormModalCore.bindFormTabTrap(form);
          FormModalCore.focusFirstField(formBody);
        })
        .catch(function () {
          formBody.innerHTML = '<div class="flash flash--error">Ошибка подключения к БД</div>';
        });
    }
    window.openFormModal = openFormModal;
    window.__openFormModal = openFormModal;

    function closeFormModal() {
      formModal.classList.remove('open');
      document.body.style.overflow = '';
      formBody.innerHTML = '';
      stashed = null;
    }

    function bindForm(form) {
      if (!form) return;
      bindMakeButtons(form);
      form.addEventListener('submit', function (e) {
        if (window.__accessFlags && window.__accessFlags.save_flag) return;
        e.preventDefault();
        const fd = new FormData(form);
        fd.set('ajax', '1');
        const submitBtn = e.submitter || form.querySelector('button[type="submit"]');
        if (submitBtn && submitBtn.name) fd.set(submitBtn.name, submitBtn.value || '1');
        fetch(form.getAttribute('action') || 'arenda_form.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
              if (stashed) { restoreStashedFormA(data); } else {
                if (submitBtn && submitBtn.name === 'action' && submitBtn.value === 'apply') {
                  applyDocum2Totals(data);
                  var idEl = form.querySelector('input[name="id"]');
                  if (idEl && !parseInt(idEl.value, 10)) idEl.value = data.id;
                } else {
                  closeFormModal();
                  const params = new URLSearchParams(location.search);
                  FormModalCore.setFocusAfterSave(params, form, data);
                  location.href = 'arenda.php?' + params.toString();
                }
              }
            } else {
              formBody.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>';
              evalFormScripts();
              initFormLookups();
              try { initD2Table(); if (window.__d2Table) { applyArendaD2Renderers(window.__d2Table); window.__d2Table.render(); } } catch(e) {}
              try { initPlatTable(); } catch(e) {}
              initArendaFormTabSwitch();
              var f = formBody.querySelector('form[data-form-modal]'); bindForm(f); FormModalCore.bindFormTabTrap(f);
              if (data && data.focusField) { var el = formBody.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } } else { FormModalCore.focusFirstField(formBody); }
            }
          }).catch(function (err) {
            const flash = document.createElement('div'); flash.className = 'flash flash--error'; flash.textContent = 'Ошибка подключения к БД: ' + (err && err.message ? err.message : 'unknown'); form.insertBefore(flash, form.firstChild);
          });
      });
    }

    document.addEventListener('click', function(e) {
      if (e.target.closest('[data-form-close]') && formModal.classList.contains('open')) {
        e.stopImmediatePropagation(); e.preventDefault();
        if (stashed) { restoreStashedFormA(null); } else { closeFormModal(); }
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && formModal.classList.contains('open')) {
        e.preventDefault();
        if (stashed) { restoreStashedFormA(null); } else { closeFormModal(); var p = new URLSearchParams(location.search); var st = document.querySelector('table.data-table tbody tr.selected'); if (st) p.set('focus', st.getAttribute('data-row-id')); location.href = location.pathname + '?' + p.toString(); }
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
          if (stashed) { restoreStashedFormA(null); } else { closeFormModal(); var p = new URLSearchParams(location.search); var st = document.querySelector('table.data-table tbody tr.selected'); if (st) p.set('focus', st.getAttribute('data-row-id')); location.href = location.pathname + '?' + p.toString(); } return;
        }
      }
      let a = e.target.closest('a[href*="arenda_form.php"]');
      if (a) { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return; e.preventDefault(); e.stopImmediatePropagation(); openFormModal(a.getAttribute('href')); return; }
      const trig = e.target.closest('[data-form-open]');
      if (trig) { e.preventDefault(); openFormModal(trig.getAttribute('data-form-open')); return; }
      if (e.target.closest('[data-form-close]')) { e.preventDefault(); if (stashed) { restoreStashedFormA(null); } else { closeFormModal(); } return; }
      if (e.target.closest('[data-lookup-add]')) {
        e.preventDefault(); e.stopImmediatePropagation();
        var addLink = e.target.closest('a[data-lookup-add]');
        var target = addLink.getAttribute('data-lookup-add');
        if (target) {
          var href = addLink.getAttribute('href') || (target + '_form.php?mode=new');
          syncFormValues();
          openFormModal(href, { onRestore: function(data, bodyEl) {
            if (!data || !data.id || !data.name) return;
            var container = bodyEl.querySelector('[data-lookup="' + target + '"]');
            if (!container) return;
            var idEl = container.querySelector('[data-lookup-id]');
            var nameEl = container.querySelector('.lookup-input');
            if (idEl) idEl.value = String(data.id);
            if (nameEl) nameEl.value = data.name;
            var items = [];
            try { items = JSON.parse(container.getAttribute('data-countries') || '[]'); } catch(e) {}
            if (!items.find(function(c) { return c.id === data.id; })) {
              items.push({ id: data.id, name: data.name });
              items.sort(function(a, b) { return a.name.localeCompare(b.name, 'ru'); });
              container.setAttribute('data-countries', JSON.stringify(items));
            }
          }});
        }
        return;
      }
    });

    function initArendaFormTabSwitch() {
      var headers = formBody.querySelectorAll('.tab-header');
      var panes   = formBody.querySelectorAll('.tab-pane');
      if (!headers.length) return;
      headers.forEach(function(h) {
        h.addEventListener('click', function() {
          var idx = this.getAttribute('data-tab-index');
          headers.forEach(function(x) { x.classList.remove('active'); });
          panes.forEach(function(x) { x.classList.remove('active'); });
          h.classList.add('active');
          var pane = formBody.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
          if (pane) pane.classList.add('active');
        });
      });
      window.__switchArendaTab = function(idx) {
        var hd = formBody.querySelector('.tab-header[data-tab-index="' + idx + '"]');
        if (hd) hd.click();
      };
    }

    /* Быстрая оплата: глобальное делегирование кликов по кнопкам Оплата/Залог/Возврат —
       работает независимо от пересоздания DOM (stash/restore). */
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('#quickplat-oplata,#quickplat-zalog,#quickplat-vozvrat') : null;
      if (!btn) return;
      var kindMap = { 'quickplat-oplata': 'oplata', 'quickplat-zalog': 'zalog', 'quickplat-vozvrat': 'vozvrat' };
      var kind = kindMap[btn.id];
      if (!kind) return;
      var f = formBody.querySelector('form[data-form-modal]');
      var idEl = f ? f.querySelector('input[name="id"]') : null;
      var id = idEl ? parseInt(idEl.value, 10) : 0;
      if (!id) { alert('Сначала сохраните документ'); return; }
      var fd = new FormData();
      fd.set('docum_id', String(id));
      fd.set('kind', kind);
      fetch('arenda_quickplat.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!(data || {}).ok) { alert((data && data.error) || 'Ошибка'); return; }
          if (typeof window.applyDocum2Totals === 'function') window.applyDocum2Totals(data);
          var tbl = window.__platTable;
          if (tbl) tbl.refresh({ focusId: data.id });
        })
        .catch(function (err) { alert('Ошибка: ' + ((err && err.message) || err)); });
    });
  </script>
<?php render_page_footer(); ?>

