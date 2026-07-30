<?php
use PHPUnit\Framework\TestCase;

class TablePageTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static string $cookieFile;

    public static function setUpBeforeClass(): void
    {
        self::$cookieFile = __DIR__ . '/../_cookie.txt';
        @unlink(self::$cookieFile);

        // Check if the server is running before setting up DB
        $baseUrl = TestDb::baseUrl();
        $fp = @fsockopen(parse_url($baseUrl, PHP_URL_HOST), parse_url($baseUrl, PHP_URL_PORT), $e, $e, 2);
        if (!$fp) {
            return; // skip — setUp will mark tests as skipped
        }
        fclose($fp);

        TestDb::setup();
        self::$baseUrl = $baseUrl;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$baseUrl !== null) {
            TestDb::teardown();
        }
        @unlink(self::$cookieFile);
    }

    private function requireServer(): void
    {
        if (self::$baseUrl === null) {
            self::markTestSkipped('PHP built-in server is not running. Start it with: run_integration_tests.ps1');
        }
    }

    private function httpGet(string $url, bool $noRedirect = false): string
    {
        $this->requireServer();
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => self::$baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEFILE => self::$cookieFile,
            CURLOPT_COOKIEJAR => self::$cookieFile,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ];
        if ($noRedirect) {
            $opts[CURLOPT_FOLLOWLOCATION] = false;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP request failed');
        }
        return $body;
    }

    private function httpPost(string $url, array $data): string
    {
        $this->requireServer();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::$baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEFILE => self::$cookieFile,
            CURLOPT_COOKIEJAR => self::$cookieFile,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP POST failed');
        }
        return $body;
    }

    public function testLogin(): void
    {
        $json = $this->httpPost('/login_handler.php', [
            'login' => 'admin',
            'password' => 'admin',
        ]);

        // After login, request the main page to verify session works
        $html = $this->httpGet('/city.php');
        $this->assertStringContainsString('data-table', $html);
        $this->assertStringContainsString('Город', $html);
    }

    /**
     * @depends testLogin
     */
    public function testCityPageRenders(): void
    {
        $html = $this->httpGet('/city.php');
        $this->assertStringContainsString('Москва', $html);
        $this->assertStringContainsString('Санкт-Петербург', $html);
        $this->assertStringContainsString('New York', $html);
        $this->assertStringContainsString('data-table', $html);
    }

    /**
     * @depends testLogin
     */
    public function testCityFormNewLoads(): void
    {
        $json = $this->httpGet('/city_form.php?mode=new&ajax=1');
        $data = json_decode($json, true);
        $this->assertNotNull($data, 'Response is not valid JSON');
        $this->assertArrayHasKey('ok', $data);
        $this->assertTrue($data['ok']);
    }

    /**
     * @depends testLogin
     */
    public function testCityFormCreate(): void
    {
        $json = $this->httpPost('/city_form.php?mode=new&ajax=1', [
            'city' => 'ТестовыйГород',
            'country_id' => '1',
            'note' => 'создан тестом',
        ]);
        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertTrue($data['ok']);
    }

    /**
     * @depends testLogin
     */
    public function testCityFormEditLoads(): void
    {
        $json = $this->httpGet('/city_form.php?mode=edit&id=1&ajax=1');
        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertTrue($data['ok']);
    }

    /**
     * @depends testLogin
     */
    public function testCityInlineEdit(): void
    {
        $json = $this->httpPost('/city_field_save.php', [
            'field' => 'city',
            'value' => 'CityChanged',
            'id' => '3',
        ]);
        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertTrue($data['ok']);

        // Revert
        $this->httpPost('/city_field_save.php', [
            'field' => 'city',
            'value' => 'New York',
            'id' => '3',
        ]);
    }

    /**
     * @depends testLogin
     */
    public function testInvoicePageRenders(): void
    {
        $html = $this->httpGet('/invoice.php');
        $this->assertStringContainsString('Счета', $html);
        $this->assertStringContainsString('data-table', $html);
    }

    /**
     * @depends testLogin
     */
    public function testInvoiceFormNewLoads(): void
    {
        $json = $this->httpGet('/invoice_form.php?mode=new&ajax=1');
        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertTrue($data['ok']);
    }
}
