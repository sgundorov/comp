# AGENTS.md

## Project Overview
<!-- Brief description of what this project does -->

## Tech Stack
<!-- Languages, frameworks, libraries used -->

## Project Structure
<!-- Key directories and their purposes -->

## Coding Conventions
- Naming conventions
- Code style / formatting rules
- Architecture patterns to follow

## Testing
- Testing framework used
- How to run tests
- Test coverage expectations

## Git Workflow
- Branch naming convention
- Commit message format
- Code review process

## Important Commands
<!-- Build, run, test, lint, deploy commands -->

## Session Summary (2026-07-14)

### Objective
Fix form modal not closing after submit in city.php/client.php (stayed open with empty body). Migrate both pages to `store.php` pattern (standalone render functions) for consistency.

### Key facts
- **Root cause**: `stashCurrentForm` in `TablePage::renderScripts()` (`lib/TablePage.php:1997`) — when `!form`, it did `stashed = { html: body.innerHTML }` instead of early `return`. On submit, `if (stashed)` was truthy → called `restoreStashedForm()` instead of `closeFormModal()` + redirect.
- `form-modal-handler.php` (centralized) had correct `if (!form) return;` — so `store.php`/`contact.php`/`country.php` worked fine.
- Only city.php and client.php used `$tp->renderScripts()` with the bug. Other tables already on `store.php` pattern or have custom form modal JS.

### Changes made
1. **`lib/TablePage.php:1997`** — `stashCurrentForm`: `if (!form) { stashed = {...}; return; }` → `if (!form) return;`
2. **`city.php`** — migrated to `store.php` pattern
3. **`client.php`** — migrated to `store.php` pattern; fixed 4 missing `<?php` tags (3 + 1 in post-migration code at line 392); `$columnWidthsJson`/`$columnDefaultWidthsJson` deduplicated; added `assets/export-modal.js` to script includes; fixed `contactsBtn`/`invoiceBtn`/`platBtn` scope — moved from separate IIFE into main RowSelect IIFE
4. **`sotr.php`** — ColumnFilter `role` filter: added `param: 'role'` (JS used `data-param="role_id"` → URL `?role_id=...` but PHP reads `$_GET['role']`); cleaned up duplicate `data-col`/`data-param` in `thAttrsCallback`
5. **`tests/README.md`** — test system documentation

### Current state
- All pages pass `php -l` syntax check
- client.php renders without JS errors (tested via built-in server)

---

## Session Summary (2026-07-24)

### Objective
- Форма оплаты счёта: авто-заполнение `sum_in` остатком (`invoice.sum - invoice.sum_plat`)
- Таблица счетов: красный текст `sum_plat` при неполной оплате
- Серверная + клиентская блокировка запретов для роли admin на критических объектах
- Исправление `_apply_discount` для invoice2 и docum2 (учёт количества, сохранение discount в заголовок)
- Меню "Администрирование" с группировкой ролей/объектов/прав

### Changes made
1. **`invoice_form.php:398`** — `childFormUrl` для `$platTable`: добавлены `client_id`, `sotr_id`, `zat_id`, `sum_in` (остаток = `sum - sum_plat`)
2. **`invoice.php:327-338`** — `render_table_tbody`: добавлен `tdExtraAttrs` с красным цветом/жирностью для `sum_plat` при `sum_plat < sum`
3. **`sotr_form.php`** — проверка на удаление последнего администратора (роль из БД)
4. **`sotr_field_save.php`** — проверка при inline-смене роли: нельзя снять последнего админа
5. **`dostup_save.php`** — серверная блокировка запретов для админа на `Sotr`, `Role`, `Object`, `Setup` (кроме `print_flag`)
6. **`dostup.php`** — клиентская блокировка: класс `admin-locked` + JS-проверка
7. **`menu.php`** — "Файлы" → "Администрирование", в него вложены Роли/Объекты/Права
8. **`app.css`** — `.submenu` border unified с `.dropdown-menu`
9. **`invoice2_field_save.php`** — `_apply_discount` исправлен: `quant * price` + `UPDATE invoice SET discount = ?`
10. **`docum2_field_save.php`** — `_apply_discount` + `UPDATE docum SET discount = ?`
11. **`invoice.php`** — хендлер apply-discount: `setData` → `refresh()` (исправление обновления таблицы в модалке)

