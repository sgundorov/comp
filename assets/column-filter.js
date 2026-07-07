(function () {
  'use strict';

  window.ColumnFilter = {
    init: function (config) {
      var th = document.querySelector('th' + config.thSelector);
      if (!th) return;
      var col   = th.getAttribute('data-col');
      var param = config.param || th.getAttribute('data-param') || (col + '_id');
      var placeholder = config.placeholder || 'Искать';
      var maxRows = config.maxRows || 8;
      var pageUrl = config.pageUrl || (th.closest('form') ? location.pathname : 'city.php');

      var initial = (th.getAttribute('data-values') || '')
        .split(',').map(function (s) { return parseInt(s, 10); }).filter(function (n) { return n > 0; });
      var selected = new Set(initial);
      var baseQs = config.preserveQuery || function () {
        var u = new URLSearchParams(location.search);
        u.delete(param);
        u.delete('page');
        return u;
      };

      var btn = th.querySelector('.col-filter-btn');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'col-filter-btn';
        btn.title = 'Фильтр по колонке';
        btn.innerHTML = '<img src="img/look.png" alt="" />';
        th.appendChild(btn);
      }
      if (selected.size > 0) btn.classList.add('active');

      var panel = document.createElement('div');
      panel.className = 'col-filter-panel';
      panel.innerHTML =
        '<input type="text" class="col-filter-search" placeholder="' + placeholder + '" />' +
        '<div class="col-filter-list"></div>' +
        '<div class="col-filter-actions">' +
       '<button type="button" class="col-filter-apply"><img src="img/ok.png" alt="" />Применить</button>' +
       '<button type="button" class="col-filter-reset"><img src="img/cancel.png" alt="" />Сброс</button>' +
        '</div>';
      document.body.appendChild(panel);

      var searchInput = panel.querySelector('.col-filter-search');
      var listEl = panel.querySelector('.col-filter-list');
      var resetBtn = panel.querySelector('.col-filter-reset');
      var applyBtn = panel.querySelector('.col-filter-apply');

      var allOptions = [];
      var loaded = false;

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
          return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
      }

      function positionPanel() {
        var r = btn.getBoundingClientRect();
        var pw = panel.offsetWidth;
        var ph = panel.offsetHeight;
        var left = r.left;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
        var top = r.bottom + 4;
        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
        panel.style.left = left + 'px';
        panel.style.top  = top + 'px';
      }
      function onScrollOrResize() { if (panel.classList.contains('open')) positionPanel(); }

      function open() {
        panel.classList.add('open');
        panel.style.display = 'block';
        positionPanel();
        if (!loaded) loadOptions(); else renderList(searchInput.value);
      }
      function close() { panel.classList.remove('open'); }
      function toggle() { panel.classList.contains('open') ? close() : open(); }

      function loadOptions() {
        if (config.options) {
          allOptions = config.options;
          loaded = true;
          renderList(searchInput.value);
          return;
        }
        var sep = location.search ? '&' : '?';
        var url = pageUrl + location.search + sep + 'action=columnFilterOptions&col=' + encodeURIComponent(col);
        fetch(url).then(function (r) { return r.json(); }).then(function (items) {
          allOptions = items;
          loaded = true;
          renderList(searchInput.value);
        });
      }

      function renderList(filterText) {
        var t = (filterText || '').toLowerCase();
        var filtered = t ? allOptions.filter(function (o) {
          return String(o.name).toLowerCase().indexOf(t) !== -1;
        }) : allOptions;
        var shown = filtered.slice(0, maxRows);
        listEl.innerHTML = '';
        if (shown.length === 0) {
          var empty = document.createElement('div');
          empty.className = 'col-filter-empty';
          empty.textContent = 'Нет совпадений';
          listEl.appendChild(empty);
        } else {
          shown.forEach(function (o) {
            var row = document.createElement('label');
            row.className = 'col-filter-row';
            row.innerHTML =
              '<input type="checkbox" value="' + o.id + '"' +
              (selected.has(o.id) ? ' checked' : '') + '/>' +
              '<span class="col-filter-name">' + escapeHtml(o.name) + '</span>';
            listEl.appendChild(row);
          });
        }
        if (filtered.length > maxRows) {
          var more = document.createElement('div');
          more.className = 'col-filter-more';
          more.textContent = 'Показано ' + maxRows + ' из ' + filtered.length + '. Уточните поиск.';
          listEl.appendChild(more);
        }
      }

      function applyAndClose(extra) {
        var u = baseQs();
        if (selected.size > 0) u.set(param, Array.from(selected).join(','));
        if (extra) for (var k in extra) u.set(k, extra[k]);
        var qs = u.toString();
        location.href = pageUrl + (qs ? '?' + qs : '');
      }

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggle();
      });
      document.addEventListener('click', function (e) {
        if (!th.contains(e.target) && !panel.contains(e.target)) close();
      });
      window.addEventListener('scroll', onScrollOrResize, true);
      window.addEventListener('resize', onScrollOrResize);
      searchInput.addEventListener('input', function () { renderList(searchInput.value); });
      searchInput.addEventListener('click', function (e) { e.stopPropagation(); });
      listEl.addEventListener('click', function (e) { e.stopPropagation(); });
      listEl.addEventListener('change', function (e) {
        var cb = e.target;
        if (cb && cb.type === 'checkbox') {
          var id = parseInt(cb.value, 10);
          if (cb.checked) selected.add(id); else selected.delete(id);
        }
      });
      resetBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        selected.clear();
        applyAndClose();
      });
      applyBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        applyAndClose();
      });
      panel.addEventListener('click', function (e) { e.stopPropagation(); });
    }
  };
})();
