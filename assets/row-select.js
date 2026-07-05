(function (global) {
  'use strict';

  function init(opts) {
    const tbody   = opts.tbody;
    const onChange = opts.onChange || function () {};
    const rowClass = opts.rowClass || 'selected';
    var currentPage  = opts.currentPage  || 0;
    var totalPages   = opts.totalPages   || 0;
    var navigateFn   = opts.navigate     || null;
    let selectedId = 0;

    function getRows() {
      return tbody ? Array.from(tbody.querySelectorAll('tr[data-row-id]')) : [];
    }
    function getId(tr) { return parseInt(tr.dataset.rowId || '0', 10) || 0; }
    function findIndex(id) {
      const rows = getRows();
      for (let i = 0; i < rows.length; i++) if (getId(rows[i]) === id) return i;
      return -1;
    }
    function applyClass() {
      getRows().forEach(function (tr) { tr.classList.toggle(rowClass, getId(tr) === selectedId); });
    }

    function selectById(id, scroll) {
      const newId = id ? parseInt(id, 10) || 0 : 0;
      selectedId = newId;
      applyClass();
      if (scroll) {
        const tr = getRows().find(function (r) { return getId(r) === selectedId; });
        if (tr) tr.scrollIntoView({ block: 'nearest' });
      }
      onChange(selectedId);
      return true;
    }

    function selectByIndex(idx) {
      const rows = getRows();
      if (idx < 0 || idx >= rows.length) return false;
      selectById(getId(rows[idx]), true);
      return true;
    }

    function selectUp() {
      const idx = findIndex(selectedId);
      if (idx > 0) return selectByIndex(idx - 1);
      if (idx === 0) {
        if (opts.onFirstRowAtTop) opts.onFirstRowAtTop();
        else if (navigateFn && currentPage > 1) navigateFn(function (p) { p.set('page', String(currentPage - 1)); p.set('focus', 'last'); });
      }
      return false;
    }

    function selectDown() {
      const idx = findIndex(selectedId);
      const rows = getRows();
      if (idx >= 0 && idx < rows.length - 1) return selectByIndex(idx + 1);
      if (idx === rows.length - 1) {
        if (opts.onLastRowAtBottom) opts.onLastRowAtBottom();
        else if (navigateFn && currentPage < totalPages) navigateFn(function (p) { p.set('page', String(currentPage + 1)); p.set('focus', 'first'); });
      }
      return false;
    }

    function selectFirst() { return selectByIndex(0); }
    function selectLast()  { const rows = getRows(); return rows.length ? selectByIndex(rows.length - 1) : false; }
    function clear() { if (selectedId !== 0) selectById(0); }

    if (tbody) {
      tbody.addEventListener('click', function (e) {
        if (e.target.closest('input[type="checkbox"]')) return;
        const tr = e.target.closest('tr[data-row-id]');
        if (!tr) return;
        selectById(getId(tr));
      });
    }

    return {
      getSelectedId: function () { return selectedId; },
      selectById: selectById,
      selectByIndex: selectByIndex,
      selectUp: selectUp,
      selectDown: selectDown,
      selectFirst: selectFirst,
      selectLast: selectLast,
      clear: clear,
      getRows: getRows
    };
  }

  global.RowSelect = { init };
})(window);
