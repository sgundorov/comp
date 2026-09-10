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

## Session Summary (2026-08-26/27)

### Objective
Модуль «Аренда» (typeop=90, таблицы `docum`/`docum2`) + справочники тарифов (`prplan`, `price`): список, форма документа с вкладкой «Товары», форма товара аренды (child), проверка занятости/бронирования, экспорт/печать, меню. Затем исправление рассинхрона колонок d2-таблицы после объединения `rezerv_flag`/`voz_flag` в одну колонку `status`, и перенос всего кода таблицы товаров из `arenda.php` в `arenda_form.php`.

### Key facts
- **Рассинхрон шапки и данных d2-таблицы**: шапка генерируется серверно из `arenda_form.php` (`$d2Columns`, колонки `status, product_name, quant, hours, days, months, sum, sum_zalog, discount, note`), а тело рисует JS `initD2Table`, который определялся в **`arenda.php`** (там оставались 2 отдельные колонки `rezerv`+`vozvr` и лишняя `sum_discount`). Итог: тело на 2 ячейки шире шапки, данные сдвинуты.
- **Причина дубля**: модалка (arenda.php `openFormModal`) грузит HTML формы по ajax, а скрипты d2-таблицы форма отдавала только в standalone-режиме (`if ($isAjax) exit;` до вывода `renderScripts`). Поэтому JS-конфиг таблицы брался из файла списка.
- **Решение (архитектурно)**:
  - JS таблицы товаров (`EmbeddedTable::renderScripts()`) перенесён **внутрь HTML формы** `arenda_form.php` (закрыт `<script>`, идёт в `$formHtml` до `ob_get_clean`) → теперь и модалка (eval скриптов), и прямое открытие используют **один источник** колонок.
  - `arenda.php` освобождён: убраны `$_d2cols`/`$_d2w`/`$d2Table`, оба вывода `renderScripts()` и `require EmbeddedTable.php`; осталось только то, что относится к таблице `docum` (список).
  - Модалка/standalone инициализируют d2 через `initD2Table(); applyArendaD2Renderers(__d2Table)` (guard `typeof window.__openFormModal === 'undefined'`).
- `assets/embedded-subtable.js` и `assets/arenda-d2-renderers.js` остаются подключёнными на странице списка (нужны модалке). Renderers: `status`-колонка показывает иконку резерва (`img/time.png`) или возврата (`img/vozvr.png`) по `rezerv_flag`/`voz_flag`.
- **Побочный плюс**: инлайн-выбор товара в d2 теперь получает `lookupData ['product_name' => productList]` (раньше в модалке список был пуст).

