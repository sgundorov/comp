<?php
if (!defined('TABLE_PAGE_SCRIPTS_LOADED')) {
define('TABLE_PAGE_SCRIPTS_LOADED', true);

function render_table_page_scripts(array $cfg): void {
    $pageUrl        = $cfg['pageUrl'] ?? '';
    $urlSep         = strpos($pageUrl, '?') === false ? '?' : '&';
    $formPrefix     = $cfg['formPrefix'] ?? '';
    $tableKey       = $cfg['tableKey'] ?? '';
    $fieldSaveUrl   = $cfg['fieldSaveUrl'] ?? '';
    $columnsSaveUrl = $cfg['columnsSaveUrl'] ?? '';
    $columnResizeUrl= $cfg['columnResizeUrl'] ?? '';
    $visibleColumns = $cfg['visibleColumns'] ?? [];
    $defaultColumns = $cfg['defaultColumns'] ?? [];
    $searchColumns  = $cfg['searchColumns'] ?? null;
    $sortColumns    = $cfg['sortColumns'] ?? null;
    $exportUrl      = $cfg['exportUrl'] ?? '';
    $printUrl       = $cfg['printUrl'] ?? '';
    $preserveParams = $cfg['preserveParams'] ?? [];
    $onOpenForm     = $cfg['onOpenForm'] ?? 'window.__openFormModal';
    $formModalConfig= $cfg['formModalConfig'] ?? [];
    $extraCode      = $cfg['extraCode'] ?? '';
    $lookupData     = $cfg['lookupData'] ?? [];
    $marksTbl       = $cfg['marksTbl'] ?? '';
    $searchPanelConfig = $cfg['searchPanelConfig'] ?? [];
    $colFilters     = $cfg['colFilters'] ?? [];
    $accessFlags    = $cfg['accessFlags'] ?? null;

    if (!$searchColumns) {
        $searchColumns = array_values(array_map(fn($c) => ['key' => $c['name'], 'label' => $c['label']], array_filter($visibleColumns, fn($c) => !empty($c['search']))));
    }
    if (!$sortColumns) {
        $sortColumns = array_values(array_map(fn($c) => ['key' => $c['name'], 'label' => $c['label']], array_filter($visibleColumns, fn($c) => !empty($c['search']))));
    }

    $searchColumnsJson  = json_encode($searchColumns, JSON_UNESCAPED_UNICODE);
    $sortColumnsJson    = json_encode($sortColumns, JSON_UNESCAPED_UNICODE);
    $preserveParamsJson = json_encode($preserveParams, JSON_UNESCAPED_UNICODE);

    $initialColumnsJson = json_encode(array_map(fn($c) => [
        'name' => $c['name'], 'label' => $c['label'], 'visible' => !empty($c['visible'])
    ], $visibleColumns), JSON_UNESCAPED_UNICODE);
    $defaultColumnsJson = json_encode(array_map(fn($c) => [
        'name' => $c['name'], 'label' => $c['label'], 'visible' => ($c['visible'] ?? 1) ? true : false
    ], $defaultColumns), JSON_UNESCAPED_UNICODE);

    $inlineFields = [];
    $valCases = '';
    foreach ($visibleColumns as $vc) {
        $cn = $vc['name'];
        if (!empty($vc['readonly']) || $cn === 'id') continue;
        $isLookup = !empty($vc['param']);
        $inlineFields[$cn] = [
            'dbField' => $isLookup ? $vc['param'] : $cn,
            'type'    => $isLookup ? 'lookup' : 'text',
            'label'   => $vc['label'],
        ];
        if ($isLookup) {
            $cnEnc = json_encode($cn, JSON_UNESCAPED_UNICODE);
            $valCases .= "    case $cnEnc: if (parseInt(value,10)<=0) return 'Выберите значение из списка'; break;\n";
        }
    }
    $inlineFieldsJson = json_encode($inlineFields, JSON_UNESCAPED_UNICODE);
    ?>
    <script>
    function closeAllPanels() {
      document.querySelectorAll('.search-cond-panel.open, .search-cond-pop.open, .columns-panel.open, .col-filter-panel.open').forEach(function (p) { p.classList.remove('open'); if (p.style) p.style.display = ''; });
    }
    window.__accessFlags = <?= $accessFlags ? json_encode($accessFlags) : 'null' ?>;
    if (window.__accessFlags && typeof applyAccessFlags === 'function') applyAccessFlags(window.__accessFlags);
    window.__focusAfterSave = function (params, form, data) {
      var mode = (form.querySelector('input[name="mode"]') || {}).value || '';
      alert('__focusAfterSave: mode=' + mode + ', data=' + JSON.stringify(data));
      if (mode === 'new' || mode === 'copy') {
        if (data.id) {
          params.set('focus', String(data.id));
          if ('page' in data) {
            params.set('page', String(data.page));
            alert('Устанавливаем page=' + data.page);
          } else {
            alert('page НЕ передан в data!');
          }
          alert('params после: ' + params.toString());
        } else {
          params.delete('focus');
        }
      } else if (mode === 'edit') {
        var eid = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
        if (eid > 0) params.set('focus', String(eid)); else params.delete('focus');
      } else if (mode === 'delete') {
        var did = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
        var rows = Array.from(document.querySelectorAll('tr[data-row-id]'));
        var idx = rows.findIndex(function (tr) { return parseInt(tr.dataset.rowId, 10) === did; });
        var tb = (document.querySelector('.toolbar') || {}).dataset || {};
        var cp = parseInt(tb.page || '1', 10), tp = parseInt(tb.pages || '1', 10);
        if (idx >= 0) {
          if (idx + 1 < rows.length) { params.set('focus', String(parseInt(rows[idx + 1].dataset.rowId, 10))); }
          else if (cp < tp) { params.set('page', String(cp + 1)); params.set('focus', 'first'); }
          else if (idx - 1 >= 0) { params.set('focus', String(parseInt(rows[idx - 1].dataset.rowId, 10))); }
          else if (cp > 1) { params.set('page', String(cp - 1)); params.delete('focus'); }
          else { params.delete('focus'); }
        } else { params.delete('focus'); }
      } else { params.delete('focus'); }
    };
    (function () {
      const checkAll  = document.getElementById('checkAll');
      const rowChecks = document.querySelectorAll('.row-check');
      const selWrap   = document.getElementById('selectedActions');
      const selCount  = document.getElementById('selectedCount');
      const toolbar   = document.querySelector('.toolbar');
      const search    = toolbar.getAttribute('data-search') || '';
      const markedSet = new Set(Array.from(rowChecks).filter(cb => cb.checked).map(cb => parseInt(cb.value || cb.dataset.id, 10)));
      let globalCount = parseInt(toolbar.getAttribute('data-marks-count') || '0', 10);

      document.querySelectorAll('.submenu a').forEach(function (a) {
        a.addEventListener('click', function () {
          var item = a.closest('.menu-item');
          if (item) item.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
        });
      });

      function refreshCounter() {
        selCount.textContent = 'Выбрано: ' + globalCount;
        selWrap.classList.toggle('visible', globalCount > 0);
        const rowTotal = rowChecks.length;
        let rowOn = 0;
        rowChecks.forEach(cb => { if (cb.checked) rowOn++; });
        if (rowTotal === 0) {
          checkAll.checked = false;
          checkAll.indeterminate = false;
        } else {
          checkAll.checked = rowOn === rowTotal;
          checkAll.indeterminate = rowOn > 0 && rowOn < rowTotal;
        }
      }

      window.__marksUrl = function (action) {
        var _u = new URLSearchParams(location.search);
        _u.set('action', action);
        if (search) _u.set('q', search);
        return '<?= h($pageUrl) ?>?' + _u.toString();
      };

      checkAll.addEventListener('change', function () {
        location.href = window.__marksUrl('toggleSelectAll');
      });

      rowChecks.forEach(cb => cb.addEventListener('change', function () {
        const id  = parseInt(cb.value || cb.dataset.id, 10);
        const to  = cb.checked;
        cb.disabled = true;
        fetch(window.__marksUrl('toggleSelect'), {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'id=' + encodeURIComponent(id) + '&to=' + (to ? '1' : '0')
        })
        .then(r => r.json())
        .then(function (j) {
          cb.disabled = false;
          if (j.ok) {
            if (to) markedSet.add(id); else markedSet.delete(id);
            globalCount = (typeof j.count === 'number') ? j.count : globalCount;
            refreshCounter();
          }
        })
        .catch(function () { cb.disabled = false; });
      }));

      SelectionToolbar.init({
        pageUrl: '<?= h($pageUrl) ?>',
        search: search,
        getExportUrl: function () {
          if (markedSet.size === 0) return null;
          return '<?= h($exportUrl) ?>?format=csv&all=1';
        },
        getPrintUrl: function () {
          if (markedSet.size === 0) return null;
          return '<?= h($printUrl) ?>?all=1';
        },
<?php if ($marksTbl): ?>
        getInvertUrl: function () {
          var p = new URLSearchParams(location.search);
          p.delete('ids');
          return '<?= h($pageUrl) ?>?action=invertSelection&' + p.toString();
        }
<?php endif; ?>
      });

<?php
$searchPanelExtra = '';
$searchPanelKeys = ['popupCheckboxes', 'emptyClass', 'labels', 'onApply', 'onToggle', 'onSubmit'];
foreach ($searchPanelKeys as $k) {
    if (isset($searchPanelConfig[$k])) {
        $searchPanelExtra .= ',' . "\n        " . json_encode($k, JSON_UNESCAPED_UNICODE) . ': ' . json_encode($searchPanelConfig[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
?>
      SearchPanel.init({
        form: document.getElementById('searchForm'),
        condBtn: document.getElementById('searchCondBtn'),
        toggleBtn: document.getElementById('searchToggleBtn'),
        columns: <?= $searchColumnsJson ?>,
        closeAllPanels: closeAllPanels,
        pageUrl: '<?= h($pageUrl) ?>',
        preserveParams: <?= $preserveParamsJson ?><?= $searchPanelExtra ?>
      });

      SortPanel.init({
        btn: document.getElementById('sortBtn'),
        columns: <?= $sortColumnsJson ?>,
        pageUrl: '<?= h($pageUrl) ?>',
        mode: 'modal',
        directions: [
          { key: 'asc',  label: 'По возрастанию' },
          { key: 'desc', label: 'По убыванию' },
        ],
      });
    })();

    ExportModal.init();
    </script>
<?php if ($marksTbl): ?>
    <script>
    window.clearSelection = function () {
      fetch(window.__marksUrl('clearSelection'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    window.invertSelection = function () {
      fetch(window.__marksUrl('invertSelection'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    window.toggleShowOnly = function () {
      fetch(window.__marksUrl('toggleShowOnly'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    };
    </script>
<?php endif; ?>
<?php if (!empty($colFilters)): ?>
    <script>
<?php foreach ($colFilters as $cf): ?>
    ColumnFilter.init(<?= json_encode($cf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
<?php endforeach; ?>
    </script>
<?php endif; ?>
    <?php render_form_modal_script(array_merge([
        'form_prefix' => $formPrefix,
        'base_url'    => $pageUrl,
    ], $formModalConfig)); ?>
    <script>
    ColumnsPanel.init({
      btn: document.getElementById('columnsBtn'),
      saveUrl: '<?= h($columnsSaveUrl) ?>',
      tbl: '<?= h($tableKey) ?>',
      closeAllPanels: closeAllPanels,
      initialColumns: <?= $initialColumnsJson ?>,
      defaultColumns: <?= $defaultColumnsJson ?>
    });

    (function () {
      const toolbar = document.querySelector('.toolbar');
      if (!toolbar) return;
      const tbody = document.querySelector('table tbody');
      const currentPage  = parseInt(toolbar.dataset.page  || '1', 10);
      const currentPages = parseInt(toolbar.dataset.pages || '1', 10);

      const openBtn   = document.getElementById('rowOpenBtn');
      const copyBtn   = document.getElementById('rowCopyBtn');
      const deleteBtn = document.getElementById('rowDeleteBtn');
      const tableWrapEl = document.querySelector('.table-wrap');

      const rowSel = RowSelect.init({
        tbody: tbody,
        rowClass: 'selected',
        onChange: function (id) {
          const enabled = id > 0;
          const af = window.__accessFlags || {};
          if (openBtn)   openBtn.disabled   = !enabled || !!af.change_flag;
          if (copyBtn)   copyBtn.disabled   = !enabled || !!af.insert_flag;
          if (deleteBtn) deleteBtn.disabled = !enabled || !!af.delete_flag;
        },
        currentPage: currentPage,
        totalPages: currentPages,
        navigate: navigate
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
          if (window.__accessFlags && window.__accessFlags.change_flag) return;
          const tr = e.target.closest('tr[data-row-id]');
          if (!tr) return;
          const id = parseInt(tr.dataset.rowId, 10) || 0;
          if (!id) return;
          rowSel.selectById(id, true);
          if (typeof <?= $onOpenForm ?> === 'function') {
            <?= $onOpenForm ?>('<?= h($formPrefix) ?>.php?mode=edit&id=' + id);
          }
        });
      }

      function openForm(mode) {
        const id = rowSel.getSelectedId();
        if (!id) return;
        if (typeof <?= $onOpenForm ?> === 'function') {
          <?= $onOpenForm ?>('<?= h($formPrefix) ?>.php?mode=' + mode + '&id=' + id);
        }
      }
      if (openBtn)   openBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('edit'); });
      if (copyBtn)   copyBtn  .addEventListener('click', function (e) { e.stopPropagation(); openForm('copy'); });
      if (deleteBtn) deleteBtn.addEventListener('click', function (e) { e.stopPropagation(); openForm('delete'); });

      function navigate(apply) {
        const p = new URLSearchParams(location.search);
        apply(p);
        location.href = '<?= h($pageUrl) ?><?= $urlSep ?>' + p.toString();
      }

      bindTableKeyboardShortcuts({
        formPrefix: '<?= h($formPrefix) ?>',
        rowSel: rowSel,
        currentPage: currentPage,
        currentPages: currentPages,
        navigate: navigate,
        tableWrapEl: tableWrapEl,
        onOpenForm: <?= $onOpenForm ?>,
        accessFlags: window.__accessFlags
      });
    })();

    (function () {
      if (window.__accessFlags && (window.__accessFlags.save_flag || window.__accessFlags.change_flag)) return;
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;

      InlineEdit.init({
        tbody: tbody,
        saveUrl: '<?= h($fieldSaveUrl) ?>',
        fields: <?= $inlineFieldsJson ?>,
        getLookupData: function (field) {
<?php if (!empty($lookupData)): ?>
          var map = <?= json_encode($lookupData, JSON_UNESCAPED_UNICODE) ?>;
          return map[field] || [];
<?php else: ?>
          return [];
<?php endif; ?>
        },
        validate: function (field, value) {
          switch (field) {
<?= $valCases ?>
          }
          return null;
        },
        onOpenForm: <?= $onOpenForm ?>
      });
    })();

<?php if ($columnResizeUrl): ?>
    ColumnResize.init({ saveUrl: '<?= h($columnResizeUrl) ?>', tbl: '<?= h($tableKey) ?>', selector: 'table.data-table' });
<?php endif; ?>
    </script>
    <?php
    if ($extraCode) {
        echo '<script>' . $extraCode . '</script>';
    }
}

} // endif TABLE_PAGE_SCRIPTS_LOADED
