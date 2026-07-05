## Общая визуальная тема

* Фон страницы тёмно-сине-серый, почти графитовый.
* Интерфейс плоский, без сильных градиентов, с минимальными тенями.
* Акцентные элементы светлые: белый текст, светлые инпуты, серые иконки, оранжевая кнопка действия.
* Таблица занимает почти всю ширину контейнера и выглядит как плотная рабочая форма для просмотра записей.



### Базовые параметры стиля

* Ширина рабочей области: `100%`.
* Основной фон: `#2f3e4e` или близкий тёмно-синий оттенок.
* Цвет текста: `#ffffff` / `#e8eef4`.
* Вторичный текст: `#c7d0d9`.
* Акцентная кнопка: оранжевая `#e67e22`.
* Границы: тонкие, в цветах `rgba(255,255,255,0.08)` или `#445462`.



## Заголовок страницы

Заголовок расположен сверху слева, крупный, без рамки, с большим воздухом вокруг. Он визуально отделён от панели инструментов и служит названием таблицы базы данных, например «Кассовая книга».

### CSS для заголовка

```css
.page-title {
  font-size: 30px;
  line-height: 1.1;
  font-weight: 400;
  color: #f3f6f8;
  margin: 0 0 12px 0;
  padding: 4px 0 0 0;
}
```



## Верхняя панель действий

Под заголовком идёт горизонтальная панель с кнопками слева и поиском справа. Слева расположены компактные квадратные кнопки-иконки, справа — поле быстрого поиска с маленькими иконками фильтрации/поиска рядом. Общий стиль панели — узкий toolbar в один ряд, элементы выровнены по центру по вертикали.

### CSS для панели

```css
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0 10px 0;
}
.toolbar-group {
  display: flex;
  align-items: center;
  gap: 4px;
}
```



### Кнопки-иконки

* Размер примерно `30x28 px`.
* Фон тёмно-серый.
* Иконка белая или светло-серая.
* При наведении цвет темнее/светлее на 5–10%.
* Скругление минимальное, почти прямоугольное.

```css
.icon-btn {
  width: 30px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #4a5561;
  color: #fff;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 2px;
  cursor: pointer;
}
.icon-btn:hover {
  background: #5a6672;
}
```



### Поиск

Поле быстрого поиска светлое, контрастирует с тёмным фоном, внутри есть placeholder. Справа могут быть кнопки-иконки: раскрытие, лупа, фильтр, сортировка, настройки.

```css
.quick-search {
  height: 30px;
  width: 180px;
  padding: 0 10px;
  border: 1px solid #cfd6dd;
  background: #ffffff;
  color: #2b2b2b;
  border-radius: 2px;
  font-size: 14px;
}
.quick-search::placeholder {
  color: #8d99a6;
}
```



## Фильтр по периоду

Слева под toolbar виден блок фильтра с подписью «Период», двумя полями даты и кнопкой «Применить». Этот блок выглядит как встроенная панель, а не отдельная карточка: тёмный фон, светлые поля, оранжевая кнопка подтверждения.



### CSS для панели фильтра

```css
.filter-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: #405162;
  border: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 10px;
}
.filter-label {
  font-weight: 600;
  color: #e7edf3;
}
.date-input {
  height: 30px;
  width: 110px;
  padding: 0 10px;
  background: #fff;
  color: #333;
  border: 1px solid #cfd6dd;
  border-radius: 2px;
}
.apply-btn {
  height: 30px;
  padding: 0 14px;
  background: #e67e22;
  color: #fff;
  border: none;
  border-radius: 2px;
  font-weight: 600;
  cursor: pointer;
}
```



## Таблица данных

Таблица — центральный элемент интерфейса. У неё тёмная шапка с белыми/светлыми названиями колонок и более светлая полосатая заливка строк. Строки компактные, с небольшими отступами, чтобы поместить много данных в ограниченное вертикальное пространство.

### Внешний вид таблицы

* `width: 100%`.
* `border-collapse: collapse`.
* Шапка таблицы чуть темнее тела.
* Чёткое разделение колонок тонкими границами или визуальным отступом.
* Строки чередуются по тону, чтобы облегчить чтение.

```css
.data-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  color: #e9eef3;
  font-size: 14px;
}
.data-table thead th {
  background: #334555;
  color: #ffffff;
  font-weight: 600;
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.data-table tbody td {
  padding: 7px 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  background: #445464;
}
.data-table tbody tr:nth-child(even) td {
  background: #3e4f60;
}
.data-table tbody tr:hover td {
  background: #506575;
}
```