### Changes made
1. **`arenda.php`** — удалён весь блок docum2 (`$_d2cols`, `$_d2w`, `$d2Table`, `renderScripts()` ×2, `require EmbeddedTable.php`, CSS `.docum2-table`); осталась только таблица `docum`. Кнопки «Установить Начало/Возврат/Скидку» и `bindMakeButtons` используют `window.__d2Table` (создаётся скриптом формы).
2. **`arenda_form.php`** — JS `$d2Table->renderScripts()` включён в HTML формы (`<script>` перед `ob_get_clean()`, с guard-инициализацией); standalone-ветка больше не дублирует `renderScripts` (только инициализация после подключения ассетов). Единственный источник колонок d2. Колонка `status` (readonly) вместо `rezerv`/`vozvr`; `sum_discount` в d2 не показывается (есть в параметрах формы).
3. **Модуль целиком (26.08)** — `arenda.php`, `arenda_form.php`, `arenda2_form.php` (форма товара: лукап product, prplan/price, часы/дни/месяцы, `check_bronir` через `arenda2_check.php`), `arenda_field_save.php` (`_apply_beg`/`_apply_voz`/`_recalc_totals`/inline), `arenda2_check.php` (`quant_date`, `check_bronir` по `residue`/пересечению периодов), `arenda_export.php`, `arenda_print.php`, `arenda_columns_save.php`, `config/arenda_*`, `assets/arenda-d2-renderers.js`; справочники `prplan.php` + `config/prplan_*`; тарифы `price_form.php`/`price_field_save.php`/`price_export.php`/`price_print.php` (подтаблица товара, открывается из `tmc.php`); меню «Аренда» (Docum) и «Тарифные планы» (Product) в `menu.php`.
4. **Чистка** — удалены временные файлы: `dbg_a2_tmp.php`, `smoke_prn_dump_tmp.php`, `smoke_sort_tmp.php`, `config--.php`, `60000`, `ainv_2-08-26.sql`, `arenda_print_out.html`, `_chk_note.php`.
5. **Главная таблица `arenda.php`** — колонки-иконки `rezerv` + `vozvr` объединены в одну колонку `status` (иконка резерва `time.png` в приоритете, иначе возврата `vozvr.png`): `config/arenda_columns.php` (defaults/widths, sort_expr `CASE...`), `arenda.php` (tbody-кейс `status`, CSS `.col-status`, SORT_COLS исключает `status`), `arenda_export.php`/`arenda_print.php` (colValues `status` = «Резерв»/«Возврат»). Сохранённые настройки колонок `column_visibility.tbl='arenda'` сброшены (были старые `rezerv`/`vozvr`). Иконки в `status`-ячейке позиционируются **абсолютно** (CSS `position:absolute; top:50%; translate(-50%,-50%)`) — высота строки задаётся текстом и не растёт от иконки (и в главной таблице, и в d2-таблице формы `arenda_form.php`).
6. **Ресайз колонок после stash/restore** (`assets/column-resize.js`) — баг: при открытии дочерней формы `arenda2_form` родительская разметка формы сериализуется в `innerHTML` (stash), в неё попадают атрибут `data-col-resize-inited` и ручки `.col-resize-handle`, но **слушатели событий не сериализуются**; после restore `ColumnResize.init` выходил рано по флагу → ручки «видимые», но мёртвые (ресайз не работает, в консоли без ошибки). Фикс: `initResize()` сделан идемпотентным — старые ручки и флаг всегда удаляются и привязываются заново. Проверено на headless Chrome: ресайз d2 работает до и после открытия дочерней формы (и при отмене, и при сохранении); видимый баг «товары не появились в таблице после save» в авто-тесте оказался артефактом синтетического заполнения (реальный выбор товара — через лукап).
7. **Расчёт тарифа и суммы товара аренды** — реализованы `get_price()`/`calc_sum()` по спецификации (`111.txt`, 111.txt:748-852):
   - `arenda2_check.php` — новое действие `get_price`: базовая цена по `product.period_aren` (`m/d/h`), залог `product.zalog` (0 при `client.nozalog_flag`), сезон → `docum2.prplan_id` по `date_beg` внутри `prplan.bdate..edate` (форматы разные: date_beg `Y-m-d`, prplan `d.m.Y` — конвертация), тарифы из `price` по `prplan+product` с фильтром `fixed_flag` по настройке `FixedFlag`; часовой тариф — `price.btime <= hours <= price.etime` (исходная запись `btime >= hours` — обратный порядок отрезка), дневной — `price.bdays <= days <= price.edays`; `price_we = price.pricef`, при 0 → `price_day`; `price_fix` для фиксированных. Возвращает `price, price_hour, price_day, price_we, price_fix, price_month, sum_zalog, prplan_id`.
   - `arenda2_form.php` — JS `get_price()` (выбор товара/периода/плана): при ручном режиме `ManualTariffFlag` только `calc_sum()`; `calc_sum()` по `111.txt`: `sum = price_day*дней + price_hour*часов` (± выходные, месячные тарифы для period `m`), скидка, множитель `quant` (если не `noquant_flag`). Сохранение новых колонок `price_hour/day/we/fix/month` в `docum2` (INSERT/UPDATE + hidden-поля), `productList` обогащён `zalog/period_aren`. Привязки: смена товара → `get_price`, дней/часов/месяцев → `get_price`, скидки/цены → `calc_sum` (обработчики обёрнуты — иначе в `calc_sum` приходил объект Event и падал `TypeError`).
   - E2E (headless Chrome, ManualTariff=0): товар «Болт 5 х 12» (период `h`), 5 дней + 10:00 ч + скидка 10% → `price=5, price_hour=10 (За час 02:00–13:00), price_day=150 (Дни с 4 по 7), price_we=150, sum_zalog=100, sum=765, sum_discount=85, prplan=1 (сезон)`, сохранено в `docum2` — соответствует ожиданиям.
