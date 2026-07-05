(function () {
  'use strict';

  window.ExportModal = {
    init: function () {
      var backdrop = document.getElementById('exportModal');
      if (!backdrop) return;
      var elInput  = document.getElementById('exportModalFilename');
      var elExt    = document.getElementById('exportModalExt');
      var elFormat = document.getElementById('exportModalFormat');
      var elCount  = document.getElementById('exportModalCount');
      var btnCancel = backdrop.querySelector('.export-cancel');
      var btnApply  = backdrop.querySelector('.export-apply');
      var toolbar   = document.querySelector('.toolbar');
      var total     = toolbar ? parseInt(toolbar.getAttribute('data-total') || '0', 10) : 0;
      var pendingUrl  = null;
      var pendingExt  = '';

      function splitName(filename) {
        var idx = filename.lastIndexOf('.');
        if (idx <= 0) return { name: filename, ext: '' };
        return { name: filename.substring(0, idx), ext: filename.substring(idx) };
      }

      function open(url, filename, format) {
        pendingUrl = url;
        var p = splitName(filename || '');
        pendingExt = p.ext;
        elInput.value = p.name;
        elExt.textContent = p.ext;
        elFormat.textContent = format;
        elCount.textContent = total > 0 ? String(total) : '—';
        backdrop.classList.add('open');
        setTimeout(function () { elInput.focus(); elInput.select(); }, 0);
      }

      function close() {
        backdrop.classList.remove('open');
        pendingUrl = null;
        pendingExt = '';
      }

      document.querySelectorAll('[data-export-url]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          open(a.getAttribute('data-export-url'), a.getAttribute('data-export-filename') || '', a.getAttribute('data-export-format') || '');
        });
      });

      btnCancel.addEventListener('click', close);
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) close();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) close();
      });
      elInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); btnApply.click(); }
      });
      btnApply.addEventListener('click', function () {
        if (!pendingUrl) return;
        var customName = elInput.value.trim() + pendingExt;
        var sep = pendingUrl.indexOf('?') === -1 ? '?' : '&';
        var u = pendingUrl + sep + 'filename=' + encodeURIComponent(customName);
        pendingUrl = null;
        close();
        window.location.href = u;
      });
    }
  };
})();
