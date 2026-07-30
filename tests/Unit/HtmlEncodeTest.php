<?php
use PHPUnit\Framework\TestCase;

class HtmlEncodeTest extends TestCase
{
    public function testPlainText(): void
    {
        $this->assertSame('hello', h('hello'));
        $this->assertSame('123', h(123));
    }

    public function testSpecialChars(): void
    {
        $this->assertSame('&amp;', h('&'));
        $this->assertSame('&lt;', h('<'));
        $this->assertSame('&gt;', h('>'));
        $this->assertSame('&quot;', h('"'));
    }

    public function testMixed(): void
    {
        $this->assertSame('a &amp; b &lt; c', h('a & b < c'));
    }

    public function testNullCoercion(): void
    {
        $this->assertSame('', h(''));
        $this->assertSame('0', h(0));
    }
}