8. **Авто-сохранение новой записи при переключении на вкладку «Товары»** — `FormModalCore.initTabAutoSave(tabIndexes)` вызывался в `sale_form.php`/`invoice_form.php`/`arenda_form.php` (строки `<script>...initTabAutoSave([1,2])</script>`), но **функция нигде не была реализована** — поэтому в новой записи при переходе на вкладку оставался плейсхолдер «Сохраните…». Реализована в `assets/form-modal-core.js`: при mode=new/copy и id=0 клик по вкладке из списка отправляет форму с `auto_save=1` (все три PHP-формы уже поддерживают: возвращают `{ok,id}` без валидации), затем открывает форму в режиме edit с этим id и переключает на целевую вкладку (проверка нового id уже сохранено). Проверено headless: arenda (id 0→123, вкладка Товары активна, d2-таблица открыта) и sale (0→124).
9. **Формат часов и название тарифа в форме товара аренды**:
   - `arenda2_form.php` — на blur поля «Часов» (`arenda2-hours`) значение форматируется как в `price_form.php` (`norm_time_smart` + `disp_time`): `2 → 2:00`, `930 → 9:30`, `1230 → 12:30`, `9:15 → 9:15`; после форматирования посылается `change` для пересчёта тарифа.
   - `arenda2_check.php` `get_price` теперь возвращает `tariff_id`/`tariff_name` выбранного тарифа (приоритет дневного, иначе часового); `arenda2_form.php` показывает его в селекте «Название тарифа» (`arenda2-price-id`), добавляя option, если записи нет в списке.
   - Проверка headless (тарифный режим): формат часов ок, при 5 днях + 10:00 → «Дни с 4 по 7» (id 3), price_day=150, price_hour=10, sum=850, ошибок JS нет.
   - **Важно для тестов**: свои проверочные записи создаются с пометкой в `note` (например `_tk`) и удаляются точечно по docum_id; записи пользователя НЕ удаляются.
10. **Расчёт стоимости, выбор тарифа по часам, цена `price_id`**:
   - `calc_sum` (`arenda2_form.php`): условие `quant > 1` убрано — при `noquant_flag=0` `quant` всегда умножается (явный `quant=0` даёт сумму 0; пустое поле по-прежнему = 1); месячный тариф **суммируется** с днями и часами (условие `period_aren='m'` убрано — при `months>0` месячная часть добавляется всегда, если `ShowMonthsFlag`).
   - `get_price` (`arenda2_check.php`): если `days==0 && months==0` — выбор/название тарифа по **часам** (часовой тариф), иначе по дням; `price_id`/`price_name` возвращаются и JS показывает их в селекте, при `price_id` из БД опция дозагружается.
   - `price_id` теперь сохраняется в `docum2` (INSERT/UPDATE) и при переоткрытии формы (edit) селект «Название тарифа» показывает сохранённый тариф.
   - Найден и исправлен баг: в INSERT `values` было на один `?` больше (31 вместо 30, `typeop` — литерал `90`) — `prepare` падал (fatal при сохранении товара).
   - Проверено через HTTP (без браузера): hours-only → тариф «От часа до 13:00» (id 9); days=5 → «Дни с 4 по 7»; после сохранения price_id=9 → re-open показывает «От часа до 13:00» выбранным.
   - Обновление названия тарифа больше **не зависит от `check_bronir`**: в `arenda2_form.php` `onPeriodChange()` вызывает `get_price()` напрямую (проверка доступности идёт отдельно по товару/quant), иначе «занятый» товар блокировал пересчёт и название не менялось. Предпочтение тарифа в `get_price`: заданы дни/месяцы + подобран дневной → дневной; иначе часовой; запасной — любой найденный.
   - `ManualTariffFlag=1` («Тарифы вводятся вручную») **отключает подбор тарифа**: `get_price` в этом режиме только пересчитывает сумму, а название/`price_id` не заполняются (по спецификации). По выбору пользователя настройка переведена в `0` — автоматический подбор активен (проверено headless с автозакрытием диалогов: товар → «Первый день»/id 1, days=5 → «Дни с 4 по 7»/id 3, hours=10:00 → тот же дневной, sum 850; в режиме 1 было «—»/id 0).
