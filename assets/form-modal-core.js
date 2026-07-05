(function () {
  'use strict';
  function appendAjax(url) {
    return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1';
  }
  function bindFormTabTrap(form) {
    if (!form) return;
    var els = Array.from(form.querySelectorAll(
      'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
      'button:not([tabindex="-1"]):not([disabled]),' +
      'a[href]:not([tabindex="-1"]),' +
      'textarea:not([tabindex="-1"]):not([readonly]),' +
      'select:not([tabindex="-1"]):not([disabled])'
    )).filter(function (el) { return el.offsetParent !== null; });
    if (els.length < 2) return;
    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var idx = els.indexOf(document.activeElement);
      if (idx === -1) return;
      e.preventDefault();
      if (e.shiftKey) {
        els[(idx - 1 + els.length) % els.length].focus();
      } else {
        els[(idx + 1) % els.length].focus();
      }
    });
  }
  function focusFirstField(root) {
    var f = root.querySelector(
      'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
      'textarea:not([tabindex="-1"]):not([readonly]),' +
      'select:not([tabindex="-1"]):not([disabled])'
    );
    if (f) { setTimeout(function () { f.focus(); if (f.select) f.select(); }, 0); return; }
    var btn = root.querySelector('button[type="submit"]:not([tabindex="-1"]):not([disabled])');
    if (btn) setTimeout(function () { btn.focus(); }, 0);
  }
  function handleLookupAdd(addLink, stashFn, openFn) {
    var target = addLink.getAttribute('data-lookup-add');
    if (!target) return;
    stashFn(function (data, bodyEl) {
      if (data && data.id && data.name) {
        var container = bodyEl.querySelector('[data-lookup="' + target + '"]');
        if (!container) return;
        var idEl   = container.querySelector('[data-lookup-id]');
        var nameEl = container.querySelector('.lookup-input');
        if (idEl)   idEl.value   = String(data.id);
        if (nameEl) nameEl.value = data.name;
        var items = [];
        try { items = JSON.parse(container.getAttribute('data-countries') || '[]'); } catch (e) {}
        if (!items.some(function (c) { return c.id === data.id; })) {
          items.push({ id: data.id, name: data.name });
          items.sort(function (a, b) { return a.name.localeCompare(b.name, 'ru'); });
          container.setAttribute('data-countries', JSON.stringify(items));
        }
      }
    });
    openFn(addLink.getAttribute('href') || target + '_form.php?mode=new');
  }

  function setFocusAfterSave(params, form, data) {
    var mode = (form.querySelector('input[name="mode"]') || {}).value || '';
    if (mode === 'new' || mode === 'copy') {
      if (data.id) {
        params.set('focus', String(data.id));
        if (data.page) params.set('page', String(data.page));
        else params.delete('page');
      } else {
        params.delete('focus');
      }
    } else if (mode === 'edit') {
      var eid = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
      if (eid > 0) params.set('focus', String(eid));
      else params.delete('focus');
    } else if (mode === 'delete') {
      var did = parseInt((form.querySelector('input[name="id"]') || {}).value || '0', 10) || 0;
      var rows = Array.from(document.querySelectorAll('tr[data-row-id]'));
      var idx = rows.findIndex(function (tr) { return parseInt(tr.dataset.rowId, 10) === did; });
      var tb = (document.querySelector('.toolbar') || {}).dataset || {};
      var cp = parseInt(tb.page || '1', 10);
      var tp = parseInt(tb.pages || '1', 10);
      if (idx >= 0) {
        if (idx + 1 < rows.length) {
          params.set('focus', String(parseInt(rows[idx + 1].dataset.rowId, 10)));
        } else if (cp < tp) {
          params.set('page', String(cp + 1));
          params.set('focus', 'first');
        } else if (idx - 1 >= 0) {
          params.set('focus', String(parseInt(rows[idx - 1].dataset.rowId, 10)));
        } else if (cp > 1) {
          params.set('page', String(cp - 1));
          params.delete('focus');
        } else {
          params.delete('focus');
        }
      } else {
        params.delete('focus');
      }
    } else {
      params.delete('focus');
    }
  }

  window.FormModalCore = {
    appendAjax: appendAjax,
    bindFormTabTrap: bindFormTabTrap,
    focusFirstField: focusFirstField,
    handleLookupAdd: handleLookupAdd,
    setFocusAfterSave: setFocusAfterSave
  };
})();
