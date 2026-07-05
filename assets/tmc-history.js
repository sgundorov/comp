(function () {
  var tabContainer = document.querySelector('.tab-container');
  if (tabContainer) {
    var headers = tabContainer.querySelectorAll('.tab-header');
    var panes = tabContainer.querySelectorAll('.tab-pane');
    headers.forEach(function (hdr) {
      hdr.addEventListener('click', function () {
        var idx = parseInt(hdr.dataset.tabIndex, 10);
        headers.forEach(function (h) { h.classList.remove('active'); });
        panes.forEach(function (p) { p.classList.remove('active'); });
        hdr.classList.add('active');
        var pane = tabContainer.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
        if (pane) pane.classList.add('active');
      });
    });
  }

  var recalcBtn = document.getElementById('recalc-residue-btn');
  if (recalcBtn) {
    recalcBtn.addEventListener('click', function () {
      var idEl = document.querySelector('input[name="id"]');
      if (!idEl) return;
      var pid = parseInt(idEl.value, 10);
      if (!pid) return;
      var btn = recalcBtn;
      btn.style.opacity = '0.5';
      var fd = new FormData();
      fd.append('id', pid);
      fd.append('field', '_residue_recalc');
      fetch('tmc_field_save.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.style.opacity = '1';
          if (!data.ok) { alert(data.error || 'Ошибка'); return; }
          var tbody = document.getElementById('residue-tbody');
          if (!tbody) return;
          if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:var(--muted);padding:20px">Нет данных об остатках</td></tr>';
          } else {
            tbody.innerHTML = data.rows.map(function (r) {
              var q = parseFloat(r.quant) || 0;
              var qStr = q % 1 === 0 ? String(q) : q.toFixed(3).replace(/\.?0+$/, '');
              return '<tr><td>' + esc(r.store_name) + '</td><td style="text-align:right">' + esc(qStr) + '</td></tr>';
            }).join('');
          }
        })
        .catch(function () { btn.style.opacity = '1'; alert('Ошибка запроса'); });
    });
  }

  function filterHistory() {
    var st = (document.getElementById('history-search') || {}).value || '';
    st = st.toLowerCase();
    var dFrom = (document.getElementById('history-date-from') || {}).value || '';
    var dTo = (document.getElementById('history-date-to') || {}).value || '';
    var onlyApproved = (document.getElementById('history-approved') || {}).checked;
    var rows = document.querySelectorAll('#history-tbody tr');
    rows.forEach(function (tr) {
      var text = tr.textContent.toLowerCase();
      var matchSearch = (st === '' || text.indexOf(st) >= 0);
      var matchApproved = !onlyApproved || tr.querySelector('img[alt]');
      var matchDate = true;
      if (dFrom || dTo) {
        var dateCell = tr.children[3];
        var cellDate = dateCell ? dateCell.textContent.trim() : '';
        if (dFrom && cellDate < dFrom) matchDate = false;
        if (dTo && cellDate > dTo) matchDate = false;
      }
      tr.style.display = (matchSearch && matchApproved && matchDate) ? '' : 'none';
    });
  }

  var historySearch = document.getElementById('history-search');
  var dateFrom = document.getElementById('history-date-from');
  var dateTo = document.getElementById('history-date-to');
  var approvedChk = document.getElementById('history-approved');
  if (historySearch) historySearch.addEventListener('input', filterHistory);
  if (dateFrom) { dateFrom.addEventListener('change', filterHistory); dateFrom.addEventListener('input', filterHistory); }
  if (dateTo) { dateTo.addEventListener('change', filterHistory); dateTo.addEventListener('input', filterHistory); }
  if (approvedChk) approvedChk.addEventListener('change', filterHistory);

  var histTable = document.getElementById('history-table');
  if (histTable) {
    var histSortCol = -1, histSortDir = 'asc';
    histTable.querySelector('thead').addEventListener('click', function (e) {
      var th = e.target.closest('th[data-sort]');
      if (!th) return;
      var allThs = Array.from(histTable.querySelectorAll('thead th[data-sort]'));
      var ci = allThs.indexOf(th);
      if (ci < 0) return;
      if (histSortCol === ci) { histSortDir = histSortDir === 'asc' ? 'desc' : 'asc'; }
      else { histSortCol = ci; histSortDir = 'asc'; }
      allThs.forEach(function (h, i) {
        h.textContent = h.textContent.replace(/\s*[\u25b2\u25bc]\s*$/, '');
        if (i === ci) h.textContent += histSortDir === 'asc' ? ' \u25b2' : ' \u25bc';
      });
      var tbody = histTable.querySelector('tbody');
      var rows = Array.from(tbody.querySelectorAll('tr'));
      var thsAll = Array.from(histTable.querySelectorAll('thead th'));
      rows.sort(function (a, b) {
        var ai = thsAll.indexOf(allThs[ci]);
        var bi = thsAll.indexOf(allThs[ci]);
        var va = (a.children[ai] ? a.children[ai].textContent : '').trim();
        var vb = (b.children[bi] ? b.children[bi].textContent : '').trim();
        var na = parseFloat(va.replace(',', '.'));
        var nb = parseFloat(vb.replace(',', '.'));
        if (!isNaN(na) && !isNaN(nb)) return histSortDir === 'asc' ? na - nb : nb - na;
        return histSortDir === 'asc' ? va.localeCompare(vb, 'ru') : vb.localeCompare(va, 'ru');
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });

    histTable.querySelectorAll('thead th').forEach(function (th, idx) {
      th.style.position = 'relative';
      th.style.cursor = 'default';
      var handle = document.createElement('div');
      handle.style.cssText = 'position:absolute;right:0;top:0;bottom:0;width:5px;cursor:col-resize;z-index:1;';
      th.appendChild(handle);
      var startX, startW;
      handle.addEventListener('mousedown', function (e) {
        e.preventDefault();
        e.stopPropagation();
        startX = e.clientX;
        startW = th.offsetWidth;
        var colEl = histTable.querySelectorAll('col')[idx];
        function onMove(ev) {
          var nw = Math.max(30, startW + ev.clientX - startX);
          th.style.width = nw + 'px';
          if (colEl) colEl.style.width = nw + 'px';
        }
        function onUp() {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
          document.body.style.cursor = '';
        }
        document.body.style.cursor = 'col-resize';
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    });
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
})();
