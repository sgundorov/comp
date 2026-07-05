# Conventions

## 1. Column Filter: appendWhere **до** getTotalCount/getRows

**НЕПРАВИЛЬНО**:
```php
$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
// filters after — won't affect query!
$tp->appendWhere("t.col IN ($ph)", $ids, '...');
```

**ПРАВИЛЬНО**:
```php
$tp->appendWhere("t.col IN ($ph)", $ids, '...');
$tp->getTotalCount($conn);
$rows = $tp->getRows($conn);
```

## 2. lookup.js choose() — устанавливать data-display

```js
input.setAttribute('data-display', name);
```

Без этого InlineEdit не прочитает отображаемое имя.

## 3. field_save.php: возвращать displayValue для lookup_int

После сохранения lookup-поля запросить имя из таблицы справочника:
```php
case 'lookup_int':
    $value = (int)$value;
    // UPDATE ...
    if ($value > 0) {
        $tableMap = [
            'field_id' => ['table' => '<table>', 'id_field' => '<pk>', 'name_field' => '<display_col>'],
        ];
        if (isset($tableMap[$field])) {
            $m = $tableMap[$field];
            $qr = $conn->query("SELECT {$m['name_field']} FROM {$m['table']} WHERE {$m['id_field']} = $value");
            if ($qr && ($rw = $qr->fetch_assoc())) $displayValue = h($rw[$m['name_field']]);
        }
    }
    break;
```

## 4. columnFilterOptions для колонок без таблицы — хардкод

Для колонок с фиксированным набором значений (нет таблицы-справочника):
```php
if ($col === '<column>') {
    echo json_encode([
        ['id' => 1, 'name' => 'Значение1'],
        ['id' => 2, 'name' => 'Значение2'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
// для обычных справочников:
echo json_encode($tp->colFilterOptions($conn, $col));
```

## 5. Инициализация формы — не полагаться на inline `<script>` при AJAX

При загрузке через `innerHTML` inline-скрипты не исполняются. Всю инициализацию выносить в JS главной страницы:
```js
function initFormLookups() { /* bindLookup для [data-lookup] */ }
function initFormCalc() { /* кастомные calc */ }
```

Вызывать в `openFormModal` success callback, `restoreStashedForm`, и при не-AJAX загрузке.

## 6. closeAllPanels() — внутри IIFE страницы

```js
function closeAllPanels() {
    document.querySelectorAll('.col-filter-panel.open').forEach(p => p.classList.remove('open'));
    document.querySelectorAll('.search-cond-panel, .search-cond-pop, .columns-panel').forEach(p => p.remove());
    document.querySelectorAll('.sort-modal-backdrop.open').forEach(p => p.classList.remove('open'));
}
```

Передаётся в `SearchPanel.init()` и `ColumnsPanel.init()`.

## 7. param в колонке — dbField для InlineEdit + URL-параметр

```php
['name' => '<col>', 'label' => '...', 'param' => '<db_field>']
```

- `param` → `dbField` в InlineEdit
- `param` → `data-param` на `<th>`
- Если URL-параметр отличается — `config.param` в `ColumnFilter.init()`

## 8. Белый список полей в field_save.php

```php
$ALLOWED = [
    'field1' => ['type' => 'text'],
    'field2' => ['type' => 'lookup_int'],
];
if (!isset($ALLOWED[$field])) { echo json_encode(['ok' => false, 'error' => 'Field not allowed']); exit; }
// $field безопасен для имени столбца в SQL
```

## 9. datetime в таблице — Y-m-d H:i

```php
$dt = strtotime((string)$row['datetime']);
$display = $dt ? date('Y-m-d H:i', $dt) : '';
```

Хронологическая сортировка совпадает с лексикографической.

## 10. Изменение поля справочника может обновлять зависимое поле

Если выбор значения из lookup влияет на другое поле записи — обновлять его в том же запросе (серверный сайд-эффект).

## 11. data-value для lookup-колонок = числовой ID

```php
case '<lookup_col>': return [(int)$row['<id_field>'], h($row['<display_field>'])];
```

Raw = ID (для InlineEdit), display = имя.

## 12. Высота попапа column filter

CSS: `.col-filter-list { max-height: 240px; overflow-y: auto; }`.

## 13. Форма: Date + Time в одном field-row

```html
<div class="field-row">
  <?= render_field('Дата', render_input('date', ...)) ?>
  <?= render_field('Время', render_input('time', ...)) ?>
</div>
```

## 14. Форма: связанные поля в одном field-row

Группировать логически связанные поля (суммы, типы, readonly-индикаторы) в `field-row`.

