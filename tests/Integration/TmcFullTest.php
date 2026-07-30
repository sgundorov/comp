<?php
use PHPUnit\Framework\TestCase;

class TmcFullTest extends TestCase
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
        $this->json('POST', '/tmc.php?action=clearSelection');
        $this->json('POST', '/tmc.php?action=toggleShowOnly');
        $this->json('POST', '/tmc.php?action=toggleShowOnly');
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

        $r = $this->req('GET', '/tmc.php');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('<th class="col-id"', $r['body']);
        $this->assertStringContainsString('<th class="col-name"', $r['body']);
        $this->assertStringContainsString('<th class="col-article"', $r['body']);
        $this->assertStringContainsString('<th class="col-categ"', $r['body']);
        $this->assertStringContainsString('<th class="col-group"', $r['body']);
        $this->assertStringContainsString('<th class="col-sgroup"', $r['body']);
        $this->assertStringContainsString('<th class="col-country"', $r['body']);
        $this->assertStringContainsString('<th class="col-quant"', $r['body']);
        $this->assertStringContainsString('<th class="col-price_in"', $r['body']);
        $this->assertStringContainsString('<th class="col-price_out"', $r['body']);
        $this->assertStringContainsString('<th class="col-note"', $r['body']);
        $this->assertStringContainsString('Ноутбук', $r['body']);
        $this->assertStringContainsString('Монитор', $r['body']);
        $this->assertStringContainsString('Мышь', $r['body']);
        $this->assertStringContainsString('Клавиатура', $r['body']);
    }

    // ======== MARKS ========

    /** @depends testLogin */
    public function testToggleSelectOn(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc.php?action=toggleSelect', [
            'id' => '1', 'to' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleSelectOff(): void
    {
        $this->resetMarks();

        $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '1', 'to' => '0']);
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testClearSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '2', 'to' => '1']);
        $r = $this->json('POST', '/tmc.php?action=clearSelection');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['count']);
    }

    /** @depends testLogin */
    public function testInvertSelection(): void
    {
        $this->resetMarks();

        $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '1', 'to' => '1']);
        $r = $this->json('POST', '/tmc.php?action=invertSelection');
        $this->assertTrue($r['ok']);
        $this->assertSame(3, $r['count']);
    }

    /** @depends testLogin */
    public function testToggleShowOnly(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc.php?action=toggleShowOnly');
        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('show_only', $r);
    }

    // ======== SEARCH ========

    /** @depends testLogin */
    public function testSearchByName(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?' . http_build_query([
            'q' => 'Ноут', 'cols' => 'name', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Ноутбук', $r['body']);
        $this->assertStringNotContainsString('Монитор', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchByArticle(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?' . http_build_query([
            'q' => 'MS-001', 'cols' => 'article', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Мышь', $r['body']);
        $this->assertStringNotContainsString('Ноутбук', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchNotFound(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?q=NonExistentProduct&cols=name&cond=contains&sf=1');
        $this->assertStringContainsString('ничего не найдено', $r['body']);
    }

    /** @depends testLogin */
    public function testSearchCleared(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?' . http_build_query([
            'q' => 'Ноут', 'cols' => 'name', 'cond' => 'contains', 'sf' => '1',
        ]));
        $this->assertStringContainsString('Ноутбук', $r['body']);
        $r2 = $this->req('GET', '/tmc.php?sf=0');
        $this->assertStringContainsString('Монитор', $r2['body']);
        $this->assertStringContainsString('Клавиатура', $r2['body']);
    }

    // ======== SORTING ========

    /** @depends testLogin */
    public function testSortByNameAsc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?sort=name:asc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-name');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="asc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortByNameDesc(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?sort=name:desc');
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-name');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="desc"', $afterCol);
    }

    /** @depends testLogin */
    public function testSortMulti(): void
    {
        $this->resetMarks();

        $this->json('POST', '/tmc_columns_save.php?ajax=1', [
            'tbl' => 'product', 'columns' => [
                ['name' => 'id',        'visible' => true, 'order' => 0],
                ['name' => 'name',      'visible' => true, 'order' => 1],
                ['name' => 'article',   'visible' => true, 'order' => 2],
                ['name' => 'categ',     'visible' => true, 'order' => 3],
                ['name' => 'group',     'visible' => true, 'order' => 4],
                ['name' => 'sgroup',    'visible' => true, 'order' => 5],
                ['name' => 'country',   'visible' => true, 'order' => 6],
                ['name' => 'quant',     'visible' => true, 'order' => 7],
                ['name' => 'price_in',  'visible' => true, 'order' => 8],
                ['name' => 'price_out', 'visible' => true, 'order' => 9],
                ['name' => 'note',      'visible' => true, 'order' => 10],
            ],
        ]);

        $r = $this->req('GET', '/tmc.php?' . http_build_query(['sort' => 'quant:asc,name:desc']));
        $this->assertSame(200, $r['httpCode']);

        $colIdx = strpos($r['body'], '<th class="col-quant');
        $afterCol = substr($r['body'], $colIdx, 300);
        $this->assertStringContainsString('data-sort-dir="asc"', $afterCol);
    }

    // ======== EXPORT ========

    /** @depends testLogin */
    public function testExportCsv(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc_export.php?format=csv');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('text/csv', $r['contentType']);
        $this->assertStringContainsString('Ноутбук', $r['body']);
        $this->assertStringContainsString('Монитор', $r['body']);
        $this->assertStringContainsString('NB-001', $r['body']);
    }

    /** @depends testLogin */
    public function testExportXls(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc_export.php?format=xls');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('ms-excel', $r['contentType']);
        $this->assertStringContainsString('<tr>', $r['body']);
        $this->assertStringContainsString('Ноутбук', $r['body']);
    }

    /** @depends testLogin */
    public function testExportCsvWithSearch(): void
    {
        $this->resetMarks();

        $searchQs = http_build_query([
            'q' => 'NB-001', 'cols' => 'article', 'cond' => 'contains', 'sf' => '1',
        ]);
        $r = $this->req('GET', '/tmc_export.php?format=csv&' . $searchQs);
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('NB-001', $r['body']);
        $this->assertStringNotContainsString('MON-001', $r['body']);
    }

    /** @depends testLogin */
    public function testExportWithMarks(): void
    {
        $this->resetMarks();
        $this->json('POST', '/tmc.php?action=toggleSelect', ['id' => '2', 'to' => '1']);

        $r = $this->req('GET', '/tmc_export.php?format=csv&all=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Монитор', $r['body']);
        $this->assertStringNotContainsString('Ноутбук', $r['body']);
    }

    // ======== COLUMN RESIZE ========

    /** @depends testLogin */
    public function testColumnWidthSave(): void
    {
        $r = $this->json('POST', '/tmc_column_width_save.php?ajax=1', [
            'tbl' => 'product', 'name' => 'name', 'width' => '300',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetSingle(): void
    {
        $this->json('POST', '/tmc_column_width_save.php?ajax=1', [
            'tbl' => 'product', 'name' => 'name', 'width' => '300',
        ]);
        $r = $this->json('POST', '/tmc_column_width_save.php?ajax=1', [
            'tbl' => 'product', 'name' => 'name', 'width' => '',
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnWidthResetAll(): void
    {
        $this->json('POST', '/tmc_column_width_save.php?ajax=1', [
            'tbl' => 'product', 'name' => 'name', 'width' => '300',
        ]);
        $r = $this->json('POST', '/column_width_reset.php?ajax=1', [
            'tbl' => 'product',
        ]);
        $this->assertTrue($r['ok']);
    }

    // ======== COLUMN VISIBILITY ========

    /** @depends testLogin */
    public function testColumnsSave(): void
    {
        $columns = [
            ['name' => 'id',        'visible' => true,  'order' => 0],
            ['name' => 'name',      'visible' => true,  'order' => 1],
            ['name' => 'article',   'visible' => true,  'order' => 2],
            ['name' => 'categ',     'visible' => true,  'order' => 3],
            ['name' => 'group',     'visible' => true,  'order' => 4],
            ['name' => 'sgroup',    'visible' => true,  'order' => 5],
            ['name' => 'country',   'visible' => true,  'order' => 6],
            ['name' => 'quant',     'visible' => true,  'order' => 7],
            ['name' => 'price_in',  'visible' => true,  'order' => 8],
            ['name' => 'price_out', 'visible' => true,  'order' => 9],
            ['name' => 'note',      'visible' => true,  'order' => 10],
        ];
        $r = $this->json('POST', '/tmc_columns_save.php?ajax=1', [
            'tbl' => 'product', 'columns' => $columns,
        ]);
        $this->assertTrue($r['ok']);
    }

    /** @depends testLogin */
    public function testColumnsSaveHidesColumn(): void
    {
        $columns = [
            ['name' => 'id',        'visible' => true,  'order' => 0],
            ['name' => 'name',      'visible' => true,  'order' => 1],
            ['name' => 'article',   'visible' => false, 'order' => 2],
            ['name' => 'categ',     'visible' => true,  'order' => 3],
            ['name' => 'group',     'visible' => true,  'order' => 4],
            ['name' => 'sgroup',    'visible' => true,  'order' => 5],
            ['name' => 'country',   'visible' => true,  'order' => 6],
            ['name' => 'quant',     'visible' => true,  'order' => 7],
            ['name' => 'price_in',  'visible' => true,  'order' => 8],
            ['name' => 'price_out', 'visible' => true,  'order' => 9],
            ['name' => 'note',      'visible' => true,  'order' => 10],
        ];
        $this->json('POST', '/tmc_columns_save.php?ajax=1', [
            'tbl' => 'product', 'columns' => $columns,
        ]);

        $r = $this->req('GET', '/tmc.php');
        $this->assertStringNotContainsString('<th class="col-article"', $r['body']);
    }

    // ======== COLUMN FILTER ========

    /** @depends testLogin */
    public function testColumnFilterOptions(): void
    {
        $r = $this->json('GET', '/tmc.php?action=columnFilterOptions&col=categ');
        $this->assertIsArray($r);
        $names = array_column($r, 'name');
        $this->assertContains('Электроника', $names);
        $this->assertContains('Мебель', $names);
    }

    /** @depends testLogin */
    public function testColumnFilterApply(): void
    {
        $this->resetMarks();

        $r = $this->req('GET', '/tmc.php?group_id=1');
        $this->assertSame(200, $r['httpCode']);
        $this->assertStringContainsString('Ноутбук', $r['body']);
        $this->assertStringContainsString('Мышь', $r['body']);
        $this->assertStringNotContainsString('Монитор', $r['body']);
        $this->assertStringNotContainsString('Клавиатура', $r['body']);
    }

    // ======== INLINE EDIT ========

    /** @depends testLogin */
    public function testInlineEditText(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '1', 'field' => 'name', 'value' => 'Ноутбук (изменён)',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('Ноутбук (изменён)', $r['value']);

        // Revert
        $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '1', 'field' => 'name', 'value' => 'Ноутбук',
        ]);
    }

    /** @depends testLogin */
    public function testInlineEditLookup(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'categ', 'value' => '2',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['value']);
        $this->assertSame('Мебель', $r['display']);

        // Revert
        $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '2', 'field' => 'categ', 'value' => '1',
        ]);
    }

    /** @depends testLogin */
    public function testInlineEditDecimal(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '3', 'field' => 'price_out', 'value' => '123.45',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(123.45, $r['value']);

        // Revert
        $this->json('POST', '/tmc_field_save.php?ajax=1', [
            'id' => '3', 'field' => 'price_out', 'value' => '100',
        ]);
    }

    // ======== FORM RETURN (FOCUS) ========

    /** @depends testLogin */
    public function testFormReturnFocusAfterCreate(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc_form.php?mode=new&ajax=1', [
            'product_name' => 'Принтер',
            'article' => 'PRN-001',
            'categ_id' => '1',
            'price_out' => '999.99',
        ]);
        $this->assertTrue($r['ok']);
        $newId = $r['id'];
        $this->assertGreaterThan(0, $newId);

        $r2 = $this->req('GET', '/tmc.php?focus=' . $newId);
        $this->assertSame(200, $r2['httpCode']);
        $this->assertStringContainsString('data-focus="' . $newId . '"', $r2['body']);

        $this->json('POST', '/tmc_form.php?mode=delete&ajax=1', ['id' => (string)$newId]);
    }

    /** @depends testLogin */
    public function testFormReturnFocusAfterEdit(): void
    {
        $this->resetMarks();

        $r = $this->json('POST', '/tmc_form.php?mode=edit&id=1&ajax=1', [
            'product_name' => 'Ноутбук (ред)',
            'article' => 'NB-001',
        ]);
        $this->assertTrue($r['ok']);

        $r2 = $this->req('GET', '/tmc.php?focus=1');
        $this->assertStringContainsString('data-focus="1"', $r2['body']);

        // Revert
        $this->json('POST', '/tmc_form.php?mode=edit&id=1&ajax=1', [
            'product_name' => 'Ноутбук',
            'article' => 'NB-001',
        ]);
    }
}