---

## Session Summary (2026-08-02)

### Objective
Создать таблицу «Инвентаризация» (ainv = акты, ainv2 = товары акта) по образцу invoice/invoice2: страница, форма акта с child-table товаров, inline-сохранение, columns/export/print, меню — и добиться рабочего E2E-цикла «создать акт → добавить товар → пересчёт сумм → удалить».

### Key facts
- **Root cause (блокер E2E)**: таблицы `ainv`/`ainv2` имели charset `latin1`, а приложение работает в utf8 (`config.php:31` `mysqli_set_charset($conn,'utf8mb4')`) → INSERT кириллицы в `product_name` падал с `errno 1366 Incorrect string value`. Конвертированы в `utf8mb4_unicode_ci` (по согласию пользователя).
- **Логика товара**: при создании товара в акт копируются `date`, `time`, `firm_id`, `mesto_id` из акта; look-ап товара заполняет `code`, `product_name`, `quant_old = product.residue`, `price = product.price_in`; `dif = quant_old - quant`, `sum = price * dif`; акт: `sum = SUM(ainv2.sum)`, `poz = COUNT(*)` (колонка БД называется `poz`).
- **Контракт child-table**: префикс `a2`, saveUrl `ainv2_field_save.php`, parentField `number`, childFormUrl `ainv2_form.php?mode=&id=&number=`, totalsCallback `applyAinv2Totals`, `formModalConfig.autoInitTables=['a2']` + `extra_restore` (initA2Table + refresh({focusId})).
- **`ainv2_form.php`** читал `number` только из `$_GET` — добавлен fallback на `$_POST` (требуется для надёжного POST).
- Таблицы уникальны: `UNIQUE(number,id)`, `UNIQUE(product_id,id)`; создание нового акта через `ainv_form.php` POST возвращает `{ok, id}`.

### Changes made
1. **`ainv.php`**, **`ainv_form.php`** — страница списка + форма акта (child-table a2, колонки сумм/позиций)
2. **`ainv2_form.php`** — форма товара акта (look-ап product, авто-подстановка, расчёт dif/sum, state 0–10, save/delete-экшены, `number` из GET или POST)
3. **`ainv2_field_save.php`** — `_list`/`_delete`/`_recalc_totals`/инлайн-сохранение (product_id, quant, state, note) с пересчётом
4. **`ainv_field_save.php`** — ALLOWED date/time/mesto_id/firm_id/first_card/last_card/type/accept_flag/note + `_list`/`_delete`/`_recalc_totals`
5. **`ainv_columns_save.php`**, **`ainv_column_width_save.php`**, **`ainv2_column_width_save.php`** — сохранение колонок/ширин
6. **`ainv_export.php`** — csv/xls с подстановкой mesto_name/firm_name, «Да» для accept_flag
7. **`ainv_print.php`** — печать (HTML + print CSS)
8. **`menu.php`** — «Инвентаризация» в меню «Документы» (после «Кассовая книга») + `'ainv.php' => 'Docum'` в `$menuAccessMap`
9. **`config/ainv_columns.php`** — `ainv2_columns_defaults()`
10. **`config/ainv_page.php`** — TablePage-конфиг (key `number`, marks `ainv_select`, col_filters mesto/firm, select_sql с JOIN mesto/firm)
11. **БД**: `ALTER TABLE ainv, ainv2 CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`

### Current state
- Все 13 файлов проходят `php -l`
- HTTP-смоук на dev-сервере `php -S 0.0.0.0:8765`: ainv.php 200; ainv2_form new/edit ok; ainv2_field_save `_list` ok; width-save валидация неверной колонки ok
- **E2E пройден**: создать акт (ok, id=4) → добавить товар (ok, id=3, number=4, dif=-195, sum=-3900, total_sum=-3900.00, pos=1) → кириллическое имя сохранено → delete акта (ok)
- БД чистая: ainv=0, ainv2=0

