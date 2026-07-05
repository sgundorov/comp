(function () {
  function initRegcodTable() {
    var tbody = document.querySelector('#regcod-table tbody');
    if (!tbody) return;
    var regcodData = window.__regcodData || [];
    var selectedId = 0;

    function updateButtons() {
      var editBtn = document.getElementById('regcod-edit-btn');
      var delBtn = document.getElementById('regcod-del-btn');
      if (editBtn) editBtn.disabled = (selectedId === 0);
      if (delBtn) delBtn.disabled = (selectedId === 0);
    }

    function selectRow(id) {
      selectedId = id;
      tbody.querySelectorAll('tr').forEach(function(tr) {
        tr.classList.toggle('selected', parseInt(tr.dataset.id, 10) === id);
      });
      updateButtons();
    }

    function renderRegcodTable() {
      var html = '';
      regcodData.forEach(function(r) {
        html += '<tr data-id="' + r.id + '" data-row-id="' + r.id + '">'
          + '<td>' + esc(r.product) + '</td>'
          + '<td>' + esc(r.regcod) + '</td>'
          + '<td>' + r.quant + '</td>'
          + '<td>' + esc(r.date) + '</td>'
          + '<td>' + esc(r.city) + '</td>'
          + '<td>' + esc(r.signat) + '</td>'
          + '<td>' + r.days + '</td>'
          + '<td>' + esc(r.note) + '</td>'
          + '</tr>';
      });
      tbody.innerHTML = html || '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:20px">Нет записей</td></tr>';
      selectedId = 0;
      updateButtons();
    }

    renderRegcodTable();
    if (regcodData.length > 0) selectRow(regcodData[0].id);

    tbody.addEventListener('click', function(e) {
      var tr = e.target.closest('tr[data-id]');
      if (tr) selectRow(parseInt(tr.dataset.id, 10));
    });

    tbody.addEventListener('dblclick', function(e) {
      var tr = e.target.closest('tr[data-id]');
      if (tr) openRegcodForm('edit', parseInt(tr.dataset.id, 10));
    });

    var addBtn = document.getElementById('regcod-add-btn');
    if (addBtn) addBtn.addEventListener('click', function() { openRegcodForm('new', 0); });

    var editBtn = document.getElementById('regcod-edit-btn');
    if (editBtn) editBtn.addEventListener('click', function() { if (selectedId) openRegcodForm('edit', selectedId); });

    var delBtn = document.getElementById('regcod-del-btn');
    if (delBtn) delBtn.addEventListener('click', function() {
      if (!selectedId) return;
      if (!confirm('Удалить регистрационный код?')) return;
      fetch('regcod_field_save.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: 'mode=delete&id=' + selectedId })
        .then(function(r) { return r.json(); }).then(function(d) {
          if (d.ok) { regcodData = regcodData.filter(function(r) { return r.id !== selectedId; }); renderRegcodTable(); }
        });
    });

    function openRegcodForm(mode, rid) {
      var cid = window.__regcodClientId;
      var url = 'regcod_form.php?mode=' + mode + '&client_id=' + cid;
      if (rid) url += '&id=' + rid;
      var backdrop = document.getElementById('formModal');
      var body = document.getElementById('formModalBody');
      if (!backdrop || !body) return;
      var savedHtml = body.innerHTML;
      backdrop.classList.add('open');
      document.body.style.overflow = 'hidden';
      fetch(url + '&ajax=1', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.html) return;
          body.innerHTML = data.html;
          var form = body.querySelector('form[data-form-modal]');
          if (form) {
            body.querySelectorAll('[data-lookup]').forEach(function(el) {
              if (el.dataset.lookupInited) return;
              var raw = el.getAttribute('data-countries');
              if (!raw) return;
              var d; try { d = JSON.parse(raw); } catch(e) { return; }
              el.dataset.lookupInited = '1';
              try { bindLookup({root:el, data:d, readonly: el.hasAttribute('data-readonly')}); } catch(e) {}
            });
            form.addEventListener('submit', function(e) {
              e.preventDefault();
              var fd = new FormData(form);
              fd.set('ajax', '1');
              fetch('regcod_form.php', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                  if (d.ok) { body.innerHTML = savedHtml; refreshRegcodList(); }
                  else { body.innerHTML = d.html || '<div class="flash flash--error">Ошибка</div>'; }
                });
            });
          }
          var closeBtn = body.querySelector('[data-form-close]');
          if (closeBtn) closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            body.innerHTML = savedHtml;
            refreshRegcodList();
          });
        });
    }

    function refreshRegcodList() {
      var cid = window.__regcodClientId || 0;
      var idInput = document.querySelector('input[name="id"]');
      if (idInput && idInput.value) cid = parseInt(idInput.value, 10) || cid;
      fetch('regcod_field_save.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: 'field=_list&client_id=' + cid })
        .then(function(r) { return r.json(); }).then(function(d) {
          if (d && d.rows) { window.__regcodData = d.rows; regcodData = d.rows; renderRegcodTable(); }
        });
    }

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  }

  function initTagPicker() {
    var container = document.getElementById('tag-picker-container');
    if (!container) return;
    var items = [];
    try { items = JSON.parse(container.getAttribute('data-items') || '[]'); } catch(e) {}
    var selectedIds = (container.getAttribute('data-values') || '').split(',').map(Number).filter(function(n) { return n > 0; });
    var hidden = document.getElementById('tag-ids-hidden');

    function renderTags() {
      container.innerHTML = '';
      if (selectedIds.length === 0) {
        container.innerHTML = '<span class="search-cond-empty">Выберите вид деятельности</span>';
      } else {
        selectedIds.forEach(function(id) {
          var item = items.find(function(i) { return i.id === id; });
          if (!item) return;
          var chip = document.createElement('span');
          chip.className = 'search-cond-chip';
          chip.textContent = item.name;
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'search-cond-chip-close';
          btn.textContent = '\u00d7';
          btn.addEventListener('click', function() {
            selectedIds = selectedIds.filter(function(n) { return n !== id; });
            if (hidden) hidden.value = selectedIds.join(',');
            renderTags();
          });
          chip.appendChild(btn);
          container.appendChild(chip);
        });
      }
      if (hidden) hidden.value = selectedIds.join(',');
    }

    renderTags();

    container.addEventListener('click', function(e) {
      if (e.target.closest('.search-cond-chip')) return;
      var pop = document.createElement('div');
      pop.className = 'search-cond-pop';
      pop.style.cssText = 'position:absolute;left:0;top:100%;z-index:1000;background:#2f3e4e;border:1px solid #6b7785;border-radius:4px;padding:8px;min-width:200px;max-height:250px;overflow-y:auto;';
      items.forEach(function(item) {
        var label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;color:#e0e0e0;';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = selectedIds.indexOf(item.id) >= 0;
        cb.value = item.id;
        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + item.name));
        pop.appendChild(label);
      });
      var applyBtn = document.createElement('button');
      applyBtn.type = 'button';
      applyBtn.className = 'btn btn-primary';
      applyBtn.textContent = '\u041f\u0440\u0438\u043c\u0435\u043d\u0438\u0442\u044c';
      applyBtn.style.cssText = 'margin-top:8px;width:100%;';
      applyBtn.addEventListener('click', function() {
        selectedIds = [];
        pop.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
          selectedIds.push(parseInt(cb.value, 10));
        });
        if (hidden) hidden.value = selectedIds.join(',');
        renderTags();
        pop.remove();
      });
      pop.appendChild(applyBtn);
      container.appendChild(pop);

      function closePop(ev) {
        if (!container.contains(ev.target)) { pop.remove(); document.removeEventListener('click', closePop); }
      }
      document.addEventListener('click', closePop);
    });
  }

  window.initRegcodTable = initRegcodTable;
  window.initTagPicker = initTagPicker;

  function autoInit() {
    if (document.getElementById('tag-picker-container')) initTagPicker();
    if (document.getElementById('regcod-table') && !document.getElementById('regcod-table')._inited) {
      document.getElementById('regcod-table')._inited = true;
      initRegcodTable();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  setInterval(autoInit, 500);
})();
