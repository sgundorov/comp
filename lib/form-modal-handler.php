<?php
if (defined('FORM_MODAL_HANDLER_LOADED')) return;
define('FORM_MODAL_HANDLER_LOADED', true);

/**
 * Выводит <script> с JS-обработчиком модального окна формы.
 *
 * @param array $config:
 *   'form_prefix'      => 'city_form'              — префикс файла формы
 *   'base_url'         => 'city.php'               — URL страницы для редиректа
 *   'form_action'      => 'city_form.php'           — action формы (по умолч. $form_prefix . '.php')
 *   'lookup_tables'    => ['country']              — таблицы для bindLookup при restore
 *   'lookup_data'      => ['country' => 'window.__countries']
 *   'redirect_cleanup' => []                       — параметры URL, удаляемые после сохранения
 *   'extra_restore'    => ''                       — доп. JS-код внутри restoreStashedForm
 *   'extra_open'       => ''                       — доп. JS-код после загрузки формы
 *   'on_save_redirect_extra' => ''
 *   'autoInitTables'   => ['d2']                   — префиксы встроенных таблиц для авто-инита
 *   'autoBindLookups'  => true                     — авто-бinding [data-lookup] элементов
 *   'autoSetupTabs'    => true                     — авто-назначение кликов по вкладкам
 */