11. **Выбор «Фиксированных тарифов» из «Названия тарифа»**:
   - Селект `arenda2-price-id` содержит **все тарифы** (обычные и фиксированные); **выбирать можно только фиксированные** (`price.fixed_flag=1` — активны, с data-атрибутами `bdays/edays/bmonths/emonths/btime/etime/price/hprice/mprice`), нефиксированные — `disabled` (видно, но не выбираются). `get_price` возвращает `prices` (все записи для prplan+product), JS пересобирает селект; авто-тариф отображается выбранным, даже если он нефиксированный — название показывается корректно.
   - Ручной выбор фикс. тарифа: `docum2.fixed_flag=1`, `price_fix` = цена записи; **дневной** → `days = edays`, `hours/months=''`; **часовой** → `hours = etime`, `days/months=''`; **месячный** (если `mprice`/`emonths` заданы) → `months = emonths`, `days/hours=''`. После установки days/hours/months пересчитываются `date_voz`/`time_voz` из `date_beg`/`time_beg` (`applyPeriodEnd`, календарный расчёт, всё через JS-математику по компонентам).
   - `calc_sum` использует фиксированную ветку при `FIXED_FLAG || fixedMode` (работает и при `FixedFlag=0`, если тариф выбран вручную). `manualFixed` удерживает выбор при смене days/hours (не переподбирает тариф).
   - `fixed_flag` сохраняется в `docum2` (INSERT/UPDATE); при переоткрытии формы фикс. тариф снова выбран.
   - E2E (headless): в списке все тарифы (0,1..9,20; disabled: 1,2,3,4,5,9,20), авто «Первый день» показан выбранным; выбор «Неделя фиксированный» → `days=7, price_fix=1000, fixed=1, sum=1000, voz=2026-09-02 12:00`; ручная правка days=10 выбор не сбрасывает; ошибок JS нет. Месячных фикс-записей в данных нет (поля `bmonths/emonths/mprice` в `price` пусты) — «Месяц фиксированный» пока задаёт `days=30`.
12. **Переработка формы `arenda2_form.php`**:
   - Убрано поле **`docum2.price`** (Тариф): из значений, POST, INSERT/UPDATE и разметки (поле не используется и вводило в заблуждение).
   - Новая раскладка: Товар+Возвращён / Количество+Начало+Возврат / Часов+Дней+Месяцев / **За час+За день+За месяц** (read-only, видимость по `ShowHoursFlag/ShowDaysFlag/ShowMonthsFlag`) / Тарифный план+Название тарифа / Скидка %+Сумма скидки / Сумма+Залог / Примечание.
   - Общая функция подбора тарифа вынесена в **`lib/arenda2_tariff.php`** (`arenda2_select_tariff()`); используется при рендере формы (название/цены/залог подставляются, даже если `price_id` в БД = 0 у старых записей) и (в JS) — «живая» подтяжка `get_price()` при открытии формы: **название тарифа и сумма всегда корректны**, независимо от устаревшего серверного рендера/кэша.
   - Исправлен INSERT: после удаления `price` оставался лишний `?` в `VALUES` (31 вместо 30) — сохранение товара падало fatal.
   - Проверено E2E (свежий сервер, headless): открытие «старой» позиции (price_id=0) → название «Дни с 4 по 7», `priceDay=150`, `sum=750`, поле «Тариф» отсутствует, ошибок JS нет.
