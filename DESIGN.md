# Архитектура проекта

## 1. Общий обзор

**Тип:** CRM/ERP для управления товарами, счетами, документами, клиентами.

**Стек:** PHP 8, MySQL (mysqli), vanilla JavaScript (ES5/ES6), CSS3.

**Паттерн:** Page-Controller — каждая страница-таблица и страница-форма реализованы отдельным PHP-файлом, используют общие классы из `lib/`.

**UI:** тёмная тема, адаптивная вёрстка (desktop + mobile). Ширина страницы задаётся в настройках (по умолчанию 1100px), при превышении центрируется.

## 2. Структура директорий

```
lib/                        # PHP-библиотеки (14 файлов)
├── TableComponent.php      # Базовый класс: колонки, видимость, ширины, lookupData
├── TablePage.php           # Класс страницы-таблицы (2471 строка): пагинация, сортировка, поиск, фильтры
├── EmbeddedTable.php       # Класс вложенной подтаблицы (позиции invoice2, docum2)
├── table-template.php      # Функции рендеринга HTML (toolbar, pagination, head/footer, scripts)
├── table-page-scripts.php  # JS-инициализация компонентов для страницы таблицы
├── controls.php            # Рендеринг контролов (input, textarea, lookup, select, buttons)
├── form-modal-handler.php  # Обработчик модальных форм (stash/restore, bindForm, autoInit)
├── template-engine.php     # Шаблонизатор печатных форм (#VarName#, #D1…rows…#1D)
├── embedded-subtable-template.php  # PHP-шаблон рендеринга вложенной подтаблицы
├── marks-actions.php       # Обработчики отметок записей (selection)
├── SimplePdf.php           # Генерация PDF
├── sum_propis.php          # Сумма прописью
└── nested-table-panels.php

assets/                     # JavaScript + CSS (20 файлов)
├── form-modal-core.js      # Ядро модальных форм (open/close/submit/stash/restore, autoInitFormBody)
├── embedded-subtable.js    # Клиентская логика вложенных подтаблиц (CRUD строк, inline edit, refresh)
├── embedded-table.js       # Базовая встраиваемая таблица (рендер, загрузка данных)
├── lookup.js               # Lookup-поле (выбор из справочника с поиском)
├── row-select.js           # Выделение строк в таблице
├── inline-edit.js          # Инлайн-редактор ячейки
├── column-resize.js        # Ресайз колонок с сохранением ширины
├── column-filter.js        # Колоночный фильтр
├── columns-panel.js        # Панель настройки колонок (видимость, порядок)
├── search-panel.js         # Панель условий поиска
├── sort-panel.js           # Панель многоуровневой сортировки
├── selection-toolbar.js    # Действия с выбранными записями
├── export-modal.js         # Модальное окно экспорта
├── keyboard.js             # Горячие клавиши (общие)
├── table-keyboard.js       # Клавиатурная навигация по таблице
├── client-form.js          # Специфичный JS для формы клиента
├── color-picker.js         # Выбор цвета
├── tmc-history.js          # История товара
└── table.css               # Стили таблицы, тулбара, тултипов

config/                     # Конфигурации страниц (60 файлов, по 2 на сущность)
├── {table}_columns.php     # Определение колонок таблицы (массив column definitions)
└── {table}_page.php        # Параметры TablePage (SQL, сортировка, поиск, фильтры)

sdoc/                       # HTML-шаблоны печатных документов
img/                        # Иконки (PNG 28×28 для таблиц, 16×16 для кнопок)
uploads/                    # Загружаемые файлы (изображения товаров)
temp/                       # Черновики, скрипты для отладки
```

## 3. Структура файлов для сущности

Для каждой таблицы создаются:
- `config/{entity}_columns.php` — описание колонок
- `config/{entity}_page.php` — конфиг TablePage
- `{entity}.php` — страница с таблицей
- `{entity}_form.php` — форма (new/edit/copy/delete)
- `{entity}_field_save.php` — inline-edit (сохранение одного поля)
- `{entity}_export.php` — экспорт CSV/XLS
- `{entity}_print.php` — печать

