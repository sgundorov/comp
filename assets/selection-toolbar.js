(function (global) {
  'use strict';

  function init(config) {
    var pageUrl = config.pageUrl;
    var search = config.search || '';
    var getInvertUrl = config.getInvertUrl || null;
    var getExportUrl = config.getExportUrl || function () { return null; };
    var getPrintUrl = config.getPrintUrl || function () { return null; };

    function marksUrl(action) {
      var sep = pageUrl.indexOf('?') >= 0 ? '&' : '?';
      var url = pageUrl + sep + 'action=' + encodeURIComponent(action);
      if (search) url += '&q=' + encodeURIComponent(search);
      return url;
    }

    function setWait(show) {
      if (show) {
        document.body.style.cursor = 'wait';
        var s = document.getElementById('sw-cursor');
        if (!s) { s = document.createElement('style'); s.id = 'sw-cursor'; document.head.appendChild(s); }
        s.textContent = '*,*::before,*::after{cursor:wait!important}';
      } else {
        document.body.style.cursor = '';
        var s = document.getElementById('sw-cursor');
        if (s) s.remove();
      }
    }

    global.clearSelection = function () {
      setWait(true);
      fetch(marksUrl('clearSelection'), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) location.reload(); });
    };

    global.invertSelection = function () {
      setWait(true);
      var url;
      if (getInvertUrl) {
        url = getInvertUrl();
      } else {
        url = marksUrl('invertSelection');
      }
      fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) location.reload(); });
    };

    global.toggleShowOnly = function () {
      setWait(true);
      fetch(marksUrl('toggleShowOnly'), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) location.reload(); });
    };

    global.exportSelected = function () {
      setWait(true);
      var url = getExportUrl();
      if (url) location.href = url;
      setWait(false);
    };

    global.printSelected = function () {
      setWait(true);
      var url = getPrintUrl();
      if (url) { var w = window.open(url, '_blank'); if (w) w.focus(); }
      setWait(false);
    };
  }

  /**
   * Инициализация чекбоксов отметок в таблице:
   * - #checkAll — отметка всех на странице
   * - .row-check — поштучная отметка через AJAX
   *
   * pageUrl — URL страницы для формирования action-ссылок
   * search  — текущий поисковый запрос (для проброса)
   */
  function initTableSelection(pageUrl, search) {
    var checkAll  = document.getElementById('checkAll');
    if (!checkAll) return;
    var rowChecks = document.querySelectorAll('.row-check');
    var selWrap   = document.getElementById('selectedActions');
    var selCount  = document.getElementById('selectedCount');
    var toolbar   = document.querySelector('.toolbar');
    var theSearch = search || (toolbar ? toolbar.getAttribute('data-search') || '' : '');
    var markedSet = new Set(Array.from(rowChecks).filter(function(cb) { return cb.checked; }).map(function(cb) { return parseInt(cb.value || cb.dataset.id, 10); }));
    var globalCount = parseInt(toolbar ? toolbar.getAttribute('data-marks-count') || '0' : '0', 10);

    window.__marksUrl = function (action) {
      var _u = new URLSearchParams(location.search);
      _u.set('action', action);
      if (theSearch) _u.set('q', theSearch);
      var base = pageUrl.split('?')[0];
      return base + '?' + _u.toString();
    };

    function refreshCounter() {
      if (selCount) selCount.textContent = 'Выбрано: ' + globalCount;
      if (selWrap) selWrap.classList.toggle('visible', globalCount > 0);
      var rowTotal = rowChecks.length;
      var rowOn = 0;
      rowChecks.forEach(function(cb) { if (cb.checked) rowOn++; });
      if (rowTotal === 0) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      } else {
        checkAll.checked = rowOn === rowTotal;
        checkAll.indeterminate = rowOn > 0 && rowOn < rowTotal;
      }
    }

    checkAll.addEventListener('change', function () {
      location.href = window.__marksUrl('toggleSelectAll');
    });

    rowChecks.forEach(function(cb) {
      cb.addEventListener('change', function () {
        var id  = parseInt(cb.value || cb.dataset.id, 10);
        var to  = cb.checked;
        cb.disabled = true;
        fetch(window.__marksUrl('toggleSelect'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + encodeURIComponent(id) + '&to=' + (to ? '1' : '0')
        })
        .then(function(r) { return r.json(); })
        .then(function(j) {
          cb.disabled = false;
          if (j.ok) {
            if (to) markedSet.add(id); else markedSet.delete(id);
            globalCount = (typeof j.count === 'number') ? j.count : globalCount;
            refreshCounter();
          }
        })
        .catch(function() { cb.disabled = false; });
      });
    });

    refreshCounter();

    global.__refreshSelectionUI = refreshCounter;
  }

  global.SelectionToolbar = { init: init, initTableSelection: initTableSelection };
})(window);
