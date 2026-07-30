# Система тестирования

PHPUnit 11.5.56 (PHAR) без Composer. Два набора тестов: **unit** и **integration**.

---

## Быстрый старт

Все тесты:
```
php phpunit.phar
```

Только unit (не требуют сервера):
```
php phpunit.phar --testsuite unit
```

Только integration (требуют PHP-сервер):
```
php phpunit.phar --testsuite integration
```

Один файл:
```
php phpunit.phar tests/Unit/FmtNumTest.php
php phpunit.phar tests/Integration/ClientFullTest.php
```

Один метод:
```
php phpunit.phar --filter testLogin tests/Integration/
```

Integration-тесты через скрипт (авто-запуск сервера):
```
.\run_integration_tests.ps1
```

---

## Структура

```
tests/
├── bootstrap.php              # Загрузка для PHPUnit
├── phpunit.xml                # Конфиг PHPUnit
├── run_integration_tests.ps1  # Запуск integration-тестов с сервером
├── Db/
│   ├── TestDb.php             # Создание/удаление тестовой БД comp_test
│   └── test_router.php        # Front-controller для встроенного PHP-сервера
├── Unit/
│   ├── FmtNumTest.php         # fmt_num()
│   ├── HtmlEncodeTest.php     # h()
│   ├── HelpersTest.php        # hilight()
│   ├── ControlsTest.php       # render_input, render_textarea и др.
│   └── TemplateEngineTest.php # replace_vars, render_template
└── Integration/
    ├── TablePageTest.php      # Базовый рендеринг таблиц
    ├── CityFullTest.php       # Города (поиск, сортировка, экспорт, форма)
    ├── ClientFullTest.php     # Контрагенты (поиск, сортировка, inline edit, форма)
    ├── TmcFullTest.php        # ТМЦ (поиск, сортировка, inline edit, форма)
    └── InvoiceTotalsTest.php  # Расчёт сумм в счетах
```

---

## Щит

### bootstrap.php
- Определяет `PHPUNIT_TEST=1` (подавляет сессионные куки, логин-экран)
- Стартует сессию без кук
- Подключает `config.php` (он загружает `config.local.php` с тестовой БД)
- Подключает `controls.php`, `template-engine.php`, `table-template.php`
- Подключает `TestDb`

### phpunit.xml
- `executionOrder="depends,defects"` — Integration-тесты идут цепочкой `@depends`
- `cacheDirectory=".phpunit.cache"`
- `colors="true"`

---

## Тестовая БД

### Создание
`TestDb::setup()`:
1. `DROP DATABASE IF EXISTS comp_test`
2. `CREATE DATABASE comp_test`
3. Копирует структуру всех таблиц из `comp` → `comp_test`
4. Засеивает стартовые данные: страны, города, единицы, категории, группы, клиенты, товары, счёт
5. Записывает `config.local.php` с настройками на `comp_test`

### Удаление
`TestDb::teardown()`:
- `DROP DATABASE IF EXISTS comp_test`
- Удаляет `config.local.php`

### Где используется
- `setUpBeforeClass()` — создание
- `tearDownAfterClass()` — удаление
- Между тестами БД **не пересоздаётся**, изменения откатываются вручную

---

## Особенности Integration-тестов

### Требования
- PHP built-in server на `127.0.0.1:8899`
- curl
- MySQL root/1439@localhost

### Зависимость тестов
Цепочка `@depends`:
```
testLogin → testToggleSelectOn → testSearch... → testSort... → testExport → ...
```
Ошибка в середине цепочки роняет все последующие.

### Сброс состояния
`resetMarks()` после каждого теста — очищает отметки/выделения через POST-запросы:
1. `toggleShowOnly` (off → on → off)
2. `clearSelection`

### Форма
Тест отправки формы: создаёт сущность через AJAX, проверяет редирект и `focus`, затем откатывает через удаление.

---

## Примеры

### Unit-тест
```php
class FmtNumTest extends TestCase
{
    public function testFormatInteger(): void
    {
        $this->assertSame('5', fmt_num(5, false));
    }
}
```

### Integration-тест
```php
class CityFullTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestDb::setup();
    }

    protected function requireServer(): void
    {
        if (!@fsockopen('127.0.0.1', 8899, $e, $e, 2))
            $this->markTestSkipped('Server not running');
    }

    public function testLogin(): int
    {
        $this->requireServer();
        $r = $this->json('POST', '/login_handler.php',
            ['login' => 'admin', 'password' => 'admin']);
        $this->assertTrue($r['ok']);
        return 0;
    }

    /** @depends testLogin */
    public function testPageLoads(): void
    {
        $r = $this->req('GET', '/city.php');
        $this->assertStringContainsString('Города', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchByName(): void
    {
        $r = $this->req('GET', '/city.php?q=Москва&sf=1');
        $this->assertStringContainsString('Москва', $r['body']);
    }

    // ...
}
```
