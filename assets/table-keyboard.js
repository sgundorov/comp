(function (global) {
  'use strict';

  function bindTableKeyboardShortcuts(config) {
    if (!config) throw new Error('config required');
    const fp = config.formPrefix;
    if (!fp) throw new Error('formPrefix required');
    const r = config.rowSel;
    const cp = config.currentPage;
    const cps = config.currentPages;
    const nav = config.navigate;
    const tw = config.tableWrapEl;
    const of = function() { return config.onOpenForm || window.__openFormModal; };
    const extraP = config.formExtraParams || '';
    const af = config.accessFlags || window.__accessFlags || {};

    Keyboard.init();
    if (!af.insert_flag) Keyboard.bind({ key: 'Insert' }, function () { of()(fp + '.php?mode=new' + extraP); });
    if (!af.insert_flag) Keyboard.bind({ key: 'Insert', ctrl: true }, function () { const btn = document.getElementById('rowCopyBtn'); if (btn) btn.click(); });
    if (!af.change_flag) Keyboard.bind({ key: 'Enter' },   function () { const id = r.getSelectedId(); if (id) of()(fp + '.php?mode=edit&id=' + id + extraP); });
    if (!af.delete_flag) Keyboard.bind({ key: 'Delete' },  function () { const id = r.getSelectedId(); if (id) of()(fp + '.php?mode=delete&id=' + id + extraP); });
    Keyboard.bind({ key: 'ArrowUp' },   function () { r.selectUp(); });
    Keyboard.bind({ key: 'ArrowDown' }, function () { r.selectDown(); });
    Keyboard.bind({ key: 'Home' },      function () { r.selectFirst(); });
    Keyboard.bind({ key: 'End' },       function () { r.selectLast(); });

    Keyboard.bind({ key: 'ArrowLeft' }, function () {
      if (!tw) return;
      var ths = Array.from(document.querySelectorAll('.data-table thead tr:first-child th:not(.col-check)'));
      if (ths.length < 4) return;
      var collapsed = parseInt(tw.dataset.collapsed || '0', 10);
      var maxCollapsible = ths.length - 3;
      if (collapsed >= maxCollapsible) return;
      var idx = 2 + collapsed;
      var cls = ths[idx].className.match(/col-\S+/);
      if (!cls) return;
      var col = document.querySelector('colgroup col.' + cls[0]);
      if (col) col.style.visibility = 'collapse';
      collapsed++;
      tw.dataset.collapsed = String(collapsed);
    });

    Keyboard.bind({ key: 'ArrowRight' }, function () {
      if (!tw) return;
      var ths = Array.from(document.querySelectorAll('.data-table thead tr:first-child th:not(.col-check)'));
      if (ths.length < 4) return;
      var collapsed = parseInt(tw.dataset.collapsed || '0', 10);
      if (collapsed <= 0) return;
      var idx = 2 + collapsed - 1;
      var cls = ths[idx].className.match(/col-\S+/);
      if (!cls) return;
      var col = document.querySelector('colgroup col.' + cls[0]);
      if (col) col.style.visibility = '';
      collapsed--;
      tw.dataset.collapsed = collapsed > 0 ? String(collapsed) : '';
    });

    Keyboard.bind({ key: 'PageUp' }, function () {
      if (cp > 1) nav(function (p) { p.set('page', String(cp - 1)); p.set('focus', 'last'); });
    });
    Keyboard.bind({ key: 'PageDown' }, function () {
      if (cp < cps) nav(function (p) { p.set('page', String(cp + 1)); p.set('focus', 'first'); });
    });
    Keyboard.bind({ key: 'Home', ctrl: true }, function () {
      nav(function (p) { p.set('page', '1'); p.set('focus', 'first'); });
    });
    Keyboard.bind({ key: 'End', ctrl: true }, function () {
      nav(function (p) { p.set('page', String(cps)); p.set('focus', 'last'); });
    });
  }

  global.bindTableKeyboardShortcuts = bindTableKeyboardShortcuts;
})(window);
