<?php
if (defined('NESTED_TABLE_PANELS_LOADED')) return;
define('NESTED_TABLE_PANELS_LOADED', true);

/**
 * Генерирует JS для панели сортировки вложенной таблицы (invoice2, docum2 и т.п.).
 * Заменяет ~220 строк дублируемого кода.
 *
 * Пример:
 *   render_nested_sort_panel_js([
 *     'btnId'      => 'inv2-sort-btn',
 *     'colKeys'    => "INV2_COL_KEYS",
 *     'columns'    => "INV2_SEARCH_COLS",
 *     'sortColVar' => 'sortCol',
 *     'sortDirVar' => 'sortDir',
 *     'pageVar'    => 'currentPage',
 *     'renderFn'   => 'renderInvoice2',
 *     'tableVar'   => 'table',
 *   ]);
 */
function render_nested_sort_panel_js(array $config): void {
    $btnId      = $config['btnId'] ?? 'inv2-sort-btn';
    $colKeys    = $config['colKeys'] ?? 'INV2_COL_KEYS';
    $columns    = $config['columns'] ?? 'INV2_SEARCH_COLS';
    $sortColVar = $config['sortColVar'] ?? 'sortCol';
    $sortDirVar = $config['sortDirVar'] ?? 'sortDir';
    $pageVar    = $config['pageVar'] ?? 'currentPage';
    $renderFn   = $config['renderFn'] ?? 'renderInvoice2';
    $tableVar   = $config['tableVar'] ?? 'table';
    $scope      = $config['scope'] ?? 'document'; // 'document' or 'formBody'

    $q = $scope === 'formBody' ? 'formBody.querySelector' : 'document.querySelector';
    $g = $scope === 'formBody' ? 'formBody' : 'document';
    ?>
      var sortBtn = <?= $q ?>('#<?= $btnId ?>');
      if (sortBtn) {
        sortBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          var closeFn = window.closeInv2Panels || window.closeAllPanels || function(){};
          closeFn();
          var cols = <?= $columns ?>;
          var directions = [{ key: 'asc', label: 'По возрастанию' }, { key: 'desc', label: 'По убыванию' }];
          var levels = <?= $sortColVar ?> >= 0
            ? [{ col: <?= $colKeys ?>[<?= $sortColVar ?>], dir: <?= $sortDirVar ?> }]
            : [{ col: cols[0].key, dir: 'asc' }];

          function colLabel(k) { for (var i = 0; i < cols.length; i++) if (cols[i].key === k) return cols[i].label; return k; }
          function dirLabel(k) { for (var i = 0; i < directions.length; i++) if (directions[i].key === k) return directions[i].label; return k; }
          function usedCols(lvs, exceptIdx) { var u = []; lvs.forEach(function(l, i) { if (i !== exceptIdx) u.push(l.col); }); return u; }

          var backdrop = document.createElement('div');
          backdrop.className = 'sort-modal-backdrop';
          backdrop.innerHTML =
            '<div class="sort-modal" role="dialog">' +
              '<div class="sort-modal-header">' +
                '<span>Сортировка</span>' +
                '<button class="sort-modal-close" type="button" title="Закрыть">✕</button>' +
              '</div>' +
              '<div class="sort-modal-body">' +
                '<div class="sort-level-actions">' +
                  '<button type="button" class="sort-add-level">+ Добавить уровень</button>' +
                  '<button type="button" class="sort-remove-level">− Удалить уровень</button>' +
                '</div>' +
                '<div class="sort-levels">' +
                  '<div class="sort-cols"><div class="sort-cols-header">Столбец</div><div class="sort-cols-list"></div></div>' +
                  '<div class="sort-dirs"><div class="sort-dirs-header">Направление</div><div class="sort-dirs-list"></div></div>' +
                '</div>' +
                '<div class="sort-modal-actions">' +
                  '<button type="button" class="sort-apply"><img src="img/ok.png" alt="" />Сортировать</button>' +
                  '<button type="button" class="sort-cancel"><img src="img/cancel.png" alt="" />Отменить</button>' +
                '</div>' +
              '</div>' +
            '</div>';
          document.body.appendChild(backdrop);

          var modal = backdrop.querySelector('.sort-modal');
          var colsList = backdrop.querySelector('.sort-cols-list');
          var dirsList = backdrop.querySelector('.sort-dirs-list');
          var addBtn = backdrop.querySelector('.sort-add-level');
          var removeBtn = backdrop.querySelector('.sort-remove-level');
          var cancelBtn = backdrop.querySelector('.sort-cancel');
          var applyBtn = backdrop.querySelector('.sort-apply');
          var closeBtn = backdrop.querySelector('.sort-modal-close');

          var pop = document.createElement('div');
          pop.className = 'sort-pop';
          document.body.appendChild(pop);

          function positionPopup(target, p) {
            var r = target.getBoundingClientRect();
            var pw = p.offsetWidth || 320;
            var ph = p.offsetHeight;
            var left = r.left;
            if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
            var top = r.bottom + 4;
            if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
            p.style.left = left + 'px';
            p.style.top = top + 'px';
          }

          function closePop() { pop.classList.remove('open'); pop.dataset.kind = ''; }
          function closeModal() { backdrop.classList.remove('open'); closePop(); }

          function renderLevels() {
            colsList.innerHTML = '';
            dirsList.innerHTML = '';
            addBtn.disabled = levels.length >= cols.length;
            removeBtn.disabled = levels.length <= 1;
            levels.forEach(function(l, i) {
              var cRow = document.createElement('div');
              cRow.className = 'sort-row';
              var cLabel = document.createElement('span');
              cLabel.className = 'sort-label';
              cLabel.textContent = i === 0 ? 'Сначала по' : 'Затем по';
              var cSel = document.createElement('div');
              cSel.className = 'sort-select';
              cSel.tabIndex = 0;
              cSel.innerHTML = '<span class="sort-select-label">' + colLabel(l.col) + '</span>';
              cSel.addEventListener('click', function(idx, el) {
                return function(ev) { ev.stopPropagation(); openColPop(idx, el); };
              }(i, cSel));
              cRow.appendChild(cLabel);
              cRow.appendChild(cSel);
              colsList.appendChild(cRow);

              var dRow = document.createElement('div');
              dRow.className = 'sort-row';
              dRow.style.gap = '0';
              var dSel = document.createElement('div');
              dSel.className = 'sort-select';
              dSel.tabIndex = 0;
              dSel.innerHTML = '<span class="sort-select-label">' + dirLabel(l.dir) + '</span>';
              dSel.addEventListener('click', function(idx, el) {
                return function(ev) { ev.stopPropagation(); openDirPop(idx, el); };
              }(i, dSel));
              dRow.appendChild(dSel);
              dirsList.appendChild(dRow);
            });
          }

          function openColPop(idx, target) {
            var used = usedCols(levels, idx);
            pop.innerHTML = '';
            cols.forEach(function(c) {
              var isSel = levels[idx].col === c.key;
              var isUsed = used.indexOf(c.key) !== -1;
              var item = document.createElement('div');
              item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
              item.textContent = c.label;
              if (isUsed && !isSel) {
                item.style.opacity = '0.45';
                item.style.cursor = 'default';
              } else {
                item.addEventListener('click', function(colKey) {
                  return function(ev) { ev.stopPropagation(); levels[idx].col = colKey; closePop(); renderLevels(); };
                }(c.key));
              }
              pop.appendChild(item);
            });
            pop.classList.add('open');
            pop.dataset.kind = 'col';
            positionPopup(target, pop);
          }

          function openDirPop(idx, target) {
            pop.innerHTML = '';
            directions.forEach(function(d) {
              var isSel = levels[idx].dir === d.key;
              var item = document.createElement('div');
              item.className = 'sort-pop-item' + (isSel ? ' selected' : '');
              item.textContent = d.label;
              item.addEventListener('click', function(dirKey) {
                return function(ev) { ev.stopPropagation(); levels[idx].dir = dirKey; closePop(); renderLevels(); };
              }(d.key));
              pop.appendChild(item);
            });
            pop.classList.add('open');
            pop.dataset.kind = 'dir';
            positionPopup(target, pop);
          }

          addBtn.addEventListener('click', function(ev) {
            ev.stopPropagation();
            if (levels.length >= cols.length) return;
            var used = usedCols(levels, -1);
            var available = [];
            cols.forEach(function(c) { if (used.indexOf(c.key) === -1) available.push(c); });
            if (available.length === 0) return;
            levels.push({ col: available[0].key, dir: 'asc' });
            renderLevels();
          });

          removeBtn.addEventListener('click', function(ev) {
            ev.stopPropagation();
            if (levels.length <= 1) return;
            levels.pop();
            renderLevels();
          });

          cancelBtn.addEventListener('click', function(ev) { ev.stopPropagation(); closeModal(); });
          closeBtn.addEventListener('click', function(ev) { ev.stopPropagation(); closeModal(); });

          applyBtn.addEventListener('click', function(ev) {
            ev.stopPropagation();
            if (levels.length > 0) {
              var col = levels[0].col;
              var idx = <?= $colKeys ?>.indexOf(col);
              <?= $sortColVar ?> = idx >= 0 ? idx : 0;
              <?= $sortDirVar ?> = levels[0].dir;
              <?= $pageVar ?> = 1;
              <?= $renderFn ?>();
              var ths = <?= $q ?>('table<?= $scope === 'formBody' ? '' : '' ?>').querySelectorAll('thead th[data-col]');
              ths.forEach(function(th) { var si = th.querySelector('.sort-indicator'); if (si) si.remove(); });
              if (ths[<?= $sortColVar ?>]) {
                var ind = document.createElement('span');
                ind.className = 'sort-indicator';
                ind.textContent = <?= $sortDirVar ?> === 'asc' ? ' ↑' : ' ↓';
                ths[<?= $sortColVar ?>].appendChild(ind);
              }
            }
            closeModal();
          });

          document.addEventListener('click', function(ev) {
            if (!backdrop.classList.contains('open')) return;
            if (ev.target.closest('.sort-modal, .sort-pop.open')) return;
            ev.stopPropagation();
            closeModal();
          });

          document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
          });

          renderLevels();
          backdrop.classList.add('open');
        });
      }
    <?php
}
