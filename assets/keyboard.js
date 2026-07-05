(function (global) {
  'use strict';

  const bindings = [];
  let isBlockedFn = null;

  function defaultIsBlocked() {
    const el = document.activeElement;
    if (el) {
      const tag = el.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable) return true;
    }
    const formModal = document.getElementById('formModal');
    if (formModal && formModal.classList.contains('open')) return true;
    const openEls = document.querySelectorAll(
      '.columns-panel.open, .search-cond-panel.open, ' +
      '.sort-pop.open, .sort-modal-backdrop.open, ' +
      '.export-modal-backdrop.open, .cell-edit-panel'
    );
    return openEls.length > 0;
  }

  function onKey(e) {
    if (isBlockedFn && isBlockedFn(e)) return;
    for (let i = 0; i < bindings.length; i++) {
      const b = bindings[i];
      const ks = b.keySpec;
      if (e.key !== ks.key) continue;
      if (ks.ctrl  !== !!e.ctrlKey)  continue;
      if (ks.shift !== !!e.shiftKey) continue;
      if (ks.alt   !== !!e.altKey)   continue;
      if (ks.meta  !== !!e.metaKey)  continue;
      e.preventDefault();
      try { b.handler(e); } catch (err) { console.error('[keyboard] handler error', err); }
      return;
    }
  }

  function init(opts) {
    if (Keyboard._inited) return;
    Keyboard._inited = true;
    opts = opts || {};
    isBlockedFn = opts.isBlocked || defaultIsBlocked;
    if (opts.attach !== false) document.addEventListener('keydown', onKey);
  }

  function bind(keySpec, handler) {
    const spec = {
      key:   keySpec.key,
      ctrl:  !!keySpec.ctrl,
      shift: !!keySpec.shift,
      alt:   !!keySpec.alt,
      meta:  !!keySpec.meta
    };
    bindings.push({ keySpec: spec, handler });
  }

  function clear() { bindings.length = 0; }

  function isTyping() {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
  }

  global.Keyboard = { init, bind, clear, isTyping };
})(window);
