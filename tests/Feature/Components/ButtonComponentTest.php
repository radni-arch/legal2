<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Button Component Tests
 *
 * Tests the x-button Blade component following TDD methodology.
 *
 * Test Coverage:
 * 1. Component renders with default props
 * 2. Component renders all variants
 * 3. Component renders all sizes
 * 4. Component shows loading state
 * 5. Component handles disabled state
 * 6. Component renders with icons
 */
class ButtonComponentTest extends TestCase
{
    /**
     * Test 1: Button component renders with default props
     *
     * @test
     */
    public function test_button_renders_with_defaults(): void
    {
        $rendered = Blade::render('<x-button>Click Me</x-button>');

        // Should render button element
        $this->assertStringContainsString('<button', $rendered);
        $this->assertStringContainsString('Click Me', $rendered);

        // Should have default variant (primary)
        $this->assertStringContainsString('bg-blue-600', $rendered);

        // Should have default size (md)
        $this->assertStringContainsString('px-4', $rendered);
        $this->assertStringContainsString('py-2', $rendered);
    }

    /**
     * Test 2: Button renders with all variants
     *
     * @test
     */
    public function test_button_renders_all_variants(): void
    {
        // Primary variant
        $primary = Blade::render('<x-button variant="primary">Primary</x-button>');
        $this->assertStringContainsString('bg-blue-600', $primary);

        // Secondary variant
        $secondary = Blade::render('<x-button variant="secondary">Secondary</x-button>');
        $this->assertStringContainsString('bg-gray-600', $secondary);

        // Danger variant
        $danger = Blade::render('<x-button variant="danger">Danger</x-button>');
        $this->assertStringContainsString('bg-red-600', $danger);

        // Success variant
        $success = Blade::render('<x-button variant="success">Success</x-button>');
        $this->assertStringContainsString('bg-green-600', $success);

        // Outline variant
        $outline = Blade::render('<x-button variant="outline">Outline</x-button>');
        $this->assertStringContainsString('border-2', $outline);
    }

    /**
     * Test 3: Button renders with all sizes
     *
     * @test
     */
    public function test_button_renders_all_sizes(): void
    {
        // Small size
        $small = Blade::render('<x-button size="sm">Small</x-button>');
        $this->assertStringContainsString('px-3', $small);
        $this->assertStringContainsString('py-1.5', $small);

        // Medium size (default)
        $medium = Blade::render('<x-button size="md">Medium</x-button>');
        $this->assertStringContainsString('px-4', $medium);
        $this->assertStringContainsString('py-2', $medium);

        // Large size
        $large = Blade::render('<x-button size="lg">Large</x-button>');
        $this->assertStringContainsString('px-6', $large);
        $this->assertStringContainsString('py-3', $large);
    }

    /**
     * Test 4: Button shows loading state
     *
     * @test
     */
    public function test_button_shows_loading_state(): void
    {
        $rendered = Blade::render('<x-button :loading="true">Processing</x-button>');

        // Should have disabled attribute when loading
        $this->assertStringContainsString('disabled', $rendered);

        // Should have opacity class when loading
        $this->assertStringContainsString('opacity-50', $rendered);
    }

    /**
     * Test 5: Button handles disabled state
     *
     * @test
     */
    public function test_button_handles_disabled_state(): void
    {
        $rendered = Blade::render('<x-button :disabled="true">Disabled</x-button>');

        // Should have disabled attribute
        $this->assertStringContainsString('disabled', $rendered);

        // Should have opacity and cursor classes
        $this->assertStringContainsString('opacity-50', $rendered);
        $this->assertStringContainsString('cursor-not-allowed', $rendered);
    }

    /**
     * Test 6: Button renders slot content
     *
     * @test
     */
    public function test_button_renders_slot_content(): void
    {
        $rendered = Blade::render('<x-button>Generate Motion</x-button>');

        $this->assertStringContainsString('Generate Motion', $rendered);
    }
}
