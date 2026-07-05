# Project Memory

## Project Overview
CRM application "Comp" (Компаньон) - PHP/JS/MySQL web app for database management.

## Tech Stack
- Backend: PHP (mysqli)
- Frontend: JavaScript (vanilla)
- Database: MySQL (database: compan)
- Pattern: TablePage + FormModalCore

## Architecture

### Entity Files Pattern
For each table, 7+ files:
- `config/{entity}_columns.php` — column definitions
- `config/{entity}_page.php` — TablePage config
- `{entity}.php` — table page
- `{entity}_form.php` — form (new/edit/copy/delete)
- `{entity}_field_save.php` — inline-edit save
- `{entity}_export.php` — CSV/XLS export
- `{entity}_print.php` — print
- `{entity}_columns_save.php` — column settings save
- `{entity}_column_width_save.php` — column width save

### Key Libraries
- `lib/TablePage.php` — main table class
- `lib/controls.php` — lookup, render functions
- `lib/table-template.php` — table rendering
- `assets/` — JS (lookup.js, inline-edit.js, form-modal-core.js, etc.)

## Discovered Durable Knowledge

### From Sessions (2026-06-18)
1. **table-page skill exists**: `.opencode/skills/table-page/SKILL.md` (179 lines)
   - Covers: DB schema validation, config setup, search_cols, lookups, column visibility
   - Created from bugs in sale.php page
   
2. **Skill installation**: User copied skill to `~/.codex/skills/table-page` for MiMoCode access

3. **30+ conventions** documented in CONVENTIONS.md (learned from bug fixes):
   - appendWhere BEFORE getTotalCount/getRows
   - lookup.js choose() must set data-display
   - field_save.php must return displayValue for lookup_int
   - Inline scripts don't work with innerHTML (use main page init)
   - closeAllPanels() must be inside IIFE
   - param → dbField mapping for InlineEdit
   - White list in field_save.php
   - datetime format Y-m-d H:i
   - SelectionToolbar.init creates global functions
   - check stashed before closing with data-form-close
   - openFormModal: don't overwrite stashed in else branch
   - computePageOfNew() from table-helper.php
   - FormModalCore.setFocusAfterSave() after save
   - closePop() must not reset inputId.value to '0'

4. **Database tables** (from DESCRIPTION.md):
   - categ, city, client, clireg, cli_categ, cli_tag, contact, con_type
   - country, docum, docum2, dostup, filt, firm, group, invoice, invoice2
   - izgot, object, packing, plat, price, prodprice, product, promo
   - repgroup, repmenu, role, sgroup, sotr, status, store, typeop, unit, zat

## Patterns

### TablePage Flow
1. parseRequest() → parseSort() → loadColumnsConfig() → loadMarks()
2. loadSession() → parseCountryFilter() → buildWhere() → buildOrderBy()
3. appendWhere() BEFORE getTotalCount()/getRows()

### FormModalCore Flow
1. openFormModal(url) → fetch → JSON {ok, html, mode, focusField}
2. Insert html → initFormLookups() → bindForm()
3. Submit → POST → JSON {ok, html, mode, id, page}
4. setFocusAfterSave() → reload with focus=N&page=M

## Rules
- All table/form pages must follow DESCRIPTION.md standards
- Use table-page skill when creating/fixing table pages
- Run graphify update after code changes
- For codebase questions, use graphify query first

## Gotchas
- NOT NULL without DEFAULT → INSERT fails
- KEY vs UNIQUE KEY for auto-numbering (parallel creation)
- querySelector vs querySelectorAll for multiple data-lookup
- Inline scripts don't work with innerHTML

### From Sessions (2026-06-29-30)
1. **Внутреннее перемещение (typeop=100)** — новая сущность в sale.php
   - sale_form.php: Откуда (store_id) / Куда (store2_id), без скидки/суммы/оплаты
   - sale.php: в меню ссылка sale.php?typeop=100
   - Остатки: при утверждении −quant на store_id, +quant на store2_id

2. **POST-обработчик в sale_form.php** — был вложен в `if ($mode === 'new')`, edit не работал

3. **syncFormValues()** — синхронизация `.value` → HTML-атрибут перед stash. Критично для лукапов.

4. **initFormLookups в initSaleForm()** — без него standalone-режим не привязывает лукапи

5. **render_form_modal_script vs ручной код modal** — никогда не смешивать оба подхода

6. **ON DUPLICATE KEY** для остатков: `quant = quant + VALUES(quant)`, не `= VALUES(quant)`

7. **mysqli_stmt close + bind_param** — вызывает fatal error, нужен отдельный statement

---
*Memory consolidated: 2026-06-18, updated: 2026-06-30*
*Sessions analyzed: 3 (Auto Distill, Auto Dream, Skill creation)*
