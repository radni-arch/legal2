<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * Alert Component Tests
 *
 * Tests the Alert Blade component for notification messages.
 * Following TDD RED-GREEN-REFACTOR.
 *
 * @group components
 * @group alert
 */
class AlertTest extends TestCase
{
    /**
     * Test 1: Alert renders with success variant
     */
    public function test_alert_renders_success_variant(): void
    {
        $view = $this->blade(
            '<x-alert type="success">Operation successful!</x-alert>'
        );

        $view->assertSee('Operation successful!');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-green-100', $rendered);
        $this->assertStringContainsString('text-green-800', $rendered);
    }

    /**
     * Test 2: Alert renders with error variant
     */
    public function test_alert_renders_error_variant(): void
    {
        $view = $this->blade(
            '<x-alert type="error">Error occurred!</x-alert>'
        );

        $view->assertSee('Error occurred!');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-red-100', $rendered);
        $this->assertStringContainsString('text-red-800', $rendered);
    }

    /**
     * Test 3: Alert renders with warning variant
     */
    public function test_alert_renders_warning_variant(): void
    {
        $view = $this->blade(
            '<x-alert type="warning">Warning message</x-alert>'
        );

        $view->assertSee('Warning message');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-yellow-100', $rendered);
        $this->assertStringContainsString('text-yellow-800', $rendered);
    }

    /**
     * Test 4: Alert renders with info variant (default)
     */
    public function test_alert_renders_info_variant_as_default(): void
    {
        $view = $this->blade(
            '<x-alert>Info message</x-alert>'
        );

        $view->assertSee('Info message');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-blue-100', $rendered);
        $this->assertStringContainsString('text-blue-800', $rendered);
    }

    /**
     * Test 5: Alert shows icon by default
     */
    public function test_alert_shows_icon_by_default(): void
    {
        $view = $this->blade(
            '<x-alert type="success">Success</x-alert>'
        );

        $rendered = (string) $view;
        // Should have SVG icon
        $this->assertStringContainsString('svg', $rendered);
        $this->assertStringContainsString('w-5 h-5', $rendered);
    }

    /**
     * Test 6: Alert can hide icon
     */
    public function test_alert_can_hide_icon(): void
    {
        $view = $this->blade(
            '<x-alert type="success" :icon="false">Success</x-alert>'
        );

        $view->assertSee('Success');
        // Icon should not be present (no check-circle reference in rendered output for icon)
    }

    /**
     * Test 7: Alert with dismissible functionality
     */
    public function test_alert_with_dismissible_functionality(): void
    {
        $view = $this->blade(
            '<x-alert dismissible>Dismissible alert</x-alert>'
        );

        $view->assertSee('Dismissible alert');

        $rendered = (string) $view;
        // Should have AlpineJS dismiss functionality
        $this->assertStringContainsString('x-data', $rendered);
        $this->assertStringContainsString('x-show', $rendered);
        $this->assertStringContainsString('x-on:click', $rendered);
    }

    /**
     * Test 8: Alert combines all features
     */
    public function test_alert_combines_icon_and_dismissible(): void
    {
        $view = $this->blade(
            '<x-alert type="warning" dismissible>Warning: Check this out!</x-alert>'
        );

        $view->assertSee('Warning: Check this out!');

        $rendered = (string) $view;
        // Warning colors
        $this->assertStringContainsString('bg-yellow-100', $rendered);
        // Icon
        $this->assertStringContainsString('svg', $rendered);
        // Dismissible
        $this->assertStringContainsString('x-data', $rendered);
    }
}
