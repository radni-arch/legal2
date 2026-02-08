<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * Badge Component Tests
 *
 * Tests the Badge Blade component for inline status indicators.
 * Following TDD RED-GREEN-REFACTOR.
 *
 * @group components
 * @group badge
 */
class BadgeTest extends TestCase
{
    /**
     * Test 1: Badge renders with default variant
     */
    public function test_badge_renders_with_default_variant(): void
    {
        $view = $this->blade(
            '<x-badge>Default</x-badge>'
        );

        $view->assertSee('Default');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-gray-100', $rendered);
        $this->assertStringContainsString('text-gray-800', $rendered);
    }

    /**
     * Test 2: Badge renders with success variant
     */
    public function test_badge_renders_with_success_variant(): void
    {
        $view = $this->blade(
            '<x-badge variant="success">Active</x-badge>'
        );

        $view->assertSee('Active');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-green-100', $rendered);
        $this->assertStringContainsString('text-green-800', $rendered);
    }

    /**
     * Test 3: Badge renders with error variant
     */
    public function test_badge_renders_with_error_variant(): void
    {
        $view = $this->blade(
            '<x-badge variant="error">Failed</x-badge>'
        );

        $view->assertSee('Failed');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-red-100', $rendered);
        $this->assertStringContainsString('text-red-800', $rendered);
    }

    /**
     * Test 4: Badge supports size variants
     */
    public function test_badge_supports_size_variants(): void
    {
        // Small
        $viewSm = $this->blade('<x-badge size="sm">Small</x-badge>');
        $this->assertStringContainsString('text-xs', (string) $viewSm);

        // Medium (default)
        $viewMd = $this->blade('<x-badge>Medium</x-badge>');
        $this->assertStringContainsString('text-sm', (string) $viewMd);

        // Large
        $viewLg = $this->blade('<x-badge size="lg">Large</x-badge>');
        $this->assertStringContainsString('text-base', (string) $viewLg);
    }

    /**
     * Test 5: Badge has rounded pill shape
     */
    public function test_badge_has_rounded_shape(): void
    {
        $view = $this->blade(
            '<x-badge>Badge</x-badge>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('rounded-full', $rendered);
    }

    /**
     * Test 6: Badge is inline element
     */
    public function test_badge_is_inline_element(): void
    {
        $view = $this->blade(
            '<x-badge>Inline</x-badge>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('inline-flex', $rendered);
    }
}
