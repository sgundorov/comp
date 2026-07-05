<?php
// lib/table-template.php
// Shared PHP/HTML rendering functions for table pages (city.php, country.php)

if (!defined('TABLE_TEMPLATE_LOADED')) {
define('TABLE_TEMPLATE_LOADED', true);

function render_head_start(string $title): void {
    ?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="app.css" />
<?php
}

function render_head_end(): void {
    $pw = isset($GLOBALS['pageWidth']) ? (int)$GLOBALS['pageWidth'] : 1100;
    ?><link rel="stylesheet" href="/comp/assets/table.css" />
    <?php if ($pw !== 1100): ?>
    <style>.page { max-width: <?= $pw ?>px; }</style>
    <?php endif; ?>
</head>
<body>
<?php
    global $CurSotrID;
    if (!empty($CurSotrID)) return;
    $login = (string)($_POST['login'] ?? '');
    ?>
<style>
#login-overlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;flex-direction:column}
#login-overlay .login-box{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:32px 40px;box-shadow:0 8px 32px rgba(0,0,0,.4);min-width:320px}
#login-overlay .login-box h2{margin:-32px -40px 20px;padding:10px 12px;background:#1f2c3a;color:#ffe9a8;font-size:22px;font-weight:700;line-height:1.1;border-bottom:2px solid #2a3a4b;border-radius:8px 8px 0 0;user-select:none}
#login-overlay .login-box .field{margin-bottom:14px}
#login-overlay .login-box .field label{display:block;font-size:12px;color:var(--muted);margin-bottom:2px}
#login-overlay .login-box .field input{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid var(--border);border-radius:4px;font-size:14px;color:var(--input-text);background:var(--input-bg)}
#login-overlay .login-box .field input:focus{border-color:var(--accent);outline:none;box-shadow:0 0 0 2px rgba(230,126,34,.2)}
#login-overlay .login-box .actions{display:flex;gap:18px;justify-content:center;margin-top:20px}
#login-overlay .login-box .btn-primary{background:var(--accent);border-color:var(--accent);color:#fff;padding:8px 24px}
#login-overlay .login-box .btn-primary:hover{background:var(--accent-hover)}
#login-overlay .login-error{color:var(--danger);font-size:13px;text-align:center;margin-top:10px;display:none}
</style>
<div id="login-overlay">
  <div class="login-box">
    <h2>Регистрация сотрудника</h2>
    <div class="field"><label>Логин</label><input type="text" id="login-login" value="<?= h($login) ?>" autocomplete="username" /></div>
    <div class="field"><label>Пароль</label><input type="password" id="login-password" autocomplete="current-password" /></div>
    <div class="login-error" id="login-error"></div>
    <div class="actions">
      <button class="btn btn-primary" id="login-submit"><img src="img/accept.png" alt="" /> Вход</button>
    </div>
  </div>
</div>
<script>
(function(){
  var overlay = document.getElementById('login-overlay');
  var loginInput = document.getElementById('login-login');
  var passInput = document.getElementById('login-password');
  var errEl = document.getElementById('login-error');
  function showError(msg) { errEl.textContent = msg; errEl.style.display = ''; }
  function hideError() { errEl.style.display = 'none'; }
  function doLogin() {
    var l = loginInput.value.trim();
    var p = passInput.value;
    if (!l || !p) { showError('Введите логин и пароль'); return; }
    hideError();
    fetch('login_handler.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', body:'login='+encodeURIComponent(l)+'&password='+encodeURIComponent(p) })
      .then(function(r){return r.json()})
      .then(function(d){
        if (d && d.ok) { location.reload(); }
        else { showError(d && d.error ? d.error : 'Ошибка входа'); }
      })
      .catch(function(){ showError('Ошибка соединения'); });
  }
  document.getElementById('login-submit').addEventListener('click', doLogin);
  loginInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') passInput.focus(); });
  passInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });
  loginInput.focus();
})();
</script>
<?php
}

function render_page_footer(): void {
    ?><script>
(function(){
  var kaTimer = null;
  function ka() {
    fetch('keepalive.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'})
      .then(function(r){return r.json()})
      .then(function(d){if(d&&!d.ok)location.reload();})
      .catch(function(){});
  }
  function rk() { if(kaTimer)clearTimeout(kaTimer); kaTimer=setTimeout(ka,120000); }
  document.addEventListener('mousedown',rk); document.addEventListener('keydown',rk); rk();
  var lb = document.getElementById('logout-btn');
  if(lb) lb.addEventListener('click',function(e){e.preventDefault();fetch('logout_handler.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'}).then(function(){location.reload();});});
})();
</script>
<?php
    echo "</body>\n</html>\n";
}

