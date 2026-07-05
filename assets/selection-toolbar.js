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

  global.SelectionToolbar = { init: init };
})(window);