## Служебные колонки

Слева в таблице есть несколько служебных колонок: чекбокс выбора, действия: открыть, удалить, копировать. Они должны быть узкими, с фиксированной шириной и центрированием контента. Это позволяет не тратить место на поля записи. У второй третьей и четвертой колонок (кнопки действия) общий заголовок колонки "Действия".

### Рекомендуемые ширины

* Чекбокс: `34–38px`.
* Действия: `70–90px`.
* Открыть: `32–40px`.
* Удалить: `32–40px`.
* Копировать: `32–40px`.

```css
.col-checkbox { width: 34px; text-align: center; }
.col-actions { width: 86px; }
.col-icon { width: 36px; text-align: center; }
```



### Иконки действий

Иконки в ячейках лучше делать маленькими, контрастными и без лишнего фона, либо в виде мини-кнопок. Для удаления можно использовать красноватый акцент, для копирования — нейтральный, для открытия — светлый.

```css
.row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  margin-right: 4px;
  color: #ffffff;
  opacity: 0.9;
}
.row-action.delete { color: #ff8c8c; }
.row-action.copy { color: #d7e3ef; }
```



## Чекбоксы и выбор

Чекбоксы выглядят стандартно и функционально, без декоративной кастомизации. Чекбокс в заголовке колонки должен визуально совпадать по стилю с чекбоксами строк, но может быть слегка выделен как элемент массового выбора.

```css
input\[type="checkbox"] {
  width: 14px;
  height: 14px;
  accent-color: #4e83c2;
  cursor: pointer;
}
```



## Пагинация

Пагинация находится под таблицей и служит для перелистывания страниц. Логично оформить её как компактный ряд кнопок с номером текущей страницы по центру.
Справа кнопка "<<" для перехода к первой странице, левее кнопка с номером предыдущей страницы, затем кнопка с номером текущей страницы (выделена цветом как активная), дальше кнопка с номером следующей страницы и кнопка ">>" для перехода к последней странице.

### Стиль пагинации

```css
.pagination {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 0;
  color: #e6edf4;
}
.page-btn {
  min-width: 30px;
  height: 28px;
  padding: 0 8px;
  background: #4a5561;
  color: #fff;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 2px;
}
.page-info {
  margin-left: 10px;
  color: #cfd8e1;
}
```



## Рекомендуемая структура CSS

Для такого интерфейса удобно держать стили слоями:

* `page` — фон, заголовок, отступы.
* `toolbar` — кнопки, поиск, иконки.
* `filters` — дата, фильтр, сортировка.
* `table` — шапка, строки, hover, чекбоксы.
* `pagination` — навигация по страницам.

Ниже — компактный каркас, который можно взять за основу:

```css
body {
  margin: 0;
  background: #2f3e4e;
  font-family: Arial, sans-serif;
  color: #e9eef3;
}

.container {
  padding: 10px 12px;
}

.page-title {
  font-size: 30px;
  font-weight: 400;
  margin: 0 0 10px;
  color: #f4f7fa;
}

.toolbar,
.filter-bar,
.pagination {
  display: flex;
  align-items: center;
  gap: 6px;
}

.toolbar {
  justify-content: space-between;
  margin-bottom: 8px;
}

.icon-btn,
.page-btn {
  height: 28px;
  min-width: 28px;
  background: #4a5561;
  border: 1px solid rgba(255,255,255,0.08);
  color: #fff;
  border-radius: 2px;
}

.quick-search,
.date-input {
  height: 30px;
  background: #fff;
  border: 1px solid #cfd6dd;
  border-radius: 2px;
  color: #222;
}

.apply-btn {
  height: 30px;
  background: #e67e22;
  border: none;
  color: #fff;
  border-radius: 2px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.data-table thead th {
  background: #334555;
  color: #fff;
  padding: 8px 10px;
  text-align: left;
}

.data-table tbody td {
  background: #445464;
  padding: 7px 10px;
}

.data-table tbody tr:nth-child(even) td {
  background: #3e4f60;
}

.data-table tbody tr:hover td {
  background: #506575;
}
```



## Особенности для вашего приложения

Для таблиц базы данных важно, чтобы CSS поддерживал:

* фиксированные служебные колонки слева;
* перенос длинных значений в ячейках;
* минимальную высоту строк без потери читаемости;
* одинаковый визуальный вес у всех иконок действий;
* компактную, но заметную кнопку массовых действий «Выбрано N»;
* адаптацию под разные размеры таблиц и поля, заданные в конфигурации.