function render_pagination(int $page, int $pages, callable $baseQs, bool $hideSingle = true): string {
    if ($hideSingle && $pages <= 1) return '';
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    ob_start();
    ?><div class="pagination">
      <a class="page-btn" href="<?= h($baseQs(1)) ?>"<?= $page <= 1 ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>«</a>
      <?php if ($prev !== $page): ?>
      <a class="page-btn" href="<?= h($baseQs($prev)) ?>"><?= $prev ?></a>
      <?php endif; ?>
      <a class="page-btn active" href="<?= h($baseQs($page)) ?>"><?= $page ?></a>
      <?php if ($next !== $page): ?>
      <a class="page-btn" href="<?= h($baseQs($next)) ?>"><?= $next ?></a>
      <?php endif; ?>
      <a class="page-btn" href="<?= h($baseQs($pages)) ?>"<?= $page >= $pages ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '' ?>>»</a>
      <span class="page-info"><?= $page ?> из <?= $pages ?></span>
    </div><?php
    return ob_get_clean();
}

function render_toolbar_wrapper_open(array $dataAttrs): void {
    $attrs = '';
    foreach ($dataAttrs as $k => $v) {
        $attrs .= ' data-' . h($k) . '="' . h((string)$v) . '"';
    }
    ?><div class="toolbar"<?= $attrs ?>><?php
}

function render_toolbar_wrapper_close(): void {
    echo "</div>\n";
}

function render_toolbar_left(string $formPrefix, int $marksCount, string $exportDropdownHtml, string $printDropdownHtml, string $extraHtml = ''): void {
    ?><div class="toolbar-left">
    <button class="icon-btn" title="Добавить" data-form-open="<?= h($formPrefix) ?>.php?mode=new"><img src="img/add.png" alt="" /></button>
    <button class="icon-btn" id="rowOpenBtn" title="Изменить" type="button" disabled><img src="img/edit.png" alt="" /></button>
    <button class="icon-btn" id="rowDeleteBtn" title="Удалить" type="button" disabled><img src="img/delete.png" alt="" /></button>
    <button class="icon-btn" id="rowCopyBtn" title="Копировать" type="button" disabled><img src="img/copy.png" alt="" /></button>
    <button class="icon-btn" title="Обновить" onclick="location.reload()"><img src="img/refresh.png" alt="" /></button>
    <?= $extraHtml ?>

    <div class="dropdown">
      <button class="icon-btn" type="button" title="Экспорт"><img src="img/export.png" alt="" /></button>
      <div class="dropdown-menu"><?= $exportDropdownHtml ?></div>
    </div>

    <div class="dropdown">
      <button class="icon-btn" type="button" title="Печать"><img src="img/print.png" alt="" /></button>
      <div class="dropdown-menu"><?= $printDropdownHtml ?></div>
    </div>

    <div class="dropdown selected-actions<?= $marksCount > 0 ? ' visible' : '' ?>" id="selectedActions">
      <button class="menu-btn" type="button" title="Действия с выбранными">
        <span id="selectedCount">Выбрано <?= (int)$marksCount ?></span>
        <img src="img/look.png" alt="" />
      </button>
      <div class="dropdown-menu">
        <a class="dropdown-item" href="#" onclick="clearSelection();return false;">Очистить выбор</a>
        <a class="dropdown-item" href="#" onclick="invertSelection();return false;">Инвертировать выбор</a>
        <a class="dropdown-item" href="#" onclick="toggleShowOnly();return false;">Показать выбранные</a>
        <a class="dropdown-item" href="#" onclick="exportSelected();return false;">Экспорт</a>
        <a class="dropdown-item" href="#" onclick="printSelected();return false;">Печать</a>
      </div>
    </div>
  </div><?php
}

