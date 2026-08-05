(function (global) {
  'use strict';

  const SVG_PENCIL = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
  const IMG_SAVE   = '<img src="img/ok.png" alt="" />';
  const IMG_CANCEL = '<img src="img/cancel.png" alt="" />';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function init(opts) {
    const cfg = {
      tbody:       opts.tbody || document.querySelector('table tbody'),
      fields:      opts.fields || {},
      saveUrl:     opts.saveUrl,
      getLookupData:  opts.getLookupData || function () { return []; },
      validate:       opts.validate || function (field, value) { return null; },
      onOpenForm:     opts.onOpenForm || null,
      onSaveSuccess:  opts.onSaveSuccess || null
    };
    if (!cfg.tbody || !cfg.saveUrl) return;

    let currentPencilTd = null;
    let currentPanel = null;
    let currentField = null;
    let currentTd = null;
    let currentRowId = 0;
    let pencilScrollCleanup = null;

    function removePencil() {
      if (pencilScrollCleanup) { pencilScrollCleanup(); pencilScrollCleanup = null; }
      if (currentPencilTd) {
        const ex = currentPencilTd.querySelector(':scope > .cell-edit-icon');
        if (ex) ex.remove();
        currentPencilTd = null;
      }
    }

    function positionPencil(btn, td) {
      const tdRect = td.getBoundingClientRect();
      const wrap = td.closest('.table-wrap');
      btn.style.position = 'fixed';
      btn.style.top  = (tdRect.top + 2) + 'px';
      if (wrap) {
        var wrapRect = wrap.getBoundingClientRect();
        const row = td.parentElement;
        const visibleCells = Array.from(row.children).filter(function (c) { return c.offsetWidth > 0; });
        var isLast = visibleCells.length > 0 && td === visibleCells[visibleCells.length - 1];
        var left;
        if (isLast || tdRect.right > wrapRect.right) {
          left = wrapRect.right - 24;
        } else {
          left = tdRect.right - 24;
        }
        btn.style.left = left + 'px';
      } else {
        btn.style.left = (tdRect.right - 24) + 'px';
      }
      btn.style.right = 'auto';
    }

    function showPencil(td) {
      if (currentPanel) return;
      if (currentPencilTd === td) return;
      removePencil();
      currentPencilTd = td;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cell-edit-icon';
      btn.title = 'Редактировать';
      btn.innerHTML = SVG_PENCIL;
      btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (currentPanel && currentTd === td) {
          closePanel();
          return;
        }
        openPanel(td);
      });
      td.appendChild(btn);
      positionPencil(btn, td);
      const wrap = td.closest('.table-wrap');
      if (wrap) {
        const onScroll = function () { positionPencil(btn, td); };
        wrap.addEventListener('scroll', onScroll);
        pencilScrollCleanup = function () { wrap.removeEventListener('scroll', onScroll); };
      }
    }

    function closePanel() {
      if (currentPanel) {
        currentPanel.remove();
        currentPanel = null;
        currentField = null;
        currentTd = null;
        currentRowId = 0;
      }
    }

    function positionPanel(panel, td) {
      const r = td.getBoundingClientRect();
      const ph = panel.offsetHeight;
      const spaceBelow = window.innerHeight - r.bottom;
      if (spaceBelow >= ph + 4 || r.top < ph + 4) panel.style.top = (r.bottom + 4) + 'px';
      else                                         panel.style.top = (r.top - ph - 4) + 'px';
      let left = r.left;
      const pw = panel.offsetWidth;
      if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
      if (left < 8) left = 8;
      panel.style.left = left + 'px';
    }

    function buildLookup(panel, root, currentRawId, fieldName) {
      const api = global.bindLookup({
        root: root,
        data: cfg.getLookupData(fieldName || ''),
        readonly: true,
        positionPop: function (pop, input, rootEl) {
          const ir = input.getBoundingClientRect();
          const pr = panel.getBoundingClientRect();
          pop.style.left = ir.left + 'px';
          pop.style.width = ir.width + 'px';
          const ph = pop.offsetHeight;
          const spaceBelow = window.innerHeight - pr.bottom;
          if (spaceBelow >= ph + 4 || pr.top < ph + 4) pop.style.top = (pr.bottom + 4) + 'px';
          else                                         pop.style.top = (pr.top - ph - 4) + 'px';
        }
      });
      panel._popEl       = api.pop;
      panel._input       = api.input;
      panel._inputId     = api.inputId;
      panel._closePop    = api.close;
      panel._positionPop = api.position;
      if (currentRawId != null) panel._inputId.value = String(currentRawId);
    }

    function openPanel(td) {
      try {
        closePanel();
        const field = td.dataset.field;
        const config = cfg.fields[field];
        if (!config) return;
        const tr = td.closest('tr');
        if (!tr) return;
        const rowId = parseInt(tr.dataset.rowId, 10);
        const rawValue = td.dataset.value != null ? td.dataset.value : '';
        const valSpan = td.querySelector(':scope > .cell-value');
        const displayValue = valSpan ? valSpan.textContent : rawValue;

        const panel = document.createElement('div');
        panel.className = 'cell-edit-panel';
        panel.setAttribute('role', 'dialog');

        if (config.type === 'text' || config.type === 'textarea') {
          const inputTag = config.type === 'textarea' ? 'textarea' : 'input';
          const inputAttrs = config.type === 'textarea' ? '' : ' type="text"';
          panel.innerHTML =
            '<' + inputTag + ' class="cell-edit-input"' + inputAttrs + '>' + (config.type === 'textarea' ? esc(displayValue) : '') + '</' + inputTag + '>' +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else if (config.type === 'lookup') {
          panel.innerHTML =
            '<div class="lookup lookup-wrap">' +
              '<input class="lookup-input cell-edit-input" type="text" autocomplete="off" readonly />' +
              '<input type="hidden" class="lookup-id" />' +
              '<div class="lookup-tools">' +
                '<button class="lookup-tool clear" type="button" title="Очистить">×</button>' +
                '<button class="lookup-tool" type="button" title="Открыть список">▾</button>' +
              '</div>' +
              '<div class="lookup-pop">' +
                '<input class="lookup-pop-search" type="text" placeholder="Поиск…" />' +
                '<div class="lookup-pop-list"></div>' +
              '</div>' +
            '</div>' +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else if (config.type === 'checkbox') {
          panel.innerHTML =
            '<label class="cell-edit-checkbox-label">' +
              '<input type="checkbox" class="cell-edit-checkbox" value="1" /> <span>' + esc(config.label || '') + '</span>' +
            '</label>' +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else if (config.type === 'color') {
          var curBg = rawValue || '#f0f0f0';
          panel.innerHTML =
            '<div class="color-picker-wrap">' +
              '<input type="hidden" data-color-picker value="' + esc(rawValue) + '" />' +
              '<div class="color-rect' + (rawValue ? '' : ' color-rect--empty') + '" data-color-rect style="background-color:' + curBg + '" tabindex="0" role="button"></div>' +
              '<button type="button" class="color-picker-btn" data-color-btn title="Выбрать цвет"><svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M17.66 7.93L12 2.27 6.34 7.93c-3.12 3.12-3.12 8.19 0 11.31C7.9 20.8 9.95 21.58 12 21.58c2.05 0 4.1-.78 5.66-2.34 3.12-3.12 3.12-8.19 0-11.31zM12 19.59c-1.6 0-3.11-.62-4.24-1.76C6.62 16.69 6 15.19 6 13.59s.62-3.11 1.76-4.24L12 5.1v14.49z"/></svg></button>' +
            '</div>' +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions" style="margin-top:8px">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else if (config.type === 'select' && config.options) {
          var optHtml = config.options.map(function (o) {
            var v = typeof o === 'object' ? o.value : o;
            var l = typeof o === 'object' ? o.label : o;
            var s = String(v) === String(displayValue) ? ' selected' : '';
            return '<option value="' + esc(v) + '"' + s + '>' + esc(l) + '</option>';
          }).join('');
          panel.innerHTML =
            '<select class="cell-edit-input cell-edit-select">' + optHtml + '</select>' +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else if (config.type === 'tags') {
          var tagData = cfg.getLookupData(field) || [];
          var currentIds = String(rawValue).split(',').map(function(s){return parseInt(s,10);}).filter(function(n){return !isNaN(n)&&n>0;});
          var selectedSet = new Set(currentIds);
          var tagsHtml = '<div class="cell-edit-tags-wrap">';
          tagData.forEach(function(t){
            tagsHtml += '<label class="cell-edit-tags-item"><input type="checkbox" value="' + t.id + '"' + (selectedSet.has(t.id)?' checked':'') + '/> ' + esc(t.name) + '</label>';
          });
          tagsHtml += '</div>';
          panel.innerHTML = tagsHtml +
            '<div class="cell-edit-error" style="display:none"></div>' +
            '<div class="cell-edit-actions">' +
              '<button type="button" class="cell-edit-btn cell-edit-btn--primary cell-edit-save">' + IMG_SAVE + ' Сохранить</button>' +
              '<button type="button" class="cell-edit-btn cell-edit-cancel">' + IMG_CANCEL + ' Отменить</button>' +
            '</div>';
        } else {
          return;
        }

        document.body.appendChild(panel);
        positionPanel(panel, td);

        let input;
        if (config.type === 'text') {
          input = panel.querySelector('input.cell-edit-input');
          input.value = displayValue;
        } else if (config.type === 'textarea') {
          input = panel.querySelector('textarea.cell-edit-input');
        } else if (config.type === 'lookup') {
          const root = panel.querySelector('.lookup-wrap');
          buildLookup(panel, root, rawValue, field);
          const lookupData = cfg.getLookupData(field);
          const currentRec = lookupData.find(function (c) { return String(c.id) === String(rawValue); }) || lookupData.find(function (c) { return c.name === rawValue; });
          const currentName = currentRec ? currentRec.name : '';
          const currentId = currentRec ? currentRec.id : rawValue;
          panel._input.value = currentName;
          if (panel._inputId) panel._inputId.value = String(currentId);
          if (currentName) panel._input.setAttribute('data-display', currentName);
          input = panel._input;
        } else if (config.type === 'checkbox') {
          input = panel.querySelector('input.cell-edit-checkbox');
          input.checked = String(rawValue) === '1';
        } else if (config.type === 'color') {
          var cpWrap = panel.querySelector('.color-picker-wrap');
          if (cpWrap && window.ColorPicker) { ColorPicker.wrap(cpWrap); }
        } else if (config.type === 'select') {
          input = panel.querySelector('select.cell-edit-input');
        }

        const saveBtn   = panel.querySelector('.cell-edit-save');
        const cancelBtn = panel.querySelector('.cell-edit-cancel');
        const errEl     = panel.querySelector('.cell-edit-error');

        function doSave() {
          saveBtn.disabled = true;
          errEl.style.display = 'none';
          if (config.type === 'lookup' && panel._closePop) panel._closePop();

          let value, displayAfter;
          if (config.type === 'text') {
            value = input.value.trim();
          } else if (config.type === 'textarea') {
            value = input.value;
          } else if (config.type === 'checkbox') {
            value = input.checked ? '1' : '0';
          } else if (config.type === 'color') {
            var cpInput = panel.querySelector('[data-color-picker]');
            value = cpInput ? cpInput.value : '';
          } else if (config.type === 'select') {
            value = input.value;
            var selOpt = input.options[input.selectedIndex];
            displayAfter = selOpt ? selOpt.text : value;
          } else if (config.type === 'tags') {
            var checked = panel.querySelectorAll('input[type="checkbox"]:checked');
            var ids = [];
            var names = [];
            checked.forEach(function(cb){
              ids.push(cb.value);
              names.push(cb.parentElement.textContent.trim());
            });
            value = ids.join(',');
            displayAfter = names.join(', ');
          } else {
            value = panel._inputId.value;
            const dn = panel._input.getAttribute('data-display');
            displayAfter = dn || '';
          }

          const err = cfg.validate(field, value, { lookupName: displayAfter });
          if (err) {
            errEl.textContent = err;
            errEl.style.display = 'block';
            saveBtn.disabled = false;
            return;
          }

          const fd = new FormData();
          fd.append('id', String(rowId));
          fd.append('field', config.dbField);
          fd.append('value', value);
          fd.append('ajax', '1');

          fetch(cfg.saveUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (r) {
              return r.text().then(function (text) {
                try { return JSON.parse(text); } catch (e) {
                  var err2 = new Error('Invalid JSON: ' + text.substring(0, 500));
                  err2.responseText = text;
                  throw err2;
                }
              });
            })
            .then(function (data) {
              if (data.ok) {
                const newDisplay = data.displayValue != null ? String(data.displayValue) : (displayAfter || (data.value != null ? String(data.value) : value));
                if (valSpan) {
                  if (config.type === 'checkbox') { valSpan.innerHTML = newDisplay; }
                  else                             { valSpan.textContent = newDisplay; }
                }
        td.dataset.value = (config.type === 'lookup') ? String(value) : value;
        if (config.type === 'color') { td.style.backgroundColor = value || ''; }
        if (cfg.onSaveSuccess) cfg.onSaveSuccess(data, field);
        closePanel();
              } else {
                errEl.textContent = data.error || 'Ошибка сохранения';
                errEl.style.display = 'block';
                saveBtn.disabled = false;
              }
            })
            .catch(function (err) {
              var msg = 'Ошибка сети';
              if (err && err.responseText) msg += ': ' + err.responseText.substring(0, 500);
              errEl.textContent = msg;
              errEl.style.display = 'block';
              saveBtn.disabled = false;
              if (console && console.error) console.error('[inline-edit] fetch error', err);
            });
        }

        saveBtn.addEventListener('click', doSave);
        cancelBtn.addEventListener('click', function () {
          if (config.type === 'lookup' && panel._closePop) panel._closePop();
          closePanel();
        });
        cancelBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });

        panel.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            e.preventDefault();
            closePanel();
            return;
          }
          if (e.key === 'Enter' && !e.shiftKey) {
            if (config.type === 'lookup' && panel._popEl && panel._popEl.classList.contains('open')) return;
            e.preventDefault();
            doSave();
          }
        });

        currentPanel = panel;
        currentField = config;
        currentTd = td;
        currentRowId = rowId;
        setTimeout(function () { input.focus(); if (input.select) input.select(); }, 0);
      } catch (err) {
        console.error('[inline-edit] openPanel error', err);
      }
    }

    cfg.tbody.addEventListener('mouseover', function (e) {
      const td = e.target.closest('td');
      if (td && td.classList.contains('cell-editable')) {
        showPencil(td);
      } else {
        if (!currentPanel) removePencil();
      }
    });
    cfg.tbody.addEventListener('mouseleave', function () {
      if (!currentPanel) removePencil();
    });

    document.addEventListener('mousedown', function (e) {
      if (!currentPanel) return;
      const pencil = currentPencilTd ? currentPencilTd.querySelector(':scope > .cell-edit-icon') : null;
      if (currentPanel.contains(e.target)) return;
      if (pencil && pencil.contains(e.target)) return;
      if (e.target.closest('#color-picker-modal')) return;
      closePanel();
    }, true);

    window.addEventListener('scroll', function () {
      if (currentPanel && currentTd) positionPanel(currentPanel, currentTd);
      if (currentPanel && currentPanel._positionPop) currentPanel._positionPop();
    }, true);
    window.addEventListener('resize', function () {
      if (currentPanel && currentTd) positionPanel(currentPanel, currentTd);
    });

    if (cfg.onOpenForm) {
      const origOpen = window.__openFormModal;
      window.__openFormModal = function (url) {
        closePanel();
        if (origOpen) origOpen(url);
      };
    }

    document.addEventListener('keydown', function (e) {
      if (!currentPanel) return;
      if (e.key !== 'Escape') return;
      e.preventDefault();
      e.stopPropagation();
      if (currentField && currentField.type === 'lookup' && currentPanel._closePop) currentPanel._closePop();
      closePanel();
    });
  }

  global.InlineEdit = { init: init };
})(window);
