<?php
if (!defined('EMBEDDED_TABLE_LOADED')) {
define('EMBEDDED_TABLE_LOADED', true);

require_once __DIR__ . '/TableComponent.php';

class EmbeddedTable extends TableComponent {
    public $prefix = 'est';
    public $saveUrl = '';
    public $parentField = '';
    public $childFormUrl = '';
    public $childFormName = '';
    public $pageSize = 15;
    public $hasExport = false;
    public $hasImport = false;
    public $hasPrint = false;
    public $hasSearch = true;
    public $readonly = false;
    public $accessFlags = null;
    public $exportUrl = '';
    public $printUrl = '';
    public $parentParam = '';
    public $columnResizeUrl = '';
    public $columnResizeTbl = '';
    public $totalsCallback = '';
    public $onSaveSuccessExtra = '';
    public $columnLabels = [];
    public $barcodeAdd = false;
    public $toolbarExtraAfterRefresh = '';

    public function __construct(array $config) {
        $config['column_visibility_tbl'] = $config['column_visibility_tbl'] ?? '';
        parent::__construct($config);

        $this->prefix       = $config['prefix'] ?? 'est';
        $this->saveUrl      = $config['saveUrl'] ?? '';
        $this->parentField  = $config['parentField'] ?? '';
        $this->childFormUrl  = $config['childFormUrl'] ?? '';
        $this->childFormName = $config['childFormName'] ?? '';
        $this->pageSize     = $config['pageSize'] ?? 15;
        $this->hasExport    = $config['hasExport'] ?? false;
        $this->hasImport    = $config['hasImport'] ?? false;
        $this->hasPrint     = $config['hasPrint'] ?? false;
        $this->hasSearch    = $config['hasSearch'] ?? true;
        $this->readonly     = $config['readonly'] ?? false;
        $this->accessFlags  = $config['accessFlags'] ?? null;
        $this->exportUrl    = $config['exportUrl'] ?? '';
        $this->printUrl     = $config['printUrl'] ?? '';
        $this->parentParam  = $config['parentParam'] ?? ($this->parentField . '=');
        $this->columnResizeUrl  = $config['columnResizeUrl'] ?? '';
        $this->columnResizeTbl  = $config['columnResizeTbl'] ?? '';
        $this->totalsCallback   = $config['totalsCallback'] ?? '';
        $this->onSaveSuccessExtra = $config['onSaveSuccessExtra'] ?? '';
        $this->columnLabels     = $config['columnLabels'] ?? [];
        $this->barcodeAdd       = $config['barcodeAdd'] ?? false;
        $this->toolbarExtraAfterRefresh = $config['toolbarExtraAfterRefresh'] ?? '';

        if (empty($this->columnLabels)) {
            foreach ($this->columns as $col) {
                $n = $col['name'] ?? $col['key'] ?? '';
                if ($n !== '') $this->columnLabels[$n] = $col['label'] ?? $n;
            }
        }

        if (!empty($config['lookupData'])) $this->lookupData = $config['lookupData'];
        if (!empty($config['colWidths'])) {
            $this->defaultColumnWidths = [];
            foreach ($config['colWidths'] as $k => $v) {
                $this->defaultColumnWidths[$k] = is_numeric($v) ? $v . 'px' : $v;
            }
        }
        if (!empty($config['data'])) {
            // data is stored for later use in render()
        }
    }

    public function render(array $data = []): string {
        $p = $this->prefix;
        $cols = $this->columns;
        $colWidths = $this->defaultColumnWidths;

        // Build visible columns (all columns if no visibility config, or filtered)
        $visibleCols = $this->visibleColumns;
        if (empty($visibleCols)) $visibleCols = $cols;

        ob_start();
        ?>
<?php
$af = $this->accessFlags;
$_readonly = $this->readonly;
$_addOk = !$_readonly && (!$af || !$af['insert_flag']);
$_editOk = !$_readonly && (!$af || !$af['change_flag']);
$_delOk = !$_readonly && (!$af || !$af['delete_flag']);
$_copyOk = !$_readonly && (!$af || !$af['insert_flag']);
$_batchDelOk = !$_readonly && (!$af || (!$af['delete_flag'] && !$af['save_flag']));
$_actionsOk = !$_readonly && (!$af || true);
?>
      <div class="toolbar" style="margin-top:0;padding-top:0;margin-bottom:8px" id="<?= h($p) ?>-toolbar">
      <div class="toolbar-left">
        <?php if ($_addOk): ?>
        <button type="button" class="icon-btn" title="Добавить" id="<?= h($p) ?>-add-btn"><img src="img/add.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($_editOk): ?>
        <button type="button" class="icon-btn" title="Изменить" id="<?= h($p) ?>-edit-btn" disabled><img src="img/edit.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($_delOk): ?>
        <button type="button" class="icon-btn" title="Удалить" id="<?= h($p) ?>-del-btn" disabled><img src="img/delete.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($_copyOk): ?>
        <button type="button" class="icon-btn" title="Копировать" id="<?= h($p) ?>-copy-btn" disabled><img src="img/copy.png" alt="" /></button>
        <?php endif; ?>
        <?php if (!$_readonly): ?>
        <button type="button" class="icon-btn" title="Обновить" id="<?= h($p) ?>-refresh-btn"><img src="img/refresh.png" alt="" /></button>
        <?= $this->toolbarExtraAfterRefresh ?>
        <?php if ($this->barcodeAdd): ?>
        <span class="barcode-add" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px">
          <input type="text" class="barcode-input" id="<?= h($p) ?>-barcode" maxlength="20" placeholder="Штрихкод" style="width:130px;height:24px;padding:0 6px;font-size:13px" />
          <span style="color:var(--muted);font-size:12px">Enter</span>
        </span>
        <?php endif; ?>
        <?php if ($this->hasImport && $_addOk): ?>
        <button type="button" class="icon-btn" title="Импорт отмеченных" id="<?= h($p) ?>-import-btn"><img src="img/import.png" alt="" /></button>
        <?php endif; ?>
        <?php if ($this->hasExport): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Экспорт" id="<?= h($p) ?>-export-btn"><img src="img/export.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-export-all">Экспорт в CSV</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-export-all-xls">Экспорт в Excel</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($this->hasPrint): ?>
        <div class="dropdown">
          <button type="button" class="icon-btn" title="Печать" id="<?= h($p) ?>-print-btn"><img src="img/print.png" alt="" /></button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-print-all">Все записи</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-print-page">Текущая страница</a>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($_actionsOk): ?>
        <div class="dropdown selected-actions" id="<?= h($p) ?>-sel-wrap">
          <button type="button" class="menu-btn" id="<?= h($p) ?>-sel-btn">Выбрано <b><span id="<?= h($p) ?>-sel-count">0</span></b> <span class="btn-caret">&#9660;</span></button>
          <div class="dropdown-menu" style="min-width:180px">
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-clear">Очистить выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-invert">Инвертировать выбор</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-show">Показать выбранные</a>
            <?php if ($_batchDelOk): ?>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-delete">Удалить отмеченные</a>
            <?php endif; ?>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-export">Экспорт выбранных</a>
            <a class="dropdown-item" href="#" id="<?= h($p) ?>-sel-print">Печать выбранных</a>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($this->hasSearch): ?>
      <div class="toolbar-right">
        <div id="<?= h($p) ?>-search-form" style="display:flex;gap:4px;align-items:center;">
          <input class="quick-search" type="text" id="<?= h($p) ?>-search-input" name="q" placeholder="Быстрый поиск" />
          <a class="icon-btn clear-filter-btn" title="Сбросить поиск" id="<?= h($p) ?>-clear-search" style="display:none;text-decoration:none">✕</a>
          <button type="button" class="icon-btn" title="Поиск" id="<?= h($p) ?>-search-btn"><img src="img/find.png" alt="" /></button>
          <button type="button" class="icon-btn" title="Условия поиска" id="<?= h($p) ?>-search-cond-btn"><img src="img/look.png" alt="" /></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="mode-banner" id="<?= h($p) ?>-filter-banner" style="display:none">
      <img src="img/filter.png" alt="" />
      <span id="<?= h($p) ?>-filter-chips"></span>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
    <table class="data-table docum2-table" style="table-layout:fixed;width:100%" id="<?= h($p) ?>-table"
           data-readonly="<?= $_readonly ? '1' : '0' ?>"
           data-items='<?= h(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>'>
      <?php $this->renderColgroup(true, $visibleCols) ?>
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" id="<?= h($p) ?>-check-all" /></th>
          <?php foreach ($visibleCols as $col):
              $cn = $col['name'] ?? $col['key'] ?? '';
              if ($cn === '') continue;
          ?>
            <th class="col-<?= h($cn) ?>"><?= h($col['label'] ?? $cn) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    </div>
    <div id="<?= h($p) ?>-pagination" style="margin-top:8px;text-align:left"></div>
    <?php
        return ob_get_clean();
    }

    public function renderScripts(): void {
        $p = $this->prefix;
        $visibleCols = $this->visibleColumns ?: $this->columns;

        // Build columns for JS: normalize 'name' → 'key', keep all other attributes
        $columnsJs = array_map(function ($c) {
            $out = $c;
            $out['key'] = $c['name'] ?? $c['key'] ?? '';
            unset($out['name']);
            return $out;
        }, $visibleCols);

        $columnsJson = json_encode($columnsJs, JSON_UNESCAPED_UNICODE);
        $searchCols = array_values(array_map(function ($c) {
            return $c['name'] ?? $c['key'] ?? '';
        }, $visibleCols));
        $searchColsJson = json_encode($searchCols, JSON_UNESCAPED_UNICODE);
        $columnLabelsJson = json_encode($this->columnLabels, JSON_UNESCAPED_UNICODE);
        $lookupDataMapJson = json_encode($this->lookupData, JSON_UNESCAPED_UNICODE);

        $totalsCallback = $this->totalsCallback;
        $totalsCallbackName = $totalsCallback ?: 'apply' . ucfirst($p) . 'Totals';
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
      if (window['__<?= $p ?>Table'] && window['__<?= $p ?>Table'].parentId === groupId && window['__<?= $p ?>Table'].tableEl === table) return;
      window['__<?= $p ?>Table'] = null;
      var data = [];
      try { data = JSON.parse(table.dataset.items || '[]'); } catch(e) {}
      var formBody = document.querySelector('.form-modal-body') || document.querySelector('[data-form-modal]') || document.body;

      window['__<?= $p ?>Table'] = EmbeddedSubTable.create({
        tableEl: table,
        formBody: formBody,
        saveUrl: '<?= $this->saveUrl ?>',
        prefix: '<?= $p ?>',
        parentId: groupId,
        parentField: '<?= $this->parentField ?>',
        data: data,
        columns: <?= $columnsJson ?>,
        searchCols: <?= $searchColsJson ?>,
        pageSize: <?= $this->pageSize ?>,
        selWrapEl: '#<?= $p ?>-sel-wrap',
        selCountEl: '#<?= $p ?>-sel-count',
        checkAllEl: '#<?= $p ?>-check-all',
        paginationEl: '#<?= $p ?>-pagination',
        btnAddEl: '#<?= $p ?>-add-btn',
        btnEditEl: '#<?= $p ?>-edit-btn',
        btnCopyEl: '#<?= $p ?>-copy-btn',
        btnDeleteEl: '#<?= $p ?>-del-btn',
        btnRefreshEl: '#<?= $p ?>-refresh-btn',
        childFormUrl: '<?= $this->childFormUrl ?>',
        childFormName: '<?= $this->childFormName ?>',
        totalsCallback: <?= json_encode($totalsCallbackName, JSON_UNESCAPED_UNICODE) ?>,
        readonly: <?= json_encode($this->readonly || ($this->accessFlags && (($this->accessFlags['change_flag'] ?? false) || ($this->accessFlags['save_flag'] ?? false)))) ?> || table.dataset.readonly === '1',
        onRowDoubleClick: <?= ($this->readonly || ($this->accessFlags && ($this->accessFlags['change_flag'] ?? false))) ? 'null' : 'function (id) { window[\'__' . $p . 'Table\'].openChildForm(\'edit\', id); }' ?>,
        filterBannerEl: '#<?= $p ?>-filter-banner',
        filterChipsEl: '#<?= $p ?>-filter-chips',
        clearSearchBtnEl: '#<?= $p ?>-clear-search'
      });

      <?php if (!$this->readonly && !($this->accessFlags && (($this->accessFlags['change_flag'] ?? false) || ($this->accessFlags['save_flag'] ?? false)))): ?>
      if (typeof InlineEdit !== 'undefined') {
        var ieFields = {};
        <?php foreach ($visibleCols as $col):
            $cn = $col['name'] ?? $col['key'] ?? '';
            $colType = (!empty($col['param']) || (($col['type'] ?? '') === 'lookup')) ? 'lookup' : ($col['type'] ?? 'text');
            $dbField = $col['dbField'] ?? $col['param'] ?? $cn;
        ?>
        ieFields['<?= $cn ?>'] = { dbField: '<?= $dbField ?>', type: '<?= $colType ?>', label: <?= json_encode($col['label'] ?? $cn, JSON_UNESCAPED_UNICODE) ?> };
        <?php endforeach; ?>
        InlineEdit.init({
          tbody: table.querySelector('tbody'),
          saveUrl: '<?= $this->saveUrl ?>',
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
            <?= $this->onSaveSuccessExtra ?>
          }
        });
      }
      <?php endif; ?>

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

      <?php if ($this->barcodeAdd): ?>
      var bcEl = formBody.querySelector('#<?= $p ?>-barcode');
      if (bcEl && !bcEl.__bcBound) {
        bcEl.__bcBound = true;
        bcEl.addEventListener('keydown', function (e) {
          if (e.key !== 'Enter' && e.key !== 'Tab') return;
          e.preventDefault();
          var code = bcEl.value.trim();
          if (!code) return;
          var tbl = window['__<?= $p ?>Table'];
          if (!tbl || !tbl.parentId) return;
          var fd = new FormData();
          fd.set('field', '_barcode_add');
          fd.set('<?= $this->parentField ?>', String(tbl.parentId));
          fd.set('value', code);
          fetch('<?= $this->saveUrl ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              bcEl.value = '';
              if (d && d.ok) {
                if (typeof window.<?= $totalsCallbackName ?> === 'function') window.<?= $totalsCallbackName ?>(d);
                if (tbl) tbl.refresh({ focusId: d.id });
              } else {
                alert((d && d.error) || 'Товар не найден');
              }
            });
        });
      }
      <?php endif; ?>

      <?php if ($this->hasImport): ?>
      var importBtn = formBody.querySelector('#<?= $p ?>-import-btn');
      if (importBtn) {
        importBtn.addEventListener('click', function () {
          var url = '<?= $this->saveUrl ?>';
          var fd = new FormData();
          fd.set('field', '_import_marked_count');
          fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!data || !data.ok) return;
              if (data.count <= 0) {
                alert('Нет отмеченных товаров!');
                return;
              }
              if (!confirm('Импортировать ' + data.count + ' товар(ов)?')) return;
              var fd2 = new FormData();
              fd2.set('field', '_import_marked');
              fd2.set('<?= $this->parentField ?>', String(groupId));
              fetch(url, { method: 'POST', body: fd2, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r2) { return r2.json(); })
                .then(function (data2) {
                  if (data2 && data2.ok) {
                    var tbl = window['__<?= $p ?>Table'];
                    if (tbl) tbl.refresh();
                  }
                });
            });
        });
      }
      <?php endif; ?>

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
            columns: <?= json_encode(array_map(function ($c) { return ['key' => $c['name'] ?? $c['key'] ?? '', 'label' => $c['label'] ?? '']; }, $visibleCols), JSON_UNESCAPED_UNICODE) ?>,
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

      var sgLabels = <?= $columnLabelsJson ?>;
      function sgCsvRow(items, allColumns) {
        var cols = allColumns || Object.keys(sgLabels);
        return cols.map(function(k) { var v = items[k]; return v != null ? '"' + String(v).replace(/"/g, '""') + '"' : ''; }).join(';');
      }
      function sgPrintHtml(items, title) {
        var cols = Object.keys(sgLabels);
        var h = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title><style>body{font:14px sans-serif;padding:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 10px;text-align:left}th{background:#eee}</style></head><body><table><thead><tr><th>' + cols.map(function(k) { return sgLabels[k]; }).join('</th><th>') + '</th></tr></thead><tbody>';
        items.forEach(function(item) { h += '<tr><td>' + cols.map(function(k) { return (item[k] != null ? String(item[k]).replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''); }).join('</td><td>') + '</td></tr>'; });
        h += '</tbody></table></body></html>';
        return h;
      }

      <?php if ($this->hasExport && $this->exportUrl): ?>
      var sgGroupId = groupId;
      var sgBaseUrl = '<?= $this->exportUrl ?>?<?= $this->parentParam ?>' + sgGroupId;
      <?php endif; ?>
      <?php if ($this->hasPrint && $this->printUrl): ?>
      var sgPrintUrl = '<?= $this->printUrl ?>?<?= $this->parentParam ?>' + groupId;
      <?php endif; ?>
      <?php if ($this->hasExport): ?>
      var sgExportAll = formBody.querySelector('#<?= $p ?>-export-all');
      if (sgExportAll) sgExportAll.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var items = tbl && tbl.showOnlyChecked ? tbl.data.filter(function(i) { return tbl.checkedIds.has(i.id); }) : (tbl ? tbl.data : []);
        <?php if ($this->exportUrl): ?>
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgBaseUrl + '&format=csv' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        if (items.length === 0) return;
        var csv = '\uFEFF' + sgCsvRow(sgLabels, Object.keys(sgLabels)) + '\n' + items.map(function(i) { return sgCsvRow(i); }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '<?= $p ?>_export.csv'; a.click(); URL.revokeObjectURL(a.href);
        <?php endif; ?>
      });
      var sgExportAllXls = formBody.querySelector('#<?= $p ?>-export-all-xls');
      if (sgExportAllXls) sgExportAllXls.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var items = tbl && tbl.showOnlyChecked ? tbl.data.filter(function(i) { return tbl.checkedIds.has(i.id); }) : (tbl ? tbl.data : []);
        <?php if ($this->exportUrl): ?>
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgBaseUrl + '&format=xls' + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        if (items.length === 0) return;
        var csv = '\uFEFF' + sgCsvRow(sgLabels, Object.keys(sgLabels)) + '\n' + items.map(function(i) { return sgCsvRow(i); }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '<?= $p ?>_export.xls'; a.click(); URL.revokeObjectURL(a.href);
        <?php endif; ?>
      });
      var selExport = formBody.querySelector('#<?= $p ?>-sel-export');
      if (selExport) selExport.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        if (!tbl) return;
        var ids = Array.from(tbl.checkedIds);
        if (ids.length === 0) return;
        var items = tbl.data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        <?php if ($this->exportUrl): ?>
        var url = sgBaseUrl + '&format=csv&ids=' + ids.join(',');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        var csv = '\uFEFF' + sgCsvRow(sgLabels, Object.keys(sgLabels)) + '\n' + items.map(function(i) { return sgCsvRow(i); }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '<?= $p ?>_selected.csv'; a.click(); URL.revokeObjectURL(a.href);
        <?php endif; ?>
      });
      <?php endif; ?>

      <?php if ($this->hasPrint): ?>
      var sgPrintAll = formBody.querySelector('#<?= $p ?>-print-all');
      if (sgPrintAll) sgPrintAll.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var items = tbl && tbl.showOnlyChecked ? tbl.data.filter(function(i) { return tbl.checkedIds.has(i.id); }) : (tbl ? tbl.data : []);
        if (items.length === 0) return;
        <?php if ($this->printUrl): ?>
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgPrintUrl + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        var w = window.open('', '_blank', 'width=800,height=600');
        w.document.write(sgPrintHtml(items, '<?= $p ?>'));
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
        <?php endif; ?>
      });
      var sgPrintPage = formBody.querySelector('#<?= $p ?>-print-page');
      if (sgPrintPage) sgPrintPage.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        var items = tbl && tbl.showOnlyChecked ? tbl.data.filter(function(i) { return tbl.checkedIds.has(i.id); }) : (tbl ? tbl.data : []);
        if (items.length === 0) return;
        <?php if ($this->printUrl): ?>
        var ids = tbl && tbl.showOnlyChecked ? Array.from(tbl.checkedIds) : [];
        var url = sgPrintUrl + '&page=' + (tbl ? tbl.currentPage : 1) + (ids.length > 0 ? '&ids=' + ids.join(',') : '');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        var w = window.open('', '_blank', 'width=800,height=600');
        w.document.write(sgPrintHtml(items, '<?= $p ?>'));
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
        <?php endif; ?>
      });
      var selPrint = formBody.querySelector('#<?= $p ?>-sel-print');
      if (selPrint) selPrint.addEventListener('click', function (e) {
        e.preventDefault();
        var tbl = window['__<?= $p ?>Table'];
        if (!tbl) return;
        var ids = Array.from(tbl.checkedIds);
        if (ids.length === 0) return;
        var items = tbl.data.filter(function(i) { return ids.indexOf(i.id) >= 0; });
        <?php if ($this->printUrl): ?>
        var url = sgPrintUrl + '&ids=' + ids.join(',');
        var q = sgQs(tbl); if (q) url += '&' + q;
        window.open(url, '_blank');
        <?php else: ?>
        var w = window.open('', '_blank', 'width=800,height=600');
        w.document.write(sgPrintHtml(items, '<?= $p ?>'));
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
        <?php endif; ?>
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
          if (pane && pane.querySelector('#<?= $p ?>-table') && window['__<?= $p ?>Table']) {
            var tbl = pane.querySelector('#<?= $p ?>-table');
            if (tbl) tbl.focus();
          }
        });
      });
<?php if ($this->columnResizeUrl): ?>
      if (typeof window.ColumnResize !== 'undefined') {
        window.ColumnResize.init({ saveUrl: '<?= h($this->columnResizeUrl) ?>', tbl: '<?= h($this->columnResizeTbl) ?>', selector: '#<?= $p ?>-table' });
      }
<?php endif; ?>
    }
    <?php
    }
}

} // endif EMBEDDED_TABLE_LOADED
