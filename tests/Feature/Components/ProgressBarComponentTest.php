<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProgressBarComponentTest extends TestCase
{
    public function test_it_renders_with_percentage(): void
    {
        $view = Blade::render('<x-progress-bar :percentage="50" />', []);
        $this->assertStringContainsString('50%', $view);
    }

    public function test_it_applies_width_based_on_percentage(): void
    {
        $view = Blade::render('<x-progress-bar :percentage="75" />', []);
        $this->assertStringContainsString('width: 75%', $view);
    }

    public function test_it_renders_with_label(): void
    {
        $view = Blade::render('<x-progress-bar :percentage="60" label="Completion" />', []);
        $this->assertStringContainsString('Completion', $view);
    }

    public function test_it_applies_color_variant(): void
    {
        $blueView = Blade::render('<x-progress-bar :percentage="50" color="blue" />', []);
        $greenView = Blade::render('<x-progress-bar :percentage="50" color="green" />', []);

        $this->assertStringContainsString('bg-blue', $blueView);
        $this->assertStringContainsString('bg-green', $greenView);
    }

    public function test_it_caps_percentage_at_100(): void
    {
        $view = Blade::render('<x-progress-bar :percentage="150" />', []);
        $this->assertStringContainsString('width: 100%', $view);
    }
}
