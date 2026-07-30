<?php
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testHilightContains(): void
    {
        $result = hilight('Hello World', 'World', 'contains');
        $this->assertStringContainsString('<span class="hl">World</span>', $result);
    }

    public function testHilightNoMatch(): void
    {
        $this->assertSame('Hello', hilight('Hello', 'World', 'contains'));
    }

    public function testHilightEmptySearch(): void
    {
        $this->assertSame('Hello', hilight('Hello', ''));
    }

    public function testHilightStartsWith(): void
    {
        $result = hilight('Hello World', 'Hello', 'starts_with');
        $this->assertStringContainsString('<span class="hl">Hello</span>', $result);
    }

    public function testHilightEndsWith(): void
    {
        $result = hilight('Hello World', 'World', 'ends_with');
        $this->assertStringContainsString('<span class="hl">World</span>', $result);
    }
}
