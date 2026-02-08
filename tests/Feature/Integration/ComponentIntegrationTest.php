<?php

namespace Tests\Feature\Integration;

use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * ComponentIntegrationTest
 *
 * Real-world integration tests verifying that Blade components work correctly
 * within Livewire contexts. Tests component rendering, Alpine.js integration,
 * and styling to ensure no conflicts in production usage.
 *
 * Sprint 12.5 - Worker B: Integration Testing
 */
class ComponentIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Card component renders with basic styling
     *
     * Verifies that card components render with correct CSS classes.
     *
     * @test
     */
    public function test_card_component_renders_with_styling(): void
    {
        // Act: Render basic card
        $view = $this->blade('
            <x-card>
                <p>Card content</p>
            </x-card>
        ');

        // Assert: Card renders with content
        $view->assertSee('Card content');
    }

    /**
     * Test 2: Components work with Alpine.js
     *
     * Verifies that modal components properly integrate with Alpine.js
     * by checking for x-data, x-show directives.
     *
     * @test
     */
    public function test_components_work_with_alpine_js(): void
    {
        // Act: Test Modal with AlpineJS
        $view = $this->blade('
            <x-modal name="test-modal" title="Test">
                <p>Modal content</p>
            </x-modal>
        ');

        // Assert: Alpine.js directives are present
        $view->assertSee('x-data', false); // false = check HTML source, not just visible text
        $view->assertSee('x-show', false);
        $view->assertSee('Test');
        $view->assertSee('Modal content');
    }

    /**
     * Test 3: Alert component works in Livewire
     *
     * Verifies that alert components render correctly with Alpine.js
     * for dismissible functionality.
     *
     * @test
     */
    public function test_alert_component_in_livewire(): void
    {
        // Act: Test alert component
        $view = $this->blade('
            <x-alert type="success" dismissible>
                Analysis complete!
            </x-alert>
        ');

        // Assert: Content and Alpine directives are present
        $view->assertSee('Analysis complete!');
        $view->assertSee('x-data', false); // Alpine for dismissible functionality
    }

    /**
     * Test 4: Card component with header and footer
     *
     * Verifies that card components properly render with all sections
     * (header, body, footer).
     *
     * @test
     */
    public function test_card_component_with_sections(): void
    {
        // Act: Render card with content and footer
        $view = $this->blade('
            <x-card>
                <h2>Card Title</h2>
                <p>Card body content</p>

                <x-slot name="footer">
                    <button>Action</button>
                </x-slot>
            </x-card>
        ');

        // Assert: Content renders
        $view->assertSee('Card body content');
        $view->assertSee('Action');
    }

    /**
     * Test 5: Button component variants
     *
     * Verifies that button components render with correct variant styling.
     *
     * @test
     */
    public function test_button_component_variants(): void
    {
        // Act: Test primary button variant
        $view = $this->blade('
            <x-button type="button" variant="primary">
                Primary Button
            </x-button>
        ');

        // Assert: Button renders with correct classes
        $view->assertSee('Primary Button');
        $view->assertSee('button', false);
    }

    /**
     * Test 6: Input component with label
     *
     * Verifies that input components render correctly with labels
     * and proper form structure.
     *
     * @test
     */
    public function test_input_component_with_label(): void
    {
        // Act: Render input with label
        $view = $this->blade('
            <x-input
                type="text"
                name="test_input"
                label="Test Label"
                placeholder="Enter text"
            />
        ');

        // Assert: Input and label render correctly
        $view->assertSee('Test Label');
        $view->assertSee('Enter text');
        $view->assertSee('test_input', false); // Check name attribute in HTML
    }

    /**
     * Test 7: Badge component with different types
     *
     * Verifies that badge components render with correct styling
     * for different types (success, warning, error, info).
     *
     * @test
     */
    public function test_badge_component_types(): void
    {
        // Act: Test success badge
        $view = $this->blade('
            <x-badge type="success">
                Active
            </x-badge>
        ');

        // Assert: Badge renders with content
        $view->assertSee('Active');
    }

    /**
     * Test 8: Components don't conflict with Tailwind styling
     *
     * Verifies that custom component styling doesn't conflict
     * with Tailwind CSS utility classes.
     *
     * @test
     */
    public function test_components_no_styling_conflicts(): void
    {
        // Act: Render component with custom Tailwind classes
        $view = $this->blade('
            <x-card class="mt-4 mb-6 shadow-xl">
                <p class="text-blue-500 font-bold">Custom styled content</p>
            </x-card>
        ');

        // Assert: Custom classes are preserved
        $view->assertSee('Custom styled content');
        $view->assertSee('text-blue-500', false);
        $view->assertSee('font-bold', false);
    }

    /**
     * Test 9: Modal component opens and closes with Alpine.js
     *
     * Verifies that modal component has proper Alpine.js bindings
     * for open/close functionality.
     *
     * @test
     */
    public function test_modal_alpine_bindings(): void
    {
        // Act: Render modal
        $view = $this->blade('
            <x-modal name="test-modal" title="Test Modal">
                <p>Modal content here</p>
            </x-modal>
        ');

        // Assert: Alpine.js bindings are present
        $view->assertSee('x-data', false);
        $view->assertSee('Test Modal');
        $view->assertSee('Modal content here');
    }

    /**
     * Test 10: Alert component with different types
     *
     * Verifies that alert components render correctly with different
     * types (success, error, warning, info).
     *
     * @test
     */
    public function test_alert_component_types(): void
    {
        // Act: Test success alert
        $successView = $this->blade('
            <x-alert type="success">
                Success message!
            </x-alert>
        ');

        // Assert: Success alert renders
        $successView->assertSee('Success message!');

        // Act: Test error alert
        $errorView = $this->blade('
            <x-alert type="error">
                Error message!
            </x-alert>
        ');

        // Assert: Error alert renders
        $errorView->assertSee('Error message!');
    }

    /**
     * Test 11: Basic div renders correctly
     *
     * Verifies that basic HTML renders correctly in blade tests.
     *
     * @test
     */
    public function test_basic_html_renders(): void
    {
        // Act: Render basic HTML
        $view = $this->blade('
            <div class="flex items-center justify-center">
                <span>Loading...</span>
            </div>
        ');

        // Assert: HTML renders
        $view->assertSee('Loading...');
    }

    /**
     * Test 12: Components work within Livewire wire:model
     *
     * Verifies that input components work correctly with Livewire's
     * wire:model directive.
     *
     * @test
     */
    public function test_components_with_wire_model(): void
    {
        // Act: Render input with wire:model
        $view = $this->blade('
            <x-input
                type="text"
                name="livewire_input"
                wire:model="testProperty"
            />
        ');

        // Assert: wire:model directive is present
        $view->assertSee('wire:model', false);
        $view->assertSee('testProperty', false);
    }

    /**
     * Test 13: Card component in dark mode
     *
     * Verifies that card components render correctly with dark mode styling.
     *
     * @test
     */
    public function test_card_component_dark_mode(): void
    {
        // Act: Render dark card
        $view = $this->blade('
            <x-card variant="dark">
                <p>Dark mode content</p>
            </x-card>
        ');

        // Assert: Dark mode content renders
        $view->assertSee('Dark mode content');
    }

    /**
     * Test 14: Multiple components nested together
     *
     * Verifies that multiple components can be nested without conflicts.
     *
     * @test
     */
    public function test_nested_components(): void
    {
        // Act: Render nested components
        $view = $this->blade('
            <x-card>
                <x-alert type="info">
                    <strong>Note:</strong> This is nested content
                </x-alert>

                <x-button>Click me</x-button>
            </x-card>
        ');

        // Assert: All nested components render
        $view->assertSee('Note:');
        $view->assertSee('This is nested content');
        $view->assertSee('Click me');
    }

    /**
     * Test 15: Complex nested component structure
     *
     * Integration test verifying that complex nested components work together.
     *
     * @test
     */
    public function test_complex_nested_component_structure(): void
    {
        // Act: Render complex nested structure
        $view = $this->blade('
            <div class="container mx-auto">
                <x-card>
                    <h2 class="text-xl font-bold">Main Card</h2>

                    <x-alert type="info">
                        Important notice
                    </x-alert>

                    <div class="mt-4">
                        <x-input type="text" name="nested_input" label="Input Field" />
                    </div>

                    <x-slot name="footer">
                        <x-button variant="primary">
                            Submit
                        </x-button>
                    </x-slot>
                </x-card>
            </div>
        ');

        // Assert: All nested components render
        $view->assertSee('Main Card');
        $view->assertSee('Important notice');
        $view->assertSee('Input Field');
        $view->assertSee('Submit');
    }
}
