(function (global) {
  'use strict';

  global.applyAccessFlags = function (flags) {
    if (!flags) return;
    global.__accessFlags = flags;

    var btns = {
      'insert_flag': ['rowOpenBtn[data-form-open]', '[data-form-open]'],
      'change_flag': ['rowOpenBtn'],
      'delete_flag': ['rowDeleteBtn'],
    };
    if (flags.insert_flag) {
      document.querySelectorAll('[data-form-open]').forEach(function (b) { b.disabled = true; });
      var copyBtn = document.getElementById('rowCopyBtn');
      if (copyBtn) copyBtn.disabled = true;
    }
    if (flags.change_flag) {
      var openBtn = document.getElementById('rowOpenBtn');
      if (openBtn) openBtn.disabled = true;
    }
    if (flags.delete_flag) {
      var delBtn = document.getElementById('rowDeleteBtn');
      if (delBtn) delBtn.disabled = true;
    }

    if (flags.change_flag || flags.save_flag) {
      global.__disableInlineEdit = true;
    }

    if (flags.print_flag) {
      // Disable Export button and all its dropdown items
      document.querySelectorAll('[data-print-export]').forEach(function (b) {
        b.disabled = true;
        var dd = b.closest('.dropdown');
        if (dd) {
          dd.querySelectorAll('.dropdown-item').forEach(function (el) {
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.35';
          });
        }
      });
      // Disable print list items
      document.querySelectorAll('[data-print-list]').forEach(function (el) {
        el.style.pointerEvents = 'none';
        el.style.opacity = '0.35';
      });
      // Disable Print button only for standard pages (no template divider)
      var printBtn = document.querySelector('.toolbar-left [title="Печать"]');
      if (printBtn) {
        var printMenu = printBtn.parentElement.querySelector('.dropdown-menu');
        if (printMenu && !printMenu.querySelector('.dropdown-divider')) {
          printBtn.disabled = true;
        }
      }
    }
  };

})(window);
