<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TableComponentTest extends TestCase
{
    public function test_it_renders_basic_table(): void
    {
        $view = Blade::render('<x-table><tr><td>Test</td></tr></x-table>', []);
        $this->assertStringContainsString('<table', $view);
        $this->assertStringContainsString('<tbody', $view);
    }

    public function test_it_renders_with_headers(): void
    {
        $view = Blade::render('<x-table :headers="[\'Name\', \'Email\']" />', []);
        $this->assertStringContainsString('<thead', $view);
        $this->assertStringContainsString('Name', $view);
        $this->assertStringContainsString('Email', $view);
    }

    public function test_it_applies_striped_styling_by_default(): void
    {
        $view = Blade::render('<x-table />', []);
        $this->assertStringContainsString('divide-y', $view);
    }

    public function test_it_applies_overflow_wrapper(): void
    {
        $view = Blade::render('<x-table />', []);
        $this->assertStringContainsString('overflow-x-auto', $view);
    }

    public function test_it_renders_slot_content(): void
    {
        $view = Blade::render('<x-table><tr><td>Row Data</td></tr></x-table>', []);
        $this->assertStringContainsString('Row Data', $view);
    }
}