13. **Обработчики полей Часов/Дней/Месяцев в `arenda2_form.php`** (по спецификации):
   - **Часов** → `applyPeriodEnd()`: `time_voz = time_beg + hours` (при >=24ч сдвигается и дата), затем `check_bronir()` и `get_price()`.
   - **Дней** → `applyPeriodEnd()`: `date_voz = date_beg + days` (часы сохраняются), затем `check_bronir()` и `get_price()`.
   - **Месяцев** → сброс `days=''`, `hours=''`, `applyPeriodEnd()`: `date_voz = date_beg + months` (календарно), `time_voz = time_beg`, затем `check_bronir()` и `get_price()`.
   - Количество: пустое поле = 1 единица (сумма ×1), явный `0` → сумма 0; у сохранённых записей `quant=0` отображается как «0» (а не пустым — иначе при edit сумма считалась ×1). E2E: hours=2:00→`voz=14:00`/сумма по часу, days=3→`voz=+3дня`/«Дни 2 и 3», months=2→`days/hours` пусты/`voz=+2 мес`; ошибок JS нет. Сумма за месячный период считается общей формулой (включая выходные за период).
14. **Месяцы: блокировка дней/часов и НДС (единообразно)**:
   - `calc_sum`: если `months > 0 && price_month > 0` — **сумма только за месяцы** `price_month × months` (дни, часы и выходные не участвуют); иначе прежняя логика (дни+часы+выходные/фикс).
   - `onMonthsChange` + `syncMonthLock()`: при `months>0` поля **Дней/Часов** становятся `readOnly`; **дата возврата считается календарно** (`date(день, месяц+months, год)` с переходом на следующий год), `days` = фактическое число дней между `date_beg` и `date_voz`, `hours=''`; при `months=0` разблокируются и сбрасываются. `applyPeriodEnd` месяцы календарно НЕ добавляет (месяцы задаются через дату возврата). `syncMonthLock()` вызывается и при загрузке формы (edit), и в фикс-месячном выборе.
   - **НДС** учитывается единообразно в конце `calc_sum` (после ветки, скидки и количества): если `nds_rate>0` и `no_nds≠1` — `sum *= (1 + nds_rate/100)`. Проверено (nds_rate=20): days=5 → `900` (150×5×1.2), months=2 → `3600` (1500×2×1.2); при months=2 `days/hours` readOnly. Календарные месяцы проверены: 26.08+2→`voz 26.10`/days 61, 15.12+3→`voz 15.03 (след. год)`/days 90; ошибок JS нет.
15. **Изменение полей `date_voz`/`time_voz`** (`arenda2_form.php`):
   - **date_voz** → если `KeepDaysOnEarlyReturn` не включён (0): `days = date_voz - date_beg`, затем `check_bronir()` и `get_price()`.
   - **time_voz** → если `ShowHoursFlag` включён: `hours = time_voz - time_beg` (в минутах; при отрицательном результате + сутки = 24·60), затем `check_bronir()` и `get_price()`.
   - Проверено (headless): date_voz 31.08→days=5, 02.09→days=7; time_voz 14:00 (beg 12:00)→hours 2:00, 10:00(<beg)→hours 22:00; сумма/название тарифа обновляются; ошибок JS нет.