function render_toolbar_right(string $search, bool $searchActive, array $urlCols, string $searchCond, callable $clearQs, string $scriptName = 'city.php'): void {
    ?><div class="toolbar-right">
    <form id="searchForm" method="get" action="<?= h($scriptName) ?>" style="display:flex;gap:4px;align-items:center;">
      <input class="quick-search" type="text" name="q" placeholder="Быстрый поиск" value="<?= h($search) ?>" />
      <input type="hidden" name="cols" value="<?= h(implode(',', $urlCols)) ?>" />
      <input type="hidden" name="cond" value="<?= h($searchCond) ?>" />
      <input type="hidden" name="sf" value="<?= $searchActive ? '1' : '0' ?>" />
      <?php if ($searchActive): ?>
      <a class="icon-btn clear-filter-btn" title="Очистить фильтр" href="<?= h($clearQs(['q','cols','cond','sf','page'])) ?>">✕</a>
      <?php endif; ?>
      <button class="icon-btn search-toggle-btn<?= $searchActive ? ' active' : '' ?>" type="button" id="searchToggleBtn" title="Искать" aria-pressed="<?= $searchActive ? 'true' : 'false' ?>">
        <img src="img/find.png" alt="" />
      </button>
      <button class="icon-btn search-mini-btn" type="button" id="searchCondBtn" title="Условия поиска" aria-haspopup="true" aria-expanded="false">
        <img src="img/look.png" alt="" />
      </button>
    </form>
    <button class="icon-btn" id="sortBtn" title="Сортировка" type="button"><img src="img/sort.png" alt="" /></button>
    <button class="icon-btn" id="columnsBtn" title="Настройка столбцов таблицы" type="button"><img src="img/setup.png" alt="" /></button>
  </div><?php
}

function render_filter_banner(array $filters, callable $clearQs, string $icon = 'img/filter.png'): void {
    if (count($filters) === 0) return;
    ?><div class="mode-banner" id="filterBanner">
    <img src="<?= h($icon) ?>" alt="" />
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
  </div><?php
}

function render_table_colgroup(array $visibleColumns, array $columnWidths, array $defaultWidths): void {
    ?><colgroup>
    <col class="col-check" style="width: 32px;" />
    <?php foreach ($visibleColumns as $vc):
        $cn = $vc['name'];
        $savedW = $columnWidths[$cn] ?? null;
        $w = $savedW !== null ? $savedW . 'px' : ($defaultWidths[$cn] ?? '150px');
    ?>
      <col class="col-<?= h($cn) ?>" style="width: <?= $w ?>;" />
    <?php endforeach; ?>
  </colgroup><?php
}

function render_table_thead(array $visibleColumns, array $COL_META, array $sortLevels, bool $checkAllChecked, bool $checkAllDisabled, array $extra = []): void {
    $hasCheckbox     = $extra['hasCheckbox'] ?? true;
    $checkboxThHtml  = $extra['checkboxThHtml'] ?? null;
    $thLabelCallback = $extra['thLabelCallback'] ?? null;  // fn(string $cn, string $label, array $cm) => string
    $thAttrsCallback = $extra['thAttrsCallback'] ?? null;  // fn(string $cn, array $cm, int $i) => string
    $thHtmlCallback  = $extra['thHtmlCallback'] ?? null;   // fn(string $cn, array $cm) => string
    ?><thead><tr>
    <?php if ($checkboxThHtml !== null): ?>
      <?= $checkboxThHtml ?>
    <?php elseif ($hasCheckbox): ?>
      <th class="col-check"><input type="checkbox" id="checkAll"<?= $checkAllChecked ? ' checked' : '' ?><?= $checkAllDisabled ? ' disabled' : '' ?> /></th>
    <?php endif; ?>
    <?php foreach ($visibleColumns as $i => $vc):
        $cn  = $vc['name'];
        $cl  = $vc['label'];
        $cm  = $COL_META[$cn] ?? null;
        $sortIdx = -1;
        foreach ($sortLevels as $si => $sl) if ($sl['col'] === $cn) $sortIdx = $si;
        $sortDir = $sortIdx >= 0 ? $sortLevels[$sortIdx]['dir'] : '';
        $hasSortExpr = !empty($vc['sort_expr']);
        $thAttrs = 'class="col-' . h($cn) . '" data-col="' . h($cn) . '"' . ($hasSortExpr ? ' data-sort-col="' . h($cn) . '"' : '') . ' data-col-idx="' . (int)$i . '" data-sort-dir="' . h($sortDir) . '"';
        if (!empty($cm['param'])) $thAttrs .= ' data-param="' . h($cm['param']) . '"';
        if (!empty($vc['no_resize'])) $thAttrs .= ' data-no-resize="1"';
        if ($thAttrsCallback) $thAttrs .= $thAttrsCallback($cn, $cm, $i);
    ?>
      <th <?= $thAttrs ?>>
        <?php if ($thLabelCallback): ?><?= $thLabelCallback($cn, $cl, $cm) ?><?php else: ?><span class="col-filter-label"><?= h($cl) ?></span><?php endif; ?>
        <?php if ($thHtmlCallback): ?><?= $thHtmlCallback($cn, $cm) ?><?php endif; ?>
        <?php if ($sortIdx >= 0): ?>
          <span class="sort-indicator"><?= ($sortIdx + 1) ?> <?= $sortDir === 'asc' ? '▲' : '▼' ?></span>
        <?php endif; ?>
      </th>
    <?php endforeach; ?>
  </tr></thead><?php
}

