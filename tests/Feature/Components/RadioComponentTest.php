<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class RadioComponentTest extends TestCase
{
    public function test_it_renders_basic_radio(): void
    {
        $view = Blade::render('<x-radio name="gender" value="male" />', []);
        $this->assertStringContainsString('type="radio"', $view);
        $this->assertStringContainsString('name="gender"', $view);
        $this->assertStringContainsString('value="male"', $view);
    }

    public function test_it_renders_with_label(): void
    {
        $view = Blade::render('<x-radio name="role" value="admin" label="Administrator" />', []);
        $this->assertStringContainsString('Administrator', $view);
    }

    public function test_it_displays_error(): void
    {
        $view = Blade::render('<x-radio name="choice" value="a" error="Select an option" />', []);
        $this->assertStringContainsString('Select an option', $view);
    }

    public function test_it_passes_through_attributes(): void
    {
        $view = Blade::render('<x-radio name="option" value="yes" checked />', []);
        $this->assertStringContainsString('checked', $view);
    }
}