## 4. TablePage (`lib/TablePage.php`)

### Ключевые публичные свойства

| Свойство | Описание |
|---|---|
| `$table` | Имя таблицы |
| `$key` | Первичный ключ |
| `$columns` | Дефолтный список колонок |
| `$colMeta` | Индексированный по `name` массив колонок |
| `$searchColExprs` | Выражения для поиска (name => SQL) |
| `$defaultSort` | `['col' => 'id', 'dir' => 'desc']` |
| `$selectSql`, `$countSql`, `$idSelectSql` | SQL с плейсхолдером `###WHERE###` |
| `$colFilters` | Настройки фильтров для колонок-справочников |
| `$orderBy` | Готовый ORDER BY |
| `$where`, `$params`, `$types` | Текущий WHERE и параметры для prepared statements |
| `$visibleColumns` | Только видимые колонки |
| `$marks`, `$marksCount` | Отмеченные записи |

### Порядок вызова методов (в конструкторе)

1. `parseRequest()` — GET-параметры (page, q, cols, cond, sf)
2. `parseSort()` — sort → $sortLevels
3. `loadColumnsConfig($conn)` — из БД
4. `loadMarks($conn)` — отметки
5. `loadSession()` — showOnly
6. `parseCountryFilter()` — фильтр по стране
7. `buildWhere()` — WHERE (поиск + страна + showOnly)
8. `buildOrderBy()` — ORDER BY
9. `buildFilters()` — массив активных фильтров для баннера

### appendWhere

```php
$tp->appendWhere($extraSql, $arrayOfParams, $typeString);
```

Вызывается **до** `getTotalCount()` / `getRows()`. Используется для column filters и кастомных WHERE.

### stmt_bind

`config.php` содержит `stmt_bind($stmt, $types, $params)` — обёртка над `bind_param` с авто-распаковкой массива по ссылкам. Количество символов в `$types` должно совпадать с длиной `$params`.

## 5. table-template.php — рендеринг

### render_toolbar_left

Кнопки: Добавить, Открыть, Удалить, Копировать, Обновить, Экспорт, Печать, Выбранные.
id кнопок: `rowOpenBtn`, `rowDeleteBtn`, `rowCopyBtn`. Обработчики — в JS страницы.

### render_script_includes

Подключает JS с версионированием (`?v=filemtime`).
Базовый набор: lookup.js, inline-edit.js, row-select.js, keyboard.js, table-keyboard.js, selection-toolbar.js, search-panel.js, sort-panel.js, column-resize.js, form-modal-core.js, columns-panel.js.
Дополнительные — через `$extra['scripts']`.

### render_table_thead

Callbacks для кастомизации:
- `thAttrsCallback(cn, cm, i)` — атрибуты на `<th>` (column filter data-param/data-values)
- `thLabelCallback(cn, label, cm)` — содержимое заголовка
- `thHtmlCallback(cn, cm)` — доп. HTML внутри `<th>`

### render_table_tbody

Ячейки:
- `data-value` — сырое значение (для lookup = числовой ID)
- `cell-editable` — если поле доступно для inline-edit
- `<span class="cell-value">` — отображаемое значение

```php
$cellValue($row, $colName, $vc) => [$rawValue, $displayValue]
```

Чекбокс имеет атрибуты `value` и `data-id` (ID записи).

## 6. Модальная форма (FormModalCore)

### Механизм

1. HTML: `<div class="form-modal-backdrop" id="formModal">`
2. Открытие: `openFormModal(url, stash?)`
   - Добавляет `ajax=1` к URL
   - Fetch GET → JSON `{ok, html, mode, focusField}`
   - Вставляет `html` в `formBody`
   - Вызывает `initFormLookups()`, `bindForm()`
3. Закрытие: `closeFormModal()` — убирает `open`, очищает `innerHTML`
4. Submit: `bindForm()` перехватывает submit → POST → JSON `{ok, html, mode, id, page, focusField}`

