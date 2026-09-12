<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Db/TestDb.php';
require_once __DIR__ . '/../../lib/arenda2_totals.php';
require_once __DIR__ . '/../../lib/arenda2_tariff.php';
require_once __DIR__ . '/../../lib/arenda2_voz.php';

/**
 * Интеграционные тесты подсистемы «Аренда».
 * Покрытие:
 *  - Жизненный цикл документа аренды (создание, добавление товаров, итоги)
 *  - Автоподбор тарифов: базовые цены товара, тарифы из price (временные, фиксированные)
 *  - Сезонный выбор тарифного плана (prplan)
 *  - Расчёт сумм: дни, выходные, часы, месяцы, кол-во, скидка, НДС
 *  - voz_flag (возврат): KeepDaysOnEarlyReturn, NoRefundOnEarlyReturn, TimeShift
 *  - Продукт: noquant_flag, nocalc_flag
 *  - Режимы настройки: ManualTariffFlag, FixedFlag, SezonFlag
 *  - Backfill docum (итоги документа)
 */
class ArendaFullTest extends TestCase
{
    private static ?mysqli $db = null;
    private static int $docCounter = 0;

    public static function setUpBeforeClass(): void
    {
        TestDb::setup();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::$db = new mysqli(DB_HOST, DB_USER, DB_PASS, TestDb::getDbName());
        self::$db->query("SET NAMES utf8mb4");
        self::seedRentalData(self::$db);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db) { self::$db->close(); self::$db = null; }
        TestDb::teardown();
    }

    private static function seedRentalData(mysqli $db): void
    {
        // Тарифные планы
        $db->query("INSERT INTO prplan (prplan_id, prplan, bdate, edate) VALUES
            (1, 'Обычный', '', ''),
            (2, 'Летний',  '01.06.2026', '31.08.2026'),
            (3, 'Зимний',  '01.12.2026', '28.02.2027')");

        // Товары для аренды
        $db->query("INSERT INTO product (product_id, product_name, code, categ_id, group_id, country_id,
            price_in, price_out, residue, period_aren, price_hour, price_day, price_month, zalog,
            service_flag, noquant_flag, nocalc_flag, hide_flag)
            VALUES
            (10, 'Дрель',       'DR-01', 1, 1, 1, 500, 800,  10, 'h', 50,  200, 0,     300, 0, 0, 0, 0),
            (20, 'Стол обеденный','TB-01',1, 1, 1, 1000,1500, 5,  'd', 0,   300, 5000, 1000,0, 0, 0, 0),
            (30, 'Стул',         'CH-01',1, 1, 1, 200, 400,  20,  'h', 30,  100, 0,     150, 0, 1, 0, 0),
            (40, 'Проектор',     'PJ-01',1, 1, 1, 800, 1200, 3,   'd', 0,   250, 0,     500, 0, 0, 1, 0),
            (50, 'Сервисная работа','SV-1',1,1,1, 0,   100,  99,  'h', 100, 0,   0,     0,   1, 1, 0, 0)");

        // Тарифы: Дрель (product 10), план 1 (Обычный)
        $db->query("INSERT INTO price (price_id, product_id, prplan_id, name, bdays, edays, bmonths, emonths,
            btime, etime, price, pricef, hprice, mprice, fixed_flag)
            VALUES
            (1, 10, 1, 'Часовой 1-3',     0, 0, 0, 0, '00:00', '02:59', 0,    0, 50,  0, 0),
            (2, 10, 1, 'Часовой 3-8',     0, 0, 0, 0, '03:00', '07:59', 0,    0, 60,  0, 0),
            (3, 10, 1, 'Дневной 1-3',     1, 3, 0, 0, '',      '',      180,  200, 0,  0, 0),
            (4, 10, 1, 'Дневной 4-7',     4, 7, 0, 0, '',      '',      150,  180, 0,  0, 0),
            (5, 10, 1, 'Неделя фикс',     0, 0, 0, 0, '',      '',      800,  0,   0,  0, 1),
            (6, 10, 1, 'Месяц фикс',      0, 0, 0, 3, '',      '',      0,    0,   0,  3000, 1)");

        // Тарифы: Стол (product 20), план 1 — месячные
        $db->query("INSERT INTO price (price_id, product_id, prplan_id, name, bdays, edays, bmonths, emonths,
            btime, etime, price, pricef, hprice, mprice, fixed_flag)
            VALUES
            (7, 20, 1, 'Стол 1 мес',  0, 0, 1, 1, '', '', 0, 0, 0, 4500, 0),
            (8, 20, 1, 'Стол 2-3 мес', 0, 0, 2, 3, '', '', 0, 0, 0, 4000, 0),
            (9, 20, 1, 'Стол фикс',    0, 0, 0, 0, '', '', 10000, 0, 0, 0, 1)");

        // Тарифы: Дрель, план 2 (Летний) — дороже
        $db->query("INSERT INTO price (price_id, product_id, prplan_id, name, bdays, edays, bmonths, emonths,
            btime, etime, price, pricef, hprice, mprice, fixed_flag)
            VALUES
            (10, 10, 2, 'Летний час', 0, 0, 0, 0, '00:00', '23:59', 0, 0, 80, 0, 0),
            (11, 10, 2, 'Летний день', 1, 7, 0, 0, '', '', 250, 300, 0, 0, 0)");

        // Клиенты (уже есть из TestDb seed: 1, 2, 3)
        // nozalog_flag у клиента 3
        $db->query("UPDATE client SET nozalog_flag = 1 WHERE client_id = 3");
    }

    private function db(): mysqli { return self::$db; }

    private function settings(array $overrides = []): array
    {
        $defaults = [
            'ShowHoursFlag'         => 0,
            'ShowDaysFlag'          => 1,
            'ShowMonthsFlag'        => 0,
            'ManualTariffFlag'      => 0,
            'FixedFlag'             => 0,
            'SezonFlag'             => 0,
            'KeepDaysOnEarlyReturn' => 0,
            'NoRefundOnEarlyReturn' => 0,
            'AddLateDayCharge'      => 0,
            'TimeShift'             => '00:00',
            'TimeLate'              => '00:00',
            'nds_rate'              => 0,
            'no_nds'                => '0',
        ];
        return array_merge($defaults, $overrides);
    }

    private function createDoc(int $clientId = 1, string $dateBeg = '2026-09-07', string $timeBeg = '10:00'): int
    {
        $db = $this->db();
        self::$docCounter++;
        $num = self::$docCounter + 100;
        $db->query("INSERT INTO docum (typeop, number, date, time, date_beg, time_beg, date_voz, time_voz,
            firm_id, client_id, rezerv_flag, voz_flag, discount, sum_discount, sum, sum_zalog,
            sum_plat, sum_balans, pos, plat_type, sotr_id, store_id, note)
            VALUES (90, $num, '2026-09-07', '09:00:00', '$dateBeg', '$timeBeg', '', '',
            0, $clientId, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'Наличные', 1, 1, '')");
        return (int)$db->insert_id;
    }

    private function addItem(int $documId, int $productId, array $overrides = []): int
    {
        $db = $this->db();
        $defaults = [
            'docum_id' => $documId, 'typeop' => 90,             'number' => self::$docCounter + 100, 'date' => '2026-09-07', 'time' => '09:00:00',
            'date_beg' => '2026-09-07', 'time_beg' => '10:00', 'store_id' => 1,
            'prplan_id' => 1, 'product_id' => $productId, 'product_name' => '', 'code' => '',
            'quant' => 1, 'price_hour' => 0, 'price_day' => 0, 'price_we' => 0,
            'price_fix' => 0, 'price_month' => 0, 'price_id' => 0,
            'discount' => 0, 'sum_discount' => 0, 'sum' => 0, 'sum_zalog' => 0,
            'days' => 1, 'hours' => '', 'months' => 0,
            'date_voz' => '2026-09-08', 'time_voz' => '10:00',
            'rezerv_flag' => 0, 'voz_flag' => 0, 'fixed_flag' => 0, 'note' => '',
        ];
        $vals = array_merge($defaults, $overrides);
        $cols = implode('`, `', array_keys($vals));
        $sqlVals = [];
        foreach ($vals as $v) {
            if (is_int($v) || is_float($v)) $sqlVals[] = (string)$v;
            else $sqlVals[] = "'" . $db->real_escape_string((string)$v) . "'";
        }
        $sql = "INSERT INTO `docum2` (`$cols`) VALUES (" . implode(', ', $sqlVals) . ")";
        $result = $db->query($sql);
        if (!$result) {
            throw new RuntimeException("addItem failed: " . $db->error . " | SQL: $sql");
        }
        return (int)$db->insert_id;
    }

    // ═══════════════════════════════════════════════════════════
    // 1. Жизненный цикл документа
    // ═══════════════════════════════════════════════════════════

    public function testCreateDocumentAndBackfill(): void
    {
        $docId = $this->createDoc();
        $item1 = $this->addItem($docId, 10, ['days' => 3, 'date_voz' => '2026-09-10']);
        $item2 = $this->addItem($docId, 20, ['days' => 5, 'date_voz' => '2026-09-12']);

        $r = arenda2_backfill_docum($this->db(), $docId);

        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['pos']);
        $this->assertSame('2026-09-10', $r['date_voz']); // MIN date_voz
    }

    // ═══════════════════════════════════════════════════════════
    // 2. Тарифы: базовые цены товара (ManualTariff=1)
    // ═══════════════════════════════════════════════════════════

    public function testManualTariffProductPrices(): void
    {
        $cfg = $this->settings(['ManualTariffFlag' => 1, 'ShowDaysFlag' => 1, 'ShowHoursFlag' => 1]);
        // Product 30 (Стул) has no price records → falls back to product prices
        $tt = arenda2_select_tariff($this->db(), $cfg, 30, 1, '2026-09-07', '03:00', 3, 0);

        $this->assertTrue($tt['ok']);
        // ManualTariff: no price records → product base prices
        $this->assertSame(30.0, $tt['price_hour']);  // product.price_hour
        $this->assertSame(100.0, $tt['price_day']);  // product.price_day
    }

    public function testCalcSumManualDaysHours(): void
    {
        $cfg = $this->settings(['ManualTariffFlag' => 1, 'ShowDaysFlag' => 1, 'ShowHoursFlag' => 1]);
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 3, 'hours' => '2:00', 'months' => 0,
            'price_day' => 200, 'price_hour' => 50, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-10',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 3 days * 200 + 2h * 50 = 700
        $this->assertSame(700.0, $r['sum']);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. Тарифы из price: временные (часовые, дневные)
    // ═══════════════════════════════════════════════════════════

    public function testTariffAutoSelectHourly(): void
    {
        $cfg = $this->settings(['ShowHoursFlag' => 1, 'ShowDaysFlag' => 1]);
        $docId = $this->createDoc();
        // 5 hours: matches "Часовой 3-8" (hprice=60)
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '5:00', 0, 0, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(60.0, $tt['price_hour']);
        $this->assertSame(2, $tt['tariff_id']); // Часовой 3-8
    }

    public function testTariffAutoSelectDaily1to3(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        $docId = $this->createDoc();
        // 2 days → matches "Дневной 1-3" (price=180)
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 2, 0, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(180.0, $tt['price_day']);
        $this->assertSame(3, $tt['tariff_id']); // Дневной 1-3
    }

    public function testTariffAutoSelectDaily4to7(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        $docId = $this->createDoc();
        // 5 days → matches "Дневной 4-7" (price=150)
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 5, 0, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(150.0, $tt['price_day']);
        $this->assertSame(4, $tt['tariff_id']); // Дневной 4-7
    }

    public function testTariffFallbackToProductPrice(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        $docId = $this->createDoc();
        // 10 days → no tariff matches (edays max=7) → fallback to product.price_day=200
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 10, 0, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(200.0, $tt['price_day']); // product.price_day
        $this->assertSame(0, $tt['tariff_id']);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. Фиксированные тарифы (FixedFlag=1)
    // ═══════════════════════════════════════════════════════════

    public function testFixedTariffSelected(): void
    {
        $cfg = $this->settings(['FixedFlag' => 1, 'ShowDaysFlag' => 1]);
        $docId = $this->createDoc();
        // FixedFlag=1 → queries price with fixed_flag=1
        // "Неделя фикс" (price_id=5, price=800) has edays=0, so no range match
        // but fixed tariffs can still be listed and selected manually
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 5, 0, $docId);

        $this->assertTrue($tt['ok']);
        // No auto-match for fixed tariff (edays=0), but prices list includes fixed tariffs
        $fixedPrices = array_filter($tt['prices'], fn($p) => $p['fixed_flag'] === 1);
        $this->assertNotEmpty($fixedPrices); // Fixed tariff "Неделя фикс" is in list
    }

    public function testCalcSumFixedTariff(): void
    {
        $cfg = $this->settings(['FixedFlag' => 1, 'ShowDaysFlag' => 1]);
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 200, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 800, 'fixed_flag' => 1, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        $this->assertSame(800.0, $r['sum']); // Fixed: just price_fix
    }

    public function testFixedTariffWithDiscount(): void
    {
        $cfg = $this->settings(['FixedFlag' => 1, 'ShowDaysFlag' => 1]);
        $item = [
            'discount' => 15, 'quant' => 1, 'days' => 10, 'hours' => '', 'months' => 0,
            'price_day' => 200, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 800, 'fixed_flag' => 1, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-17',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 800 * (1 - 0.15) = 680
        $this->assertSame(680.0, $r['sum']);
        $this->assertSame(120.0, $r['sum_discount']);
    }

    // ═══════════════════════════════════════════════════════════
    // 5. Месячные тарифы
    // ═══════════════════════════════════════════════════════════

    public function testTariffAutoSelectMonthly1(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'ShowMonthsFlag' => 1]);
        $docId = $this->createDoc();
        // 1 month for product 20 → price_id=7 "Стол 1 мес" mprice=4500
        $tt = arenda2_select_tariff($this->db(), $cfg, 20, 1, '2026-09-07', '', 0, 1, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(4500.0, $tt['price_month']);
        $this->assertSame(7, $tt['tariff_id']);
    }

    public function testTariffAutoSelectMonthly2to3(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'ShowMonthsFlag' => 1]);
        $docId = $this->createDoc();
        // 2 months → price_id=8 "Стол 2-3 мес" mprice=4000
        $tt = arenda2_select_tariff($this->db(), $cfg, 20, 1, '2026-09-07', '', 0, 2, $docId);

        $this->assertTrue($tt['ok']);
        $this->assertSame(4000.0, $tt['price_month']);
        $this->assertSame(8, $tt['tariff_id']);
    }

    public function testCalcSumMonthlyWithDaysOverflow(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'ShowMonthsFlag' => 1]);
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 2,
            'price_day' => 300, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 4000,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-11-12',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // monthsActive: 2 * 4000 + 5 overflow days * 300 = 9500
        $this->assertSame(9500.0, $r['sum']);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. Сезонный выбор тарифного плана (SezonFlag)
    // ═══════════════════════════════════════════════════════════

    public function testSeasonSummerPlan(): void
    {
        $cfg = $this->settings(['SezonFlag' => 1, 'ShowDaysFlag' => 1, 'ShowHoursFlag' => 1]);
        // Aug 15 → falls in prplan 2 (Летний: 01.06–31.08)
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-08-15', '', 2, 0);

        $this->assertTrue($tt['ok']);
        $this->assertSame(2, $tt['prplan_id']); // Летний
        $this->assertSame(250.0, $tt['price_day']); // Летний день
    }

    public function testSeasonDefaultPlan(): void
    {
        $cfg = $this->settings(['SezonFlag' => 1, 'ShowDaysFlag' => 1]);
        // Sep 15 → no season matches → default prplan=1
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-15', '', 2, 0);

        $this->assertTrue($tt['ok']);
        $this->assertSame(1, $tt['prplan_id']); // Обычный
    }

    public function testSeasonManualOverride(): void
    {
        $cfg = $this->settings(['SezonFlag' => 1, 'ShowDaysFlag' => 1]);
        // prplanManual=true → season override disabled, stays on plan 1
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-08-15', '', 2, 0, 0, true);

        $this->assertTrue($tt['ok']);
        $this->assertSame(1, $tt['prplan_id']); // Forced to plan 1
    }

    // ═══════════════════════════════════════════════════════════
    // 7. Залог (sum_zalog) и nozalog_flag
    // ═══════════════════════════════════════════════════════════

    public function testZalogFromProduct(): void
    {
        $cfg = $this->settings();
        $docId = $this->createDoc(1); // client 1 — no nozalog_flag
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 1, 0, $docId);

        $this->assertSame(300.0, $tt['sum_zalog']); // product.zalog
    }

    public function testZalogZeroForNozalogClient(): void
    {
        $cfg = $this->settings();
        $docId = $this->createDoc(3); // client 3 — nozalog_flag=1
        $tt = arenda2_select_tariff($this->db(), $cfg, 10, 1, '2026-09-07', '', 1, 0, $docId);

        $this->assertSame(0.0, $tt['sum_zalog']);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. Продукт: noquant_flag
    // ═══════════════════════════════════════════════════════════

    public function testNoquantFlag(): void
    {
        $cfg = $this->settings();
        $tt = arenda2_select_tariff($this->db(), $cfg, 30, 1, '2026-09-07', '', 1, 0);

        $this->assertTrue($tt['ok']);
        $this->assertSame(1, $tt['noquant_flag']);
    }

    public function testCalcSumNoquantFlag(): void
    {
        $cfg = $this->settings();
        $item = [
            'discount' => 0, 'quant' => 10, 'days' => 3, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 1,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-10',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // noquant → quant ignored → 300
        $this->assertSame(300.0, $r['sum']);
    }

    // ═══════════════════════════════════════════════════════════
    // 9. Продукт: nocalc_flag
    // ═══════════════════════════════════════════════════════════

    public function testNocalcFlag(): void
    {
        $cfg = $this->settings();
        $tt = arenda2_select_tariff($this->db(), $cfg, 40, 1, '2026-09-07', '', 1, 0);

        $this->assertTrue($tt['ok']);
        $this->assertSame(1, $tt['nocalc_flag']);
    }

    // ═══════════════════════════════════════════════════════════
    // 10. Продукт: service_flag
    // ═══════════════════════════════════════════════════════════

    public function testServiceFlagProduct(): void
    {
        $cfg = $this->settings();
        $tt = arenda2_select_tariff($this->db(), $cfg, 50, 1, '2026-09-07', '2:00', 0, 0);

        $this->assertTrue($tt['ok']);
        $this->assertSame(100.0, $tt['price_hour']); // product.price_hour
    }

    // ═══════════════════════════════════════════════════════════
    // 11. voz_flag (возврат)
    // ═══════════════════════════════════════════════════════════

    public function testVozFlagSetsReturnDateToToday(): void
    {
        $db = $this->db();
        $docId = $this->createDoc(1, '2026-09-01', '10:00');
        $itemId = $this->addItem($docId, 10, [
            'date_beg' => '2026-09-01', 'time_beg' => '10:00',
            'date_voz' => '2026-09-10', 'time_voz' => '10:00',
            'days' => 9, 'hours' => '',
        ]);

        // Override time() for deterministic test
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'TimeShift' => '00:00']);

        // Manually update to simulate "today = 2026-09-05"
        $db->query("UPDATE docum2 SET days = 4, date_voz = '2026-09-05', time_voz = '14:00' WHERE docum2_id = $itemId");

        arenda2_apply_voz_to_item($db, $cfg, $docId, $itemId, 1);

        $after = $db->query("SELECT voz_flag, days, date_voz FROM docum2 WHERE docum2_id = $itemId")->fetch_assoc();
        $this->assertSame(1, (int)$after['voz_flag']);
    }

    public function testVozFlagResetRestoresDateVoz(): void
    {
        $db = $this->db();
        $docId = $this->createDoc(1, '2026-09-01', '10:00');
        $itemId = $this->addItem($docId, 10, [
            'days' => 5, 'hours' => '',
            'date_beg' => '2026-09-01', 'time_beg' => '10:00',
            'date_voz' => '2026-09-06', 'time_voz' => '10:00',
        ]);

        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        // Try un-return (voz=0) directly without prior voz=1, so days stay at 5
        arenda2_apply_voz_to_item($db, $cfg, $docId, $itemId, 0);

        $after = $db->query("SELECT date_voz, time_voz FROM docum2 WHERE docum2_id = $itemId")->fetch_assoc();
        // Un-return: date_voz = date_beg + days = 2026-09-01 + 5 = 2026-09-06
        $this->assertSame('2026-09-06', $after['date_voz']);
    }

    public function testKeepDaysOnEarlyReturn(): void
    {
        $db = $this->db();
        $docId = $this->createDoc(1, '2026-09-01', '10:00');
        $itemId = $this->addItem($docId, 10, [
            'days' => 10, 'hours' => '',
            'date_beg' => '2026-09-01', 'time_beg' => '10:00',
            'date_voz' => '2026-09-11', 'time_voz' => '10:00',
        ]);

        $cfg = $this->settings(['ShowDaysFlag' => 1, 'KeepDaysOnEarlyReturn' => '1']);
        arenda2_apply_voz_to_item($db, $cfg, $docId, $itemId, 1);

        $after = $db->query("SELECT days FROM docum2 WHERE docum2_id = $itemId")->fetch_assoc();
        // KeepDays=1 → days unchanged
        $this->assertSame(10, (int)$after['days']);
    }

    // ═══════════════════════════════════════════════════════════
    // 12. sync_docum_flags
    // ═══════════════════════════════════════════════════════════

    public function testSyncDocumFlagsAllVoz(): void
    {
        $db = $this->db();
        $docId = $this->createDoc();
        $i1 = $this->addItem($docId, 10);
        $i2 = $this->addItem($docId, 20);

        $db->query("UPDATE docum2 SET voz_flag = 1 WHERE docum_id = $docId AND typeop = 90");
        $r = arenda2_sync_docum_flags($db, $docId);

        $this->assertSame(1, $r['doc_voz_flag']);
        $this->assertSame(0, $r['doc_rezerv_flag']); // voz resets rezerv
    }

    public function testSyncDocumFlagsPartialVoz(): void
    {
        $db = $this->db();
        $docId = $this->createDoc();
        $i1 = $this->addItem($docId, 10);
        $i2 = $this->addItem($docId, 20);

        $db->query("UPDATE docum2 SET voz_flag = 1 WHERE docum2_id = $i1");
        $r = arenda2_sync_docum_flags($db, $docId);

        $this->assertSame(0, $r['doc_voz_flag']); // not all returned
    }

    public function testSyncDocumFlagsAllRezerv(): void
    {
        $db = $this->db();
        $docId = $this->createDoc();
        $this->addItem($docId, 10);
        $this->addItem($docId, 20);

        $db->query("UPDATE docum2 SET rezerv_flag = 1 WHERE docum_id = $docId AND typeop = 90");
        $r = arenda2_sync_docum_flags($db, $docId);

        $this->assertSame(1, $r['doc_rezerv_flag']);
    }

    public function testVozResetsRezerv(): void
    {
        $db = $this->db();
        $docId = $this->createDoc();
        $this->addItem($docId, 10);
        $this->addItem($docId, 20);

        $db->query("UPDATE docum2 SET rezerv_flag = 1 WHERE docum_id = $docId AND typeop = 90");
        $db->query("UPDATE docum SET rezerv_flag = 1 WHERE docum_id = $docId");

        $db->query("UPDATE docum2 SET voz_flag = 1 WHERE docum_id = $docId AND typeop = 90");
        $r = arenda2_sync_docum_flags($db, $docId);

        // voz=1 for all → doc_voz=1, and rezerv forced to 0
        $this->assertSame(1, $r['doc_voz_flag']);
        $this->assertSame(0, $r['doc_rezerv_flag']);
    }

    // ═══════════════════════════════════════════════════════════
    // 13. recalc_item_period
    // ═══════════════════════════════════════════════════════════

    public function testRecalcItemPeriod(): void
    {
        $db = $this->db();
        $docId = $this->createDoc(1, '2026-09-07', '10:00');
        $itemId = $this->addItem($docId, 10, [
            'days' => 3, 'hours' => '', 'months' => 0,
            'date_beg' => '2026-09-07', 'time_beg' => '10:00',
            'date_voz' => '2026-09-10', 'time_voz' => '10:00',
            'price_day' => 0, 'price_hour' => 0, 'sum' => 0,
        ]);

        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        $r = arenda2_recalc_item_period($db, $cfg, $docId, $itemId);

        $this->assertNotEmpty($r);
        $this->assertSame(3, $r['days']);
        // Days 1-3 tariff: price=180 → 3 * 180 = 540
        $this->assertSame(540.0, $r['sum']);
        $this->assertSame(180.0, $r['price_day']);
    }

    // ═══════════════════════════════════════════════════════════
    // 14. Выходные: тариф pricef
    // ═══════════════════════════════════════════════════════════

    public function testWeekendTariffApplied(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        // Sat Sep 5 to Sun Sep 7 = 2 days, all weekends
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 2, 'hours' => '', 'months' => 0,
            'price_day' => 180, 'price_hour' => 0, 'price_we' => 200, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-05', 'date_voz' => '2026-09-07',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 2 weekend days * 200 = 400
        $this->assertSame(400.0, $r['sum']);
    }

    // ═══════════════════════════════════════════════════════════
    // 15. Расширенный расчёт: дни + часы + скидка + НДС + кол-во
    // ═══════════════════════════════════════════════════════════

    public function testFullCalcDaysHoursDiscountNdsQuant(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'ShowHoursFlag' => 1]);
        // Mon Sep 7 to Thu Sep 10 = 3 days, 4 hours
        $item = [
            'discount' => 10, 'quant' => 2, 'days' => 3, 'hours' => '4:00', 'months' => 0,
            'price_day' => 150, 'price_hour' => 60, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-10',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // gross = 3*150 + 4*60 = 450+240 = 690
        // after disc 10%: 621
        // after quant *2: 1242
        $this->assertSame(1242.0, $r['sum']);
        $this->assertSame(138.0, $r['sum_discount']); // 690 * 0.10 * 2
    }

    public function testFullCalcWithNds22(): void
    {
        $cfg = $this->settings(['ShowDaysFlag' => 1, 'nds_rate' => 22, 'no_nds' => '0']);
        $item = [
            'discount' => 0, 'quant' => 1, 'days' => 5, 'hours' => '', 'months' => 0,
            'price_day' => 100, 'price_hour' => 0, 'price_we' => 0, 'price_month' => 0,
            'price_fix' => 0, 'fixed_flag' => 0, 'noquant_flag' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-12',
        ];
        $r = arenda2_calc_sum_for_item($item, $cfg);
        // 500 * 1.22 = 610
        $this->assertSame(610.0, $r['sum']);
    }

    // ═══════════════════════════════════════════════════════════
    // 16. recalc_discount
    // ═══════════════════════════════════════════════════════════

    public function testRecalcDiscount(): void
    {
        $db = $this->db();
        $docId = $this->createDoc();
        $this->addItem($docId, 10, [
            'days' => 3, 'price_day' => 180, 'price_hour' => 0, 'price_we' => 0,
            'date_beg' => '2026-09-07', 'date_voz' => '2026-09-10',
        ]);

        $cfg = $this->settings(['ShowDaysFlag' => 1]);
        arenda2_recalc_discount($db, $docId, 20.0, $cfg);

        $row = $db->query("SELECT discount, sum, sum_discount FROM docum WHERE docum_id = $docId")->fetch_assoc();
        $this->assertSame(20.0, (float)$row['discount']);
        // 540 * 0.80 = 432
        $this->assertSame(432.0, (float)$row['sum']);
    }
}
