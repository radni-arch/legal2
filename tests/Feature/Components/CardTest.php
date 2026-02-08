<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * Card Component Tests
 *
 * Tests the Card Blade component for various rendering scenarios.
 * Following TDD RED-GREEN-REFACTOR:
 * - RED: These tests are written FIRST before component implementation
 * - GREEN: Component will be implemented to pass these tests
 * - REFACTOR: Clean up as needed
 *
 * @group components
 * @group card
 */
class CardTest extends TestCase
{
    /**
     * Test 1: Card renders basic content
     *
     * RED phase: This test should FAIL because component doesn't exist yet
     *
     * Verifies:
     * - Component renders without errors
     * - Slot content is displayed
     * - Default classes are applied
     */
    public function test_card_renders_basic_content(): void
    {
        $view = $this->blade(
            '<x-card>Test content here</x-card>'
        );

        $view->assertSee('Test content here');
        // Should have default white background and shadow
        $rendered = (string) $view;
        $this->assertStringContainsString('bg-white', $rendered);
        $this->assertStringContainsString('shadow', $rendered);
    }

    /**
     * Test 2: Card renders with title
     *
     * RED phase: Should fail - title prop doesn't exist yet
     *
     * Verifies:
     * - Title prop is rendered
     * - Title has proper styling
     * - Border separator exists between title and content
     */
    public function test_card_renders_with_title(): void
    {
        $view = $this->blade(
            '<x-card title="Evidence Analysis">Card content</x-card>'
        );

        $view->assertSee('Evidence Analysis');
        $view->assertSee('Card content');

        $rendered = (string) $view;
        // Title should be in an h3 tag
        $this->assertStringContainsString('<h3', $rendered);
        // Should have border separator
        $this->assertStringContainsString('border-b', $rendered);
    }

    /**
     * Test 3: Card renders with footer
     *
     * RED phase: Should fail - footer slot doesn't exist yet
     *
     * Verifies:
     * - Footer slot content renders
     * - Footer has top border separator
     * - Footer is separated from main content
     */
    public function test_card_renders_with_footer(): void
    {
        $view = $this->blade(
            '<x-card>
                Main content
                <x-slot:footer>
                    <button>Action Button</button>
                </x-slot:footer>
            </x-card>'
        );

        $view->assertSee('Main content');
        $view->assertSee('Action Button');

        $rendered = (string) $view;
        // Footer should have top border
        $this->assertStringContainsString('border-t', $rendered);
    }

    /**
     * Test 4: Card supports dark variant
     *
     * RED phase: Should fail - variant prop doesn't exist yet
     *
     * Verifies:
     * - Dark variant applies bg-gray-800
     * - Dark variant applies text-white
     * - Dark variant changes border colors
     */
    public function test_card_supports_dark_variant(): void
    {
        $view = $this->blade(
            '<x-card variant="dark">Dark card content</x-card>'
        );

        $view->assertSee('Dark card content');

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-gray-800', $rendered);
        $this->assertStringContainsString('text-white', $rendered);
    }

    /**
     * Test 5: Card supports bordered variant
     *
     * RED phase: Should fail - bordered variant doesn't exist yet
     *
     * Verifies:
     * - Bordered variant has border-2
     * - Bordered variant has white background
     * - Border color is gray-200
     */
    public function test_card_supports_bordered_variant(): void
    {
        $view = $this->blade(
            '<x-card variant="bordered">Bordered card</x-card>'
        );

        $view->assertSee('Bordered card');

        $rendered = (string) $view;
        $this->assertStringContainsString('border-2', $rendered);
        $this->assertStringContainsString('border-gray-200', $rendered);
        $this->assertStringContainsString('bg-white', $rendered);
    }

    /**
     * Test 6: Card supports custom padding
     *
     * RED phase: Should fail - padding prop doesn't exist yet
     *
     * Verifies:
     * - Custom padding can be specified
     * - Default padding is p-4
     * - Custom padding overrides default
     */
    public function test_card_supports_custom_padding(): void
    {
        // Test default padding
        $viewDefault = $this->blade('<x-card>Content</x-card>');
        $this->assertStringContainsString('p-4', (string) $viewDefault);

        // Test custom padding
        $viewCustom = $this->blade('<x-card padding="p-6">Content</x-card>');
        $this->assertStringContainsString('p-6', (string) $viewCustom);
    }

    /**
     * Test 7: Card merges custom attributes
     *
     * RED phase: Should fail - attribute merging doesn't exist yet
     *
     * Verifies:
     * - Custom classes can be added
     * - Custom attributes are merged properly
     * - ID and other attributes work
     */
    public function test_card_merges_custom_attributes(): void
    {
        $view = $this->blade(
            '<x-card id="custom-card" class="custom-class">Content</x-card>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('id="custom-card"', $rendered);
        $this->assertStringContainsString('custom-class', $rendered);
    }

    /**
     * Test 8: Card with title and footer together
     *
     * RED phase: Should fail - combined title+footer doesn't exist yet
     *
     * Verifies:
     * - Title and footer can be used together
     * - All three sections (title, content, footer) render
     * - Borders separate all sections
     */
    public function test_card_with_title_and_footer(): void
    {
        $view = $this->blade(
            '<x-card title="Analysis Results" variant="dark">
                <p>Main content here</p>
                <x-slot:footer>
                    <button>Generate Motion</button>
                </x-slot:footer>
            </x-card>'
        );

        $view->assertSee('Analysis Results');
        $view->assertSee('Main content here');
        $view->assertSee('Generate Motion');

        $rendered = (string) $view;
        // Should have both title bottom border and footer top border
        $this->assertStringContainsString('border-b', $rendered);
        $this->assertStringContainsString('border-t', $rendered);
    }
}