### initFormLookups

Инициализирует `bindLookup` для `[data-lookup]` в форме. Вызывается:
- После AJAX-подгрузки HTML формы
- В `restoreStashedForm()` при возврате к предыдущей форме
- При загрузке страницы без AJAX

## 7. Lookup (`lib/controls.php` + `assets/lookup.js`)

### render_lookup

```php
render_lookup($tableName, $hiddenName, $hiddenValue, $labelValue,
              $listJson, $addUrl, $readonly, $attrs)
```

- `<div data-lookup="$tableName" data-countries='...json...'>`
- `<input class="lookup-input" readonly>` — отображаемое имя
- `<input type="hidden" ... data-lookup-id>` — значение ID
- Кнопки: очистить, открыть попап, добавить (+)

### bindLookup

```js
bindLookup({ root, data, readonly, onSelect })
```

- `onSelect(id, name)` — callback при выборе
- В `choose()` обязательно: `input.setAttribute('data-display', name);`

### lookup-add (stashing)

При нажатии "+" — сохранение текущей формы в stash, открытие дочерней формы, после закрытия — восстановление через `restoreStashedForm`.

## 8. Inline Edit (`assets/inline-edit.js`)

```js
InlineEdit.init({
  tbody, saveUrl, fields, validate, onOpenForm, getLookupData
})
```

### fields

```
lookup-колонка: { dbField: '<id_field>', type: 'lookup', label: '...' }
select:         { dbField: '<field>',    type: 'select', label: '...', options: [...] }
textarea:       { dbField: '<field>',    type: 'textarea', label: '...' }
text:           { dbField: '<field>',    type: 'text', label: '...' }
```

### getLookupData

```js
getLookupData: function(field) {
  switch (field) {
    case '<lookup_col>': return __lookupData;
    ...
  }
  return [];
}
```

Массив `{id, name}`.

### field_save.php

- Белый список `$ALLOWED` — `$field` напрямую в UPDATE, строго по белому списку
- Типы: `text`, `int`, `decimal`, `lookup_int` (и кастомные)
- Для `lookup_int` — вернуть `displayValue` (имя из таблицы)
- Если изменение поля справочника влияет на другое поле — обновить его в том же запросе

## 9. Column Filter (`assets/column-filter.js`)

```js
ColumnFilter.init({ thSelector, pageUrl, param, placeholder, maxRows })
```

- `param` — URL-параметр (по умолчанию `data-param` или `{col}_id`)
- Данные через `action=columnFilterOptions&col=...`
- `data-values` на `<th>` — предустановленные значения

### server-side

```php
// 1. Парсинг GET-параметра фильтра
// 2. appendWhere — обязательно до getTotalCount
// 3. Аналогично для остальных фильтров
$tp->appendWhere("t.col IN ($ph)", $filterValues, $typeString);
```

Для колонок без таблицы справочника — `action=columnFilterOptions` возвращает хардкодный массив опций с маппингом ID → значение.

### thAttrsCallback для кастомного param

```php
'thAttrsCallback' => function($cn, $cm, $i) {
    if ($cn === '<column>') {
        $attrs = ' data-param="<custom_param>"';
        // data-values при активном фильтре
        return $attrs;
    }
    return '';
}
```

## 10. Hot Keys (`assets/table-keyboard.js`)

```js
bindTableKeyboardShortcuts({ formPrefix, rowSel, currentPage, currentPages, navigate, tableWrapEl })
```

- Insert — новая запись
- Enter — открыть
- Delete — удалить
- ArrowUp / ArrowDown — навигация
- Home / End — первая / последняя

Требует `window.__openFormModal`.

## 11. Selection Actions

- `SelectionToolbar.init()` создаёт глобальные функции: `clearSelection`, `invertSelection`, `toggleShowOnly`, `exportSelected`, `printSelected`
- Селекты хранятся в таблице `marks` (id, tbl, row_id)

## 12. Форматирование

