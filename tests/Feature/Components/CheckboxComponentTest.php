<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CheckboxComponentTest extends TestCase
{
    public function test_it_renders_basic_checkbox(): void
    {
        $view = Blade::render('<x-checkbox name="terms" />', []);
        $this->assertStringContainsString('type="checkbox"', $view);
        $this->assertStringContainsString('name="terms"', $view);
    }

    public function test_it_renders_with_label(): void
    {
        $view = Blade::render('<x-checkbox name="agree" label="I agree to terms" />', []);
        $this->assertStringContainsString('I agree to terms', $view);
    }

    public function test_it_displays_error(): void
    {
        $view = Blade::render('<x-checkbox name="consent" error="Required" />', []);
        $this->assertStringContainsString('Required', $view);
        $this->assertStringContainsString('text-red-600', $view);
    }

    public function test_it_passes_through_attributes(): void
    {
        $view = Blade::render('<x-checkbox name="subscribe" checked />', []);
        $this->assertStringContainsString('checked', $view);
    }
}