16. **Включение «Возвращено» (voz_flag)** (`arenda2_form.php`, по 111.txt:660-683):
   - При `months == 0` — прежняя логика дней/часов: `days = date_voz - date_beg` (при `N=NoRefundOnEarlyReturn && E && (!K=KeepDaysOnEarlyReturn || dv<today)`; clamp <0 → 0 и `date_voz=''`), блок «`days==0`/не показывать Дней» — `hours = now+time_shift - time_beg`; затем `date_voz=тек.дата`, `time_voz=тек.время+time_shift` (только если сейчас > времени выдачи). Настройки `NoRefundOnEarlyReturn`, `KeepDaysOnEarlyReturn`, `TimeShift`, `ShowHoursFlag`/`ShowDaysFlag`.
   - При `months > 0` — **пропорционально фактическому сроку** (по выбору): `used_days = тек.дата - date_beg`, сумма = `price_month × (used_days/30) × quant` (скрытое поле `used_days`), `days=used_days` (locked), `hours=''`, `date_voz=тек.дата`, `time_voz=тек.время+time_shift`.
   - Выключение: `date_voz = date_beg + days`, `time_voz = time_beg + hours`; после любого изменения флага → `get_price()`.
   - **Внимание**: у checkbox `value` всегда «1» — состояние проверять только через `el.checked` (не `getVal`). E2E (headless): months=2 → ON: days=5, sum=250 (1500×5/30), voz=сегодня; OFF: sum=3000, vozD=beg+days; ошибок JS нет.
17. **Бэкаполь документа и пересчёт итогов** (`lib/arenda2_totals.php`):
   - `arenda2_backfill_docum($conn, $documId)` вызывается после **каждого** сохранения/удаления товара аренды (`arenda2_form.php`: delete/new/copy/edit) и после инлайн-операций в `docum2_field_save.php` (если doc `typeop=90`).
   - Обновляет в `docum`: `date_voz = MIN(docum2.date_voz)` среди позиций с `voz_flag=0`; `time_voz = MIN(time_voz)` при этой дате; `days/hours` — из минимальной позиции; `sum/sum_zalog/sum_discount/pos/sum_balans/sum_plat`.
   - Возвращает итоги (`total_sum`, `total_sum_discount`, `total_sum_zalog`, `pos`, `sum_plat`, `date_voz`, `time_voz`, `days`, `hours`) — обновляют родительскую форму через `applyDocum2Totals` (исправлен баг: `arenda-sum-discount` не присваивался; добавлены `date_voz/time_voz/days/hours`).
   - «Не изменять Дней при досрочном возврате» (`KeepDaysOnEarlyReturn=1`) при `months>0` → **месяцы не уменьшаются** (остаются полными), иначе — пропорционально `days/30`. Смена тарифного плана → `get_price()` (уже было).
   - Проверено HTTP: A (voz 10.09, days 5, sum 750) → B (voz 05.09, days 2) → docum `date_voz=05.09`, `days=2`, `sum=1050`, `sum_zalog=200`, `pos=2`; после удаления B → `date_voz=10.09`, `days=5`, `sum=750`, `pos=1`.
18. **Проброс флагов документа на товары при сохранении** (`arenda_form.php` + `lib/arenda2_voz.php`):
   - При переключении `docum.rezerv_flag` / `docum.voz_flag` в момент сохранения формы флаг пробрасывается на **все** `docum2` (typeop=90): `arenda2_items_set_flags()`.
   - При переключении `voz_flag` — `arenda2_items_recalc_sums()`: пересчёт стоимости каждого товара (месячные — пропорц. `days/30` или полные при `KeepDaysOnEarlyReturn`; иначе дни/часы/выходные), для `voz=1` — `date_voz=тек.дата`, `time_voz=тек.время+TimeShift`, и `arenda2_backfill_docum()`.
   - **Перед сохранением**: если возвращены **все** товары документа → `docum.voz_flag` принудительно 1 (поэтому «снять» возврат с документа возможно только сняв его со всех товаров по одному).
   - Проверено HTTP: rezerv=1 → у всех товаров rezerv=1; voz=1 → товары voz=1, дата/время возврата = текущие, сумма пересчитана, `docum.voz=1`, `docum.date_voz=''` (нет открытых позиций).
 19. **Сохранение Дней/Часов при переоткрытии** — `syncMonthLock()` при загрузке формы в ветке «months=0» **очищал** поля `days`/`hours`, стирая сохранённые значения (при months видно не было — они заполняются заново). Исправлено: при `months=0` поля только разблокируются (не очищаются); очистка осталась только в интерактивном `onMonthsChange` при сбросе месяцев. Проверено: days=5/hours=2:00 → сохранилось и при переоткрытии осталось (E2E headless до фикса показывало days/hours пустыми).

