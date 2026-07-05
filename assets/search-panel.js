(function () {
  'use strict';

  window.SearchPanel = {
    init: function (config) {
      if (!config || !config.form || !config.condBtn || !config.toggleBtn || !config.columns || !config.pageUrl) return;

      var columns = config.columns;
      var conditions = config.conditions || [
        { key: 'contains',     label: 'Содержит' },
        { key: 'not_contains', label: 'Не содержит' },
        { key: 'starts_with',  label: 'Начинается с' },
        { key: 'ends_with',    label: 'Заканчивается на' },
        { key: 'equals',       label: 'Равно' },
        { key: 'not_equals',   label: 'Не равно' },
        { key: 'gt',           label: 'Больше' },
        { key: 'lt',           label: 'Меньше' },
      ];
      var pageUrl = config.pageUrl;
      var labels = config.labels || {};
      var colsLabel = labels.cols || 'Столбцы';
      var condLabel = labels.cond || 'Условие поиска';
      var emptyText = labels.emptyCols || 'Все';
      var preserveParams = config.preserveParams || [];
      var closeAllPanels = config.closeAllPanels;

      var COL_LABEL = {};
      columns.forEach(function (c) { COL_LABEL[c.key] = c.label; });
      var COND_LABEL = {};
      conditions.forEach(function (c) { COND_LABEL[c.key] = c.label; });

      var form = config.form;
      var condBtn = config.condBtn;
      var toggleBtn = config.toggleBtn;

      var initialCols = (form.querySelector('input[name="cols"]') || { value: '' }).value
        .split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      var initialCond = (form.querySelector('input[name="cond"]') || { value: 'contains' }).value || 'contains';
      var validCond = conditions.some(function (c) { return c.key === initialCond; });
      var state = {
        cols: new Set(initialCols.length > 0 ? initialCols : columns.map(function (c) { return c.key; })),
        cond: validCond ? initialCond : 'contains',
      };

      var panel = document.createElement('div');
      panel.className = 'search-cond-panel';
      panel.innerHTML =
        '<div class="search-cond-header"><span>Условия поиска</span><button type="button" class="search-cond-close" data-cond-close aria-label="Закрыть">✕</button></div>' +
        '<div class="search-cond-body">' +
          '<div class="search-cond-row search-cond-row--cols">' +
            '<span class="search-cond-label">' + colsLabel + '</span>' +
            '<div class="search-cond-row-body">' +
              '<div class="search-cond-control" id="scCols" tabindex="0"></div>' +
              '<button type="button" class="search-cond-colsclear" title="Очистить все столбцы">✕</button>' +
            '</div>' +
          '</div>' +
          '<div class="search-cond-row">' +
            '<span class="search-cond-label">' + condLabel + '</span>' +
            '<div class="search-cond-control" id="scCond" tabindex="0"></div>' +
          '</div>' +
          '<div class="search-cond-actions">' +
             '<button type="button" class="search-cond-apply"><img src="img/ok.png" alt="" />Применить</button>' +
             '<button type="button" class="search-cond-cancel"><img src="img/cancel.png" alt="" />Отменить</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(panel);

      var colsEl = panel.querySelector('#scCols');
      var condEl = panel.querySelector('#scCond');
      var cancelBtn = panel.querySelector('.search-cond-cancel');
      var applyBtn = panel.querySelector('.search-cond-apply');

      var pop = document.createElement('div');
      pop.className = 'search-cond-pop';
      document.body.appendChild(pop);
      pop.addEventListener('click', function (e) { e.stopPropagation(); });

      function renderCols() {
        colsEl.innerHTML = '';
        if (state.cols.size === 0) {
          var e = document.createElement('span');
          e.className = config.emptyClass || 'search-cond-empty';
          e.textContent = emptyText;
          colsEl.appendChild(e);
          return;
        }
        var self = this;
        state.cols.forEach(function (k) {
          var chip = document.createElement('span');
          chip.className = 'search-cond-chip';
          var removeAttr = config.popupCheckboxes ? ' data-k="' + k + '"' : '';
          chip.innerHTML = '<span>' + (COL_LABEL[k] || k) + '</span>' +
            '<button type="button" class="search-cond-chip-remove"' + removeAttr + ' title="Убрать">✕</button>';
          if (config.popupCheckboxes) {
            chip.querySelector('.search-cond-chip-remove').addEventListener('click', function (e) {
              e.stopPropagation();
              state.cols.delete(k);
              renderCols();
            });
          } else {
            chip.querySelector('.search-cond-chip-remove').addEventListener('click', function (e) {
              e.stopPropagation();
              state.cols.delete(k);
              renderCols();
            });
          }
          colsEl.appendChild(chip);
        });
      }

      function renderCond() {
        condEl.innerHTML = '';
        var t = document.createElement('span');
        t.textContent = COND_LABEL[state.cond] || state.cond;
        condEl.appendChild(t);
      }

      function positionPop(target, p) {
        var r = target.getBoundingClientRect();
        var pw = p.offsetWidth;
        var ph = p.offsetHeight;
        var left = r.left;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
        var top = r.bottom + 4;
        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
        p.style.left = left + 'px';
        p.style.top  = top + 'px';
      }

      function openColsPop() {
        pop.innerHTML = '';
        columns.forEach(function (c) {
          if (config.popupCheckboxes) {
            var item = document.createElement('label');
            item.className = 'search-cond-pop-item';
            var checked = state.cols.has(c.key);
            item.innerHTML = '<input type="checkbox"' + (checked ? ' checked' : '') + ' data-k="' + c.key + '" /><span>' + c.label + '</span>';
            item.addEventListener('click', function (ev) {
              if (ev.target.tagName !== 'INPUT') {
                var cb = item.querySelector('input');
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
                ev.preventDefault();
                ev.stopPropagation();
              }
            });
            item.querySelector('input').addEventListener('change', function (ev) {
              if (ev.target.checked) state.cols.add(c.key); else state.cols.delete(c.key);
              renderCols();
              ev.stopPropagation();
            });
            pop.appendChild(item);
          } else {
            var item = document.createElement('div');
            item.className = 'search-cond-pop-item' + (state.cols.has(c.key) ? ' selected' : '');
            item.innerHTML =
              '<input type="checkbox"' + (state.cols.has(c.key) ? ' checked' : '') + '/>' +
              '<span>' + c.label + '</span>';
            item.addEventListener('click', function (e) {
              e.stopPropagation();
              if (state.cols.has(c.key)) state.cols.delete(c.key); else state.cols.add(c.key);
              renderCols();
              item.classList.toggle('selected', state.cols.has(c.key));
              item.querySelector('input').checked = state.cols.has(c.key);
            });
            pop.appendChild(item);
          }
        });
        pop.classList.add('open');
        pop.dataset.kind = 'cols';
        positionPop(colsEl, pop);
      }

      function openCondPop() {
        pop.innerHTML = '';
        conditions.forEach(function (c) {
          var item = document.createElement('div');
          item.className = 'search-cond-pop-item' + (state.cond === c.key ? ' selected' : '');
          item.textContent = c.label;
          item.addEventListener('click', function (e) {
            e.stopPropagation();
            state.cond = c.key;
            renderCond();
            closePop();
          });
          pop.appendChild(item);
        });
        pop.classList.add('open');
        pop.dataset.kind = 'cond';
        positionPop(condEl, pop);
      }

      function closePop() { pop.classList.remove('open'); }

      function positionPanel() {
        var r = condBtn.getBoundingClientRect();
        var ph = panel.offsetHeight;
        var left;
        if (config.panelPosition === 'left') {
          left = r.left;
        } else {
          left = r.right - panel.offsetWidth;
        }
        if (left < 8) left = 8;
        if (left + panel.offsetWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - panel.offsetWidth - 8);
        var top = r.bottom + 4;
        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
        panel.style.left = left + 'px';
        panel.style.top  = top + 'px';
      }

      function onScrollOrResize(e) {
        if (panel.classList.contains('open')) positionPanel();
        if (pop.classList.contains('open')) {
          if (e && e.type === 'scroll' && e.target && pop.contains(e.target)) return;
          closePop();
        }
      }

      function openPanel() {
        if (closeAllPanels) closeAllPanels();
        if (panel.parentNode !== document.body) document.body.appendChild(panel);
        if (pop.parentNode !== document.body) document.body.appendChild(pop);
        renderCols();
        renderCond();
        panel.classList.add('open');
        positionPanel();
      }

      function closePanel() {
        panel.classList.remove('open');
        closePop();
      }

      condBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.classList.contains('open')) closePanel(); else openPanel();
      });

      colsEl.addEventListener('click', function (e) {
        if (config.popupCheckboxes && e.target.classList.contains('search-cond-chip-remove')) {
          var k = e.target.getAttribute('data-k');
          if (k) { state.cols.delete(k); renderCols(); }
          e.stopPropagation();
          return;
        }
        e.stopPropagation();
        if (pop.classList.contains('open') && pop.dataset.kind === 'cols') { closePop(); return; }
        pop.dataset.kind = 'cols';
        openColsPop();
      });

      condEl.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pop.classList.contains('open') && pop.dataset.kind === 'cond') { closePop(); return; }
        pop.dataset.kind = 'cond';
        openCondPop();
      });

      document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && e.target !== condBtn && !condBtn.contains(e.target)) closePanel();
        if (!pop.contains(e.target)) closePop();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePanel();
      });

      window.addEventListener('scroll', onScrollOrResize, true);
      window.addEventListener('resize', onScrollOrResize);

      cancelBtn.addEventListener('click', function (e) { e.stopPropagation(); closePanel(); });
      var closeHeaderBtn = panel.querySelector('[data-cond-close]');
      if (closeHeaderBtn) closeHeaderBtn.addEventListener('click', function (e) { e.stopPropagation(); closePanel(); });
      var colsclearBtn = panel.querySelector('.search-cond-colsclear');
      if (colsclearBtn) colsclearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        state.cols.clear();
        renderCols();
      });

      applyBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (config.onApply) {
          config.onApply(state);
          return;
        }
        var params = new URLSearchParams();
        var qVal = form.querySelector('input[name="q"]').value.trim();
        if (qVal !== '') params.set('q', qVal);
        if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
        params.set('cond', state.cond);
        params.set('sf', '1');
        var other = new URLSearchParams(location.search);
        preserveParams.forEach(function (k) { if (other.has(k)) params.set(k, other.get(k)); });
        location.href = pageUrl + (params.toString() ? '?' + params.toString() : '');
      });

      toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (config.onToggle) {
          config.onToggle(state);
          return;
        }
        var qVal = form.querySelector('input[name="q"]').value.trim();
        if (qVal === '') {
          form.querySelector('input[name="q"]').focus();
          return;
        }
        var params = new URLSearchParams();
        params.set('q', qVal);
        if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
        params.set('cond', state.cond);
        params.set('sf', '1');
        var other = new URLSearchParams(location.search);
        preserveParams.forEach(function (k) { if (other.has(k)) params.set(k, other.get(k)); });
        location.href = pageUrl + (params.toString() ? '?' + params.toString() : '');
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (config.onSubmit) {
          config.onSubmit(state);
          return;
        }
        var qVal = form.querySelector('input[name="q"]').value.trim();
        if (qVal === '') {
          var other = new URLSearchParams(location.search);
          ['q', 'cols', 'cond', 'sf', 'page'].forEach(function (k) { other.delete(k); });
          var s = other.toString();
          location.href = pageUrl + (s ? '?' + s : '');
          return;
        }
        var params = new URLSearchParams();
        params.set('q', qVal);
        if (state.cols.size > 0) params.set('cols', Array.from(state.cols).join(','));
        params.set('cond', state.cond);
        params.set('sf', '1');
        var other = new URLSearchParams(location.search);
        preserveParams.forEach(function (k) { if (other.has(k)) params.set(k, other.get(k)); });
        location.href = pageUrl + (params.toString() ? '?' + params.toString() : '');
      });
    },

    open: function (config) {
      if (!config || !config.target || !config.columns) return;
      var conditions = config.conditions || [
        { key: 'contains',     label: 'Содержит' },
        { key: 'not_contains', label: 'Не содержит' },
        { key: 'starts_with',  label: 'Начинается с' },
        { key: 'ends_with',    label: 'Заканчивается на' },
        { key: 'equals',       label: 'Равно' },
        { key: 'not_equals',   label: 'Не равно' },
        { key: 'gt',           label: 'Больше' },
        { key: 'lt',           label: 'Меньше' },
      ];
      var COL_LABEL = {};
      config.columns.forEach(function (c) { COL_LABEL[c.key] = c.label; });
      var COND_LABEL = {};
      conditions.forEach(function (c) { COND_LABEL[c.key] = c.label; });
      var panel = document.getElementById('_sp_cond_panel');
      if (!panel) {
        panel = document.createElement('div');
        panel.id = '_sp_cond_panel';
        panel.className = 'search-cond-panel';
        document.body.appendChild(panel);
      }
      var defaultCols = config.columns.map(function (c) { return c.key; });
      var state = {
        cond: config.currentCond || 'contains',
        cols: new Set(config.currentCols || defaultCols),
      };
      var condEl, colsEl, pop;
      panel.innerHTML =
        '<div class="search-cond-header"><span>Условия поиска</span><button type="button" class="search-cond-close" data-cond-close aria-label="Закрыть">✕</button></div>' +
        '<div class="search-cond-body">' +
          '<div class="search-cond-row search-cond-row--cols">' +
            '<span class="search-cond-label">Столбцы</span>' +
            '<div class="search-cond-row-body">' +
              '<div class="search-cond-control" id="_spCols" tabindex="0"></div>' +
              '<button type="button" class="search-cond-colsclear" title="Очистить все столбцы">✕</button>' +
            '</div>' +
          '</div>' +
          '<div class="search-cond-row">' +
            '<span class="search-cond-label">Условие</span>' +
            '<div class="search-cond-control" id="_spCond" tabindex="0"></div>' +
          '</div>' +
          '<div class="search-cond-actions">' +
            '<button type="button" class="search-cond-apply"><img src="img/ok.png" alt="" />Применить</button>' +
            '<button type="button" class="search-cond-cancel"><img src="img/cancel.png" alt="" />Отменить</button>' +
          '</div>' +
        '</div>';
      condEl = panel.querySelector('#_spCond');
      colsEl = panel.querySelector('#_spCols');
      pop = document.getElementById('_sp_cond_pop');
      if (!pop) {
        pop = document.createElement('div');
        pop.id = '_sp_cond_pop';
        pop.className = 'search-cond-pop';
        document.body.appendChild(pop);
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
      }

      function renderCols() {
        colsEl.innerHTML = '';
        if (state.cols.size === 0) {
          var sp = document.createElement('span');
          sp.className = 'search-cond-placeholder';
          sp.textContent = 'Выберите столбцы…';
          colsEl.appendChild(sp);
          return;
        }
        state.cols.forEach(function (k) {
          var chip = document.createElement('span');
          chip.className = 'search-cond-chip';
          chip.innerHTML = '<span>' + (COL_LABEL[k] || k) + '</span>' +
            '<button type="button" class="search-cond-chip-remove" title="Убрать">✕</button>';
          chip.querySelector('.search-cond-chip-remove').addEventListener('click', function (e) {
            e.stopPropagation();
            state.cols.delete(k);
            renderCols();
          });
          colsEl.appendChild(chip);
        });
      }

      function renderCond() {
        condEl.innerHTML = '';
        var t = document.createElement('span');
        t.textContent = COND_LABEL[state.cond] || state.cond;
        condEl.appendChild(t);
      }

      function closePop() { pop.classList.remove('open'); }

      function openColsPop() {
        pop.innerHTML = '';
        config.columns.forEach(function (c) {
          var item = document.createElement('label');
          item.className = 'search-cond-pop-item';
          var checked = state.cols.has(c.key);
          item.innerHTML = '<input type="checkbox"' + (checked ? ' checked' : '') + ' data-k="' + c.key + '" /><span>' + c.label + '</span>';
          item.addEventListener('click', function (ev) {
            if (ev.target.tagName !== 'INPUT') {
              var cb = item.querySelector('input');
              cb.checked = !cb.checked;
              cb.dispatchEvent(new Event('change'));
              ev.preventDefault();
              ev.stopPropagation();
            }
          });
          item.querySelector('input').addEventListener('change', function (ev) {
            if (ev.target.checked) state.cols.add(c.key); else state.cols.delete(c.key);
            renderCols();
            ev.stopPropagation();
          });
          pop.appendChild(item);
        });
        var r = colsEl.getBoundingClientRect();
        var pw = pop.offsetWidth || 200;
        var ph = pop.offsetHeight;
        var left = r.left;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
        var top = r.bottom + 4;
        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        pop.classList.add('open');
        pop.dataset.kind = 'cols';
      }

      function openCondPop() {
        pop.innerHTML = '';
        conditions.forEach(function (c) {
          var item = document.createElement('div');
          item.className = 'search-cond-pop-item' + (state.cond === c.key ? ' selected' : '');
          item.textContent = c.label;
          item.addEventListener('click', function (e) {
            e.stopPropagation();
            state.cond = c.key;
            renderCond();
            closePop();
          });
          pop.appendChild(item);
        });
        var r = condEl.getBoundingClientRect();
        var pw = pop.offsetWidth || 200;
        var ph = pop.offsetHeight;
        var left = r.left;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
        var top = r.bottom + 4;
        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        pop.classList.add('open');
        pop.dataset.kind = 'cond';
      }

      function closePanel() {
        panel.classList.remove('open');
        closePop();
      }

      function positionPanel() {
        var r = config.target.getBoundingClientRect();
        var left = r.right - panel.offsetWidth;
        if (left < 8) left = 8;
        if (left + panel.offsetWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - panel.offsetWidth - 8);
        var top = r.bottom + 4;
        if (top + panel.offsetHeight > window.innerHeight - 8) top = Math.max(8, r.top - panel.offsetHeight - 4);
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
      }

      document.querySelectorAll('.search-cond-panel.open').forEach(function (p) { p.classList.remove('open'); });
      renderCols();
      renderCond();
      panel.classList.add('open');
      positionPanel();

      var closeBtn = panel.querySelector('[data-cond-close]');
      if (closeBtn) closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closePanel(); });
      panel.querySelector('.search-cond-apply').addEventListener('click', function (e) {
        e.stopPropagation();
        if (config.onApply) config.onApply(state);
        closePanel();
      });
      panel.querySelector('.search-cond-cancel').addEventListener('click', function (e) {
        e.stopPropagation();
        closePanel();
      });
      var colsclearBtn = panel.querySelector('.search-cond-colsclear');
      if (colsclearBtn) colsclearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        state.cols.clear();
        renderCols();
      });
      colsEl.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pop.classList.contains('open') && pop.dataset.kind === 'cols') { closePop(); return; }
        openColsPop();
      });
      condEl.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pop.classList.contains('open') && pop.dataset.kind === 'cond') { closePop(); return; }
        openCondPop();
      });
      document.addEventListener('click', function _popClick(e) {
        if (pop.classList.contains('open') && !pop.contains(e.target) && e.target !== colsEl && !colsEl.contains(e.target) && e.target !== condEl && !condEl.contains(e.target)) {
          closePop();
        }
      });
      document.addEventListener('click', function _docClick(e) {
        if (!panel.contains(e.target) && e.target !== config.target && !config.target.contains(e.target)) {
          closePanel();
          document.removeEventListener('click', _docClick);
          document.removeEventListener('click', _popClick);
        }
      });
      document.addEventListener('keydown', function _esc(e) {
        if (e.key === 'Escape') { closePanel(); document.removeEventListener('keydown', _esc); document.removeEventListener('click', _docClick); document.removeEventListener('click', _popClick); }
      });
    }
  };
})();