- Дата в таблице: `Y-m-d H:i` (сортируемый формат)
- Числа: `number_format($f, 2, ',', ' ')`
- Для булевых / enum-полей — маппинг в читаемый текст
- Выделение строк через CSS-класс (например, по значению поля)

## 13. Вложенные подтаблицы (EmbeddedSubTable)

Используются для документов со строковой частью (счёт → товары, накладная → товары).

### PHP-конфигурация (`EmbeddedTable.php`)

```php
// В invoice_form.php
$inv2Table = new EmbeddedTable([
    'prefix'       => 'inv2',
    'saveUrl'      => 'invoice2_field_save.php',
    'parentField'  => 'invoice_id',
    'childFormUrl' => 'invoice2_form.php',
    'childFormName'=> 'Invoice2Form',
    'totalsCallback'=> 'applyInvoice2Totals',
    'colWidths'    => array_merge([...], load_columns_widths($conn, 'invoice2')),
    'columns'      => invoice2_columns_defaults(),
]);
$inv2Table->render($conn, $invoiceId);
```

### JS-компонент (`embedded-subtable.js`)

- Рендер таблицы с пагинацией, колоночным поиском, сортировкой
- CRUD строк: дочерняя форма (`childFormUrl`) + inline edit (`saveUrl`)
- `refresh()` — перезагрузка данных с сервера, восстановление выделения и страницы
- `totalsCallback` — обновление итогов родительского документа после сохранения/удаления строки
- `onDataChange` — callback при любом изменении данных

### Эндпоинты

| URL | Назначение |
|-----|-----------|
| `{prefix}_form.php` | Дочерняя форма для одной строки (new/edit/copy/delete) |
| `{prefix}_field_save.php?field=_list&parent_id=X` | Получить все строки |
| `{prefix}_field_save.php?field=_recalc_totals&parent_id=X` | Пересчитать итоги |
| `{prefix}_field_save.php` (POST field=name,value,id) | Inline edit / add / delete |

### Схема работы

```
Родительская форма (invoice_form.php)
  └─ EmbeddedTable (inv2)
       ├─ render() — рендерит HTML + JS
       ├─ refresh() — загружает список строк
       ├─ Дочерняя форма (invoice2_form.php) — добавить/редактировать строку
       │    └─ после сохранения → JSON → tbl.refresh()
       └─ Inline edit (invoice2_field_save.php) — изменить поле прямо в таблице
            └─ после сохранения → обновить ячейку + totalsCallback
```

## 14. Печатные документы

### Шаблонизатор (`template-engine.php`)

HTML-файлы в `sdoc/` с синтаксисом:

- `#VarName#` — подстановка значения (замена во всём шаблоне)
- `#D1...строки табличной части...#1D` — цикл по строкам дочерней таблицы

```php
$output = render_template($templatePath, $vars, $details);
// $vars = ['Номер' => '123', 'Дата' => '01.01.2024', ...]
// $details = ['D1' => [ ['Товар' => '...', 'Кол-во' => '...'], ... ]]
```

### Настройка шаблонов (`repmenu`)

- Таблица `repmenu` — список доступных шаблонов с группировкой (`repgroup`)
- Группа «Счета» (`gr_id = 1`) — для invoices и offers
- Группа «Документы» (`gr_id = 2`) — для sale/docum
- Каждый шаблон: `name` (отображаемое имя), `fname` (путь к HTML-файлу)
- Пункт меню «Настройка документов» → `repmenu.php` — управление шаблонами

### Подключение к странице

```php
// В invoice.php / invo.php
$repmenuTemplates = [];
$stmtTpl = $conn->prepare("SELECT rp_id, name, fname FROM repmenu WHERE gr_id = 1 AND (HIDE_FLAG IS NULL OR HIDE_FLAG = 0) AND fname != '' ORDER BY number");
// ... строим print dropdown из repmenuTemplates + render_print_dropdown_items()
```

### JS-функция печати с шаблоном

```javascript
window.printWithTemplate = function (rpId, fname) {
    const id = rowSel.getSelectedId();
    window.open('invoice_print_template.php?id=' + id + '&template=' + encodeURIComponent(fname), '_blank');
};
```

