<?php
use PHPUnit\Framework\TestCase;

class CityFullTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static string $cookieFile;

    public static function setUpBeforeClass(): void
    {
        self::$cookieFile = __DIR__ . '/../_cookie.txt';
        @unlink(self::$cookieFile);
        $baseUrl = TestDb::baseUrl();
        $fp = @fsockopen(parse_url($baseUrl, PHP_URL_HOST), parse_url($baseUrl, PHP_URL_PORT), $e, $e, 2);
        if (!$fp) { return; }
        fclose($fp);
        TestDb::setup();
        self::$baseUrl = $baseUrl;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$baseUrl !== null) TestDb::teardown();
        @unlink(self::$cookieFile);
    }

    private function requireServer(): void
    {
        if (self::$baseUrl === null) {
            self::markTestSkipped('PHP built-in server is not running. Start it with: run_integration_tests.ps1');
        }
    }

    private function req(string $method, string $url, array $data = []): array
    {
        $this->requireServer();
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => self::$baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEFILE => self::$cookieFile,
            CURLOPT_COOKIEJAR => self::$cookieFile,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException('HTTP request failed: ' . $url);
        $headerSize = $info['header_size'];
        return [
            'headers' => substr($resp, 0, $headerSize),
            'body' => substr($resp, $headerSize),
            'httpCode' => $info['http_code'],
            'contentType' => $info['content_type'] ?? '',
        ];
    }

    private function json(string $method, string $url, array $data = []): array
    {
        $r = $this->req($method, $url, $data);
        $decoded = json_decode($r['body'], true);
        if ($decoded === null) {
            throw new RuntimeException('Invalid JSON response: ' . substr($r['body'], 0, 200));
        }
        return $decoded;
    }

    /** Reset marks state for clean test isolation */
    private function resetMarks(): void
    {
        $this->json('POST', '/city.php?action=clearSelection');
        // Turn off show_only if it was on
        $this->json('POST', '/city.php?action=toggleShowOnly');
        $this->json('POST', '/city.php?action=toggleShowOnly');
    }

    // --- Login ---

    public function testLogin(): void
    {
        $r = $this->json('POST', '/login_handler.php', [
            'login' => 'admin', 'password' => 'admin',
        ]);
        $this->assertTrue($r['ok']);
        $this->resetMarks();
    }

    // ======== MARKS ========

    /** @depends testLogin */
    public function testToggleSelectOn(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/city.php?action=toggleSelect', [
            'id' => '1', 'to' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleSelectOff(): void
    {
        $this->resetMarks();

        $this->json('POST', '/city.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/city.php?action=toggleSelect', ['id' => '1', 'to' => '0']);
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testClearSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/city.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $this->json('POST', '/city.php?action=toggleSelect', ['id' => '2', 'to' => '1']);
        $r = $this->json('POST', '/city.php?action=clearSelection');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testInvertSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/city.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/city.php?action=invertSelection');
        $this->assertTrue($r['ok']);
        // 3 total rows, 1 was marked → after invert 2 should be marked
        $this->assertSame(2, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleShowOnly(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/city.php?action=toggleShowOnly');
        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('show_only', $r);
    }

    // ======== SEARCH ========

    /** @depends testLogin */
    public function testSearchByCity(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?q=' . urlencode('Москва') . '&cols=city&cond=contains&sf=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Москва', $r['body']);
        $this->assertStringNotContainsString('New York', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchNotFound(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?q=NonExistentCity&cols=city&cond=contains&sf=1');
        $this->assertStringContainsString('ничего не найдено', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchStartsWith(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?q=New&cols=city&cond=starts_with&sf=1');
        $this->assertStringContainsString('New York', $r['body']);
        $this->assertStringNotContainsString('Москва', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchNotContains(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?q=' . urlencode('Москва') . '&cols=city&cond=not_contains&sf=1');
        $this->assertStringNotContainsString('data-value="Москва"', $r['body']);
        $this->assertStringContainsString('data-value="New York"', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchCleared(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?q=' . urlencode('Москва') . '&cols=city&cond=contains&sf=1');
        $this->assertStringContainsString('Москва', $r['body']);
        $r2 = $this->req('GET', '/city.php?sf=0');
        $this->assertStringContainsString('New York', $r2['body']);
        $this->assertStringContainsString('Москва', $r2['body']);
    }

    // ======== SORTING ========

    /** @depends testLogin */
    public function testSortByCityAsc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?sort=city:asc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-city');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="asc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortByCityDesc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?sort=city:desc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-city');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="desc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortMulti(): void
    {
        $this->resetMarks();

        // Ensure all columns are visible (may have been hidden by testColumnsSaveHidesColumn)
        $this->json('POST', '/city_columns_save.php?ajax=1', [
            'tbl' => 'city', 'columns' => [
                ['name' => 'id',      'visible' => true, 'order' => 0],
                ['name' => 'city',    'visible' => true, 'order' => 1],
                ['name' => 'country', 'visible' => true, 'order' => 2],
                ['name' => 'note',    'visible' => true, 'order' => 3],
            ],
        ]);

        $r = $this->req('GET', '/city.php?' . http_build_query(['sort' => 'country:desc,city:asc']));
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-country');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="desc"', $afterCol);
    }

    // ======== EXPORT ========

    /** @depends testLogin */
    public function testExportCsv(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city_export.php?format=csv');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('text/csv', $r['contentType']);
        $this->assertStringContainsString('ID', $r['body']);
        $this->assertStringContainsString('Москва', $r['body']);
        $this->assertStringContainsString('New York', $r['body']);
    }

    /** @depends testLogin */
    public function testExportXls(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city_export.php?format=xls');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ms-excel', $r['contentType']);
        $this->assertStringContainsString('<tr>', $r['body']);
        $this->assertStringContainsString('Москва', $r['body']);
    }

    /** @depends testLogin */
    public function testExportCsvWithSearch(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city_export.php?format=csv&q=New+York&cols=city&cond=contains&sf=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('New York', $r['body']);
        $this->assertStringNotContainsString('Москва', $r['body']);
    }

    /** @depends testLogin */
    public function testExportWithMarks(): void
    {
        $this->resetMarks();
        $this->json('POST', '/city.php?action=toggleSelect', ['id' => '1', 'to' => '1']);

        $r = $this->req('GET', '/city_export.php?format=csv&all=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Москва', $r['body']);
        $this->assertStringNotContainsString('New York', $r['body']);
    }

    // ======== COLUMN RESIZE ========

    /** @depends testLogin */
    public function testColumnWidthSave(): void
    {
        $r = $this->json('POST', '/city_column_width_save.php?ajax=1', [
            'tbl' => 'city', 'name' => 'city', 'width' => '300',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetSingle(): void
    {
        $this->json('POST', '/city_column_width_save.php?ajax=1', [
            'tbl' => 'city', 'name' => 'city', 'width' => '300',
        ]);
        $r = $this->json('POST', '/city_column_width_save.php?ajax=1', [
            'tbl' => 'city', 'name' => 'city', 'width' => '',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetAll(): void
    {
        $this->json('POST', '/city_column_width_save.php?ajax=1', [
            'tbl' => 'city', 'name' => 'city', 'width' => '300',
        ]);
        $r = $this->json('POST', '/column_width_reset.php?ajax=1', [
            'tbl' => 'city',
        ]);
        $this->assertTrue($r['ok']);
    }

    // ======== COLUMN VISIBILITY ========

    /** @depends testLogin */
    public function testColumnsSave(): void
    {
        $columns = [
            ['name' => 'id',      'visible' => true,  'order' => 0],
            ['name' => 'note',    'visible' => true,  'order' => 1],
            ['name' => 'city',    'visible' => true,  'order' => 2],
            ['name' => 'country', 'visible' => true,  'order' => 3],
        ];
        $r = $this->json('POST', '/city_columns_save.php?ajax=1', [
            'tbl' => 'city', 'columns' => $columns,
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnsSaveHidesColumn(): void
    {
        $columns = [
            ['name' => 'id',      'visible' => true,  'order' => 0],
            ['name' => 'city',    'visible' => true,  'order' => 1],
            ['name' => 'country', 'visible' => false, 'order' => 2],
            ['name' => 'note',    'visible' => true,  'order' => 3],
        ];
        $this->json('POST', '/city_columns_save.php?ajax=1', [
            'tbl' => 'city', 'columns' => $columns,
        ]);

        $r = $this->req('GET', '/city.php');
        $this->assertStringNotContainsString('<th class="col-country"', $r['body']);
    }

    // ======== COLUMN FILTER ========

    /** @depends testLogin */
    public function testColumnFilterOptions(): void
    {
        $r = $this->json('GET', '/city.php?action=columnFilterOptions&col=country');
        $this->assertIsArray($r);
        $names = array_column($r, 'name');
        $this->assertContains('Россия', $names);
        $this->assertContains('США', $names);
    }

    /** @depends testLogin */
    public function testColumnFilterApply(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/city.php?country_id=2');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('New York', $r['body']);
        $this->assertStringNotContainsString('Москва', $r['body']);
    }

    // ======== FORM RETURN (FOCUS) ========

    /** @depends testLogin */
    public function testFormReturnFocusAfterCreate(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/city_form.php?mode=new&ajax=1', [
            'city' => 'FocusTestCity',
            'country_id' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $newId = $r['id'];
        $this->assertGreaterThan(0, $newId);

        $r2 = $this->req('GET', '/city.php?focus=' . $newId);
        $this->assertSame(200, $r2['httpCode']);
        $this->assertStringContainsString('data-focus="' . $newId . '"', $r2['body']);

        $this->json('POST', '/city_form.php?mode=delete&ajax=1', ['id' => (string)$newId]);
    }

    /** @depends testLogin */
    public function testFormReturnFocusAfterEdit(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/city_form.php?mode=edit&id=1&ajax=1', [
            'city' => 'Москва-Сити',
            'country_id' => '1',
        ]);
        $this->assertTrue($r['ok']);

        $r2 = $this->req('GET', '/city.php?focus=1');
        $this->assertStringContainsString('data-focus="1"', $r2['body']);

        // Revert
        $this->json('POST', '/city_form.php?mode=edit&id=1&ajax=1', [
            'city' => 'Москва', 'country_id' => '1',
        ]);
    }
}
