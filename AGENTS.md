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
- Pending: browser verification, integration tests

См. также [USER.md](./USER.md) — профиль пользователя и правила взаимодействия.
