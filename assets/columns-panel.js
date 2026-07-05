(function (global) {
  'use strict';

  function init(opts) {
    var btn = opts.btn;
    if (!btn) return;

    var saveUrl = opts.saveUrl;
    var tbl = opts.tbl;
    var initialColumns = opts.initialColumns || [];
    var defaultColumns = opts.defaultColumns || initialColumns;
    var closeAllPanels = opts.closeAllPanels;
    var onSave = opts.onSave;

    var panel = document.createElement('div');
    panel.className = 'columns-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Настройка столбцов таблицы');
    panel.innerHTML =
      '<div class="columns-panel-header">' +
        '<span>Настройка столбцов таблицы</span>' +
        '<div class="columns-panel-actions">' +
          '<button type="button" class="columns-panel-close" title="Закрыть">✕</button>' +
        '</div>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:6px;padding:8px 10px;border-bottom:1px solid var(--line)">' +
        '<button type="button" id="columnsResetWidthBtn" style="height:34px;padding:0 8px;background:var(--btn);color:#fff;border:1px solid var(--line);border-radius:2px;cursor:pointer;font-size:14px">Сброс ширины</button>' +
        '<button type="button" id="columnsResetOrderBtn" style="height:34px;padding:0 8px;margin-left:6px;background:var(--btn);color:#fff;border:1px solid var(--line);border-radius:2px;cursor:pointer;font-size:14px">Сброс порядка</button>' +
        '<button type="button" class="columns-panel-move" id="columnsUpBtn" title="Поднять" aria-label="Поднять" style="height:34px;width:34px;padding:0;background:var(--btn);border:1px solid var(--line);border-radius:2px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center"><img src="img/up.png" alt="" style="width:20px;height:20px" /></button>' +
        '<button type="button" class="columns-panel-move" id="columnsDownBtn" title="Опустить" aria-label="Опустить" style="height:34px;width:34px;padding:0;background:var(--btn);border:1px solid var(--line);border-radius:2px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center"><img src="img/down.png" alt="" style="width:20px;height:20px" /></button>' +
      '</div>' +
      '<div class="columns-panel-table" id="columnsList"></div>' +
      '<div class="columns-panel-footer">' +
        '<button type="button" id="columnsApplyBtn" class="columns-apply"><img src="img/ok.png" alt="" />Сохранить</button>' +
        '<button type="button" id="columnsCancelBtn"><img src="img/cancel.png" alt="" />Отменить</button>' +
      '</div>';
    document.body.appendChild(panel);

    var listEl = panel.querySelector('#columnsList');
    var applyBtn = panel.querySelector('#columnsApplyBtn');
    var cancelBtn = panel.querySelector('#columnsCancelBtn');
    var closeBtn = panel.querySelector('.columns-panel-close');
    var upBtn = panel.querySelector('#columnsUpBtn');
    var downBtn = panel.querySelector('#columnsDownBtn');
    var resetWidthBtn = panel.querySelector('#columnsResetWidthBtn');
    var resetOrderBtn = panel.querySelector('#columnsResetOrderBtn');
    var onResetWidth = opts.onResetWidth || null;
    var onResetOrder = opts.onResetOrder || null;

    var state = initialColumns.map(function (c, i) {
      return { name: c.name, label: c.label, visible: !!c.visible, order: i };
    });
    var hoveredIdx = -1;

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function render() {
      listEl.innerHTML = '';
      state.forEach(function (c, i) {
        var row = document.createElement('div');
        row.className = 'columns-row' + (i === hoveredIdx ? ' hovered' : '');
        row.dataset.idx = String(i);
        row.innerHTML =
          '<input type="checkbox"' + (c.visible ? ' checked' : '') + '/>' +
          '<span class="columns-name">' + escapeHtml(c.label) + '</span>';
        var cb = row.querySelector('input');
        cb.addEventListener('click', function (e) { e.stopPropagation(); });
        cb.addEventListener('change', function () {
          state[i].visible = cb.checked;
        });
        row.addEventListener('click', function (e) {
          if (e.target.tagName === 'INPUT') return;
          var idx = parseInt(row.dataset.idx, 10);
          if (idx !== hoveredIdx) {
            hoveredIdx = idx;
            updateHover();
          }
        });
        listEl.appendChild(row);
      });
      updateHover();
    }

    function updateHover() {
      var rows = listEl.querySelectorAll('.columns-row');
      rows.forEach(function (r, i) {
        r.classList.toggle('hovered', i === hoveredIdx);
      });
      upBtn.disabled = hoveredIdx <= 0;
      downBtn.disabled = hoveredIdx < 0 || hoveredIdx >= state.length - 1;
    }

    function move(delta) {
      if (hoveredIdx < 0) return;
      var j = hoveredIdx + delta;
      if (j < 0 || j >= state.length) return;
      var tmp = state[hoveredIdx];
      state[hoveredIdx] = state[j];
      state[j] = tmp;
      hoveredIdx = j;
      render();
    }

    function position() {
      var r = btn.getBoundingClientRect();
      var pw = panel.offsetWidth;
      var ph = panel.offsetHeight;
      var left = r.right - pw;
      if (left < 8) left = 8;
      if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
      var top = r.bottom + 4;
      if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
      panel.style.left = left + 'px';
      panel.style.top = top + 'px';
    }

    function open() {
      if (panel.parentNode !== document.body) document.body.appendChild(panel);
      panel.classList.add('open');
      position();
    }

    function close() {
      panel.classList.remove('open');
    }

    function reset() {
      state = initialColumns.map(function (c, i) {
        return { name: c.name, label: c.label, visible: !!c.visible, order: i };
      });
      hoveredIdx = -1;
    }

    cancelBtn.addEventListener('click', function (e) { e.stopPropagation(); reset(); close(); });
    closeBtn.addEventListener('click', function (e) { e.stopPropagation(); reset(); close(); });
    upBtn.addEventListener('click', function (e) { e.stopPropagation(); if (!upBtn.disabled) move(-1); });
    downBtn.addEventListener('click', function (e) { e.stopPropagation(); if (!downBtn.disabled) move(1); });
    resetWidthBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      resetWidthBtn.disabled = true;
      var fd = new FormData();
      fd.set('tbl', tbl);
      fetch('column_width_reset.php?ajax=1', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        resetWidthBtn.disabled = false;
        if (j && j.ok) { if (onResetWidth) onResetWidth(); else location.reload(); }
        else alert('Ошибка сброса ширины');
      })
      .catch(function () { resetWidthBtn.disabled = false; });
    });
    resetOrderBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      resetOrderBtn.disabled = true;
      var fd = new FormData();
      fd.set('tbl', tbl);
      defaultColumns.forEach(function (c, i) {
        var cur = state.find(function (s) { return s.name === c.name; });
        fd.append('columns[' + i + '][name]', c.name);
        fd.append('columns[' + i + '][visible]', (cur ? cur.visible : !!c.visible) ? '1' : '0');
        fd.append('columns[' + i + '][order]', String(i));
      });
      fetch(saveUrl + '?ajax=1', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        resetOrderBtn.disabled = false;
        if (j && j.ok) { if (onResetOrder) onResetOrder(); else location.reload(); }
        else alert('Ошибка сброса порядка');
      })
      .catch(function () { resetOrderBtn.disabled = false; });
    });
    applyBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var fd = new FormData();
      fd.set('tbl', tbl);
      state.forEach(function (c, i) {
        fd.append('columns[' + i + '][name]', c.name);
        fd.append('columns[' + i + '][visible]', c.visible ? '1' : '0');
        fd.append('columns[' + i + '][order]', String(i));
      });
      applyBtn.disabled = true;
      fetch(saveUrl + '?ajax=1', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          applyBtn.disabled = false;
          if (j && j.ok) {
            if (onSave) onSave(state);
            else location.reload();
            initialColumns = state.map(function(c) {
              return { name: c.name, label: c.label, visible: c.visible !== false };
            });
          } else { alert('Ошибка сохранения'); }
        })
        .catch(function () { applyBtn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
      if (!panel.classList.contains('open')) return;
      if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) {
      if (!panel.classList.contains('open')) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); return; }
    });
    window.addEventListener('scroll', function () { if (panel.classList.contains('open')) position(); }, true);
    window.addEventListener('resize', function () { if (panel.classList.contains('open')) position(); });

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (panel.classList.contains('open')) close();
      else { if (closeAllPanels) closeAllPanels(); reset(); hoveredIdx = state.findIndex(function (c) { return c.name === 'id'; }); if (hoveredIdx < 0) hoveredIdx = 0; render(); open(); }
    });

    render();
  }

  global.ColumnsPanel = { init: init };
})(window);
