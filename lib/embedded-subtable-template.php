<?php
if (!defined('EMBEDDED_SUBTABLE_TEMPLATE_LOADED')) {
define('EMBEDDED_SUBTABLE_TEMPLATE_LOADED', true);

function render_embedded_subtable(array $cfg): string {
    $prefix     = $cfg['prefix'] ?? 'est';
    $columns    = $cfg['columns'] ?? [];
    $data       = $cfg['data'] ?? [];
    $colWidths  = $cfg['colWidths'] ?? [];
    $readonly   = $cfg['readonly'] ?? false;
    $hasExport  = $cfg['hasExport'] ?? false;
    $hasImport  = $cfg['hasImport'] ?? false;
    $hasPrint   = $cfg['hasPrint'] ?? false;
    $hasSearch  = $cfg['hasSearch'] ?? true;

    ob_start();
    ?>
    <div class="toolbar" style="margin-top:0;padding-top:0;margin-bottom:8px" id="<?= h($prefix) ?>-toolbar">
      <div class="toolbar-left">
        <?php if (!$readonly): ?>
        <button type="button" class="icon-btn" title="Добавить" id="<?= h($prefix) ?>-add-btn"><img src="img/add.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Изменить" id="<?= h($prefix) ?>-edit-btn" disabled><img src="img/edit.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Удалить" id="<?= h($prefix) ?>-del-btn" disabled><img src="img/delete.png" alt="" /></button>
        <button type="button" class="icon-btn" title="Копировать" id="<?= h($prefix) ?>-copy-btn" disabled><img src="img/copy.png" alt="" /></button>
        <?php endif; ?>
        <button type="button" class="icon-btn" title="Обновить" id="<?= h($prefix) ?>-refresh-btn"><img src="img/refresh.png" alt="" /></button>
        <?php if ($hasImport && !$readonly): ?>
        <button type="button" class="icon-btn" title="Импорт отмеченных" id="<?= h($prefix) ?>-import-btn"><img src="img/import.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($hasExport): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Экспорт" id="<?= h($prefix) ?>-export-btn"><img src="img/export.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-export-all">Экспорт в CSV</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-export-all-xls">Экспорт в Excel</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($hasPrint): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Печать" id="<?= h($prefix) ?>-print-btn"><img src="img/print.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-print-all">Все записи</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-print-page">Текущая страница</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!$readonly): ?>
        <div class="dropdown selected-actions" id="<?= h($prefix) ?>-sel-wrap">
          <button type="button" class="menu-btn" id="<?= h($prefix) ?>-sel-btn">Выбрано <b><span id="<?= h($prefix) ?>-sel-count">0</span></b> <span class="btn-caret">&#9660;</span></button>
          <div class="dropdown-menu" style="min-width:180px">
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-clear">Очистить выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-invert">Инвертировать выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-show">Показать выбранные</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-delete">Удалить отмеченные</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-export">Экспорт выбранных</a>
            <a class="dropdown-item" href="#" id="<?= h($prefix) ?>-sel-print">Печать выбранных</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasSearch): ?>
      <div class="toolbar-right">
        <div id="<?= h($prefix) ?>-search-form" style="display:flex;gap:4px;align-items:center;">
          <input class="quick-search" type="text" id="<?= h($prefix) ?>-search-input" name="q" placeholder="Быстрый поиск" />
          <a class="icon-btn clear-filter-btn" title="Сбросить поиск" id="<?= h($prefix) ?>-clear-search" style="display:none;text-decoration:none">✕</a>
          <button type="button" class="icon-btn" title="Поиск" id="<?= h($prefix) ?>-search-btn"><img src="img/find.png" alt="" /></button>
          <button type="button" class="icon-btn" title="Условия поиска" id="<?= h($prefix) ?>-search-cond-btn"><img src="img/look.png" alt="" /></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="mode-banner" id="<?= h($prefix) ?>-filter-banner" style="display:none">
      <img src="img/filter.png" alt="" />
      <span id="<?= h($prefix) ?>-filter-chips"></span>
    </div>
    <table class="data-table docum2-table" style="min-width:auto;table-layout:fixed;width:100%" id="<?= h($prefix) ?>-table"
           data-items='<?= h(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>'>
      <colgroup>
        <col class="col-check" style="width:32px" />
        <?php foreach ($columns as $col):
            $w = $colWidths[$col['key']] ?? 'auto';
        ?>
          <col class="col-<?= h($col['key']) ?>" style="width:<?= h($w) ?>" />
        <?php endforeach; ?>
      </colgroup>
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" id="<?= h($prefix) ?>-check-all" /></th>
          <?php foreach ($columns as $col): ?>
            <th class="col-<?= h($col['key']) ?>"><?= h($col['label']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="<?= h($prefix) ?>-pagination" style="margin-top:8px;text-align:left"></div>
    <?php
    return ob_get_clean();
}

function render_embedded_subtable_scripts(array $cfg): void {
    $prefix     = $cfg['prefix'] ?? 'est';
    $columns    = $cfg['columns'] ?? [];
    $searchCols = $cfg['searchCols'] ?? array_column($columns, 'key');
    $saveUrl    = $cfg['saveUrl'] ?? '';
    $parentField= $cfg['parentField'] ?? '';
    $childFormUrl  = $cfg['childFormUrl'] ?? '';
    $childFormName = $cfg['childFormName'] ?? '';
    $pageSize   = $cfg['pageSize'] ?? 15;
    $hasExport  = $cfg['hasExport'] ?? false;
    $hasPrint   = $cfg['hasPrint'] ?? false;
    $hasSearch  = $cfg['hasSearch'] ?? true;
    $readonly   = $cfg['readonly'] ?? false;
    $exportUrl  = $cfg['exportUrl'] ?? '';
    $printUrl   = $cfg['printUrl'] ?? '';
    $parentParam = $cfg['parentParam'] ?? ($parentField . '=');
    $lookupDataMap = $cfg['lookupData'] ?? [];
    $totalsCallback = $cfg['totalsCallback'] ?? '';
    $totalsCallbackName = $totalsCallback ?: 'apply' . ucfirst($prefix) . 'Totals';
    $onSaveSuccessExtra = $cfg['onSaveSuccessExtra'] ?? '';
    $columnLabels = $cfg['columnLabels'] ?? [];
    $colWidths  = $cfg['colWidths'] ?? [];
    if (empty($columnLabels)) {
        foreach ($columns as $col) $columnLabels[$col['key']] = $col['label'];
    }
    $columnsJson = json_encode($columns, JSON_UNESCAPED_UNICODE);
    $searchColsJson = json_encode($searchCols, JSON_UNESCAPED_UNICODE);
    $columnLabelsJson = json_encode($columnLabels, JSON_UNESCAPED_UNICODE);
    $lookupDataMapJson = json_encode($lookupDataMap, JSON_UNESCAPED_UNICODE);
    $p = $prefix;
    ?>
<?php if (!$totalsCallback): ?>
    window.<?= $totalsCallbackName ?> = function (data) {
      if (!data || typeof data !== 'object') return;
      Object.keys(data).forEach(function (key) {
        var el = document.querySelector('[data-total-field="' + key + '"]');
        if (el) el.textContent = data[key];
      });
    };
<?php endif; ?>
    window.init<?= ucfirst($p) ?>Table = function () {
      var table = document.getElementById('<?= $p ?>-table');
      if (!table) return;
      var groupIdEl = document.querySelector('input[name="id"]');
      var groupId = groupIdEl ? parseInt(groupIdEl.value, 10) : 0;
      if (!groupId) return;
      if (window['__<?= $p ?>Table'] && window['__<?= $p ?>Table'].parentId === groupId && window['__<?= $p ?>Table'].tableEl === table) return;
      window['__<?= $p ?>Table'] = null;
      var data = [];
      try { data = JSON.parse(table.dataset.items || '[]'); } catch(e) {}
      var formBody = document.querySelector('.form-modal-body') || document.querySelector('[data-form-modal]') || document.body;

      window['__<?= $p ?>Table'] = EmbeddedSubTable.create({
        tableEl: table,
        formBody: formBody,
        saveUrl: '<?= $saveUrl ?>',
        prefix: '<?= $p ?>',
        parentId: groupId,
        parentField: '<?= $parentField ?>',
        data: data,
        columns: <?= $columnsJson ?>,
        searchCols: <?= $searchColsJson ?>,
        pageSize: <?= $pageSize ?>,
        selWrapEl: '#<?= $p ?>-sel-wrap',
        selCountEl: '#<?= $p ?>-sel-count',
        checkAllEl: '#<?= $p ?>-check-all',
        paginationEl: '#<?= $p ?>-pagination',
        btnAddEl: '#<?= $p ?>-add-btn',
        btnEditEl: '#<?= $p ?>-edit-btn',
        btnCopyEl: '#<?= $p ?>-copy-btn',
        btnDeleteEl: '#<?= $p ?>-del-btn',
        btnRefreshEl: '#<?= $p ?>-refresh-btn',
        childFormUrl: '<?= $childFormUrl ?>',
        childFormName: '<?= $childFormName ?>',
        totalsCallback: <?= json_encode($totalsCallbackName, JSON_UNESCAPED_UNICODE) ?>,
        readonly: <?= json_encode($readonly) ?>,
        onRowDoubleClick: <?= $readonly ? 'null' : 'function (id) { window[\'__' . $p . 'Table\'].openChildForm(\'edit\', id); }' ?>,
        filterBannerEl: '#<?= $p ?>-filter-banner',
        filterChipsEl: '#<?= $p ?>-filter-chips',
        clearSearchBtnEl: '#<?= $p ?>-clear-search'
      });

      <?php if (!$readonly): ?>
      if (typeof InlineEdit !== 'undefined') {
        var ieFields = {};
        <?php foreach ($columns as $col):
            $colType = $col['type'] ?? 'text';
            $dbField = $col['dbField'] ?? $col['key'];
        ?>
        ieFields['<?= $col['key'] ?>'] = { dbField: '<?= $dbField ?>', type: '<?= $colType ?>', label: <?= json_encode($col['label'], JSON_UNESCAPED_UNICODE) ?> };
        <?php endforeach; ?>
        InlineEdit.init({
          tbody: table.querySelector('tbody'),
          saveUrl: '<?= $saveUrl ?>',
          fields: ieFields,
          getLookupData: function (field) {
            var map = <?= $lookupDataMapJson ?>;
            return map[field] || [];
          },
          validate: function (field, value) {
            var fieldCfg = ieFields[field];
            if (fieldCfg && fieldCfg.type === 'lookup' && (!value || value === '0' || value === 0)) return 'Выберите значение из списка';
            return null;
          },
          onSaveSuccess: function (data, field) {
            if (data && data.item && data.item.id) {
              var item = data.item;
              for (var i = 0; i < window['__<?= $p ?>Table'].data.length; i++) {
                if (window['__<?= $p ?>Table'].data[i].id == item.id) { window['__<?= $p ?>Table'].data[i] = item; break; }
              }
              window['__<?= $p ?>Table'].selectedId = item.id;
              window['__<?= $p ?>Table'].render();
            }
            if (typeof window.<?= $totalsCallbackName ?> === 'function') window.<?= $totalsCallbackName ?>(data);
            <?= $onSaveSuccessExtra ?>
          }
        });
      }
      <?php endif; ?>

      if (typeof ColumnResize !== 'undefined') {
        window.__columnDefaultWidths = <?= json_encode($colWidths, JSON_UNESCAPED_UNICODE) ?>;
        ColumnResize.init({ saveUrl: '<?= $cfg['columnResizeUrl'] ?? '' ?>', tbl: '<?= $cfg['columnResizeTbl'] ?? '' ?>', selector: '#<?= $p ?>-table' });
      }

      var addBtn = formBody.querySelector('#<?= $p ?>-add-btn');
      if (addBtn) addBtn.addEventListener('click', function () {
        window['__<?= $p ?>Table'].openChildForm('new');
      });
      var editBtn = formBody.querySelector('#<?= $p ?>-edit-btn');
      if (editBtn) editBtn.addEventListener('click', function () {
        if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('edit', window['__<?= $p ?>Table'].selectedId);
      });
      var delBtn = formBody.querySelector('#<?= $p ?>-del-btn');
      if (delBtn) delBtn.addEventListener('click', function () {
        if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('delete', window['__<?= $p ?>Table'].selectedId);
      });
      var copyBtn = formBody.querySelector('#<?= $p ?>-copy-btn');
      if (copyBtn) copyBtn.addEventListener('click', function () {
        if (window['__<?= $p ?>Table'].selectedId) window['__<?= $p ?>Table'].openChildForm('copy', window['__<?= $p ?>Table'].selectedId);
      });
      var refreshBtn = formBody.querySelector('#<?= $p ?>-refresh-btn');
      if (refreshBtn) refreshBtn.addEventListener('click', function () {
        window['__<?= $p ?>Table'].refresh();
      });

      var searchInput = formBody.querySelector('#<?= $p ?>-search-input');
      var searchBtn = formBody.querySelector('#<?= $p ?>-search-btn');
      var searchCondBtn = formBody.querySelector('#<?= $p ?>-search-cond-btn');
      if (searchInput && searchBtn) {
        searchBtn.addEventListener('click', function () {
          var q = searchInput.value;
          window['__<?= $p ?>Table'].searchText = q;
          window['__<?= $p ?>Table'].searchActive = q.length > 0;
          window['__<?= $p ?>Table'].currentPage = 1;
          window['__<?= $p ?>Table'].render();
        });
        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); searchBtn.click(); }
        });
      }
      if (searchCondBtn) {
        searchCondBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          SearchPanel.open({
            target: searchCondBtn,
            columns: <?= json_encode(array_map(fn($c) => ['key' => $c['key'], 'label' => $c['label']], $columns), JSON_UNESCAPED_UNICODE) ?>,
            currentCond: window['__<?= $p ?>Table'] ? window['__<?= $p ?>Table'].searchCond : 'contains',
            currentCols: window['__<?= $p ?>Table'] ? window['__<?= $p ?>Table'].searchCols : null,
            onApply: function (state) {
              if (!window['__<?= $p ?>Table']) return;
              window['__<?= $p ?>Table'].searchCols = Array.from(state.cols);
              window['__<?= $p ?>Table'].searchCond = state.cond;
              window['__<?= $p ?>Table'].searchActive = true;
              window['__<?= $p ?>Table'].currentPage = 1;
              window['__<?= $p ?>Table'].render();
            }
          });
        });
      }

      var selClear = formBody.querySelector('#<?= $p ?>-sel-clear');
      if (selClear) selClear.addEventListener('click', function (e) {
        e.preventDefault();
        window['__<?= $p ?>Table'].checkedIds = new Set();
        window['__<?= $p ?>Table'].render();
      });
      var selInvert = formBody.querySelector('#<?= $p ?>-sel-invert');
      if (selInvert) selInvert.addEventListener('click', function (e) {
        e.preventDefault();
        var rows = window['__<?= $p ?>Table'].tbody.querySelectorAll('.d2-row-check');
        rows.forEach(function (cb) {
          var id = parseInt(cb.dataset.id, 10);
          if (window['__<?= $p ?>Table'].checkedIds.has(id)) { window['__<?= $p ?>Table'].checkedIds.delete(id); } else { window['__<?= $p ?>Table'].checkedIds.add(id); }
          cb.checked = window['__<?= $p ?>Table'].checkedIds.has(id);
        });
        window['__<?= $p ?>Table']._updateCheckedUI();
      });
      var selDelete = formBody.querySelector('#<?= $p ?>-sel-delete');
      if (selDelete) selDelete.addEventListener('click', function (e) {
        e.preventDefault();
        window['__<?= $p ?>Table'].batchDeleteChecked();
      });
      var selShow = formBody.querySelector('#<?= $p ?>-sel-show');
      if (selShow) selShow.addEventListener('click', function (e) {
        e.preventDefault();
        window['__<?= $p ?>Table'].showOnlyChecked = !window['__<?= $p ?>Table'].showOnlyChecked;
        window['__<?= $p ?>Table'].currentPage = 1;
        window['__<?= $p ?>Table'].render();
      });

      function sgQs(tbl) {
        if (!tbl || !tbl.searchActive || !tbl.searchText) return '';
        var q = 'q=' + encodeURIComponent(tbl.searchText) + '&sf=1';
        if (tbl.searchCols && tbl.searchCols.length > 0) q += '&cols=' + encodeURIComponent(tbl.searchCols.join(','));
        q += '&cond=' + encodeURIComponent(tbl.searchCond || 'contains');
        return q;
      }

      <?php if ($hasExport && $exportUrl): ?>
      var sgGroupId = groupId;
      var sgBaseUrl = '<?= $exportUrl ?>?<?= $parentParam ?>' + sgGroupId;
      var sgExportAll = formBody.querySelector('#<?= $p ?>-export-all');
      if (sgExportAll) sgExportAll.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgBaseUrl + '&format=csv' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      var sgExportAllXls = formBody.querySelector('#<?= $p ?>-export-all-xls');
      if (sgExportAllXls) sgExportAllXls.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgBaseUrl + '&format=xls' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      var selExport = formBody.querySelector('#<?= $p ?>-sel-export');
      if (selExport) selExport.addEventListener('click', function (e) {
        e.preventDefault();
        var ids = Array.from(window['__<?= $p ?>Table'].checkedIds);
        if (ids.length === 0) return;
        var url = sgBaseUrl + '&format=csv&ids=' + ids.join(',');
        var q = sgQs(window['__<?= $p ?>Table']); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      <?php endif; ?>

      <?php if ($hasPrint && $printUrl): ?>
      var sgPrintUrl = '<?= $printUrl ?>?<?= $parentParam ?>' + groupId;
      var sgPrintAll = formBody.querySelector('#<?= $p ?>-print-all');
      if (sgPrintAll) sgPrintAll.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgPrintUrl + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      var sgPrintPage = formBody.querySelector('#<?= $p ?>-print-page');
      if (sgPrintPage) sgPrintPage.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgPrintUrl + '&page=' + (tbl ? tbl.currentPage : 1) + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      var selPrint = formBody.querySelector('#<?= $p ?>-sel-print');
      if (selPrint) selPrint.addEventListener('click', function (e) {
        e.preventDefault();
        var ids = Array.from(window['__<?= $p ?>Table'].checkedIds);
        if (ids.length === 0) return;
        var url = sgPrintUrl + '&ids=' + ids.join(',');
        var q = sgQs(window['__<?= $p ?>Table']); if (q) url += '&' + q;
        window.open(url, '_blank');
      });
      <?php endif; ?>

      var tabs = formBody.querySelectorAll('.tab-header');
      var panes = formBody.querySelectorAll('.tab-pane');
      tabs.forEach(function (h) {
        h.addEventListener('click', function () {
          var idx = parseInt(h.dataset.tabIndex, 10);
          tabs.forEach(function (t) { t.classList.remove('active'); });
          panes.forEach(function (p) { p.classList.remove('active'); });
          h.classList.add('active');
          var pane = formBody.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
          if (pane) pane.classList.add('active');
          if (idx === 1 && window['__<?= $p ?>Table']) {
            var tbl = formBody.querySelector('#<?= $p ?>-table');
            if (tbl) tbl.focus();
          }
        });
      });
    }
    <?php
}

} // endif EMBEDDED_SUBTABLE_TEMPLATE_LOADED
