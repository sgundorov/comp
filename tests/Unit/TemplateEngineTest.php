<?php
use PHPUnit\Framework\TestCase;

class TemplateEngineTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../_fixtures';
        if (!is_dir($this->fixturesDir)) {
            mkdir($this->fixturesDir, 0777, true);
        }
    }

    public function testReplaceVars(): void
    {
        $html = '<p>#Name#</p>';
        $result = replace_vars($html, ['Name' => 'John']);
        $this->assertSame('<p>John</p>', $result);
    }

    public function testReplaceMultipleVars(): void
    {
        $html = '#Greeting#, #Name#!';
        $result = replace_vars($html, ['Greeting' => 'Hello', 'Name' => 'World']);
        $this->assertSame('Hello, World!', $result);
    }

    public function testReplaceVarNotFound(): void
    {
        $html = '#Missing#';
        $result = replace_vars($html, ['Other' => 'val']);
        $this->assertSame('#Missing#', $result);
    }

    public function testProcessDetailBlocks(): void
    {
        $html = 'before#D1<tr><td>#Item#</td></tr>#1Dafter';
        $details = ['D1' => [['Item' => 'A'], ['Item' => 'B']]];
        $result = process_detail_blocks($html, $details);
        $this->assertStringContainsString('<tr><td>A</td></tr>', $result);
        $this->assertStringContainsString('<tr><td>B</td></tr>', $result);
        $this->assertStringContainsString('before', $result);
        $this->assertStringContainsString('after', $result);
    }

    public function testProcessDetailBlocksEmpty(): void
    {
        $html = 'before#D1#Item##1Dafter';
        $details = ['D1' => []];
        $result = process_detail_blocks($html, $details);
        $this->assertSame('beforeafter', $result);
    }

    public function testRenderTemplate(): void
    {
        $tpl = $this->fixturesDir . '/test_template.html';
        file_put_contents($tpl, '<h1>#Title#</h1>#D1<p>#Row#</p>#1D');
        $result = render_template($tpl, ['Title' => 'Test'], ['D1' => [['Row' => '1'], ['Row' => '2']]]);
        $this->assertStringContainsString('<h1>Test</h1>', $result);
        $this->assertStringContainsString('<p>1</p>', $result);
        $this->assertStringContainsString('<p>2</p>', $result);
        unlink($tpl);
    }

    public function testRenderTemplateFileNotFound(): void
    {
        $result = render_template('/nonexistent/path.html');
        $this->assertStringContainsString('Шаблон не найден', $result);
    }
}
