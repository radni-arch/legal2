<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Icon Component Tests
 *
 * Tests the x-icon Blade component following TDD methodology.
 *
 * Test Coverage:
 * 1. Component renders with icon name
 * 2. Component applies custom classes
 * 3. Component renders common icons
 */
class IconComponentTest extends TestCase
{
    /**
     * Test 1: Icon component renders with name
     *
     * @test
     */
    public function test_icon_renders_with_name(): void
    {
        $rendered = Blade::render('<x-icon name="save" />');

        // Should render SVG element
        $this->assertStringContainsString('<svg', $rendered);
    }

    /**
     * Test 2: Icon applies custom classes
     *
     * @test
     */
    public function test_icon_applies_custom_classes(): void
    {
        $rendered = Blade::render('<x-icon name="save" class="w-6 h-6 text-blue-500" />');

        $this->assertStringContainsString('w-6', $rendered);
        $this->assertStringContainsString('h-6', $rendered);
        $this->assertStringContainsString('text-blue-500', $rendered);
    }

    /**
     * Test 3: Icon renders common icons
     *
     * @test
     */
    public function test_icon_renders_common_icons(): void
    {
        $icons = ['save', 'delete', 'edit', 'check', 'x', 'search'];

        foreach ($icons as $icon) {
            $rendered = Blade::render("<x-icon name=\"{$icon}\" />");
            $this->assertStringContainsString('<svg', $rendered, "Icon {$icon} should render SVG");
        }
    }
}
