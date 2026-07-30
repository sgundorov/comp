<?php
use PHPUnit\Framework\TestCase;

class FmtNumTest extends TestCase
{
    public function testZeroReturnsEmpty(): void
    {
        $this->assertSame('', fmt_num(0, 2));
        $this->assertSame('', fmt_num('0', 2));
        $this->assertSame('', fmt_num('0.00', 2));
        $this->assertSame('', fmt_num(0.0, 3));
    }

    public function testIntegerReturnsWithoutDecimals(): void
    {
        $this->assertSame('100', fmt_num(100, 2));
        $this->assertSame('100', fmt_num('100.00', 2));
        $this->assertSame('5', fmt_num(5, 3));
    }

    public function testTrimsTrailingZeros(): void
    {
        $this->assertSame('100,5', fmt_num('100.50', 2));
        $this->assertSame('100,55', fmt_num('100.55', 2));
        $this->assertSame('100,55', fmt_num('100.550', 3));
        $this->assertSame('0,5', fmt_num('0.50', 2));
    }

    public function testCommaInput(): void
    {
        $this->assertSame('100,5', fmt_num('100,50', 2));
        $this->assertSame('200', fmt_num('200,00', 2));
    }

    public function testThreeDecimals(): void
    {
        $this->assertSame('1,5', fmt_num('1.500', 3));
        $this->assertSame('1,555', fmt_num('1.555', 3));
        $this->assertSame('2', fmt_num('2.000', 3));
    }

    public function testOneDecimal(): void
    {
        $this->assertSame('10', fmt_num('10.0', 1));
        $this->assertSame('10,5', fmt_num('10.5', 1));
    }

    public function testNegativeValues(): void
    {
        $this->assertSame('-100,5', fmt_num('-100.50', 2));
        $this->assertSame('-5', fmt_num('-5.00', 2));
    }
}
