(function () {
  'use strict';

  function parseSort(s) {
    var out = [];
    if (!s) return out;
    s.split(',').forEach(function (part) {
      var p = part.split(':');
      var c = p[0], d = (p[1] || 'asc').toLowerCase();
      if (c && (d === 'asc' || d === 'desc')) out.push({ col: c, dir: d });
    });
    return out;
  }

  function serialize(levels) {
    return levels.map(function (l) { return l.col + ':' + l.dir; }).join(',');
  }

  function colLabel(columns, k) {
    for (var i = 0; i < columns.length; i++) if (columns[i].key === k) return columns[i].label;
    return k;
  }

  function dirLabel(directions, k) {
    for (var i = 0; i < directions.length; i++) if (directions[i].key === k) return directions[i].label;
    return k;
  }

  function usedCols(levels, exceptIdx) {
    var u = [];
    levels.forEach(function (l, i) { if (i !== exceptIdx) u.push(l.col); });
    return u;
  }

  var COL_HEADER_SEL = 'th[data-sort-col]';

  function bindHeaderClick(config) {
    var headers = document.querySelectorAll(COL_HEADER_SEL);
    Array.from(headers).forEach(function (th) {
      th.addEventListener('click', function (e) {
        if (e.target.closest('.col-filter-btn')) return;
        if (e.target.closest('.col-resize-handle')) return;
        var col = th.getAttribute('data-sort-col');
        if (!col) return;
        var current = config.currentSort || parseSort(new URLSearchParams(location.search).get('sort'));
        var existing = current.length === 1 ? current[0] : null;
        var nextDir;
        if (existing && existing.col === col) {
          nextDir = existing.dir === 'asc' ? 'desc' : 'asc';
        } else {
          nextDir = 'asc';
        }
        var params = new URLSearchParams(location.search);
        params.delete('page');
        params.delete('sort');
        if (nextDir) params.set('sort', col + ':' + nextDir);
        location.href = config.pageUrl.split('?')[0] + (params.toString() ? '?' + params.toString() : '');
      });
    });
  }

  function updateSortIndicators(config) {
    var current = config.currentSort || parseSort(new URLSearchParams(location.search).get('sort'));
    if (!current || current.length === 0) return;
    var headers = document.querySelectorAll(COL_HEADER_SEL);
    Array.from(headers).forEach(function (th) {
      var col = th.getAttribute('data-sort-col');
      var existing = th.querySelector('.sort-indicator');
      if (existing) existing.remove();
      var idx = -1;
      for (var i = 0; i < current.length; i++) if (current[i].col === col) { idx = i; break; }
      if (idx >= 0) {
        var span = document.createElement('span');
        span.className = 'sort-indicator';
        span.textContent = (idx + 1) + ' ' + (current[idx].dir === 'asc' ? '▲' : '▼');
        var label = th.querySelector('.col-filter-label');
        if (label) {
          label.insertAdjacentElement('afterend', span);
        } else {
          th.appendChild(span);
        }
      }
    });
  }

  function positionPopup(target, pop, minWidth) {
    var r = target.getBoundingClientRect();
    var pw = pop.offsetWidth || minWidth || 320;
    var ph = pop.offsetHeight;
    var left = r.left;
    if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
    var top = r.bottom + 4;
    if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
  }

  function buildModalUI(config) {
    var columns = config.columns;
    var directions = config.directions || [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }];
    var pageUrl = config.pageUrl;
    var current = config.currentSort || parseSort(new URLSearchParams(location.search).get('sort'));
    var levels = current.length > 0 ? JSON.parse(JSON.stringify(current)) : [{ col: columns[0].key, dir: 'asc' }];

    var backdrop = document.createElement('div');
    backdrop.className = 'sort-modal-backdrop';
    backdrop.innerHTML =
      '<div class="sort-modal" role="dialog" aria-labelledby="sortModalTitle">' +
        '<div class="sort-modal-header">' +
          '<span id="sortModalTitle">Сортировка</span>' +
          '<button class="sort-modal-close" type="button" title="Закрыть">✕</button>' +
        '</div>' +
        '<div class="sort-modal-body">' +
          '<div class="sort-level-actions">' +
            '<button type="button" class="sort-add-level" id="sortAddBtn">+ Добавить уровень</button>' +
            '<button type="button" class="sort-remove-level" id="sortRemoveBtn">− Удалить уровень</button>' +
          '</div>' +
          '<div class="sort-levels">' +
            '<div class="sort-cols">' +
              '<div class="sort-cols-header">Столбец</div>' +
              '<div class="sort-cols-list" id="sortColsList"></div>' +
            '</div>' +
            '<div class="sort-dirs">' +
              '<div class="sort-dirs-header">Направление</div>' +
              '<div class="sort-dirs-list" id="sortDirsList"></div>' +
            '</div>' +
          '</div>' +
          '<div class="sort-modal-actions">' +
             '<button type="button" class="sort-apply" id="sortApplyBtn"><img src="img/ok.png" alt="" />Сортировать</button>' +
             '<button type="button" class="sort-cancel" id="sortCancelBtn"><img src="img/cancel.png" alt="" />Отменить</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(backdrop);

    var modal     = backdrop.querySelector('.sort-modal');
    var colsList  = backdrop.querySelector('#sortColsList');
    var dirsList  = backdrop.querySelector('#sortDirsList');
    var addBtn    = backdrop.querySelector('#sortAddBtn');
    var removeBtn = backdrop.querySelector('#sortRemoveBtn');
    var cancelBtn = backdrop.querySelector('#sortCancelBtn');
    var applyBtn  = backdrop.querySelector('#sortApplyBtn');
    var closeBtn  = backdrop.querySelector('.sort-modal-close');

    var pop = document.createElement('div');
    pop.className = 'sort-pop';
    document.body.appendChild(pop);

    function render() {
      colsList.innerHTML = '';
      dirsList.innerHTML = '';
      addBtn.disabled    = levels.length >= columns.length;
      removeBtn.disabled = levels.length <= 1;
      levels.forEach(function (l, i) {
        var cRow = document.createElement('div');
        cRow.className = 'sort-row';
        var cLabel = document.createElement('span');
        cLabel.className = 'sort-label';
        cLabel.textContent = i === 0 ? 'Сначала по' : 'Затем по';
        var cSel = document.createElement('div');
        cSel.className = 'sort-select';
        cSel.tabIndex = 0;
        cSel.innerHTML = '<span class="sort-select-label">' + colLabel(columns, l.col) + '</span>';
        cSel.addEventListener('click', function (idx, el) {
          return function (e) { e.stopPropagation(); openColPop(idx, el); };
        }(i, cSel));
        cRow.appendChild(cLabel);
        cRow.appendChild(cSel);
        colsList.appendChild(cRow);

        var dRow = document.createElement('div');
        dRow.className = 'sort-row';
        dRow.style.gap = '0';
        var dSel = document.createElement('div');
        dSel.className = 'sort-select';
        dSel.tabIndex = 0;
        dSel.innerHTML = '<span class="sort-select-label">' + dirLabel(directions, l.dir) + '</span>';
        dSel.addEventListener('click', function (idx, el) {
          return function (e) { e.stopPropagation(); openDirPop(idx, el); };
        }(i, dSel));
        dRow.appendChild(dSel);
        dirsList.appendChild(dRow);
      });
    }

    function openColPop(idx, target) {
      var used = usedCols(levels, idx);
      pop.innerHTML = '';
      columns.forEach(function (c) {
        var isSel = levels[idx].col === c.key;
        var isUsed = used.indexOf(c.key) !== -1;
        var item = document.createElement('div');
        item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
        item.textContent = c.label;
        if (isUsed && !isSel) {
          item.style.opacity = '0.45';
          item.style.cursor = 'default';
        } else {
          item.addEventListener('click', function (colKey) {
            return function (e) {
              e.stopPropagation();
              levels[idx].col = colKey;
              closePop();
              render();
            };
          }(c.key));
        }
        pop.appendChild(item);
      });
      pop.classList.add('open');
      pop.dataset.kind = 'col';
      positionPopup(target, pop);
    }

    function openDirPop(idx, target) {
      pop.innerHTML = '';
      directions.forEach(function (d) {
        var isSel = levels[idx].dir === d.key;
        var item = document.createElement('div');
        item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
        item.textContent = d.label;
        item.addEventListener('click', function (dirKey) {
          return function (e) {
            e.stopPropagation();
            levels[idx].dir = dirKey;
            closePop();
            render();
          };
        }(d.key));
        pop.appendChild(item);
      });
      pop.classList.add('open');
      pop.dataset.kind = 'dir';
      positionPopup(target, pop);
    }

    function closePop() { pop.classList.remove('open'); pop.dataset.kind = ''; }

    function openModal() {
      if (config.beforeOpen) config.beforeOpen();
      var current = parseSort(new URLSearchParams(location.search).get('sort'));
      levels = current.length > 0 ? current : [{ col: columns[0].key, dir: 'asc' }];
      backdrop.classList.add('open');
      render();
    }

    function closeModal() {
      backdrop.classList.remove('open');
      closePop();
    }

    addBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (levels.length >= columns.length) return;
      var used = usedCols(levels, -1);
      var available = [];
      columns.forEach(function (c) { if (used.indexOf(c.key) === -1) available.push(c); });
      if (available.length === 0) return;
      levels.push({ col: available[0].key, dir: 'asc' });
      render();
    });

    removeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (levels.length <= 1) return;
      levels.pop();
      render();
    });

    cancelBtn.addEventListener('click', function (e) { e.stopPropagation(); closeModal(); });
    closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closeModal(); });

    applyBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var params = new URLSearchParams(location.search);
      params.delete('page');
      params.delete('sort');
      params.set('sort', serialize(levels));
      location.href = pageUrl.split('?')[0] + '?' + params.toString();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
    });

    function _sortOutsideClick(e) {
      if (!backdrop.classList.contains('open')) return;
      if (e.target.closest('.sort-modal, .sort-pop.open')) return;
      e.stopPropagation();
      closeModal();
    }
    document.addEventListener('mousedown', _sortOutsideClick, true);
    document.addEventListener('mouseup',   _sortOutsideClick, true);
    document.addEventListener('click',     _sortOutsideClick, true);

    document.addEventListener('click', function (e) {
      if (pop.classList.contains('open') && !pop.contains(e.target)) closePop();
    });

    window.addEventListener('scroll', function () {
      if (pop.classList.contains('open')) closePop();
    }, true);

    window.addEventListener('resize', function () {
      if (pop.classList.contains('open')) closePop();
    });

    config.btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openModal();
    });
  }

  function buildPopupUI(config) {
    var columns = config.columns;
    var pageUrl = config.pageUrl;
    var maxLevels = config.maxLevels || 4;
    var current = config.currentSort || [];
    var levels = current.length > 0 ? JSON.parse(JSON.stringify(current)) : [{ col: columns[0].key, dir: 'asc' }];

    function closeAllPanels() {
      var p = document.querySelector('.sort-pop-panel');
      if (p) p.remove();
    }

    function openSort() {
      closeAllPanels();
      if (config.beforeOpen) config.beforeOpen();
      var panel = document.createElement('div');
      panel.className = 'sort-pop sort-pop-panel';
      panel.style.position = 'absolute';
      panel.style.zIndex = '50';

      function render() {
        var html = '<div class="sort-pop-header">Сортировка</div><div class="sort-pop-body">';
        levels.forEach(function (lv, i) {
          html += '<div class="sort-pop-row">';
          html += '<select data-i="' + i + '">';
          columns.forEach(function (c) {
            html += '<option value="' + c.key + '"' + (c.key === lv.col ? ' selected' : '') + '>' + c.label + '</option>';
          });
          html += '</select>';
          html += '<button class="dir-btn" data-i="' + i + '">' + (lv.dir === 'asc' ? '↑' : '↓') + '</button>';
          html += '<button class="remove-btn" data-i="' + i + '">×</button>';
          html += '</div>';
        });
        html += '<div class="sort-pop-add"><button id="sortAddBtn"' + (levels.length >= maxLevels ? ' disabled' : '') + '>+ уровень</button></div>';
        html += '<div class="sort-pop-actions"><button class="primary" data-act="apply"><img src="img/ok.png" alt="" />Применить</button><button data-act="reset"><img src="img/cancel.png" alt="" />Сбросить</button></div>';
        html += '</div>';
        panel.innerHTML = html;

        var parent = config.btn.parentNode;
        parent.appendChild(panel);
        var r = config.btn.getBoundingClientRect();
        var left = r.left;
        var panelMinWidth = 320;
        if (left + panelMinWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - panelMinWidth - 8);
        panel.style.left = left + 'px';
        panel.style.top = r.bottom + 'px';

        panel.querySelectorAll('select').forEach(function (sel) {
          sel.addEventListener('change', function () { levels[parseInt(sel.dataset.i, 10)].col = sel.value; });
        });
        panel.querySelectorAll('.dir-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var i = parseInt(btn.dataset.i, 10);
            levels[i].dir = levels[i].dir === 'asc' ? 'desc' : 'asc';
            render();
          });
        });
        panel.querySelectorAll('.remove-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var i = parseInt(btn.dataset.i, 10);
            levels.splice(i, 1);
            render();
          });
        });
        var addBtn = panel.querySelector('#sortAddBtn');
        if (addBtn) {
          addBtn.addEventListener('click', function () {
            if (levels.length < maxLevels) { levels.push({ col: columns[0].key, dir: 'asc' }); render(); }
          });
        }
        panel.querySelector('[data-act="reset"]').addEventListener('click', function () {
          var other = new URLSearchParams(location.search);
          other.delete('sort'); other.delete('page'); other.delete('focus');
          window.location.href = pageUrl.split('?')[0] + '?' + other.toString();
        });
        panel.querySelector('[data-act="apply"]').addEventListener('click', function () {
          var other = new URLSearchParams(location.search);
          other.set('sort', serialize(levels));
          other.delete('page'); other.delete('focus');
          window.location.href = pageUrl.split('?')[0] + '?' + other.toString();
        });
      }

      render();

      function closeOnOutside(ev) {
        if (!panel.contains(ev.target) && ev.target !== config.btn) {
          panel.remove();
          document.removeEventListener('mousedown', closeOnOutside);
        }
      }
      document.addEventListener('mousedown', closeOnOutside);
    }

    config.btn.addEventListener('click', function (e) {
      e.preventDefault();
      openSort();
    });
  }

  window.SortPanel = {
    init: function (config) {
      if (!config || !config.btn || !config.columns || !config.pageUrl) return;
      if (config.mode === 'popup') {
        buildPopupUI(config);
      } else {
        buildModalUI(config);
      }
      bindHeaderClick(config);
      updateSortIndicators(config);
    },
    updateIndicators: function (config) {
      updateSortIndicators(config);
    }
  };
})();
