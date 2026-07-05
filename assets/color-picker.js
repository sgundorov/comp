(function (rootNS) {
  'use strict';

  var PALETTE = [
    '#000000','#1a1a1a','#333333','#4d4d4d','#666666','#808080','#999999','#b3b3b3','#cccccc','#e6e6e6','#ffffff',
    '#8b0000','#cc0000','#ff0000','#ff3333','#ff6666','#ff9999','#ffcccc',
    '#cc4400','#ff6600','#ff8833','#ffaa66','#ffcc99',
    '#999900','#cccc00','#ffff00','#ffff33','#ffff66','#ffff99','#ffffcc',
    '#006400','#008800','#00aa00','#00cc00','#33cc33','#66cc66','#99cc99','#ccffcc',
    '#008080','#00aaaa','#00cccc','#33cccc','#66cccc','#99cccc',
    '#00008b','#0000cc','#0000ff','#3333ff','#6666ff','#9999ff','#ccccff',
    '#4b0082','#6600cc','#8800ff','#aa44ff','#cc88ff','#eeccff',
    '#8b008b','#cc00cc','#ff00ff','#ff33ff','#ff66ff','#ff99ff','#ffccff',
    '#8b4513','#a0522d','#cd853f','#deb887','#d2b48c',
  ];

  var modalEl = null;
  var gridEl = null;
  var nativeEl = null;
  var currentCallback = null;

  function init() {
    if (document.getElementById('color-picker-modal')) {
      modalEl = document.getElementById('color-picker-modal');
      gridEl = document.getElementById('color-picker-grid');
      nativeEl = document.getElementById('color-picker-native');
      bindEvents();
      return;
    }
    modalEl = document.createElement('div');
    modalEl.id = 'color-picker-modal';
    modalEl.className = 'color-picker-modal';
    modalEl.innerHTML =
      '<div class="color-picker-modal-backdrop"></div>' +
      '<div class="color-picker-modal-content">' +
        '<div class="color-picker-modal-header">' +
          '<span>Выберите цвет</span>' +
          '<button type="button" class="color-picker-modal-close" id="color-picker-modal-close">&times;</button>' +
        '</div>' +
        '<div class="color-picker-grid" id="color-picker-grid"></div>' +
        '<div class="color-picker-footer">' +
          '<button type="button" class="color-picker-custom-btn" id="color-picker-custom">Другой цвет\u2026</button>' +
          '<button type="button" class="color-picker-clear-btn" id="color-picker-clear">Сбросить</button>' +
        '</div>' +
        '<input type="color" id="color-picker-native" style="display:none" />' +
      '</div>';
    document.body.appendChild(modalEl);
    gridEl = document.getElementById('color-picker-grid');
    nativeEl = document.getElementById('color-picker-native');
    bindEvents();
  }

  function bindEvents() {
    var closeBtn = document.getElementById('color-picker-modal-close');
    var backdrop = modalEl.querySelector('.color-picker-modal-backdrop');
    var customBtn = document.getElementById('color-picker-custom');
    var clearBtn = document.getElementById('color-picker-clear');

    function close() { modalEl.classList.remove('open'); currentCallback = null; }

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (backdrop) backdrop.addEventListener('click', close);
    if (clearBtn) clearBtn.addEventListener('click', function () {
      if (currentCallback) currentCallback('');
      close();
    });
    if (customBtn) customBtn.addEventListener('click', function () {
      if (nativeEl) nativeEl.click();
    });
    if (nativeEl) {
      nativeEl.addEventListener('input', function () {
        if (currentCallback) currentCallback(this.value);
      });
      nativeEl.addEventListener('change', close);
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modalEl && modalEl.classList.contains('open')) {
        close();
      }
    });
  }

  function renderGrid(selected) {
    if (!gridEl) return;
    gridEl.innerHTML = '';
    var sel = (selected || '').toLowerCase();
    PALETTE.forEach(function (c) {
      var el = document.createElement('div');
      el.className = 'color-swatch-item' + (c.toLowerCase() === sel ? ' selected' : '');
      el.style.backgroundColor = c;
      el.dataset.color = c;
      el.addEventListener('click', function () {
        gridEl.querySelectorAll('.color-swatch-item').forEach(function (s) { s.classList.remove('selected'); });
        el.classList.add('selected');
        if (currentCallback) currentCallback(c);
        modalEl.classList.remove('open');
        currentCallback = null;
      });
      gridEl.appendChild(el);
    });
  }

  function open(selected, callback) {
    currentCallback = callback;
    renderGrid(selected);
    modalEl.classList.add('open');
  }

  function close() {
    if (modalEl) modalEl.classList.remove('open');
    currentCallback = null;
  }

  function wrap(root) {
    var input = root.querySelector('[data-color-picker]');
    var rect = root.querySelector('[data-color-rect]');
    var btn = root.querySelector('[data-color-btn]');
    if (!input || !rect) return null;

    function setColor(val) {
      val = val || '';
      input.value = val;
      rect.style.backgroundColor = val || '#f0f0f0';
      rect.classList.toggle('color-rect--empty', !val);
    }

    function openPicker() {
      if (rect.hasAttribute('data-readonly')) return;
      open(input.value, function (val) { setColor(val); });
    }

    rect.addEventListener('click', openPicker);
    rect.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
    });
    if (btn) btn.addEventListener('click', openPicker);

    return {
      input: input,
      rect: rect,
      setValue: setColor,
      getValue: function () { return input.value; }
    };
  }

  function setPalette(colors) {
    if (Array.isArray(colors)) PALETTE = colors;
  }

  rootNS.ColorPicker = {
    init: init,
    open: open,
    close: close,
    wrap: wrap,
    setPalette: setPalette
  };
})(window);
