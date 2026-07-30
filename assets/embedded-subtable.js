(function (global) {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function fmtNum(v, dec) {
    if (dec === undefined) dec = 2;
    var n = parseFloat(String(v != null ? v : '0').replace(',', '.'));
    return isNaN(n) ? '-' : n.toFixed(dec);
  }

  function EmbeddedSubTable(config) {
    if (!config || !config.tableEl) throw new Error('EmbeddedSubTable: tableEl required');

    this.tableEl = config.tableEl;
    this.tbody = this.tableEl.querySelector('tbody');
    this.formBody = config.formBody || document.body;
    this.saveUrl = config.saveUrl || '';
    this.prefix = config.prefix || 'est';
    this.parentId = config.parentId || 0;
    this.parentField = config.parentField || '';

    this.columns = config.columns || [];
    this.renderRow = config.renderRow || function () { return ''; };
    this.renderCell = config.renderCell || null;

    this.onRowSelect = config.onRowSelect || null;
    this.onRowDoubleClick = config.onRowDoubleClick || null;
    this.onDataChange = config.onDataChange || null;
    this.readonly = config.readonly || false;

    this.data = config.data || [];
    this.selectedId = 0;
    this.checkedIds = new Set();

    this.searchActive = false;
    this.searchText = '';
    this.searchCond = 'contains';
    this.showOnlyChecked = false;
    this.searchCols = config.searchCols || [];
    this.sortCol = -1;
    this.sortDir = 'asc';
    this.currentPage = 1;
    this.pageSize = config.pageSize || 15;

    this.selWrap = config.selWrapEl ? this.formBody.querySelector(config.selWrapEl) : null;
    this.selCountEl = config.selCountEl ? this.formBody.querySelector(config.selCountEl) : null;
    this.checkAllEl = config.checkAllEl ? this.tableEl.querySelector(config.checkAllEl) : null;
    this.paginationEl = config.paginationEl ? this.formBody.querySelector(config.paginationEl) : null;

    this.btnAdd = config.btnAddEl ? this.formBody.querySelector(config.btnAddEl) : null;
    this.btnEdit = config.btnEditEl ? this.formBody.querySelector(config.btnEditEl) : null;
    this.btnCopy = config.btnCopyEl ? this.formBody.querySelector(config.btnCopyEl) : null;
    this.btnDelete = config.btnDeleteEl ? this.formBody.querySelector(config.btnDeleteEl) : null;
    this.btnRefresh = config.btnRefreshEl ? this.formBody.querySelector(config.btnRefreshEl) : null;

    this.filterBanner = config.filterBannerEl ? this.formBody.querySelector(config.filterBannerEl) : null;
    this.filterChipsEl = config.filterChipsEl ? this.formBody.querySelector(config.filterChipsEl) : null;
    this.clearSearchBtn = config.clearSearchBtnEl ? this.formBody.querySelector(config.clearSearchBtnEl) : null;

    this.childFormUrl = config.childFormUrl || '';
    this.childFormName = config.childFormName || '';
    this.totalsCallback = config.totalsCallback || '';

    this._docType = 0;
    try {
      var _u = new URL(this.childFormUrl, location.href);
      var _dt = _u.searchParams.get('doc_type');
      if (_dt) this._docType = parseInt(_dt, 10) || 0;
    } catch(e) {}

    this._bindEvents();
    this._bindKeyboard();
    this._bindFilterUI();
    this.render();
    this._updateButtons();

    var self = this;
    if (this.data.length > 0) {
      setTimeout(function () { if (self.selectedId === 0) self.selectById(self.data[0].id); }, 0);
    }
  }

  EmbeddedSubTable.prototype.getFilteredData = function () {
    var filtered = this.data;
    if (this.showOnlyChecked && this.checkedIds.size > 0) {
      var checked = this.checkedIds;
      filtered = filtered.filter(function (item) { return checked.has(Number(item.id)); });
    }
    if (this.searchActive && this.searchText) {
      var st = this.searchText.toLowerCase();
      var cols = this.searchCols.length > 0 ? this.searchCols : this.columns.map(function (c) { return c.key; });
      filtered = filtered.filter(function (item) {
        return cols.some(function (key) {
          var val = String(item[key] != null ? item[key] : '').toLowerCase();
          var cond = this.searchCond;
          if (cond === 'contains') return val.indexOf(st) >= 0;
          if (cond === 'not_contains') return val.indexOf(st) < 0;
          if (cond === 'starts_with') return val.indexOf(st) === 0;
          if (cond === 'ends_with') return val.indexOf(st) === val.length - st.length;
          if (cond === 'equals') return val === st;
          if (cond === 'not_equals') return val !== st;
          return false;
        }.bind(this));
      }.bind(this));
    }
    if (this.sortCol >= 0 && this.columns[this.sortCol]) {
      var key = this.columns[this.sortCol].key;
      var dir = this.sortDir;
      filtered = filtered.slice().sort(function (a, b) {
        var va = (a[key] || '').toString().toLowerCase();
        var vb = (b[key] || '').toString().toLowerCase();
        var na = parseFloat(va.replace(',', '.'));
        var nb = parseFloat(vb.replace(',', '.'));
        if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
        if (va < vb) return dir === 'asc' ? -1 : 1;
        if (va > vb) return dir === 'asc' ? 1 : -1;
        return 0;
      });
    }
    return filtered;
  };

  EmbeddedSubTable.prototype.getPageItems = function () {
    var filtered = this.getFilteredData();
    var total = filtered.length;
    var pages = Math.max(1, Math.ceil(total / this.pageSize));
    if (this.currentPage > pages) this.currentPage = pages;
    var start = (this.currentPage - 1) * this.pageSize;
    return { items: filtered.slice(start, start + this.pageSize), total: total, pages: pages };
  };

  EmbeddedSubTable.prototype.render = function () {
    var p = this.getPageItems();
    var items = p.items;
    var total = p.total;
    var pages = p.pages;

    if (total === 0) {
      this.tbody.innerHTML = '<tr><td colspan="' + (1 + this.columns.length) + '" style="text-align:center;padding:12px;color:var(--muted)">Нет данных</td></tr>';
    } else {
      var html = '';
      var st = this.searchActive && this.searchText ? this.searchText.toLowerCase() : '';
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var sel = (item.id == this.selectedId) ? ' selected' : '';
        var chk = this.checkedIds.has(Number(item.id)) ? ' checked' : '';
        html += '<tr data-id="' + item.id + '" data-row-id="' + item.id + '" class="d2-row' + sel + '">';
        html += '<td class="col-check"><input type="checkbox" class="d2-row-check" data-id="' + item.id + '"' + chk + ' /></td>';
        for (var j = 0; j < this.columns.length; j++) {
          var col = this.columns[j];
          var val = item[col.key] != null ? item[col.key] : '';
          var cellVal = (col.dbField && col.dbField !== col.key) ? (item[col.dbField] != null ? item[col.dbField] : '') : val;
          var display = col.render ? col.render(val, item) : (col.hideZero && (val === 0 || val === '0') ? '' : esc(val));
          if (st && this.searchCols.indexOf(col.key) >= 0) {
            display = this._highlight(display, st);
          }
          var align = col.align || 'left';
          var editable = (this.readonly || col.readonly) ? '' : ' class="cell-editable"';
          html += '<td' + editable + ' data-field="' + col.key + '" data-value="' + esc(String(cellVal)) + '" style="text-align:' + align + '"><span class="cell-value">' + display + '</span></td>';
        }
        html += '</tr>';
      }
      this.tbody.innerHTML = html;
    }

    this._renderPagination(total, pages);
    this._updateButtons();
    this._updateCheckedUI();
    this._updateFilterUI();
  };

  EmbeddedSubTable.prototype._highlight = function (text, search) {
    if (!search) return text;
    var re = new RegExp('(' + search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return text.replace(re, '<span class="hl">$1</span>');
  };

  EmbeddedSubTable.prototype._bindFilterUI = function () {
    var self = this;
    function clearAllFilters() {
      self.searchActive = false;
      self.searchText = '';
      self.showOnlyChecked = false;
      self.currentPage = 1;
      var searchInput = self.formBody.querySelector('#' + self.prefix + '-search-input');
      if (searchInput) searchInput.value = '';
      self.render();
    }
    if (this.clearSearchBtn) {
      this.clearSearchBtn.addEventListener('click', function (e) {
        e.preventDefault();
        clearAllFilters();
      });
    }
  };

  EmbeddedSubTable.prototype._updateFilterUI = function () {
    var searchActive = this.searchActive && this.searchText;
    var filterActive = searchActive || this.showOnlyChecked;
    if (this.filterBanner) this.filterBanner.style.display = filterActive ? '' : 'none';
    if (this.filterChipsEl) {
      var chips = [];
      if (this.showOnlyChecked) {
        chips.push({ label: 'Только отмеченные', type: 'showOnly' });
      }
      if (searchActive && this.searchText) {
        var condLabels = { 'contains': 'содержит', 'not_contains': 'не содержит', 'starts_with': 'начинается с', 'ends_with': 'заканчивается на', 'equals': 'равно', 'not_equals': 'не равно' };
        var colLabels = [];
        var allLabels = {};
        for (var ci = 0; ci < this.columns.length; ci++) { allLabels[this.columns[ci].key] = this.columns[ci].label; }
        var sc = this.searchCols && this.searchCols.length > 0 ? this.searchCols : this.columns.map(function (c) { return c.key; });
        for (var si = 0; si < sc.length; si++) { if (allLabels[sc[si]]) colLabels.push(allLabels[sc[si]]); }
        var condText = condLabels[this.searchCond] || 'содержит';
        chips.push({ label: (colLabels.join(', ') || 'Все') + ' ' + condText + ' «' + this.searchText + '»', type: 'search' });
      }
      var html = '';
      for (var i = 0; i < chips.length; i++) {
        html += '<span class="filter-chip"><span class="filter-chip-text">' + esc(chips[i].label) + '</span><button type="button" class="filter-chip-close" data-filter-type="' + chips[i].type + '" title="Сбросить">✕</button></span>';
      }
      this.filterChipsEl.innerHTML = html;
      var self = this;
      this.filterChipsEl.querySelectorAll('.filter-chip-close').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var type = btn.getAttribute('data-filter-type');
          if (type === 'search') {
            self.searchActive = false;
            self.searchText = '';
            self.currentPage = 1;
            var searchInput = self.formBody.querySelector('#' + self.prefix + '-search-input');
            if (searchInput) searchInput.value = '';
          } else if (type === 'showOnly') {
            self.showOnlyChecked = false;
          }
          self.render();
        });
      });
    }
    if (this.clearSearchBtn) this.clearSearchBtn.style.display = filterActive ? '' : 'none';
  };

  EmbeddedSubTable.prototype._renderPagination = function (total, pages) {
    if (!this.paginationEl) return;
    if (pages <= 1) { this.paginationEl.innerHTML = ''; return; }
    var ph = '';
    var firstDisabled = this.currentPage <= 1 ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
    var lastDisabled = this.currentPage >= pages ? ' aria-disabled="true" style="pointer-events:none;opacity:.5;"' : '';
    ph += '<a class="page-btn" href="#" data-page="1"' + firstDisabled + '>&laquo;</a>';
    var startP = Math.max(1, this.currentPage - 2);
    var endP = Math.min(pages, this.currentPage + 2);
    for (var p = startP; p <= endP; p++) {
      var active = p === this.currentPage ? ' active' : '';
      ph += '<a class="page-btn' + active + '" href="#" data-page="' + p + '">' + p + '</a>';
    }
    ph += '<a class="page-btn" href="#" data-page="' + pages + '"' + lastDisabled + '>&raquo;</a>';
    ph += '<span class="page-info">' + this.currentPage + ' из ' + pages + '</span>';
    this.paginationEl.innerHTML = ph;

    var self = this;
    this.paginationEl.querySelectorAll('a.page-btn').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        self.currentPage = parseInt(a.dataset.page, 10) || 1;
        self.render();
      });
    });
  };

  EmbeddedSubTable.prototype._updateButtons = function () {
    var hasSelection = this.selectedId > 0;
    var af = window.__accessFlags || {};
    if (this.btnAdd) this.btnAdd.disabled = !!(af.insert_flag);
    if (this.btnEdit) this.btnEdit.disabled = !hasSelection || !!(af.change_flag);
    if (this.btnCopy) this.btnCopy.disabled = !hasSelection || !!(af.insert_flag);
    if (this.btnDelete) this.btnDelete.disabled = !hasSelection || !!(af.delete_flag);
  };

  EmbeddedSubTable.prototype._updateCheckedUI = function () {
    var n = this.checkedIds.size;
    if (n === 0 && this.showOnlyChecked) {
      this.showOnlyChecked = false;
      this.currentPage = 1;
    }
    if (this.selWrap) this.selWrap.classList.toggle('visible', n > 0);
    if (this.selCountEl) this.selCountEl.textContent = n;
    this._syncCheckAll();
  };

  EmbeddedSubTable.prototype._syncCheckAll = function () {
    if (!this.checkAllEl) return;
    var cbs = this.tbody.querySelectorAll('.d2-row-check');
    if (cbs.length === 0) { this.checkAllEl.checked = false; this.checkAllEl.indeterminate = false; return; }
    var checked = 0;
    cbs.forEach(function (cb) { if (cb.checked) checked++; });
    this.checkAllEl.checked = checked === cbs.length;
    this.checkAllEl.indeterminate = checked > 0 && checked < cbs.length;
  };

  EmbeddedSubTable.prototype.selectById = function (id) {
    this.selectedId = id;
    var rows = this.tbody.querySelectorAll('.d2-row');
    rows.forEach(function (r) {
      r.classList.toggle('selected', parseInt(r.dataset.id, 10) === id);
    });
    this._updateButtons();
    if (this.onRowSelect) this.onRowSelect(id);
  };

  EmbeddedSubTable.prototype._bindEvents = function () {
    var self = this;

    this.tableEl.addEventListener('change', function (e) {
      if (e.target.classList && e.target.classList.contains('d2-row-check')) {
        e.stopPropagation();
        var id = parseInt(e.target.dataset.id, 10);
        if (e.target.checked) { self.checkedIds.add(id); } else { self.checkedIds.delete(id); }
        self._updateCheckedUI();
        if (self.showOnlyChecked) self.render();
      }
      if (e.target === self.checkAllEl) {
        var wasShowOnly = self.showOnlyChecked;
        if (e.target.checked) {
          var allItems = self.getFilteredData();
          allItems.forEach(function (item) { self.checkedIds.add(item.id); });
        } else {
          self.checkedIds.clear();
        }
        self.tbody.querySelectorAll('.d2-row-check').forEach(function (cb) { cb.checked = e.target.checked; });
        self._updateCheckedUI();
        if (wasShowOnly || self.showOnlyChecked) self.render();
      }
    });

    this.tableEl.addEventListener('click', function (e) {
      var tr = e.target.closest('.d2-row');
      if (!tr) return;
      var id = parseInt(tr.dataset.id, 10);
      if (e.target.classList && e.target.classList.contains('d2-row-check')) return;
      self.selectById(id);
    });

    this.tableEl.addEventListener('dblclick', function (e) {
      var tr = e.target.closest('.d2-row');
      if (!tr) return;
      if (self.onRowDoubleClick) self.onRowDoubleClick(parseInt(tr.dataset.id, 10));
    });

    if (this.btnRefresh) {
      this.btnRefresh.addEventListener('click', function () { self.refresh(); });
    }
  };

  EmbeddedSubTable.prototype._bindKeyboard = function () {
    var self = this;
    var scope = this.tableEl;
    scope.setAttribute('tabindex', '0');
    scope.style.outline = 'none';
    scope.addEventListener('keydown', function (e) {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
      var rows = Array.from(self.tbody.querySelectorAll('.d2-row'));
      if (rows.length === 0) return;
      var curIdx = -1;
      for (var k = 0; k < rows.length; k++) {
        if (parseInt(rows[k].dataset.id, 10) === self.selectedId) { curIdx = k; break; }
      }
      var handled = true;
      var af = window.__accessFlags || {};
      switch (e.key) {
        case 'Insert':
          if (!af.insert_flag && self.btnAdd) self.btnAdd.click();
          break;
        case 'Enter':
          if (!af.change_flag && self.selectedId && self.btnEdit) self.btnEdit.click();
          break;
        case 'Delete':
          if (!af.delete_flag && self.selectedId && self.btnDelete) self.btnDelete.click();
          break;
        case 'ArrowDown':
          if (curIdx < rows.length - 1) self.selectById(parseInt(rows[curIdx + 1].dataset.id, 10));
          break;
        case 'ArrowUp':
          if (curIdx > 0) self.selectById(parseInt(rows[curIdx - 1].dataset.id, 10));
          break;
        case 'Home':
          self.currentPage = 1;
          self.render();
          var homeRows = self.tbody.querySelectorAll('.d2-row');
          if (homeRows.length > 0) self.selectById(parseInt(homeRows[0].dataset.id, 10));
          break;
        case 'End':
          var endTotal = Math.ceil(self.getFilteredData().length / self.pageSize);
          self.currentPage = Math.max(1, endTotal);
          self.render();
          var endRows = self.tbody.querySelectorAll('.d2-row');
          if (endRows.length > 0) self.selectById(parseInt(endRows[endRows.length - 1].dataset.id, 10));
          break;
        case 'PageUp':
          if (self.currentPage > 1) {
            self.currentPage--;
            self.render();
            var upRows = self.tbody.querySelectorAll('.d2-row');
            if (upRows.length > 0) self.selectById(parseInt(upRows[0].dataset.id, 10));
          }
          break;
        case 'PageDown':
          var pg = Math.ceil(self.getFilteredData().length / self.pageSize);
          if (self.currentPage < pg) {
            self.currentPage++;
            self.render();
            var dnRows = self.tbody.querySelectorAll('.d2-row');
            if (dnRows.length > 0) self.selectById(parseInt(dnRows[0].dataset.id, 10));
          }
          break;
        default:
          handled = false;
      }
      if (handled) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
    scope.addEventListener('click', function () { scope.focus(); });
  };

   EmbeddedSubTable.prototype.refresh = function (opts) {
     var self = this;
     if (!this.saveUrl || !this.parentId) return;
     var fd = new FormData();
     fd.set('field', '_list');
     fd.set(this.parentField, String(this.parentId));
     if (this._docType) fd.set('doc_type', String(this._docType));
     fetch(this.saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
       .then(function (r) {
         if (!r.ok) throw new Error('HTTP ' + r.status);
         return r.json();
       })
       .then(function (data) {
         if (Array.isArray(data)) {
           self.data = data;
           if (opts) {
             if (opts.desiredIdx !== undefined) {
               var cp = opts.currentPage || self.currentPage;
               var tp = opts.totalPages || Math.ceil(self.data.length / self.pageSize) || 1;
               if (opts.desiredIdx >= 0 && opts.desiredIdx < self.data.length) {
                 self.selectedId = Number(self.data[opts.desiredIdx].id);
                 self.currentPage = Math.floor(opts.desiredIdx / self.pageSize) + 1;
               } else if (cp < tp) {
                 self.currentPage = cp + 1;
                 self.selectedId = 0;
                 var firstOnPage = (self.currentPage - 1) * self.pageSize;
                 if (firstOnPage < self.data.length) self.selectedId = Number(self.data[firstOnPage].id);
               } else if (opts.desiredIdx - 1 >= 0) {
                 self.selectedId = Number(self.data[opts.desiredIdx - 1].id);
                 self.currentPage = Math.floor((opts.desiredIdx - 1) / self.pageSize) + 1;
               } else if (cp > 1) {
                 self.currentPage = cp - 1;
                 self.selectedId = 0;
               } else {
                 self.selectedId = 0;
               }
             } else if (opts.focusId) {
               var found = false;
               for (var i = 0; i < self.data.length; i++) {
                 if (self.data[i].id == opts.focusId) {
                   self.currentPage = Math.floor(i / self.pageSize) + 1;
                   self.selectedId = Number(opts.focusId);
                   found = true;
                   break;
                 }
               }
               if (!found && self.selectedId > 0 && !self.data.some(function (item) { return Number(item.id) === Number(self.selectedId); })) {
                 var pages = Math.ceil(self.data.length / self.pageSize) || 1;
                 if (self.currentPage > pages) self.currentPage = pages;
                 self.selectedId = 0;
               }
             }
           }
           self.render();
           if (self.onDataChange) self.onDataChange(self.data);
           if (self.totalsCallback && typeof global[self.totalsCallback] === 'function') {
             var tf = new FormData();
             tf.set('field', '_recalc_totals');
             tf.set(self.parentField, String(self.parentId));
             fetch(self.saveUrl, { method: 'POST', body: tf, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
               .then(function (r) { return r.json(); })
               .then(function (td) { if (td && td.ok) global[self.totalsCallback](td); })
               .catch(function () {});
           }
         }
       })
       .catch(function (e) { console.error('[EST] refresh() error', e); });
   };

  EmbeddedSubTable.prototype.deleteById = function (id) {
    if (window.__accessFlags && window.__accessFlags.delete_flag) return;
    var self = this;
    id = Number(id);
    var fd = new FormData();
    fd.set('field', '_delete');
    fd.set('id', String(id));
    fetch(this.saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          var idx = -1;
          for (var i = 0; i < self.data.length; i++) { if (self.data[i].id == id) { idx = i; break; } }
          self.data = self.data.filter(function (item) { return Number(item.id) !== id; });
          self.checkedIds.delete(id);
          if (Number(self.selectedId) === id) {
            var tp = Math.ceil(self.data.length / self.pageSize) || 1;
            if (idx >= 0 && idx < self.data.length) {
              self.selectedId = Number(self.data[idx].id);
            } else if (self.currentPage < tp) {
              self.currentPage++;
              self.selectedId = 0;
              var firstOnPage = (self.currentPage - 1) * self.pageSize;
              if (firstOnPage < self.data.length) self.selectedId = Number(self.data[firstOnPage].id);
            } else if (idx - 1 >= 0 && idx - 1 < self.data.length) {
              self.selectedId = Number(self.data[idx - 1].id);
            } else if (self.currentPage > 1) {
              self.currentPage--;
              self.selectedId = 0;
            } else {
              self.selectedId = 0;
            }
          }
          self.render();
          if (self.onDataChange) self.onDataChange(self.data);
          if (self.totalsCallback && typeof global[self.totalsCallback] === 'function') {
            global[self.totalsCallback](d);
          }
        }
      });
  };

  EmbeddedSubTable.prototype.batchDeleteChecked = function () {
    if (window.__accessFlags && window.__accessFlags.delete_flag) return;
    var self = this;
    var ids = Array.from(this.checkedIds);
    if (ids.length === 0) return;
    if (!confirm('Удалить ' + ids.length + ' отмеченных записей?')) return;
    (function next(i, lastResp) {
      if (i >= ids.length) {
        self.checkedIds = new Set();
        self.selectedId = 0;
        self.render();
        if (self.onDataChange) self.onDataChange(self.data);
        if (lastResp && self.totalsCallback && typeof global[self.totalsCallback] === 'function') {
          global[self.totalsCallback](lastResp);
        }
        return;
      }
      var fd = new FormData();
      fd.set('field', '_delete');
      fd.set('id', String(ids[i]));
      fetch(self.saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          self.data = self.data.filter(function (item) { return Number(item.id) !== ids[i]; });
          next(i + 1, d && d.ok ? d : lastResp);
        })
        .catch(function () { next(i + 1, lastResp); });
    })(0, null);
  };

  EmbeddedSubTable.prototype.openChildForm = function (mode, id) {
    var self = this;
    var af = window.__accessFlags || {};
    if (mode === 'new' || mode === 'copy') {
      if (af.insert_flag) return;
    } else if (mode === 'edit') {
      if (af.change_flag) return;
    } else if (mode === 'delete') {
      if (af.delete_flag) return;
    }
    var proceed = function () {
      var url = self.childFormUrl + (self.childFormUrl.indexOf('?') >= 0 ? '&' : '?') + 'mode=' + mode;
      if (id) url += '&id=' + id;
      url += '&' + self.parentField + '=' + self.parentId;
      var openFn = window.openFormModal || window.__openFormModal;
      if (typeof openFn === 'function') {
        if (self.tableEl && self.data) {
          try { self.tableEl.dataset.items = JSON.stringify(self.data); } catch (e) {}
        }
        openFn(url, {
          onRestore: function (data) {
            if (data && data.ok) {
              var tbl = window['__' + self.prefix + 'Table'] || self;
              if (self.totalsCallback && typeof global[self.totalsCallback] === 'function') global[self.totalsCallback](data);
              if (tbl) {
                if (data.sgroup_list) {
                  tbl.data = data.sgroup_list;
                  if (data._deleted) {
                    if (tbl.data.length > 0) {
                      var idx = Math.min(tbl.data.length - 1, 0);
                      tbl.selectedId = Number(tbl.data[idx].id);
                    } else {
                      tbl.selectedId = 0;
                    }
                  } else if (data.id) {
                    tbl.selectedId = Number(data.id);
                  }
                  tbl.render();
                } else if (data._deleted) {
                  var ddata = tbl.data || self.data;
                  var idx = -1;
                  for (var i = 0; i < ddata.length; i++) { if (ddata[i].id == data.id) { idx = i; break; } }
                  tbl.refresh({ desiredIdx: idx, currentPage: tbl.currentPage || self.currentPage, totalPages: Math.ceil(ddata.length / (tbl.pageSize || self.pageSize)) || 1 });
                } else {
                  tbl.refresh({ focusId: data.id || 0 });
                }
              }
            }
          },
          activeId: String(self.selectedId || ''),
          _estSelectedId: self.selectedId || 0
        });
      }
    };

    if (self.parentId <= 0) {
      var form = self.formBody.querySelector('form[data-form-modal]');
      if (!form) return;
      var fd = new FormData(form);
      fd.set('ajax', '1');
      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn && submitBtn.name) fd.set(submitBtn.name, submitBtn.value || '1');
      fetch(form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok && data.id) {
            self.parentId = data.id;
            var idEl = form.querySelector('input[name="id"]');
            if (idEl) idEl.value = String(data.id);
            proceed();
          }
        });
    } else {
      proceed();
    }
  };

  global.EmbeddedSubTable = { create: function (config) { return new EmbeddedSubTable(config); } };
})(window);