/**
 * Форматирование числа для вывода в ячейке таблицы.
 *
 * - 0 → пустая строка
 * - Суммы (isSum=true) → 2 знака, разделитель пробел ("1 234.50")
 * - Целые числа (isSum=false) → без дробной части ("5", а не "5.00")
 * - Дробные числа (isSum=false) → до 4 знаков, без лишних нулей ("0.5", а не "0.5000")
 */
function fmt_table_num($val, bool $isSum = false): string {
    $n = (float)str_replace(',', '.', (string)$val);
    if ($n == 0) return '';
    if ($isSum) return number_format($n, 2, '.', ' ');
    if ($n == (int)$n) return (string)(int)$n;
    $s = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    return str_replace('.', ',', $s);
}

function apply_search_highlight(string $display, string $search): string {
    if ($search === '') return $display;
    return preg_replace('/' . preg_quote($search, '/') . '/iu', '<span class="hl">$0</span>', $display);
}

function render_table_tbody(array $visibleColumns, array $rows, array $marks, string $search, string $key, callable $cellValue, array $extra = []): void {
    $hasCheckbox = $extra['hasCheckbox'] ?? true;
    $rowIdAttr   = $extra['rowIdAttr'] ?? 'data-row-id';
    $checkboxCallback = $extra['checkboxCallback'] ?? null;
    $tdExtraAttrs  = $extra['tdExtraAttrs'] ?? null;
    $trExtraAttrs  = $extra['trExtraAttrs'] ?? null;
    $searchActive = $extra['searchActive'] ?? false;
    $searchCols   = $extra['searchCols'] ?? [];
    $rowReadonly  = $extra['rowReadonly'] ?? null;
    $noDataMessage = $extra['noDataMessage'] ?? ($search !== '' ? 'По запросу &laquo;' . h($search) . '&raquo; ничего не найдено.' : 'Нет данных.');
    ?><tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="<?= ($hasCheckbox ? 1 : 0) + count($visibleColumns) ?>" style="text-align:center; padding:20px; color:var(--muted);"><?= $noDataMessage ?></td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r):
          $rid = (int)$r[$key];
      ?>
      <tr <?= $rowIdAttr ?>="<?= $rid ?>"<?= $trExtraAttrs ? ' ' . $trExtraAttrs($r) : '' ?>>
        <?php if ($checkboxCallback): ?>
          <td class="col-check"><?= $checkboxCallback($rid) ?></td>
        <?php elseif ($hasCheckbox): ?>
          <td class="col-check"><input type="checkbox" class="row-check" value="<?= $rid ?>" data-id="<?= $rid ?>"<?= isset($marks[$rid]) ? ' checked' : '' ?> /></td>
        <?php endif; ?>
        <?php foreach ($visibleColumns as $i => $vc):
            $cn = $vc['name'];
            [$rawValue, $displayValue] = $cellValue($r, $cn, $vc);
            if ($search !== '' && strpos($displayValue, '<span class="hl"') === false) {
                $displayValue = apply_search_highlight($displayValue, $search);
            }
            $readonly = !empty($vc['readonly']) || $cn === 'id' || ($rowReadonly && $rowReadonly($r, $cn));
            $editable = !$readonly;
            $tdAttrs = 'class="col-' . h($cn) . ($editable ? ' cell-editable' : '') . '"';
            if ($editable) $tdAttrs .= ' data-field="' . h($cn) . '"';
            $tdAttrs .= ' data-value="' . h((string)$rawValue) . '"';
            if ($tdExtraAttrs) $tdAttrs .= $tdExtraAttrs($cn, $vc, $r, $i);
        ?>
          <td <?= $tdAttrs ?>>
            <span class="cell-value"><?= $displayValue ?></span>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody><?php
}