## Session Summary (2026-08-30) — пересчёт days/hours при включении voz_flag

### Objective
Если параметр «Не изменять „Дней“ при досрочном возврате» (`KeepDaysOnEarlyReturn`) НЕ включён, то при включении `docum2.voz_flag` должны пересчитываться `days` и `hours` и суммы. В том числе при пробросе `docum.voz_flag` через сохранение формы документа (`arenda_form.php`).

### Key facts
- Раньше серверный пересчёт при `voz_flag` (`arenda2_items_recalc_sums`) пересчитывал только `sum`/`sum_discount`, ставил `date_voz=today`/`time_voz=now+TimeShift`, но **не менял `days`/`hours`**. Пересчёт `days`/`hours` была только в JS `onVozFlagChange()` (`arenda2_form.php:1014-1085`), т.е. только при сохранении формы товара.
- По решению пользователя:
  - настройка `NoRefundOnEarlyReturn` («не возвращать деньги при досрочном возврате») **пока не используется** — при расчёте её игнорируем;
  - при `KeepDaysOnEarlyReturn=0` и `months==0`: `days = today - date_beg` (фактический срок до момента возврата), `hours = now+TimeShift - time_beg` (если сейчас позже времени выдачи);
  - при `months>0` (и `ShowMonthsFlag`): `months/days = splitPeriod(date_beg, today)` (пропорционально факт. сроку), `hours = ''`;
  - при `KeepDaysOnEarlyReturn=1`: `days`/`hours`/`months` НЕ изменяются (только `date_voz=today`, `time_voz=now+shift`, пересчёт сумм);
  - при выключении `voz_flag=0`: `date_voz = date_beg + days + (hours переполнение/1440)`, `time_voz = time_beg + hours (mod 1440)` (как JS `onVozFlagChange`/else), суммы пересчитываются.

### Changes made
1. **`lib/arenda2_voz.php`** — новая функция `arenda2_apply_voz_to_item($conn, $appSettings, $documId, $itemId, $voz)`: применяет voz_flag к одному товару с пересчётом days/hours/months (см. выше), date_voz/time_voz и sum/sum_discount (через `arenda2_calc_sum_for_item`). `arenda2_items_recalc_sums` переписана на перебор товаров и вызов `arenda2_apply_voz_to_item` (проброс `docum.voz_flag`).
2. **`arenda2_form.php`** — после UPDATE docum2 (mode=edit) вызывается `arenda2_apply_voz_to_item` при `voz_flag` перед `arenda2_backfill_docum()`; подключён `lib/arenda2_voz.php`.
3. **`docum2_field_save.php`** — при инлайн-правке `voz_flag` аренды: ветка `elseif ($field === 'voz_flag')` вызывает `arenda2_apply_voz_to_item` (на случай если где-то есть инлайн; подключён `lib/arenda2_voz.php`).
4. **`arenda2_form.php` (мгновенное обновление при включении voz_flag)** — раньше поля формы (`date_voz/time_voz/days/hours/sum`) обновлялись только при нажатии «Записать» (серверный apply). Причина: JS `onVozFlagChange` падал с `ReferenceError: SHOW_DAYS is not defined` (переменная не была объявлена, но использовалась в блоке 2), из-за чего прерывался до установки дат и вызова `get_price()`. Исправлено:
   - добавлена `var SHOW_DAYS = <?= $showDaysFlag ? 1 : 0 ?>;` (рядом с SHOW_HOURS/SHOW_MONTHS);
   - блок `months==0` приведён к серверной логике `arenda2_apply_voz_to_item`: `days = today - date_beg`, `hours = now+shift - time_beg` (без зависимостей от N/E/days==0), затем `date_voz/today`, `time_voz/now`; в конце — `get_price()` → `calc_sum()` пересчитывает `sum` мгновенно.
   - headless: doc 198 `days 5→2`, `hours ''→9:26`, `date_voz 02.09→30.08`, `sum 750→444.33` — все поля обновляются при клике на чекбокс «Возвращён», до сохранения.