## 15. SelectionToolbar.init — определяет глобальные функции

`SelectionToolbar.init()` создаёт: `clearSelection`, `invertSelection`, `toggleShowOnly`, `exportSelected`, `printSelected`. Дублировать не нужно.

## 16. Hot keys — проверять наличие bindTableKeyboardShortcuts

```js
if (typeof bindTableKeyboardShortcuts === 'function') {
    bindTableKeyboardShortcuts({ formPrefix, rowSel, currentPage, ... });
}
```

## 17. Радиокнопки — name общий, id уникальный

```php
foreach ($options as $opt):
    $radioId = '<prefix>' . str_replace([' ', '.'], '_', $opt);
    // type="radio" name="<field>" value="$opt" id="$radioId"
endforeach;
```

## 18. Кастомный авто-расчёт в форме — инициализация в главной странице

```js
function initFormCalc() {
    // навесить input/change обработчики на поля
}
```

Вызывать в `initFormLookups()` или отдельно.

## 19. stmt_bind — типы и значения синхронизировать

Длина `$types` = количество элементов `$params`. Для `'sii'` нужно 3 элемента.

## 20. clearQs — сохранять параметры column filter

```php
$clearQs = function($drop) {
    // ...
    if (!in_array('<filter_param>', $drop, true) && !empty($_GET['<filter_param>'])) {
        $qs['<filter_param>'] = $_GET['<filter_param>'];
    }
    // ...
};
```

## 21. Фильтрация VARCHAR-колонок — строковые параметры

Если колонка имеет тип VARCHAR, параметры в `appendWhere` должны быть строками:
```php
$tp->appendWhere("t.col IN ($ph)", $filterNames, str_repeat('s', count($filterNames)));
```

## 22. Фильтрация integer-колонок с маппингом

Если значения фильтра (ID из UI) не равны напрямую значениям в БД — маппинг:
```php
$idToDbValue = [
    1 => <db_value_a>,
    2 => <db_value_b>,
];
$tp->appendWhere("t.col IN ($ph)", $mappedValues, str_repeat('i', count($mappedValues)));
```

## 23. baseQs для пагинации — сохранять column filter params

```php
$baseQs = function($p) use (...) {
    $qs = ['page' => (int)$p];
    if (!empty($_GET['<filter_param>'])) $qs['<filter_param>'] = $_GET['<filter_param>'];
    return http_build_query($qs);
};
```

## 24. thAttrsCallback для кастомного param

```php
'thAttrsCallback' => function($cn, $cm, $i) {
    if ($cn === '<column>') {
        $attrs = ' data-param="<custom_param>"';
        $raw = (string)($_GET['<custom_param>'] ?? '');
        if ($raw !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v > 0));
            if (count($ids) > 0) $attrs .= ' data-values="' . implode(',', $ids) . '"';
        }
        return $attrs;
    }
    return '';
}
```

## 25. Modal stashing при открытии дочерней формы

При `[data-lookup-add]` — сохранить текущую форму через `stashed`, открыть дочернюю форму, после её закрытия — восстановить через `restoreStashedForm`. Колбэк `onRestore` вызывается после восстановления, может обработать данные из ответа.

## 26. `data-form-close` — проверять stashed перед закрытием

**НЕПРАВИЛЬНО** (закроет и родительскую форму):
```js
if (e.target.closest('[data-form-close]')) { e.preventDefault(); closeFormModal(); return; }
```

**ПРАВИЛЬНО** (восстановит родительскую форму, если есть stashed):
```js
if (e.target.closest('[data-form-close]')) { e.preventDefault(); if (stashed) { restoreStashedForm(null); } else { closeFormModal(); } return; }
```

Без этой проверки нажатие `×` при открытой дочерней форме (через `[data-lookup-add]`) закрывает ВСЕ формы, вместо возврата к родительской.

## 27. pageOfNew — использовать `computePageOfNew()` из `lib/table-helper.php`

Вычисление страницы для новой записи вынесено в общую функцию `computePageOfNew()` в `lib/table-helper.php`. Функция учитывает направление сортировки (ASC/DESC) и вычисляет позицию записи, а не просто количество строк.

**НЕПРАВИЛЬНО** (вручную, копипаста):
```php
$cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM <table>");
$pageOfNew = (int)ceil($cntRow['cnt'] / PAGE_SIZE);
```

**ПРАВИЛЬНО**:
```php
$pageOfNew = computePageOfNew($conn, 'table', 'pk_col', 'sort_col', 'asc', $sortVal, $newId, 'where_cond');
```

