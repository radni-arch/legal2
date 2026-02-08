<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * Tooltip Component Tests
 *
 * Tests the Tooltip Blade component for hover popups.
 * Following TDD RED-GREEN-REFACTOR.
 *
 * @group components
 * @group tooltip
 */
class TooltipTest extends TestCase
{
    /**
     * Test 1: Tooltip renders with trigger content
     */
    public function test_tooltip_renders_with_trigger_content(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Help text">Hover me</x-tooltip>'
        );

        $view->assertSee('Hover me');
    }

    /**
     * Test 2: Tooltip renders with text prop
     */
    public function test_tooltip_renders_with_text_prop(): void
    {
        $view = $this->blade(
            '<x-tooltip text="This is helpful">Button</x-tooltip>'
        );

        $view->assertSee('This is helpful');
        $view->assertSee('Button');
    }

    /**
     * Test 3: Tooltip has AlpineJS show/hide functionality
     */
    public function test_tooltip_has_alpine_show_hide(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Tooltip">Content</x-tooltip>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('x-data', $rendered);
        $this->assertStringContainsString('x-show', $rendered);
        $this->assertStringContainsString('show', $rendered);
    }

    /**
     * Test 4: Tooltip has mouse event handlers
     */
    public function test_tooltip_has_mouse_event_handlers(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Info">Hover</x-tooltip>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('x-on:mouseenter', $rendered);
        $this->assertStringContainsString('x-on:mouseleave', $rendered);
    }

    /**
     * Test 5: Tooltip supports top position (default)
     */
    public function test_tooltip_supports_top_position(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Top tooltip">Content</x-tooltip>'
        );

        $rendered = (string) $view;
        // Should have bottom positioning (tooltip appears above = bottom CSS)
        $this->assertStringContainsString('bottom-full', $rendered);
    }

    /**
     * Test 6: Tooltip supports bottom position
     */
    public function test_tooltip_supports_bottom_position(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Bottom tooltip" position="bottom">Content</x-tooltip>'
        );

        $rendered = (string) $view;
        // Should have top positioning (tooltip appears below = top CSS)
        $this->assertStringContainsString('top-full', $rendered);
    }

    /**
     * Test 7: Tooltip has proper styling
     */
    public function test_tooltip_has_proper_styling(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Styled">Content</x-tooltip>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-gray-900', $rendered);
        $this->assertStringContainsString('text-white', $rendered);
        $this->assertStringContainsString('rounded-lg', $rendered);
    }

    /**
     * Test 8: Tooltip is positioned absolutely
     */
    public function test_tooltip_is_positioned_absolutely(): void
    {
        $view = $this->blade(
            '<x-tooltip text="Test">Content</x-tooltip>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('absolute', $rendered);
        $this->assertStringContainsString('relative', $rendered); // Container
    }
}