5. **`arenda_field_save.php` + `arenda_form.php` (мгновенный проброс docum.voz_flag/rezerv_flag)** — раньше при переключении чекбоксов «Возвращено»/«Резервирование» в форме документа проброс флагов на товары (`arenda2_items_set_flags` + `arenda2_items_recalc_sums`) и пересчёт сумм/периодов выполнялись только при нажатии «Записать». Добавлен новый экшен `_set_doc_flags` в `arenda_field_save.php` (подключён `lib/arenda2_voz.php`): обновляет `docum.rezerv_flag/voz_flag`, пробрасывает на все `docum2` через `arenda2_items_set_flags`, при изменении `voz_flag` — пересчёт через `arenda2_items_recalc_sums` (→ `arenda2_apply_voz_to_item`), затем `arenda2_backfill_docum`. Возвращает `{ok, total_sum, total_sum_discount, pos, sum_plat, date_voz, time_voz, days, hours, months, doc_rezerv_flag, doc_voz_flag, changed}`. В `arenda_form.php`: чекбоксам `voz_flag`/`rezerv_flag` добавлены `id="arenda-doc-voz-flag"` / `id="arenda-doc-rezerv-flag"`; JS-функция `bindArendaDocFlags()` (глобальная, перепривязывается после restore из модалки через `evalFormScripts`/eval) при изменении чекбоксов вызывает `arendaSendDocFlags()` → fetch `_set_doc_flags` → `applyDocum2Totals(data)` обновляет поля формы (sum/date_voz/days/hours) и `__d2Table.refresh({focusId: selectedId})` перерисовывает товары (иконки статусов). Сработало в headless для standalone (`arenda_form.php?mode=edit&id=215`) и модалки (`arenda.php` → dblclick строки 215 → клик voz): чекбокс переключается, `arenda-sum 667,66`, `arenda-date-voz 2026-08-30`, d2=2 строки. Пользовательские данные (doc 125/153/172) не тронуты.

### Testing (HTTP, без браузера)
- `docum2` days=38/date_voz=05.10/beg=28.08, voz=1 (Keep=0): days→2 (30.08-28.08), hours→12:xx, date_voz→30.08, sum пересчитан; voz=0: date_voz→beg+days, time→hours.
- `months=3/beg=15.06, voice=1` (ShowMonths=1, Keep=0): months→2, days→15, sum пересчитан; с Keep=1: months остаётся 3.
- Временные `docum2` (`_tk`) удалены; БД чистая.
- Все 3 файла проходят `php -l`.

### Current state
- Все файлы проходят `php -l` (проверка `c:/xa/php/php.exe -l`)
- Dev-проверка: список открывается; модалка формы содержит `initD2Table` из самой формы; шапка d2 = колонки JS (`status, product_name, quant, hours, days, months, sum, sum_zalog, discount, note`) — совпадают; standalone тоже работает
- Главная таблица: шапка `check, status, firm, number, vremya, client, beg, vozvrat, poz, sum, sum_plat, plat_type, sotr, note`; в `status` — иконка резерва или возврата; старых колонок `rezerv`/`vozvr` нет (сброс `column_visibility` для arenda)
- БД почищена: нет записей `docum typeop=90`, `docum2 typeop=90`, сиротских docum2, plat/marks по аренде
- **Известные «хвосты»**: тариф за месяц из `price` (колонки `bmonths/emonths/mprice`) пока не выбирается — `price_month` берётся из `product.price_month`; при создании товара `docum2.discount` копируется из документа (а не из поля «Скидка» формы товара) — но `sum` в форме считается по введённой скидке

---

См. также [USER.md](./USER.md) — профиль пользователя и правила взаимодействия.