## 15. Схема данных

Основные сущности и их связи:

```
Фирма (firm) → Страна (country) → Город (city)
Контрагент (client) → Категория (cli_categ) → Вид деятельности (cli_tag)
Товар (product) → Категория (categ) → Группа (group) → Подгруппа (sgroup) → Производитель (izgot) → Ед.изм (unit)
Сотрудник (sotr) → Роль (role) → Объекты доступа (object)

Счёт (invoice) → Товары счёта (invoice2)  [doctype_id: 5=КП, 10=Счёт]
Документ движения (docum) → Товары документа (docum2) → Тип операции (typeop)
Кассовая книга (plat) → привязка к invoice/docum через doc_id + doc_type
```

## 16. Эндпоинты сущности

Для каждой таблицы (`{table}`) создаются следующие endpoint-ы:

| Endpoint | Метод | Назначение |
|----------|-------|-----------|
| `{table}.php` | GET | Страница-таблица с пагинацией, поиском, фильтрами |
| `{table}_form.php` | GET/POST | Форма (new/edit/copy/delete), JSON при AJAX |
| `{table}_field_save.php` | POST | Inline edit (field+value+id), `_list`, `_delete`, `_recalc_totals` |
| `{table}_export.php` | GET | Экспорт в CSV/XLS |
| `{table}_print.php` | GET | Табличная печать (HTML) |
| `{table}_print_template.php` | GET | Детальная печать документа по HTML-шаблону |
| `{table}_columns_save.php` | POST | Сохранение видимости и порядка колонок |
| `{table}_column_width_save.php` | POST | Сохранение ширины колонок |

## 17. Конвенции именования

### Файлы

| Паттерн | Пример |
|---------|--------|
| `{table}.php` | `city.php`, `invoice.php` |
| `{table}_form.php` | `client_form.php`, `invoice_form.php` |
| `{table}_field_save.php` | `sale_field_save.php` |
| `config/{table}_columns.php` | `config/client_columns.php` |
| `config/{table}_page.php` | `config/invoice_page.php` |
| `img/{table}.png` | `img/city.png` (иконка для заголовка) |

### URL-параметры

- `mode` — режим формы: `new`, `edit`, `copy`, `delete`
- `id` — ID записи
- `ajax=1` — флаг AJAX-запроса (ответ JSON, без HTML-обёртки)
- `q` — строка поиска
- `cols` — колонки поиска (через запятую)
- `cond` — условие поиска (`contains`, `starts_with`, `equals`, `gt`, `lt`…)
- `sf` — флаг активации поиска
- `sort` — JSON-массив уровней сортировки
- `page` — номер страницы
- `show-only` — показать только выбранные

### CSS-классы

- `.data-table` — таблица данных
- `.toolbar-left` / `.toolbar-right` — тулбар
- `.icon-btn` — кнопка с иконкой (34×34px)
- `.dropdown` / `.dropdown-menu` / `.dropdown-item` — выпадающие меню
- `.form-table` — таблица полей формы
- `.lookup-wrap` / `.lookup-pop` — lookup-поле
- `.cell-editable` / `.cell-value` — inline-edit
- `.form-modal` / `.form-modal-body` — модальное окно
- `.page-title` — заголовок страницы

### data-атрибуты

- `data-table` — имя таблицы для marks
- `data-form-modal` — флаг: форма открывается в модальном окне
- `data-lookup` — имя lookup-таблицы
- `data-lookup-id` — скрытое поле со значением ID
- `data-countries` — JSON-массив данных для lookup
- `data-lookup-add` — кнопка «+» для добавления нового элемента справочника
- `data-form-close` — кнопка закрытия формы
- `data-field` — имя поля для inline-edit
- `data-value` — сырое значение ячейки (для inline-edit)
- `data-row-id` — ID записи в строке таблицы
- `data-col-resize-inited` — флаг инициализации ресайза колонок
- `data-col-resize-tbl` / `data-col-resize-url` — конфиг ресайза
