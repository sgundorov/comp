<?php
use PHPUnit\Framework\TestCase;

class ControlsTest extends TestCase
{
    public function testRenderInputText(): void
    {
        $html = render_input('text', 'name', 'John');
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('value="John"', $html);
    }

    public function testRenderInputHidden(): void
    {
        $html = render_input('hidden', 'id', '5');
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('value="5"', $html);
    }

    public function testRenderInputEmptyValueNoValueAttr(): void
    {
        $html = render_input('text', 'name', '');
        $this->assertStringNotContainsString('value=', $html);
    }

    public function testRenderInputWithAttrs(): void
    {
        $html = render_input('text', 'field', 'val', ['class' => 'my-class', 'id' => 'f1', 'readonly' => true]);
        $this->assertStringContainsString('class="my-class"', $html);
        $this->assertStringContainsString('id="f1"', $html);
        $this->assertStringContainsString('readonly', $html);
    }

    public function testRenderTextarea(): void
    {
        $html = render_textarea('note', 'text content');
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="note"', $html);
        $this->assertStringContainsString('text content', $html);
    }

    public function testRenderBtnText(): void
    {
        $html = render_btn_text('Click me');
        $this->assertStringContainsString('class="btn"', $html);
        $this->assertStringContainsString('Click me', $html);
    }

    public function testRenderBtnPrimary(): void
    {
        $html = render_btn_primary('img/save.png', 'Save');
        $this->assertStringContainsString('class="btn-primary', $html);
        $this->assertStringContainsString('img/save.png', $html);
        $this->assertStringContainsString('Save', $html);
    }

    public function testRenderBtnDanger(): void
    {
        $html = render_btn_danger('img/delete.png', 'Delete', ['id' => 'delBtn']);
        $this->assertStringContainsString('class="btn-danger', $html);
        $this->assertStringContainsString('id="delBtn"', $html);
    }
}
