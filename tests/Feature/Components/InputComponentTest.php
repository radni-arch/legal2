<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * TDD Tests for Input Blade Component
 *
 * Following RED-GREEN-REFACTOR cycle:
 * 1. Write failing tests (RED)
 * 2. Implement component (GREEN)
 * 3. Refactor if needed
 */
class InputComponentTest extends TestCase
{
    /**
     * Test 1: Input component renders with basic attributes
     */
    public function test_it_renders_basic_input(): void
    {
        $view = Blade::render('<x-input name="test_field" />', []);

        $this->assertStringContainsString('type="text"', $view);
        $this->assertStringContainsString('name="test_field"', $view);
    }

    /**
     * Test 2: Input component renders with label
     */
    public function test_it_renders_with_label(): void
    {
        $view = Blade::render('<x-input name="email" label="Email Address" />', []);

        $this->assertStringContainsString('<label', $view);
        $this->assertStringContainsString('Email Address', $view);
    }

    /**
     * Test 3: Input component shows required asterisk when required=true
     */
    public function test_it_shows_required_indicator(): void
    {
        $view = Blade::render('<x-input name="email" label="Email" required />', []);

        $this->assertStringContainsString('<span class="text-red-500">*</span>', $view);
    }

    /**
     * Test 4: Input component displays error message and error styling
     */
    public function test_it_displays_error_state(): void
    {
        $view = Blade::render('<x-input name="email" error="Email is required" />', []);

        $this->assertStringContainsString('border-red-500', $view);
        $this->assertStringContainsString('Email is required', $view);
        $this->assertStringContainsString('text-red-600', $view);
    }

    /**
     * Test 5: Input component displays hint text
     */
    public function test_it_displays_hint_text(): void
    {
        $view = Blade::render('<x-input name="password" hint="Must be at least 8 characters" />', []);

        $this->assertStringContainsString('Must be at least 8 characters', $view);
        $this->assertStringContainsString('text-gray-500', $view);
    }

    /**
     * Test 6: Input component uses correct border color without error
     */
    public function test_it_uses_normal_border_without_error(): void
    {
        $view = Blade::render('<x-input name="username" />', []);

        $this->assertStringContainsString('border-gray-300', $view);
        $this->assertStringNotContainsString('border-red-500', $view);
    }

    /**
     * Test 7: Input component supports different input types
     */
    public function test_it_supports_different_input_types(): void
    {
        $emailView = Blade::render('<x-input name="email" type="email" />', []);
        $passwordView = Blade::render('<x-input name="password" type="password" />', []);
        $numberView = Blade::render('<x-input name="age" type="number" />', []);

        $this->assertStringContainsString('type="email"', $emailView);
        $this->assertStringContainsString('type="password"', $passwordView);
        $this->assertStringContainsString('type="number"', $numberView);
    }

    /**
     * Test 8: Input component passes through additional attributes
     */
    public function test_it_passes_through_additional_attributes(): void
    {
        $view = Blade::render('<x-input name="username" placeholder="Enter username" maxlength="50" />', []);

        $this->assertStringContainsString('placeholder="Enter username"', $view);
        $this->assertStringContainsString('maxlength="50"', $view);
    }

    /**
     * Test 9: Input component applies focus ring styling
     */
    public function test_it_applies_focus_ring_styling(): void
    {
        $view = Blade::render('<x-input name="search" />', []);

        $this->assertStringContainsString('focus:ring-2', $view);
        $this->assertStringContainsString('focus:ring-blue-500', $view);
        $this->assertStringContainsString('focus:border-blue-500', $view);
    }

    /**
     * Test 10: Input component renders with all features combined
     */
    public function test_it_renders_with_all_features(): void
    {
        $view = Blade::render(
            '<x-input
                name="evidence_text"
                label="Evidence Text"
                type="text"
                required
                error="This field is required"
                hint="Enter the evidence text to analyze"
                placeholder="Type here..."
            />',
            []
        );

        $this->assertStringContainsString('Evidence Text', $view);
        $this->assertStringContainsString('<span class="text-red-500">*</span>', $view);
        $this->assertStringContainsString('border-red-500', $view);
        $this->assertStringContainsString('This field is required', $view);
        $this->assertStringContainsString('Enter the evidence text to analyze', $view);
        $this->assertStringContainsString('placeholder="Type here..."', $view);
    }
}
