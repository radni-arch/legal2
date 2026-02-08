<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StatCardComponentTest extends TestCase
{
    public function test_it_renders_with_label_and_value(): void
    {
        $view = Blade::render('<x-stat-card label="Total Cases" value="42" />', []);
        $this->assertStringContainsString('Total Cases', $view);
        $this->assertStringContainsString('42', $view);
    }

    public function test_it_renders_with_change_indicator(): void
    {
        $view = Blade::render('<x-stat-card label="Cases" value="100" change="+12%" />', []);
        $this->assertStringContainsString('+12%', $view);
    }

    public function test_it_renders_with_trend_up(): void
    {
        $view = Blade::render('<x-stat-card label="Cases" value="100" change="+15%" trend="up" />', []);
        $this->assertStringContainsString('text-green', $view);
    }

    public function test_it_renders_with_trend_down(): void
    {
        $view = Blade::render('<x-stat-card label="Cases" value="100" change="-8%" trend="down" />', []);
        $this->assertStringContainsString('text-red', $view);
    }

    public function test_it_applies_card_styling(): void
    {
        $view = Blade::render('<x-stat-card label="Test" value="1" />', []);
        $this->assertStringContainsString('bg-white', $view);
        $this->assertStringContainsString('rounded', $view);
    }
}
