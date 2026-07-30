(function (global) {
  'use strict';

  function init(opts) {
    var MIN_WIDTH = 40;
    var SAVE_URL = opts.saveUrl;
    var tbl = opts.tbl;
    var selector = opts.selector || '.data-table';

    function initResize() {
      var table = document.querySelector(selector);
      if (!table) return;
      if (table.dataset.colResizeInited) return;
      table.dataset.colResizeInited = '1';
      if (SAVE_URL) table.dataset.colResizeUrl = SAVE_URL;
      if (tbl) table.dataset.colResizeTbl = tbl;

      var ths = table.querySelectorAll('thead tr:first-child th:not(.col-check)');
      if (!ths.length) return;

      ths = Array.from(ths).filter(function (th) { return !th.hasAttribute('data-no-resize'); });

      function setTableWidth(w, forceMin) {
        var rw = Math.round(w);
        table.style.width = rw + 'px';
        if (forceMin) {
          table.style.minWidth = rw + 'px';
        }
      }

      // Apply colgroup widths immediately so saved pixel widths
      // take effect without requiring a handle click (fixes embedded
      // tables in hidden tabs where browser distributes 100% width
      // differently than the colgroup pixel values).
      var dataCols = table.querySelectorAll('colgroup col:not(.col-check)');
      var totalW = 0;
      dataCols.forEach(function (col) {
        var w = parseInt(col.style.width, 10);
        if (!isNaN(w) && w > 0) { totalW += w; }
      });
      var checkW = (function () {
        var col = table.querySelector('colgroup col.col-check');
        var w = col ? parseInt(col.style.width, 10) : 0;
        return w > 0 ? w : 32;
      })();
      var totalWfull = totalW + checkW;
      if (totalWfull > 0) {
        setTableWidth(totalWfull, true);
      }

      ths.forEach(function (th) {
        var handle = document.createElement('div');
        handle.className = 'col-resize-handle';
        th.appendChild(handle);

        function onMouseDown(e) {
          e.preventDefault();
          e.stopPropagation();
          var cn = th.className.match(/col-\S+/);
          if (!cn) return;
          var colEl = table.querySelector('colgroup col.' + cn[0]);
          if (!colEl) return;

          var allCols = [].slice.call(table.querySelectorAll('colgroup col:not(.col-check)'));
          var targetIdx = allCols.indexOf(colEl);
          if (targetIdx < 0) return;

          var checkW = (function () {
            var col = table.querySelector('colgroup col.col-check');
            var w = col ? parseInt(col.style.width, 10) : 0;
            return w > 0 ? w : 32;
          })();

          var colWidths = [];
          var totalW = 0;
          allCols.forEach(function (col, i) {
            var w = parseInt(col.style.width, 10);
            if (isNaN(w) || w <= 0) w = Math.round(col.offsetWidth);
            if (w > 0) {
              col.style.width = w + 'px';
              colWidths[i] = w;
              totalW += w;
            } else {
              colWidths[i] = null;
            }
          });
          if (totalW > 0) setTableWidth(totalW + checkW, true);

          var startX = e.clientX;
          var startW = colWidths[targetIdx];
          if (startW == null) return;

          handle.classList.add('resizing');
          document.body.style.cursor = 'col-resize';
          document.body.style.userSelect = 'none';

          function onMouseMove(ev) {
            var diff = ev.clientX - startX;
            var newW = Math.max(MIN_WIDTH, startW + diff);
            var newTotal = totalW + newW - startW;
            allCols.forEach(function (col, i) {
              var cw = i === targetIdx ? newW : colWidths[i];
              if (cw != null) col.style.width = Math.round(cw) + 'px';
            });
            setTableWidth(newTotal + checkW, true);
          }

          function onMouseUp(ev) {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            handle.classList.remove('resizing');

            var finalW = Math.round(Math.max(MIN_WIDTH, startW + (ev.clientX - startX)));
            allCols.forEach(function (col, i) {
              var cw = i === targetIdx ? finalW : colWidths[i];
              if (cw != null) col.style.width = Math.round(cw) + 'px';
            });
            setTableWidth(Math.round(totalW + finalW - startW) + checkW, true);
            saveWidth(th, finalW);
          }

          document.addEventListener('mousemove', onMouseMove);
          document.addEventListener('mouseup', onMouseUp);
        }

        handle.addEventListener('mousedown', onMouseDown);
        handle.addEventListener('click', function (e) { e.stopPropagation(); });
        handle.addEventListener('dblclick', function (e) {
          e.stopPropagation();
          var cn = th.className.match(/col-\S+/);
          if (!cn) return;
          var name = cn[0].replace('col-', '');
          var def = (global.__columnDefaultWidths || {})[name];
          if (def == null) return;
          if (!SAVE_URL) return;
          if (def === 'auto') {
            var colEl = table.querySelector('colgroup col.' + cn[0]);
            if (colEl) colEl.style.width = 'auto';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', SAVE_URL, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () { location.reload(); };
            xhr.send('tbl=' + encodeURIComponent(tbl) + '&name=' + encodeURIComponent(name) + '&width=');
            return;
          }
          var w;
          if (typeof def === 'string' && def.indexOf('%') > 0) {
            w = Math.round(table.offsetWidth * parseInt(def, 10) / 100);
          } else {
            w = parseInt(def, 10);
          }
          w = Math.max(MIN_WIDTH, Math.round(w));
          var colEl = table.querySelector('colgroup col.' + cn[0]);
          if (colEl) { colEl.style.width = w + 'px'; }

          saveWidth(th, w);
        });
      });
    }

    function saveWidth(th, w) {
      var cn = th.className.match(/col-\S+/);
      if (!cn) return;
      var name = cn[0].replace('col-', '');
      if (!SAVE_URL) return;
      var xhr = new XMLHttpRequest();
      xhr.open('POST', SAVE_URL, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.send('tbl=' + encodeURIComponent(tbl) + '&name=' + encodeURIComponent(name) + '&width=' + w);
    }

    initResize();
  }

  global.ColumnResize = { init: init };
})(window);