Параметры функции:
- `$conn` — соединение с БД
- `$table` — имя таблицы
- `$pkCol` — первичный ключ (например, `id`, `invoice_id`)
- `$sortCol` — колонка сортировки из `default_sort` страницы
- `$sortDir` — направление: `'asc'` или `'desc'`
- `$sortVal` — значение колонки сортировки для новой записи
- `$newPk` — значение первичного ключа новой записи
- `$where` — дополнительный фильтр (без `WHERE`), например `"doctype_id = 10"`

Логика: при `ORDER BY … DESC` запись с бóльшим значением сортировки получает меньший номер позиции. Запрос `COUNT(*) + 1 WHERE (sortCol > val OR (sortCol = val AND pk > newPk))` считает строки, которые должны быть выше в порядке DESC.

## 28. Клиентская часть «выделенной записи» — `FormModalCore.setFocusAfterSave()`

После успешного сохранения формы (новой, копии, редактирования или удаления записи) клиентский код должен перезагрузить страницу таблицы с параметрами `focus=N&page=M`, чтобы новая/изменённая запись оказалась выделенной.

Логика вынесена в общую функцию `FormModalCore.setFocusAfterSave(params, form, data)` в `assets/form-modal-core.js`. Она определяет режим (`mode`) по скрытому полю `<input name="mode">` и:

- **new/copy**: устанавливает `focus` из `data.id` и `page` из `data.page` (вычисленного на сервере через `computePageOfNew`).
- **edit**: устанавливает `focus` из `id` формы.
- **delete**: находит соседнюю строку в DOM; если удалена последняя на странице — переходит на предыдущую страницу.
- **прочее**: удаляет `focus`.

**НЕПРАВИЛЬНО** (копипаста режимов в каждой странице):
```js
const mode = (form.querySelector('input[name="mode"]') || {}).value || '';
const params = new URLSearchParams(location.search);
if (mode === 'new' || mode === 'copy') { if (data.id) { params.set('focus', String(data.id)); if (data.page) params.set('page', String(data.page)); else params.delete('page'); } else { params.delete('focus'); } }
else if (mode === 'edit') { ... }
else if (mode === 'delete') { ... }
else { params.delete('focus'); }
location.href = 'entity.php?' + params.toString();
```

**ПРАВИЛЬНО**:
```js
const params = new URLSearchParams(location.search);
FormModalCore.setFocusAfterSave(params, form, data);
location.href = 'entity.php?' + params.toString();
```

**Дополнительные параметры**: если странице нужно очистить дополнительные query-параметры при создании записи (например, `country_id`, `city_id`), это делается отдельной строкой ПОСЛЕ вызова `setFocusAfterSave`:
```js
['q', 'cols', 'cond', 'sf', 'country_id'].forEach(function (k) { params.delete(k); });
```

## 29. `closePop()` — не сбрасывать `inputId.value` в `'0'`

**НЕПРАВИЛЬНО** — `closePop()` проверяет введённый текст и сбрасывает скрытый id в `'0'` при несовпадении:
```js
function closePop() {
  pop.classList.remove('open');
  const typed = input.value.trim();
  const found = data.find(function (c) { return c.name === typed; });
  if (found) inputId.value = String(found.id);
  else inputId.value = '0';   // <--- сбрасывает выбранное значение
}
```

Проблема: при клике на кнопку «Сохранить» с открытым попапом сначала срабатывает `document mousedown`, который вызывает `closePop()`. Если введённый текст не совпадает с данными (например, частичный ввод), `inputId.value` сбрасывается в `'0'`. Затем срабатывает `submit`, и `FormData` захватывает `client_id=0`.

**ПРАВИЛЬНО** — `closePop()` только закрывает попап, не трогая скрытый input. Значение hidden-поля меняется только в `choose()` (выбор из списка) или в `btnClear` (кнопка очистки):
```js
function closePop() {
  pop.classList.remove('open');
}
```

## 30. `openFormModal` — не затирать `stashed` при открытии дочерней формы

При открытии формы через `data-lookup-add` («+») последовательность вызовов:
1. `stashFn` записывает `stashed`
2. `openFn` вызывает `openFormModal(url)` без аргумента `stash`

Если `openFormModal` имеет `else { stashed = null; }`, то на шаге 2 `stashed` затирается, и при закрытии дочерней формы родительская не восстанавливается.

**НЕПРАВИЛЬНО** — безусловный `else`:
```js
if (stash && formBody.innerHTML) { stashed = { ... }; } else { stashed = null; }
```

**ПРАВИЛЬНО** — только при пустом `formBody`:
```js
if (stash && formBody.innerHTML) { stashed = { ... }; } else if (!formBody.innerHTML) { stashed = null; }
```
