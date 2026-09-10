# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

"Компаньон" (Companion) — PHP/MySQL business management application. No framework, vanilla PHP with procedural + OOP mix. Russian-language UI.

## Common Commands

```bash
# Run all tests
php phpunit.phar

# Unit tests only (no server required)
php phpunit.phar --testsuite unit

# Integration tests (requires server)
.\run_integration_tests.ps1

# Single test file
php phpunit.phar tests/Unit/FmtNumTest.php
php phpunit.phar tests/Integration/ClientFullTest.php

# Syntax check
php -l city.php
```

## Architecture

### Entry Point
`config.php` — loads DB connection, session, auth helpers, utility functions (`h()`, `hilight()`, `fmt_num()`), and auto-creates system tables (`marks`, `app_settings`, `column_visibility`).

### Table Pages Pattern
Each entity has 4+ files:
- `{entity}.php` — list page with TablePage
- `{entity}_form.php` — create/edit form
- `{entity}_export.php` — CSV/XLS export
- `{entity}_print.php` — print view
- `config/{entity}_page.php` — page config (SQL, search cols, marks settings)
- `config/{entity}_columns.php` — column definitions (labels, widths, visibility)

### Core Classes
- `TablePage` (`lib/TablePage.php`) — list page: search, sort, pagination, marks/selections, filters
- `TableComponent` (`lib/TableComponent.php`) — column visibility, widths, lookup data
- `EmbeddedTable` (`lib/EmbeddedTable.php`) — nested/embedded sub-tables

### Rendering
- `table-template.php` — HTML shell, login overlay, table markup
- `controls.php` — buttons, inputs, form controls
- `table-helper.php` — inline edit, row actions
- `form-modal-handler.php` — modal form JS (store.php pattern)

### Key Patterns
- **DB queries**: raw mysqli, prepared statements via `bind_auto()`
- **Session**: PHP sessions, marks stored in `marks` table (row selections persist across pages)
- **Auth**: login overlay, session-based, `$CurSotrID` global
- **Auto-tables**: `ensure_*_table()` functions in config.php create tables on first request
- **Column visibility**: stored in `column_visibility` table, loaded per-user per-table

### Data Flow
1. `entity.php` includes `config.php`, `config/entity_page.php`, `config/entity_columns.php`
2. Creates `TablePage` instance → parses URL params, loads marks, builds WHERE
3. Renders via `table-template.php` + inline PHP
4. Forms submit to `entity_form.php` → redirects back to list
5. Export endpoints read same params, output CSV/XLS