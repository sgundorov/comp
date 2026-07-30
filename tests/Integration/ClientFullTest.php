<?php
use PHPUnit\Framework\TestCase;

class ClientFullTest extends TestCase
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

    private function resetMarks(): void
    {
        $this->json('POST', '/client.php?action=clearSelection');
        $this->json('POST', '/client.php?action=toggleShowOnly');
        $this->json('POST', '/client.php?action=toggleShowOnly');
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

    // ======== PAGE RENDER ========

    /** @depends testLogin */
    public function testPageRender(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('<th class="col-id"', $r['body']);
        $this->assertStringContainsString('<th class="col-name"', $r['body']);
        $this->assertStringContainsString('<th class="col-phone"', $r['body']);
        $this->assertStringContainsString('<th class="col-email"', $r['body']);
        $this->assertStringContainsString('ООО Ромашка', $r['body']);
        $this->assertStringContainsString('Иванов Иван', $r['body']);
    }

    // ======== MARKS ========

    /** @depends testLogin */
    public function testToggleSelectOn(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client.php?action=toggleSelect', [
            'id' => '1', 'to' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleSelectOff(): void
    {
        $this->resetMarks();

        $this->json('POST', '/client.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/client.php?action=toggleSelect', ['id' => '1', 'to' => '0']);
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testClearSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/client.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $this->json('POST', '/client.php?action=toggleSelect', ['id' => '2', 'to' => '1']);
        $r = $this->json('POST', '/client.php?action=clearSelection');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testInvertSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/client.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/client.php?action=invertSelection');
        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleShowOnly(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client.php?action=toggleShowOnly');
        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('show_only', $r);
    }

    // ======== SEARCH ========

    /** @depends testLogin */
    public function testSearchByPhone(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?' . http_build_query([
            'q' => '123-45', 'cols' => 'phone', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('+7 495 123-45-67', $r['body']);
        $this->assertStringNotContainsString('ivan@example.com', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchByEmail(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?' . http_build_query([
            'q' => 'ivan@', 'cols' => 'email', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ivan@example.com', $r['body']);
        $this->assertStringNotContainsString('info@romashka.ru', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchNotFound(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?q=NonExistentClient&cols=name&cond=contains&sf=1');
        $this->assertStringContainsString('ничего не найдено', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchCleared(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?' . http_build_query([
            'q' => '123-45', 'cols' => 'phone', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertStringContainsString('+7 495 123-45-67', $r['body']);
        $r2 = $this->req('GET', '/client.php?sf=0');
        $this->assertStringContainsString('ООО Ромашка', $r2['body']);
        $this->assertStringContainsString('Иванов Иван', $r2['body']);
    }

    // ======== SORTING ========

    /** @depends testLogin */
    public function testSortByNameAsc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?sort=name:asc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-name');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="asc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortByNameDesc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?sort=name:desc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-name');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="desc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortMulti(): void
    {
        $this->resetMarks();

        $this->json('POST', '/client_columns_save.php?ajax=1', [
            'tbl' => 'client', 'columns' => [
                ['name' => 'id',           'visible' => true, 'order' => 0],
                ['name' => 'name',         'visible' => true, 'order' => 1],
                ['name' => 'cli_categ_id', 'visible' => true, 'order' => 2],
                ['name' => 'phone',        'visible' => true, 'order' => 3],
                ['name' => 'email',        'visible' => true, 'order' => 4],
                ['name' => 'city_id',      'visible' => true, 'order' => 5],
                ['name' => 'country_id',   'visible' => true, 'order' => 6],
                ['name' => 'address',      'visible' => true, 'order' => 7],
                ['name' => 'note',         'visible' => true, 'order' => 8],
            ],
        ]);

        $r = $this->req('GET', '/client.php?' . http_build_query(['sort' => 'city_id:desc,name:asc']));
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-city_id');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="desc"', $afterCol);
    }

    // ======== EXPORT ========

    /** @depends testLogin */
    public function testExportCsv(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client_export.php?format=csv');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('text/csv', $r['contentType']);
        $this->assertStringContainsString('Контрагент', $r['body']);
        $this->assertStringContainsString('ООО Ромашка', $r['body']);
        $this->assertStringContainsString('Иванов Иван', $r['body']);
    }

    /** @depends testLogin */
    public function testExportXls(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client_export.php?format=xls');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ms-excel', $r['contentType']);
        $this->assertStringContainsString('<tr>', $r['body']);
        $this->assertStringContainsString('ООО Ромашка', $r['body']);
    }

    /** @depends testLogin */
    public function testExportCsvWithSearch(): void
    {
        $this->resetMarks();

        $searchQs = http_build_query([
            'q' => 'info@romashka.ru', 'cols' => 'email', 'cond' => 'contains', 'sf' => '1',
        ]);
        $r = $this->req('GET', '/client_export.php?format=csv&' . $searchQs);
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('info@romashka.ru', $r['body']);
        $this->assertStringNotContainsString('ivan@example.com', $r['body']);
    }

    /** @depends testLogin */
    public function testExportWithMarks(): void
    {
        $this->resetMarks();
        $this->json('POST', '/client.php?action=toggleSelect', ['id' => '2', 'to' => '1']);

        $r = $this->req('GET', '/client_export.php?format=csv&all=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ООО Ромашка', $r['body']);
        $this->assertStringNotContainsString('Иванов Иван', $r['body']);
    }

    // ======== COLUMN RESIZE ========

    /** @depends testLogin */
    public function testColumnWidthSave(): void
    {
        $r = $this->json('POST', '/client_column_width_save.php?ajax=1', [
            'tbl' => 'client', 'name' => 'name', 'width' => '300',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetSingle(): void
    {
        $this->json('POST', '/client_column_width_save.php?ajax=1', [
            'tbl' => 'client', 'name' => 'name', 'width' => '300',
        ]);
        $r = $this->json('POST', '/client_column_width_save.php?ajax=1', [
            'tbl' => 'client', 'name' => 'name', 'width' => '',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetAll(): void
    {
        $this->json('POST', '/client_column_width_save.php?ajax=1', [
            'tbl' => 'client', 'name' => 'name', 'width' => '300',
        ]);
        $r = $this->json('POST', '/column_width_reset.php?ajax=1', [
            'tbl' => 'client',
        ]);
        $this->assertTrue($r['ok']);
    }

    // ======== COLUMN VISIBILITY ========

    /** @depends testLogin */
    public function testColumnsSave(): void
    {
        $columns = [
            ['name' => 'id',           'visible' => true,  'order' => 0],
            ['name' => 'name',         'visible' => true,  'order' => 1],
            ['name' => 'cli_categ_id', 'visible' => true,  'order' => 2],
            ['name' => 'phone',        'visible' => true,  'order' => 3],
            ['name' => 'email',        'visible' => true,  'order' => 4],
            ['name' => 'city_id',      'visible' => true,  'order' => 5],
            ['name' => 'country_id',   'visible' => true,  'order' => 6],
            ['name' => 'address',      'visible' => true,  'order' => 7],
            ['name' => 'note',         'visible' => true,  'order' => 8],
        ];
        $r = $this->json('POST', '/client_columns_save.php?ajax=1', [
            'tbl' => 'client', 'columns' => $columns,
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnsSaveHidesColumn(): void
    {
        $columns = [
            ['name' => 'id',           'visible' => true,  'order' => 0],
            ['name' => 'name',         'visible' => true,  'order' => 1],
            ['name' => 'cli_categ_id', 'visible' => false, 'order' => 2],
            ['name' => 'phone',        'visible' => true,  'order' => 3],
            ['name' => 'email',        'visible' => true,  'order' => 4],
            ['name' => 'city_id',      'visible' => true,  'order' => 5],
            ['name' => 'country_id',   'visible' => true,  'order' => 6],
            ['name' => 'address',      'visible' => true,  'order' => 7],
            ['name' => 'note',         'visible' => true,  'order' => 8],
        ];
        $this->json('POST', '/client_columns_save.php?ajax=1', [
            'tbl' => 'client', 'columns' => $columns,
        ]);

        $r = $this->req('GET', '/client.php');
        $this->assertStringNotContainsString('<th class="col-cli_categ_id"', $r['body']);
    }

    // ======== COLUMN FILTER ========

    /** @depends testLogin */
    public function testColumnFilterOptions(): void
    {
        $r = $this->json('GET', '/client.php?action=columnFilterOptions&col=cli_categ_id');
        $this->assertIsArray($r);
        $names = array_column($r, 'name');
        $this->assertContains('Постоянный', $names);
        $this->assertContains('Разовый', $names);
    }

    /** @depends testLogin */
    public function testColumnFilterApply(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/client.php?cli_categ_id=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ООО Ромашка', $r['body']);
        $this->assertStringNotContainsString('Иванов Иван', $r['body']);
    }

    // ======== INLINE EDIT ========

    /** @depends testLogin */
    public function testInlineEditCheckbox(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'supplier_flag', 'value' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('1', $r['value']);

        // Revert
        $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'supplier_flag', 'value' => '0',
        ]);
    }

    /** @depends testLogin */
    public function testInlineEditLookup(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'cli_categ_id', 'value' => '2',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('2', $r['value']);
        $this->assertSame('Разовый', $r['displayValue']);

        // Revert
        $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'cli_categ_id', 'value' => '1',
        ]);
    }

    /** @depends testLogin */
    public function testInlineEditText(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '3', 'field' => 'phone', 'value' => '+7 999 999-99-99',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('+7 999 999-99-99', $r['value']);

        // Revert
        $this->json('POST', '/client_field_save.php?ajax=1', [
            'id' => '3', 'field' => 'phone', 'value' => '+7 495 765-43-21',
        ]);
    }

    // ======== FORM RETURN (FOCUS) ========

    /** @depends testLogin */
    public function testFormReturnFocusAfterCreate(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client_form.php?mode=new&ajax=1', [
            'last_name' => 'Тестов',
            'first_name' => 'Тест',
            'cli_categ_id' => '1',
            'phone' => '+7 111 222-33-44',
        ]);
        $this->assertTrue($r['ok']);
        $newId = $r['id'];
        $this->assertGreaterThan(0, $newId);

        $r2 = $this->req('GET', '/client.php?focus=' . $newId);
        $this->assertSame(200, $r2['httpCode']);
        $this->assertStringContainsString('data-focus="' . $newId . '"', $r2['body']);

        $this->json('POST', '/client_form.php?mode=delete&ajax=1', ['id' => (string)$newId]);
    }

    /** @depends testLogin */
    public function testFormReturnFocusAfterEdit(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/client_form.php?mode=edit&id=2&ajax=1', [
            'last_name' => 'Ромашкин',
            'first_name' => '',
        ]);
        $this->assertTrue($r['ok']);

        $r2 = $this->req('GET', '/client.php?focus=2');
        $this->assertStringContainsString('data-focus="2"', $r2['body']);

        // Revert
        $this->json('POST', '/client_form.php?mode=edit&id=2&ajax=1', [
            'last_name' => '',
            'first_name' => '',
        ]);
    }
}