---

## Session Summary (2026-08-03)

### Objective
Кнопка «Утверждено» (accept-toggle) в тулбаре ainv.php, как в sale.php; при `accept_flag=1` заблокировать редактирование в формах (кроме `ainv.note`); разобраться с «пропавшим» полем «Примечание».

### Key facts
- **«Примечание» не пропадало**: колонка была в HTML (colgroup/thead/tbody = 12/12/12, включая accept_flag). Проблема — в `render_table_colgroup`: note имел `width: auto`, а при `table-layout: fixed` (`assets/table.css:233`) `auto`-колонка схлопывается. При печати использовался другой layout — поэтому там note был виден.
- **Фикс**: явные ширины note — главная таблица `'note' => '400px'` (ainv.php `render_table_colgroup`), встроенная a2 `'note' => '300px'` (ainv.php + ainv_form.php `colWidths`). sale.php использует fallback `150px` (пустая строка в widths).
- **acceptToggle** в ainv.php: ключ `number` (не `docum_id`), только `ainv.accept_flag` (в ainv2 нет accept_flag). Кнопка — `<button class="icon-btn" id="acceptBtn">` с `img/lock.png`, передаётся в `render_toolbar_left($FORM_PREFIX,...,$acceptBtnHtml)` (5-й аргумент).
- **JS acceptBtn** в `extraCode` (не внутри IIFE с `rowSel`): id берётся из `document.querySelector('tbody tr.selected').getAttribute('data-row-id')`, POST `ainv.php?action=acceptToggle&id=N`, reload.
- **Лока форм**: ainv_form.php — `$isApproved = $values['accept_flag'] === 1; $ro = $isReadonly || $isApproved;` поля в `$ro`, note — только `$isReadonly`; `$a2Table->readonly = $ro;`. POST-защита: `$origApproved` + восстановление полей кроме note. Удаление утверждённого акта заблокировано («Нельзя удалить утверждённый акт», как sale_form.php:99-108).
- ainv2_form.php — `$parentAccepted` из `ainv.accept_flag` по `number`; `$isReadonly = $mode==='delete' || $parentAccepted`; серверный guard в POST save/delete.
- **Серверные guard** (обязательны, клиент можно обойти):
  - `ainv_field_save.php` — при approved: inline-правка только `field==='note'`, `_delete` заблокирован (проверка по `number` из ainv2).
  - `ainv2_field_save.php` — `ainv2_check_accepted($conn,$num)`: inline-edit и `_delete` заблокированы при approved-родителе.
- `render_toolbar_left` поддерживает 5-й аргумент `$extraHtml` (`lib/table-template.php:138`).

### Changes made
1. **`ainv.php`** — POST-хендлер `action=acceptToggle`; `$acceptBtnHtml` + передача в `render_toolbar_left`; JS на `acceptBtn` в extraCode; `colgroup note 400px`; a2 `colWidths note 300px`
2. **`ainv_form.php`** — `$isApproved/$ro`, блокировка полей кроме note, `$a2Table->readonly=$ro`, POST-защита origApproved, блок удаления утверждённого акта; a2 `note 300px`
3. **`ainv2_form.php`** — `$parentAccepted` (accept_flag из ainv), `$isReadonly` расширен, guard в POST save/delete
4. **`ainv_field_save.php`** — inline-запрет (только note при approved), запрет `_delete`
5. **`ainv2_field_save.php`** — `ainv2_check_accepted()` для inline-edit и `_delete`

### Current state
- Все 5 файлов проходят `php -l`
- E2E на dev-сервере: toggle accept (5→1→0), поля формы readonly кроме note, date-edit заблокирован / note-edit разрешён, ainv2 save/delete заблокированы, удаление утверждённого акта заблокировано
- Тестовые акты 5–8 (repro/double) остались в БД; временные `_chk_cols.php`, `_chk_note.php` — на удаление (по разрешению пользователя)

---

См. также [USER.md](./USER.md) — профиль пользователя и правила взаимодействия.