function render_form_modal(): void {
    ?><div class="form-modal-backdrop" id="formModal" role="dialog" aria-labelledby="formModalTitle" aria-modal="true">
    <div class="form-modal">
      <button type="button" class="form-modal-close" data-form-close title="Закрыть" aria-label="Закрыть">&times;</button>
      <div class="form-modal-body" id="formModalBody"></div>
    </div>
  </div><?php
}

function render_export_modal(): void {
    ?><div class="export-modal-backdrop" id="exportModal" role="dialog" aria-labelledby="exportModalTitle" aria-modal="true">
    <div class="export-modal">
      <div class="export-modal-title" id="exportModalTitle">Сохранение файла</div>
      <div class="export-modal-row export-modal-row-input">
        <label class="export-modal-label" for="exportModalFilename">Имя файла:</label>
        <input class="export-modal-input" type="text" id="exportModalFilename" />
        <span class="export-modal-ext" id="exportModalExt"></span>
      </div>
      <div class="export-modal-row">
        <div class="export-modal-label">Формат:</div>
        <div class="export-modal-value" id="exportModalFormat"></div>
      </div>
      <div class="export-modal-row">
        <div class="export-modal-label">Записей:</div>
        <div class="export-modal-value" id="exportModalCount"></div>
      </div>
      <div class="export-modal-actions">
        <button type="button" class="export-cancel">Отмена</button>
        <button type="button" class="export-apply">Скачать</button>
      </div>
    </div>
  </div><?php
}

function render_script_includes(array $extra = []): void {
    $core = [
        'assets/lookup.js',
        'assets/inline-edit.js',
        'assets/row-select.js',
        'assets/keyboard.js',
        'assets/table-keyboard.js',
        'assets/selection-toolbar.js',
    ];
    if (!empty($extra['noSearch'])) {
        // skip search-panel and sort-panel
    } else {
        $core[] = 'assets/search-panel.js';
        $core[] = 'assets/sort-panel.js';
    }
    $core[] = 'assets/column-resize.js';
    $core[] = 'assets/form-modal-core.js';
    $core[] = 'assets/columns-panel.js';
    $extraScripts = $extra['scripts'] ?? [];
    $allScripts = array_values(array_unique(array_merge($core, $extraScripts)));
    foreach ($allScripts as $src) {
        $fullPath = __DIR__ . '/../' . $src;
        $ts = file_exists($fullPath) ? filemtime($fullPath) : 0;
        ?><script src="<?= h($src) ?>?v=<?= $ts ?>"></script>
<?php
    }
}

function render_export_dropdown_items(array $items, string $exportQs): string {
    $html = '';
    foreach ($items as $item) {
        $fmt = $item['fmt'] ?? 'csv';
        $label = $item['label'] ?? ('CSV' === strtoupper($fmt) ? 'Экспорт в CSV' : 'Экспорт в Excel');
        $filename = $item['filename'] ?? '';
        $format = $item['format'] ?? strtoupper($fmt);
        $fullUrl = $item['url'] ?? ($item['page'] ?? '') . '_export.php?format=' . $fmt . ($exportQs !== '' ? '&' . $exportQs : '');
        $html .= '<a class="dropdown-item" href="#" data-export-url="' . h($fullUrl) . '" data-export-filename="' . h($filename) . '" data-export-format="' . h($format) . '">' . h($label) . '</a>';
    }
    return $html;
}

function render_print_dropdown_items(string $pageUrl, string $exportQs, int $page, bool $hasSelected = false): string {
    $printQs = $exportQs !== '' ? '?' . $exportQs : '';
    $allQs = $exportQs !== '' ? '?all=1&' . $exportQs : '?all=1';
    return
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php' . $printQs . '" target="_blank">Все записи</a>' .
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php' . $allQs . '" target="_blank">Выбранные</a>' .
        '<a class="dropdown-item" href="' . h($pageUrl) . '_print.php?page=' . (int)$page . ($exportQs !== '' ? '&' . $exportQs : '') . '" target="_blank">Текущая страница</a>';
}

} // endif TABLE_TEMPLATE_LOADED
