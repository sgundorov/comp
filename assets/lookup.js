(function (rootNS) {
  'use strict';

  function findBtnClear(root) {
    return root.querySelector('.lookup-tool.clear') || root.querySelector('[data-lookup-clear]');
  }
  function findBtnOpen(root) {
    const byClass = root.querySelectorAll('.lookup-tool');
    for (let i = 0; i < byClass.length; i++) {
      if (!byClass[i].classList.contains('clear') && !byClass[i].classList.contains('add')) return byClass[i];
    }
    return root.querySelector('[data-lookup-open]');
  }
  function findBtnAdd(root) {
    return root.querySelector('.lookup-tool.add') || root.querySelector('[data-lookup-add]');
  }
  function findInputId(root) {
    return root.querySelector('.lookup-id') || root.querySelector('#country-id') || root.querySelector('[data-lookup-id]');
  }
  function findInputName(root) {
    return root.querySelector('.lookup-input') || root.querySelector('#country-name') || root.querySelector('[data-lookup-name]');
  }

  function defaultPositionPop(pop, input, root) {
    const r = root.getBoundingClientRect();
    pop.style.left = r.left + 'px';
    pop.style.width = r.width + 'px';
    const ph = pop.offsetHeight;
    const spaceBelow = window.innerHeight - r.bottom;
    if (spaceBelow >= ph + 4 || r.top < ph + 4) pop.style.top = (r.bottom + 4) + 'px';
    else pop.style.top = (r.top - ph - 4) + 'px';
  }

  function bindLookup(opts) {
    const root      = opts.root;
    const data      = opts.data || [];
    const readonly  = !!opts.readonly;
    const onSelect  = opts.onSelect || null;
    const positionPop = opts.positionPop || defaultPositionPop;

    const pop       = root.querySelector('.lookup-pop') || root.querySelector('[data-lookup-pop]');
    const popList   = root.querySelector('.lookup-pop-list') || root.querySelector('[data-lookup-list]');
    const popSearch = root.querySelector('.lookup-pop-search') || root.querySelector('[data-lookup-search]');
    const input     = findInputName(root);
    const inputId   = findInputId(root);
    const btnClear  = findBtnClear(root);
    const btnOpen   = findBtnOpen(root);
    const btnAdd    = findBtnAdd(root);

    if (root.__lookupBound) return;
    root.__lookupBound = true;

    let activeIndex = -1;

    function visibleItems() { return Array.from(popList.querySelectorAll('.lookup-pop-item')); }

    function renderList(filter) {
      const f = (filter || '').toLowerCase().trim();
      popList.innerHTML = '';
      const matches = data.filter(function (c) { return c.name.toLowerCase().includes(f); });
      if (matches.length === 0) {
        const e = document.createElement('div');
        e.className = 'lookup-pop-empty';
        e.textContent = 'Ничего не найдено';
        popList.appendChild(e);
        activeIndex = -1;
        return;
      }
      matches.forEach(function (c) {
        const it = document.createElement('div');
        it.className = 'lookup-pop-item';
        it.textContent = c.name;
        it.dataset.id = String(c.id);
        it.dataset.name = c.name;
        it.addEventListener('mousedown', function (e) { e.preventDefault(); choose(c.id, c.name); });
        popList.appendChild(it);
      });
      activeIndex = 0;
      highlight();
    }

    function highlight() {
      const items = visibleItems();
      items.forEach(function (el, i) {
        el.classList.toggle('selected', i === activeIndex);
        if (i === activeIndex) el.scrollIntoView({ block: 'nearest' });
      });
    }

    function choose(id, name) {
      inputId.value = String(id);
      input.value = name;
      input.setAttribute('data-display', name);
      inputId.dispatchEvent(new Event('change', { bubbles: true }));
      if (onSelect) { try { onSelect(id, name); } catch (err) { console.error('[lookup] onSelect', err); } }
      closePop();
    }

    function openPop() {
      if (pop.classList.contains('open')) return;
      pop.classList.add('open');
      btnOpen.disabled = true;
      document.body.style.cursor = 'wait';
      positionPop(pop, input, root);
      if (popSearch) {
        popSearch.value = '';
        renderList('');
        setTimeout(function () { popSearch.focus(); }, 0);
      } else {
        renderList(input.value);
      }
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          btnOpen.disabled = false;
          document.body.style.cursor = '';
        });
      });
    }

    function closePop() {
      pop.classList.remove('open');
      btnOpen.disabled = false;
      document.body.style.cursor = '';
    }

    if (!readonly) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'Enter') { e.preventDefault(); openPop(); return; }
        if (e.key === 'Escape' && pop.classList.contains('open')) { e.preventDefault(); closePop(); return; }
      });
    }

    if (popSearch) {
      popSearch.addEventListener('input', function () { renderList(popSearch.value); });
      popSearch.addEventListener('keydown', function (e) {
        const items = visibleItems();
        if (e.key === 'ArrowDown')      { e.preventDefault(); activeIndex = Math.min(items.length - 1, activeIndex + 1); highlight(); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); activeIndex = Math.max(0, activeIndex - 1); highlight(); }
        else if (e.key === 'Enter') {
          if (activeIndex >= 0 && items[activeIndex]) {
            e.preventDefault();
            choose(parseInt(items[activeIndex].dataset.id, 10), items[activeIndex].dataset.name);
          }
        } else if (e.key === 'Escape') { closePop(); }
      });
    }

    btnOpen.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (btnOpen.disabled) return;
      if (pop.classList.contains('open')) closePop(); else openPop();
    });

    btnClear.addEventListener('mousedown', function (e) {
      e.preventDefault();
      inputId.value = '0';
      input.value = '';
      inputId.dispatchEvent(new Event('change', { bubbles: true }));
      if (!readonly) input.focus();
    });

    document.addEventListener('mousedown', function (e) {
      if (!pop.classList.contains('open')) return;
      if (!root.contains(e.target) && !pop.contains(e.target)) closePop();
    });
    window.addEventListener('scroll',  function () { if (pop.classList.contains('open')) positionPop(pop, input, root); }, true);
    window.addEventListener('resize',  function () { if (pop.classList.contains('open')) positionPop(pop, input, root); });

    var api = {
      open: openPop,
      close: closePop,
      position: function () { positionPop(pop, input, root); },
      input: input,
      inputId: inputId,
      pop: pop,
      btnAdd: btnAdd,
      choose: choose
    };
    root.__lookupApi = api;
    return api;
  }

  rootNS.bindLookup = bindLookup;
})(window);
