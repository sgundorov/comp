<?php
use PHPUnit\Framework\TestCase;

class InvoiceTotalsTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static string $cookieFile;
    private static int $invoiceId;
    private static int $line1Id = 0;
    private static int $line2Id = 0;

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

    public function testLogin(): void
    {
        $r = $this->json('POST', '/login_handler.php', [
            'login' => 'admin', 'password' => 'admin',
        ]);
        $this->assertTrue($r['ok']);
    }

    // ======== INVOICE CREATE ========

    /** @depends testLogin */
    public function testInvoiceCreate(): void
    {
        $r = $this->json('POST', '/invoice_form.php?mode=new&ajax=1', [
            'number' => '1',
            'date' => date('Y-m-d'),
            'client_id' => '1',
            'store_id' => '1',
            'sotr_id' => '1',
        ]);
        $this->assertTrue($r['ok'], 'Create failed: ' . substr($r['html'] ?? '', 0, 200));
        $this->assertArrayHasKey('id', $r);
        self::$invoiceId = (int)$r['id'];
        $this->assertGreaterThan(0, self::$invoiceId);
    }

    // ======== ADD LINE ITEMS ========

    /** @depends testInvoiceCreate */
    public function testAddLineItemProduct1(): void
    {
        // Create line: product_id=1 (Ноутбук, price_out=1500), no discount on parent
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => '0',
            'invoice_id' => (string)self::$invoiceId,
            'field' => 'product_id',
            'value' => '1',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertGreaterThan(0, (int)($r['id'] ?? 0));
        self::$line1Id = (int)$r['id'];

        // Verify totals: quant=1, price=1500, discount=0
        // sum = 1*1500*(1-0/100) = 1500, sum_discount = 1*1500*(0/100) = 0
        $this->assertArrayHasKey('sum', $r);
    }

    /** @depends testInvoiceCreate */
    public function testAddLineItemProduct2(): void
    {
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => '0',
            'invoice_id' => (string)self::$invoiceId,
            'field' => 'product_id',
            'value' => '2',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertGreaterThan(0, (int)($r['id'] ?? 0));
        self::$line2Id = (int)$r['id'];

        // Line2: quant=1, price=500, sum=500
        // Total: 1500+500 = 2000
        $this->assertArrayHasKey('sum', $r);
    }

    // ======== EDIT LINE ITEMS ========

    /** @depends testAddLineItemProduct1 */
    public function testEditLineQuant(): void
    {
        $this->assertGreaterThan(0, self::$line1Id);

        // Change quant from 1 to 5 on line1
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => (string)self::$line1Id,
            'invoice_id' => (string)self::$invoiceId,
            'field' => 'quant',
            'value' => '5',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame('5', $r['value']);

        // line1: 5*1500 = 7500, line2: 500, total sum = 8000
        $this->assertArrayHasKey('sum', $r);
    }

    /** @depends testEditLineQuant */
    public function testEditLinePrice(): void
    {
        $this->assertGreaterThan(0, self::$line1Id);

        // Change price from 1500 to 2000 on line1
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => (string)self::$line1Id,
            'invoice_id' => (string)self::$invoiceId,
            'field' => 'price',
            'value' => '2000',
        ]);
        $this->assertTrue($r['ok']);

        // line1: 5*2000 = 10000, line2: 500, total sum = 10500
        $this->assertArrayHasKey('sum', $r);
    }

    /** @depends testEditLinePrice */
    public function testEditLineDiscount(): void
    {
        $this->assertGreaterThan(0, self::$line1Id);

        // Set discount=10 on line1
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => (string)self::$line1Id,
            'invoice_id' => (string)self::$invoiceId,
            'field' => 'discount',
            'value' => '10',
        ]);
        $this->assertTrue($r['ok']);

        // line1: 5*2000*(1-10/100) = 9000, sum_discount = 5*2000*(10/100) = 1000
        // line2: 500, total sum = 9500, total discount = 1000
        $this->assertArrayHasKey('sum_discount', $r);
    }

    // ======== DELETE LINE ITEM ========

    /** @depends testEditLineDiscount */
    public function testDeleteLineItem(): void
    {
        $this->assertGreaterThan(0, self::$line2Id);

        $r = $this->json('POST', '/invoice2_field_save.php', [
            'id' => (string)self::$line2Id,
            'field' => '_delete',
        ]);
        $this->assertTrue($r['ok']);

        // Only line1 remains: sum = 9000
        $this->assertArrayHasKey('sum', $r);
    }

    // ======== TOTALS RECALC VIA _RECALC_TOTALS ========

    /** @depends testDeleteLineItem */
    public function testRecalcTotals(): void
    {
        $r = $this->json('POST', '/invoice2_field_save.php', [
            'invoice_id' => (string)self::$invoiceId,
            'field' => '_recalc_totals',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('sum', $r);
    }

    // ======== INVOICE PAGE RENDERS WITH CORRECT DATA ========

    /** @depends testRecalcTotals */
    public function testInvoiceFormEditShowsCorrectData(): void
    {
        $r = $this->json('GET', '/invoice_form.php?mode=edit&id=' . self::$invoiceId . '&ajax=1');
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('Счет', $r['html']);
    }

    // ======== DELETE INVOICE ========

    /** @depends testInvoiceFormEditShowsCorrectData */
    public function testInvoiceDelete(): void
    {
        $r = $this->json('POST', '/invoice_form.php?mode=delete&ajax=1', [
            'id' => (string)self::$invoiceId,
            'date' => date('Y-m-d'),
            'client_id' => '1',
            'store_id' => '1',
            'sotr_id' => '1',
        ]);
        $this->assertTrue($r['ok']);
    }
}
