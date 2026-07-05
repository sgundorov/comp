# Архитектура проекта

## Структура файлов для сущности

Для каждой таблицы создаются:
- `config/{entity}_columns.php` — описание колонок
- `config/{entity}_page.php` — конфиг TablePage
- `{entity}.php` — страница с таблицей
- `{entity}_form.php` — форма (new/edit/copy/delete)
- `{entity}_field_save.php` — inline-edit (сохранение одного поля)
- `{entity}_export.php` — экспорт CSV/XLS
- `{entity}_print.php` — печать

## TablePage (`lib/TablePage.php`)

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

## table-template.php — рендеринг

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

## Модальная форма (FormModalCore)

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

## Lookup (`lib/controls.php` + `assets/lookup.js`)

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

## Inline Edit (`assets/inline-edit.js`)

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

## Column Filter (`assets/column-filter.js`)

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

## Hot Keys (`assets/table-keyboard.js`)

```js
bindTableKeyboardShortcuts({ formPrefix, rowSel, currentPage, currentPages, navigate, tableWrapEl })
```

- Insert — новая запись
- Enter — открыть
- Delete — удалить
- ArrowUp / ArrowDown — навигация
- Home / End — первая / последняя

Требует `window.__openFormModal`.

## Selection Actions

- `SelectionToolbar.init()` создаёт глобальные функции: `clearSelection`, `invertSelection`, `toggleShowOnly`, `exportSelected`, `printSelected`
- Селекты хранятся в таблице `marks` (id, tbl, row_id)

## Форматирование

- Дата в таблице: `Y-m-d H:i` (сортируемый формат)
- Числа: `number_format($f, 2, ',', ' ')`
- Для булевых / enum-полей — маппинг в читаемый текст
- Выделение строк через CSS-класс (например, по значению поля)
