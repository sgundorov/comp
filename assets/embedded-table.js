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

  function EmbeddedTable(config) {
    if (!config || !config.tableEl) throw new Error('EmbeddedTable: tableEl required');
    this.tableEl = config.tableEl;
    this.tbody = this.tableEl.querySelector('tbody');
    if (!this.tbody) throw new Error('EmbeddedTable: tbody not found');

    this.columns = config.columns || [];
    this.renderRow = config.renderRow || null;
    this.saveUrl = config.saveUrl || '';
    this.onRowClick = config.onRowClick || null;
    this.onRowDblClick = config.onRowDblClick || null;
    this.onSelectionChange = config.onSelectionChange || null;
    this.onCheckedChange = config.onCheckedChange || null;

    this._data = config.data || [];
    this._selectedId = config.selectedId || 0;
    this._checkedIds = new Set();

    this._searchActive = false;
    this._searchText = '';
    this._searchCond = 'contains';
    this._searchCols = config.searchCols || [];
    this._searchKeyMap = {};
    (this._searchCols || []).forEach(function (c) { this._searchKeyMap[c.key] = true; }.bind(this));
    this.filterBanner = config.filterBannerEl ? (typeof config.filterBannerEl === 'string' ? document.querySelector(config.filterBannerEl) : config.filterBannerEl) : null;
    this.selWrap = config.selWrapEl ? (typeof config.selWrapEl === 'string' ? document.querySelector(config.selWrapEl) : config.selWrapEl) : null;
    this.selCountEl = config.selCountEl ? (typeof config.selCountEl === 'string' ? document.querySelector(config.selCountEl) : config.selCountEl) : null;
    this.checkAllEl = config.checkAllEl ? (typeof config.checkAllEl === 'string' ? this.tableEl.querySelector(config.checkAllEl) : config.checkAllEl) : null;

    var self = this;

    this._filteredIds = function () {
      if (!self._searchActive || !self._searchText) return null;
      var st = self._searchText.toLowerCase();
      var keys = Object.keys(self._searchKeyMap);
      return self._data.filter(function (item) {
        return keys.some(function (key) {
          var val = String(item[key] != null ? item[key] : '').toLowerCase();
          var cond = self._searchCond;
          if (cond === 'contains') return val.indexOf(st) >= 0;
          if (cond === 'not_contains') return val.indexOf(st) < 0;
          if (cond === 'starts_with') return val.indexOf(st) === 0;
          if (cond === 'ends_with') return val.indexOf(st) === val.length - st.length;
          if (cond === 'equals') return val === st;
          if (cond === 'not_equals') return val !== st;
          return false;
        });
      });
    };

    this._render();
    this._bindEvents();
    this._bindKeyboard();
    this._updateBatchUI();
    var self = this;
    setTimeout(function () { self.selectFirst(); }, 0);
  }

  EmbeddedTable.prototype.setData = function (data) {
    this._data = data || [];
    this._render();
  };

  EmbeddedTable.prototype.getData = function () {
    return this._data;
  };

  EmbeddedTable.prototype.setSearch = function (active, text, cond) {
    this._searchActive = active;
    this._searchText = text || '';
    if (cond !== undefined) this._searchCond = cond;
    this._render();
  };

  EmbeddedTable.prototype.selectFirst = function () {
    var rows = this.tbody.querySelectorAll('[data-row-id]');
    if (rows.length > 0) {
      var id = parseInt(rows[0].dataset.rowId, 10);
      this._selectRow(id, rows[0]);
    }
  };

  EmbeddedTable.prototype.selectById = function (id) {
    var tr = this.tbody.querySelector('[data-row-id="' + id + '"]');
    if (tr) this._selectRow(id, tr);
  };

  EmbeddedTable.prototype.getSelectedId = function () {
    return this._selectedId;
  };

  EmbeddedTable.prototype.getCheckedIds = function () {
    return this._checkedIds;
  };

  EmbeddedTable.prototype.getFilteredData = function () {
    var f = this._filteredIds();
    return f !== null ? f : this._data;
  };

  EmbeddedTable.prototype.refresh = function (fetchUrl) {
    if (!fetchUrl) return;
    var self = this;
    fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.html) {
          var tmp = document.createElement('div');
          tmp.innerHTML = d.html;
          var nt = tmp.querySelector('[data-' + self.tableEl.dataset.embeddedTable + '-table]');
          if (!nt) nt = tmp.querySelector('[' + self.tableEl.dataset.embeddedTable + ']');
          var items = nt ? nt.dataset.items : null;
          if (items) {
            try { self._data = JSON.parse(items); } catch (e) {}
            self._render();
          }
        }
      });
  };

  EmbeddedTable.prototype._selectRow = function (id, tr) {
    this._selectedId = id;
    this.tbody.querySelectorAll('[data-row-id].selected').forEach(function (r) { r.classList.remove('selected'); });
    if (tr) tr.classList.add('selected');
    if (this.onSelectionChange) this.onSelectionChange(id);
  };

  EmbeddedTable.prototype._render = function () {
    if (!this.tbody) return;
    var self = this;
    var filtered = self._filteredIds();
    var items = filtered !== null ? filtered : this._data;

    if (items.length === 0) {
      self.tbody.innerHTML = '<tr><td colspan="' + Math.max(1, self.columns.length) + '" style="text-align:center;padding:20px;color:var(--muted)">Нет записей</td></tr>';
    } else if (self.renderRow) {
      var html = '';
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var sel = item.id === this._selectedId ? ' selected' : '';
        html += this.renderRow(item, sel, { esc: esc, fmtNum: fmtNum, hl: hl, searchActive: self._searchActive, searchText: self._searchText });
      }
      self.tbody.innerHTML = html;
    }

    self.tbody.querySelectorAll('[data-row-id]').forEach(function (tr) {
      tr.addEventListener('click', function (e) {
        if (e.target.closest('input[type="checkbox"]')) return;
        var id = parseInt(tr.dataset.rowId, 10);
        if (id && self._selectedId !== id) {
          self._selectRow(id, tr);
        }
        if (self.onRowClick) self.onRowClick(id, tr);
      });
      tr.addEventListener('dblclick', function () {
        var id = parseInt(tr.dataset.rowId, 10);
        if (id && self.onRowDblClick) self.onRowDblClick(id);
      });
    });

    self._syncChecks();
  };

  EmbeddedTable.prototype._bindEvents = function () {
    var self = this;

    if (this.checkAllEl) {
      this.checkAllEl.addEventListener('change', function () {
        var rows = self.tbody.querySelectorAll('[data-row-id]');
        if (self.checkAllEl.checked) {
          rows.forEach(function (tr) { self._checkedIds.add(parseInt(tr.dataset.rowId, 10)); });
        } else {
          self._checkedIds.clear();
        }
        self._syncChecks();
        self._updateBatchUI();
      });
    }

    this.tbody.addEventListener('change', function (e) {
      var cb = e.target.closest('input[type="checkbox"]');
      if (!cb || !cb.closest('[data-row-id]')) return;
      var tr = cb.closest('[data-row-id]');
      var id = parseInt(tr.dataset.rowId, 10);
      if (!id) return;
      if (cb.checked) self._checkedIds.add(id); else self._checkedIds.delete(id);
      if (self.checkAllEl) {
        var all = self.tbody.querySelectorAll('[data-row-id]');
        self.checkAllEl.checked = all.length > 0 && all.length === self._checkedIds.size;
      }
      self._updateBatchUI();
    });
  };

  EmbeddedTable.prototype._bindKeyboard = function () {
    var self = this;
    if (typeof Keyboard === 'undefined') return;
    if (!Keyboard._inited) Keyboard.init();

    Keyboard.bind({ key: 'ArrowUp' }, function () {
      var rows = Array.from(self.tbody.querySelectorAll('[data-row-id]'));
      if (rows.length === 0) return;
      var idx = rows.findIndex(function (tr) { return parseInt(tr.dataset.rowId, 10) === self._selectedId; });
      if (idx > 0) {
        var tr = rows[idx - 1];
        var id = parseInt(tr.dataset.rowId, 10);
        self._selectRow(id, tr);
        tr.scrollIntoView({ block: 'nearest' });
      }
    });

    Keyboard.bind({ key: 'ArrowDown' }, function () {
      var rows = Array.from(self.tbody.querySelectorAll('[data-row-id]'));
      if (rows.length === 0) return;
      var idx = rows.findIndex(function (tr) { return parseInt(tr.dataset.rowId, 10) === self._selectedId; });
      if (idx < 0) { self.selectFirst(); return; }
      if (idx < rows.length - 1) {
        var tr = rows[idx + 1];
        var id = parseInt(tr.dataset.rowId, 10);
        self._selectRow(id, tr);
        tr.scrollIntoView({ block: 'nearest' });
      }
    });

    Keyboard.bind({ key: 'Home' }, function () {
      self.selectFirst();
      var tr = self.tbody.querySelector('[data-row-id].selected');
      if (tr) tr.scrollIntoView({ block: 'nearest' });
    });

    Keyboard.bind({ key: 'End' }, function () {
      var rows = Array.from(self.tbody.querySelectorAll('[data-row-id]'));
      if (rows.length > 0) {
        var tr = rows[rows.length - 1];
        self._selectRow(parseInt(tr.dataset.rowId, 10), tr);
        tr.scrollIntoView({ block: 'nearest' });
      }
    });
  };

  EmbeddedTable.prototype._syncChecks = function () {
    var self = this;
    this.tbody.querySelectorAll('[data-row-id]').forEach(function (tr) {
      var cb = tr.querySelector('input[type="checkbox"]');
      if (cb) {
        var id = parseInt(tr.dataset.rowId, 10);
        cb.checked = self._checkedIds.has(id);
      }
    });
  };

  EmbeddedTable.prototype._updateBatchUI = function () {
    var n = this._checkedIds.size;
    if (this.selCountEl) this.selCountEl.textContent = n;
    if (this.selWrap) this.selWrap.classList.toggle('visible', n > 0);
    if (this.onCheckedChange) this.onCheckedChange(this._checkedIds);
  };

  EmbeddedTable.prototype.clearChecked = function () {
    this._checkedIds.clear();
    if (this.checkAllEl) this.checkAllEl.checked = false;
    this._syncChecks();
    this._updateBatchUI();
  };

  EmbeddedTable.prototype.invertChecked = function () {
    var self = this;
    var rows = this.tbody.querySelectorAll('[data-row-id]');
    rows.forEach(function (tr) {
      var id = parseInt(tr.dataset.rowId, 10);
      if (self._checkedIds.has(id)) self._checkedIds.delete(id); else self._checkedIds.add(id);
    });
    if (this.checkAllEl) this.checkAllEl.checked = rows.length > 0 && rows.length === this._checkedIds.size;
    this._syncChecks();
    this._updateBatchUI();
  };

  function hl(text, opts) {
    if (!opts || !opts.searchActive || !opts.searchText) return esc(text);
    var str = String(text == null ? '' : text);
    var st = opts.searchText;
    var idx = str.toLowerCase().indexOf(st.toLowerCase());
    if (idx === -1) return esc(str);
    return esc(str.substring(0, idx)) + '<span class="hl">' + esc(str.substring(idx, idx + st.length)) + '</span>' + esc(str.substring(idx + st.length));
  }

  var instances = [];

  EmbeddedTable.create = function (config) {
    var t = new EmbeddedTable(config);
    instances.push(t);
    return t;
  };

  EmbeddedTable.getInstances = function () { return instances; };

  global.EmbeddedTable = EmbeddedTable;
})(window);
