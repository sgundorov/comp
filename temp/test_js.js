(function () {
  var lastFocused = null;

  function initTabs(container) {
    var headers = container.querySelectorAll('.tab-header');
    var panes = container.querySelectorAll('.tab-pane');
    headers.forEach(function (hdr) {
      hdr.addEventListener('click', function () {
        var idx = parseInt(hdr.dataset.tabIndex, 10);
        headers.forEach(function (h) { h.classList.remove('active'); });
        panes.forEach(function (p) { p.classList.remove('active'); });
        hdr.classList.add('active');
        var pane = container.querySelector('.tab-pane[data-tab-index="' + idx + '"]');
        if (pane) pane.classList.add('active');
      });
    });
  }

  var tabContainer = document.querySelector('.tab-container');
  if (tabContainer) initTabs(tabContainer);

  function updateNameDisplay() {
    var jur = (document.getElementById('jur-name') || {}).value || '';
    var last = (document.getElementById('last-name') || {}).value || '';
    var first = (document.getElementById('first-name') || {}).value || '';
    var display = document.getElementById('name-display');
    if (display) display.value = jur || (last + ' ' + first).trim();
    var flag = document.getElementById('juridical-flag');
    if (flag) flag.checked = jur !== '';
  }

  document.getElementById('jur-name')?.addEventListener('input', updateNameDisplay);
  document.getElementById('last-name')?.addEventListener('input', updateNameDisplay);
  document.getElementById('first-name')?.addEventListener('input', updateNameDisplay);
  updateNameDisplay();

  var cityRoot = document.querySelector('[data-lookup="city"]');
  var countryRoot = document.querySelector('[data-lookup="country"]');
  var cityList = cityRoot ? JSON.parse(cityRoot.getAttribute('data-countries') || '[]') : [];
  var countryList = countryRoot ? JSON.parse(countryRoot.getAttribute('data-countries') || '[]') : [];

  var cityReadonly = cityRoot && cityRoot.querySelector('.lookup-input') && cityRoot.querySelector('.lookup-input').hasAttribute('readonly');
  var countryReadonly = countryRoot && countryRoot.querySelector('.lookup-input') && countryRoot.querySelector('.lookup-input').hasAttribute('readonly');

  function onCitySelect(id, name) {
    var city = cityList.find(function (c) { return String(c.id) === String(id); });
    if (city && city.country_id) {
      var country = countryList.find(function (c) { return String(c.id) === String(city.country_id); });
      if (country) {
        var countryInput = document.querySelector('[data-lookup="country"] .lookup-input');
        var countryHidden = document.querySelector('[data-lookup="country"] [data-lookup-id]');
        if (countryInput) countryInput.value = country.name;
        if (countryHidden) countryHidden.value = country.id;
      }
    }
  }

  if (cityRoot && !cityReadonly) {
    bindLookup({ root: cityRoot, data: cityList, readonly: cityReadonly, onSelect: onCitySelect });
  }
  if (countryRoot && !countryReadonly) {
    bindLookup({ root: countryRoot, data: countryList, readonly: countryReadonly });
  }

  var cliCategRoot = document.querySelector('[data-lookup="cli_categ"]');
  var cliCategList = cliCategRoot ? JSON.parse(cliCategRoot.getAttribute('data-countries') || '[]') : [];
  if (cliCategRoot && !cliCategRoot.querySelector('.lookup-input')?.readOnly) {
    bindLookup({ root: cliCategRoot, data: cliCategList, readonly: false });
  }

  var promoRoot = document.querySelector('[data-lookup="promo"]');
  var promoList = promoRoot ? JSON.parse(promoRoot.getAttribute('data-countries') || '[]') : [];
  if (promoRoot && !promoRoot.querySelector('.lookup-input')?.readOnly) {
    bindLookup({ root: promoRoot, data: promoList, readonly: false });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var anyOpen = document.querySelectorAll('.lookup-pop.open');
      if (anyOpen.length > 0) return;
      var anyTagPop = document.querySelectorAll('.search-cond-pop.open');
      if (anyTagPop.length > 0) return;
      e.preventDefault();
      if (window.parent && window.parent !== window) {
        try { window.parent.postMessage({ type: 'form-cancel' }, '*'); } catch (err) {}
      } else {
        window.location.href = 'client.php';
      }
    }
  });
})();

(function () {
  var form = document.querySelector('form[data-form-modal]');
  if (!form) return;
  var focusable = Array.from(form.querySelectorAll(
    'input:not([type="hidden"]):not([tabindex="-1"]):not([readonly]),' +
    'button:not([tabindex="-1"]):not([disabled]),' +
    'a[href]:not([tabindex="-1"]),' +
    'textarea:not([tabindex="-1"]):not([readonly]),' +
    'select:not([tabindex="-1"]):not([readonly])'
  )).filter(function (el) { return el.offsetParent !== null; });
  if (focusable.length < 2) return;
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var idx = focusable.indexOf(document.activeElement);
    if (idx === -1) return;
    e.preventDefault();
    if (e.shiftKey) {
      focusable[(idx - 1 + focusable.length) % focusable.length].focus();
    } else {
      focusable[(idx + 1) % focusable.length].focus();
    }
  });
})();
</script>
