(function () {
  'use strict';
  
  var FormModalCore = {
    appendAjax: function(url) {
      return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1&_=' + Date.now();
    },
    
    bindFormTabTrap: function(form) {
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
    },
    
    focusFirstField: function(root) {
      var f = root.querySelector(
        'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
        'textarea:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
        'select:not([tabindex="-1"]):not([disabled])'
      );
      if (f) { setTimeout(function () { f.focus(); if (f.select) f.select(); }, 0); return; }
      var btn = root.querySelector('button[type="submit"]:not([tabindex="-1"]):not([disabled])');
      if (btn) setTimeout(function () { btn.focus(); }, 0);
    },
    
    setFocusAfterSave: function(params, form, data) {
      // Если сервер говорит делать редирект, игнорируем
      if (data.redirect) {
        return;
      }
      
      var mode = form.querySelector('input[name="mode"]').value || '';
      if (mode === 'new' || mode === 'copy') {
        if (data.id) {
          params.set('focus', String(data.id));
          if (data.page !== undefined) {
            params.set('page', String(data.page));
          }
        } else {
          params.delete('focus');
        }
      } else if (mode === 'edit') {
        var eid = parseInt(form.querySelector('input[name="id"]').value || '0', 10) || 0;
        if (eid > 0) params.set('focus', String(eid));
        else params.delete('focus');
      } else if (mode === 'delete') {
        var did = parseInt(form.querySelector('input[name="id"]').value || '0', 10) || 0;
        var rows = Array.from(document.querySelectorAll('tr[data-row-id]'));
        var idx = rows.findIndex(function(tr) { return parseInt(tr.dataset.rowId, 10) === did; });
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
    },

    /* Авто-сохранение новой записи при переключении на указанную вкладку.
     * При создании документа (mode=new/copy, id=0) и клике на вкладку из idxList
     * (например, "Товары") форма сохраняется в режиме auto_save=1, после чего
     * открывается в режиме edit на этой же вкладке (сервер возвращает {ok,id}).
     * Если запись уже сохранена (id>0) — работает обычное переключение вкладок. */
    initTabAutoSave: function (idxList) {
      if (typeof idxList === 'number') idxList = [idxList];
      if (!Array.isArray(idxList)) idxList = [idxList];
      if (!idxList.length) return;

      function switchTab(ix) {
        var hs = document.querySelectorAll('.tab-header');
        var ps = document.querySelectorAll('.tab-pane');
        var hd = null, pane = null;
        for (var i = 0; i < hs.length; i++) hs[i].classList.remove('active');
        for (var j = 0; j < ps.length; j++) ps[j].classList.remove('active');
        for (var k = 0; k < hs.length; k++) { if (parseInt(hs[k].getAttribute('data-tab-index') || '', 10) === ix) { hd = hs[k]; break; } }
        for (var m = 0; m < ps.length; m++) { if (parseInt(ps[m].getAttribute('data-tab-index') || '', 10) === ix) { pane = ps[m]; break; } }
        if (hd) hd.classList.add('active');
        if (pane) pane.classList.add('active');
      }

      var headers = Array.prototype.slice.call(document.querySelectorAll('.tab-header'));
      if (!headers.length) return;

      headers.forEach(function (h) {
        var idx = parseInt(h.getAttribute('data-tab-index') || '', 10);
        if (idxList.indexOf(idx) === -1) return;

        h.addEventListener('click', function (e) {
          var form = document.querySelector('form[data-form-modal]');
          if (!form) return;
          var idEl = form.querySelector('input[name="id"]');
          var modeEl = form.querySelector('input[name="mode"]');
          var id = idEl ? (parseInt(idEl.value, 10) || 0) : 0;
          var mode = modeEl ? modeEl.value : '';
          if (id > 0 || (mode !== 'new' && mode !== 'copy')) return;

          e.preventDefault();
          e.stopImmediatePropagation();

          var action = (form.getAttribute('action') || '').split(/\?|#/)[0] || location.pathname;
          var fd = new FormData(form);
          fd.set('auto_save', '1');

          fetch(action + (action.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (!(data && data.ok && data.id)) { switchTab(idx); return; }
              var openFn = window.openFormModal || window.__openFormModal || function (u) { location.href = u; };
              openFn(action + '?mode=edit&id=' + data.id);
              var t0 = Date.now();
              var switched = false;
              var trySwitch = function () {
                if (switched) return;
                var h2 = document.querySelector('.tab-header[data-tab-index="' + idx + '"]');
                if (h2 && document.contains(h2)) {
                  h2.click();
                  var pane = document.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
                  if (pane && pane.classList.contains('active')) { switched = true; return; }
                }
                if (Date.now() - t0 < 4000) { setTimeout(trySwitch, 200); }
                else { switchTab(idx); }
              };
              setTimeout(trySwitch, 300);
            })
            .catch(function () { switchTab(idx); });
        });
      });
    }
  };
  
  window.FormModalCore = FormModalCore;

  /* Обновление флагов документа (rezerv_flag/voz_flag) после сохранения товара аренды.
   * При сохранении docum2 (arenda2_form.php) сервер возвращает doc_voz_flag/doc_rezerv_flag.
   * Обновляем чекбоксы на родительской форме (arenda_form.php). */
  function updateParentDocFlags(data) {
    if (!data || typeof data !== 'object') return;
    if (data.doc_voz_flag === undefined && data.doc_rezerv_flag === undefined) return;
    var parentDoc = window.__parentFormDocId || document.querySelector('input[name="docum_id"]');
    if (!parentDoc) return;
    var docId = parseInt(parentDoc.value || '0', 10) || 0;
    if (docId <= 0) return;
    var vozChk = document.querySelector('input[name="voz_flag"]');
    var rezChk = document.querySelector('input[name="rezerv_flag"]');
    if (vozChk && data.doc_voz_flag !== undefined) {
      vozChk.checked = data.doc_voz_flag === 1;
      var lbl = vozChk.closest('label');
      if (lbl) lbl.style.opacity = data.doc_voz_flag === 1 ? '1' : '';
    }
    if (rezChk && data.doc_rezerv_flag !== undefined) {
      rezChk.checked = data.doc_rezerv_flag === 1;
      var lbl2 = rezChk.closest('label');
      if (lbl2) lbl2.style.opacity = data.doc_rezerv_flag === 1 ? '1' : '';
    }
  }

  window.__arenda2UpdateParentFlags = updateParentDocFlags;
})();
