<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * TDD Tests for Select Blade Component
 */
class SelectComponentTest extends TestCase
{
    public function test_it_renders_basic_select(): void
    {
        $view = Blade::render('<x-select name="country" />', []);

        $this->assertStringContainsString('<select', $view);
        $this->assertStringContainsString('name="country"', $view);
    }

    public function test_it_renders_with_label(): void
    {
        $view = Blade::render('<x-select name="status" label="Status" />', []);

        $this->assertStringContainsString('<label', $view);
        $this->assertStringContainsString('Status', $view);
    }

    public function test_it_shows_required_indicator(): void
    {
        $view = Blade::render('<x-select name="category" label="Category" required />', []);

        $this->assertStringContainsString('<span class="text-red-500">*</span>', $view);
    }

    public function test_it_displays_error_state(): void
    {
        $view = Blade::render('<x-select name="type" error="Selection is required" />', []);

        $this->assertStringContainsString('border-red-500', $view);
        $this->assertStringContainsString('Selection is required', $view);
    }

    public function test_it_displays_hint_text(): void
    {
        $view = Blade::render('<x-select name="priority" hint="Choose priority level" />', []);

        $this->assertStringContainsString('Choose priority level', $view);
    }

    public function test_it_renders_options_from_slot(): void
    {
        $view = Blade::render('<x-select name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </x-select>', []);

        $this->assertStringContainsString('<option value="active">Active</option>', $view);
        $this->assertStringContainsString('<option value="inactive">Inactive</option>', $view);
    }

    public function test_it_passes_through_additional_attributes(): void
    {
        $view = Blade::render('<x-select name="country" disabled />', []);

        $this->assertStringContainsString('disabled', $view);
    }

    public function test_it_applies_focus_ring_styling(): void
    {
        $view = Blade::render('<x-select name="type" />', []);

        $this->assertStringContainsString('focus:ring-2', $view);
        $this->assertStringContainsString('focus:ring-blue-500', $view);
    }
}