function render_form_modal_script(array $config = []): void {
    $formPrefix      = $config['form_prefix'] ?? 'city_form';
    $baseUrl         = $config['base_url'] ?? 'city.php';
    $formAction      = $config['form_action'] ?? $formPrefix . '.php';
    $lookupTables    = $config['lookup_tables'] ?? [];
    $lookupData      = $config['lookup_data'] ?? [];
    $cleanup         = $config['redirect_cleanup'] ?? [];
    $extraRestore    = $config['extra_restore'] ?? '';
    $extraOpen       = $config['extra_open'] ?? '';
    $onSaveExtra     = $config['on_save_redirect_extra'] ?? '';
    $autoInitTables  = $config['autoInitTables'] ?? [];
    $autoBindLookups = $config['autoBindLookups'] ?? true;
    $autoSetupTabs   = $config['autoSetupTabs'] ?? true;
    $urlSep          = strpos($baseUrl, '?') === false ? '?' : '&';
    $cleanupJs       = json_encode($cleanup);
    $lookupTablesJs  = json_encode($lookupTables);
    $formActionJs    = json_encode($formAction, JSON_UNESCAPED_SLASHES);
    $baseUrlJs       = json_encode($baseUrl, JSON_UNESCAPED_SLASHES);
    $formPrefixJs    = json_encode($formPrefix);

    // Build auto-init JS snippet
    $autoInitChunks = [];
    if ($autoBindLookups) {
        $autoInitChunks[] = "
            root.querySelectorAll('[data-lookup]:not([data-lookup-bound])').forEach(function(el){
                el.setAttribute('data-lookup-bound','1');
                if(!el.__lookupBound){
                    try{var _d=JSON.parse(el.getAttribute('data-countries')||'[]');
                    var _ro=el.hasAttribute('data-readonly');
                    window.bindLookup({root:el,data:_d,readonly:_ro});}catch(ex){}
                }
            });";
    }
    if ($autoSetupTabs) {
        $autoInitChunks[] = "
            var _tc=root.querySelector('.tab-container');
            if(_tc&&!_tc.__fmTabs){
                _tc.__fmTabs=true;
                var _hds=_tc.querySelectorAll('.tab-header'),_pns=_tc.querySelectorAll('.tab-pane');
                _hds.forEach(function(h){
                    h.addEventListener('click',function(){
                        var _i=parseInt(h.dataset.tabIndex,10);
                        _hds.forEach(function(t){t.classList.remove('active');});
                        _pns.forEach(function(p){p.classList.remove('active');});
                        h.classList.add('active');
                        var _p=_tc.querySelector('.tab-pane[data-tab-index=\"'+_i+'\"]');
                        if(_p)_p.classList.add('active');
                    });
                });
            }";
    }
    foreach ($autoInitTables as $p) {
        $fn = 'init' . ucfirst($p) . 'Table';
        $autoInitChunks[] = "if(typeof $fn==='function')$fn();";
    }
    $autoInitJs = empty($autoInitChunks) ? '' : implode("\n", $autoInitChunks);
    ?>
    <script>
    (function () {
      var backdrop = document.getElementById('formModal');
      var body     = document.getElementById('formModalBody');
      if (!backdrop || !body) return;

      var stashed = null;
      var _fmDirty = false;

      function autoInitFormBody(root) {
        if (typeof root === 'undefined') root = body;
        root.querySelectorAll('script:not([src])').forEach(function(s){
          try{ eval(s.textContent); }catch(ex){ console.error('[FM] script error', ex); }
        });
        <?= $autoInitJs ?>
        try {
          if (typeof window.ColumnResize !== 'undefined') {
            root.querySelectorAll('.data-table').forEach(function(t) {
              t.querySelectorAll('.col-resize-handle').forEach(function(h) { h.remove(); });
              t.removeAttribute('data-col-resize-inited');
              var opts = { selector: '#' + t.id };
              if (t.dataset.colResizeUrl) opts.saveUrl = t.dataset.colResizeUrl;
              if (t.dataset.colResizeTbl) opts.tbl = t.dataset.colResizeTbl;
              window.ColumnResize.init(opts);
            });
          }
        } catch(ex) { console.error('[FM] ColumnResize re-init error', ex); }
      }

      function stashCurrentForm(onRestore, estSelectedId) {
        var form = body.querySelector('form[data-form-modal]');
        if (!form) return;
        var active = document.activeElement;
        body.querySelectorAll('input, textarea, select').forEach(function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (el.checked) el.setAttribute('checked', ''); else el.removeAttribute('checked');
          } else if (el.tagName === 'SELECT') {
            Array.from(el.options).forEach(function (o) { o.removeAttribute('selected'); });
            var sel = el.options[el.selectedIndex];
            if (sel) sel.setAttribute('selected', '');
          } else {
            el.setAttribute('value', el.value);
          }
        });
        var savedTableSelections = {};
        Object.keys(window).forEach(function(k) {
          if (k.indexOf('__') === 0 && k.indexOf('Table') === k.length - 5 && window[k] && typeof window[k].selectedId === 'number') {
            savedTableSelections[k] = window[k].selectedId;
          }
        });
        if (estSelectedId && !Object.keys(savedTableSelections).length) {
          savedTableSelections['__estFallback'] = estSelectedId;
        }
        stashed = {
          html: body.innerHTML,
          onRestore: onRestore || function () {},
          activeId: active && active.id ? active.id : null,
          _tableSelections: savedTableSelections
        };
      }

      function restoreStashedForm(data) {
        if (!stashed) return false;
        var savedTableSelections = stashed._tableSelections || {};
        body.innerHTML = stashed.html;
        var old = stashed;
        stashed = null;
        var form = body.querySelector('form[data-form-modal]');
        bindForm(form);
        <?php foreach ($lookupTables as $t): ?>
        body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
        <?php endforeach; ?>
            FormModalCore.bindFormTabTrap(form);
            <?= $extraRestore ?>
            try { <?= $extraOpen ?> } catch(ex) { console.error('[FM] extraOpen error', ex); }
        autoInitFormBody(body);
        if (savedTableSelections && Object.keys(savedTableSelections).length) {
          var restoreFn = function() {
            Object.keys(savedTableSelections).forEach(function(k) {
              if (k === '__estFallback') return;
              var tbl = window[k];
              if (tbl && typeof tbl.selectedId === 'number' && savedTableSelections[k]) {
                var id = savedTableSelections[k];
                var found = false;
                if (tbl.data) { for (var i = 0; i < tbl.data.length; i++) { if (tbl.data[i].id == id) { found = true; break; } } }
                if (found) {
                  tbl.selectedId = id;
                  tbl.render();
                  if (tbl.tbody) {
                    var row = tbl.tbody.querySelector('tr[data-id="' + id + '"]');
                    if (row) row.scrollIntoView({ block: 'nearest' });
                  }
                }
              }
            });
            if (savedTableSelections['__estFallback']) {
              var fallbackId = savedTableSelections['__estFallback'];
              Object.keys(window).forEach(function(k) {
                if (k.indexOf('__') === 0 && k.indexOf('Table') === k.length - 5 && window[k] && typeof window[k].selectedId === 'number' && window[k].data) {
                  var tbl = window[k];
                  var found = false;
                  for (var i = 0; i < tbl.data.length; i++) { if (tbl.data[i].id == fallbackId) { found = true; break; } }
                  if (found) {
                    tbl.selectedId = fallbackId;
                    tbl.render();
                    if (tbl.tbody) {
                      var row = tbl.tbody.querySelector('tr[data-id="' + fallbackId + '"]');
                      if (row) row.scrollIntoView({ block: 'nearest' });
                    }
                  }
                }
              });
            }
          };
          setTimeout(restoreFn, 50);
          setTimeout(restoreFn, 200);
        }
        if (data) { try { old.onRestore(data, body); } catch (e) { console.error('[FM] onRestore error', e); } }
        if (old.activeId) {
          var el = body.querySelector('#' + old.activeId);
          if (el && !el.readOnly) { el.focus(); if (el.select) el.select(); }
        } else {
          FormModalCore.focusFirstField(body);
        }
        return true;
      }

      function openFormModal(url, stash) {
        stashCurrentForm(stash && stash.onRestore, stash && stash._estSelectedId);
        body.innerHTML = '<div style="padding:20px;color:var(--muted);">Загрузка…</div>';
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        fetch(FormModalCore.appendAjax(url), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function (data) {
            if (!data || typeof data.html !== 'string') throw new Error('bad response');
            body.innerHTML = data.html;
            var form = body.querySelector('form[data-form-modal]');
            bindForm(form);
            <?php foreach ($lookupTables as $t): ?>
            body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
            <?php endforeach; ?>
            FormModalCore.bindFormTabTrap(form);
            FormModalCore.focusFirstField(body);
            <?= $extraOpen ?>
            autoInitFormBody(body);
          })
          .catch(function (err) {
            body.innerHTML = '<div class="flash flash--error">Ошибка загрузки: ' + (err && err.message ? err.message : 'неизвестная ошибка') + '</div>';
            if (console && console.error) console.error('[form-modal]', err);
          });
      }

      function closeFormModal() {
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        body.innerHTML = '';
        stashed = null;
      }

      function bindForm(form) {
        if (!form) return;
        if (window.__accessFlags && window.__accessFlags.save_flag) {
          form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
        }
        form.addEventListener('submit', function (e) {
          if (window.__accessFlags && window.__accessFlags.save_flag) return;
          e.preventDefault();
          var fd = new FormData(form);
          var submitBtn = e.submitter || form.querySelector('button[type="submit"]');
          if (submitBtn && submitBtn.name) fd.set(submitBtn.name, submitBtn.value || '1');
          fetch(form.getAttribute('action') || <?= $formActionJs ?>, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          })
          .then(function (r) { return r.json(); })
           .then(function (data) { if (data && data.ok) {
              _fmDirty = true;
              if (stashed) {
                restoreStashedForm(data);
              } else if (fd.get('action') === 'apply') {
                body.innerHTML = data.html;
                var f = body.querySelector('form[data-form-modal]');
                bindForm(f);
                <?php foreach ($lookupTables as $t): ?>
                body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
                <?php endforeach; ?>
                FormModalCore.bindFormTabTrap(f);
                <?= $extraOpen ?>
                autoInitFormBody(body);
                if (data && data.focusField) { var el = body.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } }
                else { FormModalCore.focusFirstField(body); }
              } else {
                closeFormModal();
                var params = new URLSearchParams(location.search);
                <?php if (!empty($cleanup)): ?>
                <?= json_encode($cleanup) ?>.forEach(function (k) { params.delete(k); });
                <?php endif; ?>
                if (typeof window.__focusAfterSave === 'function') window.__focusAfterSave(params, form, data); else FormModalCore.setFocusAfterSave(params, form, data);
                <?= $onSaveExtra ?>
                location.href = <?= $baseUrlJs ?> + '<?= $urlSep ?>' + params.toString();
              }
            } else {
              body.innerHTML = (data && data.html) || '<div class="flash flash--error">Ошибка подключения к БД</div>';
              var f = body.querySelector('form[data-form-modal]');
              bindForm(f);
              <?php foreach ($lookupTables as $t): ?>
              body.querySelectorAll('[data-lookup="<?= $t ?>"]').forEach(function (el) { bindLookup(el, <?= json_encode($t) ?>); });
              <?php endforeach; ?>
              FormModalCore.bindFormTabTrap(f);
              <?= $extraOpen ?>
              autoInitFormBody(body);
              if (data && data.focusField) { var el = body.querySelector('[name="' + data.focusField + '"]'); if (el) { el.focus(); if (el.select) el.select(); } }
              else { FormModalCore.focusFirstField(body); }
            }
          })
          .catch(function (err) {
            var flash = document.createElement('div');
            flash.className = 'flash flash--error';
            flash.textContent = 'Ошибка подключения к БД: ' + (err && err.message ? err.message : 'unknown');
            form.insertBefore(flash, form.firstChild);
          });
        });
      }

      function bindLookup(root, tableName) {
        if (!root) return;
        <?php if (!empty($lookupData)): ?>
        var dataVar = <?= json_encode($lookupData) ?>;
        var list = window[dataVar[tableName]] || [];
        <?php else: ?>
        var list = JSON.parse(root.getAttribute('data-countries') || '[]');
        <?php endif; ?>
        var isReadonly = root.hasAttribute('data-readonly');
        window.bindLookup({
          root: root,
          data: list,
          readonly: isReadonly
        });
      }

      function closeOrReload() {
        if (_fmDirty) {
          _fmDirty = false;
          var params = new URLSearchParams(location.search);
          var idEl = body.querySelector('input[name="id"]');
          var focusId = idEl ? parseInt(idEl.value, 10) : 0;
          if (focusId > 0) params.set('focus', String(focusId));
          closeFormModal();
          location.href = <?= $baseUrlJs ?> + '<?= $urlSep ?>' + params.toString();
        } else {
          closeFormModal();
        }
      }

      document.addEventListener('click', function (e) {
        if (backdrop.classList.contains('open')) {
          var cancelA = e.target.closest('a.btn-secondary');
          if (cancelA && cancelA.closest('.form-actions')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (stashed) { restoreStashedForm(null); } else { closeOrReload(); }
            return;
          }
        }
        if (backdrop.classList.contains('open')) {
          var addLink = e.target.closest('a[data-lookup-add]');
          if (addLink) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var target = addLink.getAttribute('data-lookup-add');
            if (!target) return;
            openFormModal(addLink.getAttribute('href') || (target + '_form.php?mode=new'), {
              onRestore: function (data, bodyEl) {
                if (data && data.id && data.name) {
                  var container = bodyEl.querySelector('[data-lookup="' + target + '"]');
                  if (!container) return;
                  var idEl   = container.querySelector('[data-lookup-id]');
                  var nameEl = container.querySelector('.lookup-input');
                  if (idEl)   idEl.value   = String(data.id);
                  if (nameEl) nameEl.value = data.name;
                  var items = [];
                  try { items = JSON.parse(container.getAttribute('data-countries') || '[]'); } catch (e) {}
                  if (!items.find(function (c) { return c.id === data.id; })) {
                    items.push({ id: data.id, name: data.name });
                    items.sort(function (a, b) { return a.name.localeCompare(b.name, 'ru'); });
                    container.setAttribute('data-countries', JSON.stringify(items));
                  }
                }
              }
            });
            return;
          }
        }
        var a = e.target.closest('a[href*="<?= $formPrefix ?>.php"]');
        if (a) {
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
          e.preventDefault();
          e.stopImmediatePropagation();
          openFormModal(a.getAttribute('href'));
          return;
        }
        var b = e.target.closest('button[data-form-open], button[onclick*="<?= $formPrefix ?>.php"]');
        if (b) {
          var url = b.getAttribute('data-form-open');
          if (!url && b.getAttribute('onclick')) {
            var m = b.getAttribute('onclick').match(/['"]([^'"]*<?= $formPrefix ?>\.php[^'"]*)['"]/);
            if (m) url = m[1];
          }
          if (url) {
            e.preventDefault();
            e.stopImmediatePropagation();
            openFormModal(url);
          }
        }
      }, true);

      backdrop.addEventListener('click', function (e) {
        if (e.target.closest('[data-form-close]')) {
          if (stashed) restoreStashedForm(null); else { closeOrReload(); }
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!backdrop.classList.contains('open')) return;
        var popOpen = body.querySelector('.lookup-pop.open');
        if (popOpen) return;
        e.preventDefault();
        if (stashed) restoreStashedForm(null); else { closeOrReload(); }
      });

      // Enter в открытой модалке = нажать основную submit-кнопку формы
      // (в т.ч. "Удалить"), даже если фокус не внутри формы (readonly-поля).
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return;
        if (!backdrop.classList.contains('open')) return;
        if (e.defaultPrevented) return;
        var t = e.target;
        if (!t) return;
        if (t.tagName === 'TEXTAREA' || t.tagName === 'SELECT') return;
        if (t.closest && t.closest('.lookup-pop, .col-filter-panel, .search-cond-panel, .columns-panel')) return;
        if (t.closest && t.closest('table')) return;
        if (t.closest && t.closest('[data-form-close], [data-lookup-add], [type="reset"], .lookup-tool, .col-filter-btn, .lookup-tool')) return;
        var form = body.querySelector('form[data-form-modal]');
        if (!form) return;
        var submitBtn = form.querySelector('button[type="submit"]:not([disabled])');
        if (!submitBtn) return;
        e.preventDefault();
        submitBtn.click();
      });

      window.__openFormModal = openFormModal;
    })();
    </script>
    <?php
}
