<?php
class TestDb
{
    private static string $testDbName = 'comp_test';
    private static ?mysqli $adminConn = null;

    public static function getDbName(): string
    {
        return self::$testDbName;
    }

    public static function setup(): void
    {
        $admin = self::adminConn();

        $admin->query("DROP DATABASE IF EXISTS `" . self::$testDbName . "`");
        $admin->query("CREATE DATABASE `" . self::$testDbName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $admin->select_db(self::$testDbName);

        $srcDb = 'comp';
        $tables = $admin->query("SHOW TABLES FROM `$srcDb`");
        while ($row = $tables->fetch_row()) {
            $table = $row[0];
            $admin->query("CREATE TABLE `" . self::$testDbName . "`.`$table` LIKE `$srcDb`.`$table`");
        }

        self::seed($admin);
        $admin->close();
        self::$adminConn = null;

        self::writeConfig();
    }

    public static function teardown(): void
    {
        $admin = self::adminConn();
        $admin->query("DROP DATABASE IF EXISTS `" . self::$testDbName . "`");
        $admin->close();
        self::$adminConn = null;
        self::restoreConfig();
    }

    public static function baseUrl(): string
    {
        return 'http://localhost:8899';
    }

    private static function adminConn(): mysqli
    {
        if (self::$adminConn === null) {
            self::$adminConn = new mysqli('localhost', 'root', '1439');
            if (self::$adminConn->connect_error) {
                throw new RuntimeException('DB connection failed: ' . self::$adminConn->connect_error);
            }
            self::$adminConn->query("SET NAMES utf8mb4");
        }
        return self::$adminConn;
    }

    private static function writeConfig(): void
    {
        $config = [
            'DB_HOST' => 'localhost',
            'DB_USER' => 'root',
            'DB_PASS' => '1439',
            'DB_NAME' => self::$testDbName,
        ];
        $content = '<?php return ' . var_export($config, true) . ';';
        file_put_contents(__DIR__ . '/../../config.local.php', $content);
    }

    private static function restoreConfig(): void
    {
        $path = __DIR__ . '/../../config.local.php';
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private static function seed(mysqli $db): void
    {
        $db->query("INSERT INTO country (country_id, country, note) VALUES (1, 'Россия', ''), (2, 'США', '')");
        $db->query("INSERT INTO city (city_id, city, note, country_id) VALUES (1, 'Москва', '', 1), (2, 'Санкт-Петербург', '', 1), (3, 'New York', '', 2)");
        $db->query("INSERT INTO app_settings (`key`, `value`) VALUES ('page_size', '20'), ('page_width', '1100')");
        $db->query("INSERT INTO unit (unit_id, unit, note) VALUES (1, 'шт.', ''), (2, 'кг', '')");
        $db->query("INSERT INTO sotr (sotr_id, login, passw, doc_name) VALUES (1, 'admin', 'admin', 'Администратор')");
        $db->query("INSERT INTO cli_categ (cli_categ_id, categ, note) VALUES (1, 'Постоянный', ''), (2, 'Разовый', '')");
        $db->query("INSERT INTO store (store_id, name, note) VALUES (1, 'Основной склад', '')");
        $db->query("INSERT INTO categ (categ_id, categ, note) VALUES (1, 'Электроника', ''), (2, 'Мебель', '')");
        $db->query("INSERT INTO `group` (group_id, name) VALUES (1, 'Компьютеры'), (2, 'Периферия')");
        $db->query("INSERT INTO sgroup (sgroup_id, name, group_id) VALUES (1, 'Ноутбуки', 1), (2, 'Мониторы', 2)");
        $db->query("INSERT INTO product (product_id, product_name, article, categ_id, group_id, country_id, price_in, price_out, residue, note) VALUES (1, 'Ноутбук', 'NB-001', 1, 1, 1, 1200.00, 1500.00, 10.000, ''), (2, 'Монитор', 'MON-001', 1, 2, 1, 400.00, 500.00, 20.000, ''), (3, 'Мышь', 'MS-001', 1, 1, 2, 30.00, 100.00, 50.000, 'Оптическая'), (4, 'Клавиатура', 'KB-001', 1, 2, 1, 80.00, 200.00, 30.000, 'Механическая')");
        $db->query("INSERT INTO client (client_id, name, last_name, first_name, second_name, title, passw, phone, cphone, email, site, city, country, address_jur, address, pasport, pasp_vydan, promo_id, inn, kpp, ogrn, jur_name, director, glavbuh, bank, bik, schet, kschet, okonh, okpo, card_num, dop1, dop2, logo, tags, note) VALUES (1, 'Тестовый клиент', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '')");
        $db->query("INSERT INTO client (client_id, name, last_name, first_name, cli_categ_id, phone, email, city_id, country_id, note) VALUES (2, 'ООО Ромашка', '', '', 1, '+7 495 123-45-67', 'info@romashka.ru', 1, 1, 'Надёжный поставщик')");
        $db->query("INSERT INTO client (client_id, name, last_name, first_name, cli_categ_id, phone, email, city_id, country_id, note) VALUES (3, 'Иванов Иван', 'Иванов', 'Иван', 2, '+7 495 765-43-21', 'ivan@example.com', 2, 1, 'Физ. лицо')");
        $db->query("INSERT INTO tag (tag_id, tag, client_flag) VALUES (1, 'VIP', 1), (2, 'Loyal', 1)");
        $db->query("INSERT INTO client_tag (client_id, tag_id) VALUES (2, 1), (3, 2)");
        $db->query("INSERT INTO promo (promo_id, promo) VALUES (1, 'Сайт')");
        $db->query("INSERT INTO invoice (invoice_id, number, date, time, client_id, state, store_id, discount, sum, sum_discount, sum_nds, sum_plat, sotr_id, note, doctype_id) VALUES (1, 1, CURDATE(), CURTIME(), NULL, 1, NULL, 0, 0, 0, 0, 0, 1, '', 10)");
    }
}
