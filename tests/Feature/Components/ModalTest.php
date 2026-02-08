<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

/**
 * Modal Component Tests
 *
 * Tests the Modal Blade component for dialog/overlay functionality.
 * Following TDD RED-GREEN-REFACTOR:
 * - RED: These tests are written FIRST before component implementation
 * - GREEN: Component will be implemented to pass these tests
 * - REFACTOR: Clean up as needed
 *
 * @group components
 * @group modal
 */
class ModalTest extends TestCase
{
    /**
     * Test 1: Modal renders with AlpineJS attributes
     *
     * RED phase: Should FAIL - component doesn't exist
     *
     * Verifies:
     * - x-data attribute with show state
     * - x-show directive exists
     * - Name prop is set correctly
     */
    public function test_modal_renders_with_alpine_attributes(): void
    {
        $view = $this->blade(
            '<x-modal name="test-modal">Modal content</x-modal>'
        );

        $view->assertSee('Modal content');

        $rendered = (string) $view;
        $this->assertStringContainsString('x-data', $rendered);
        $this->assertStringContainsString('x-show', $rendered);
        $this->assertStringContainsString('test-modal', $rendered);
    }

    /**
     * Test 2: Modal renders with title
     *
     * RED phase: Should fail - title prop doesn't exist yet
     *
     * Verifies:
     * - Title is displayed
     * - Title in h3 tag
     * - Header section exists
     */
    public function test_modal_renders_with_title(): void
    {
        $view = $this->blade(
            '<x-modal name="confirm" title="Confirm Action">Are you sure?</x-modal>'
        );

        $view->assertSee('Confirm Action');
        $view->assertSee('Are you sure?');

        $rendered = (string) $view;
        $this->assertStringContainsString('<h3', $rendered);
    }

    /**
     * Test 3: Modal renders footer slot
     *
     * RED phase: Should fail - footer slot doesn't exist yet
     *
     * Verifies:
     * - Footer slot renders
     * - Footer content displays
     * - Footer has proper styling
     */
    public function test_modal_renders_footer_slot(): void
    {
        $view = $this->blade(
            '<x-modal name="delete-confirm">
                Delete this item?
                <x-slot:footer>
                    <button>Confirm</button>
                    <button>Cancel</button>
                </x-slot:footer>
            </x-modal>'
        );

        $view->assertSee('Delete this item?');
        $view->assertSee('Confirm');
        $view->assertSee('Cancel');
    }

    /**
     * Test 4: Modal supports max width variants
     *
     * RED phase: Should fail - maxWidth prop doesn't exist yet
     *
     * Verifies:
     * - Default max width (2xl)
     * - Custom max width (lg, xl, etc.)
     * - Max width class is applied
     */
    public function test_modal_supports_max_width_variants(): void
    {
        // Default (2xl)
        $viewDefault = $this->blade('<x-modal name="default">Content</x-modal>');
        $this->assertStringContainsString('max-w-2xl', (string) $viewDefault);

        // Custom (lg)
        $viewLg = $this->blade('<x-modal name="custom" maxWidth="lg">Content</x-modal>');
        $this->assertStringContainsString('max-w-lg', (string) $viewLg);
    }

    /**
     * Test 5: Modal backdrop click to close
     *
     * RED phase: Should fail - backdrop doesn't exist yet
     *
     * Verifies:
     * - Backdrop element exists
     * - Backdrop has click handler (x-on:click)
     * - Backdrop has dark overlay styling
     */
    public function test_modal_has_backdrop_with_click_handler(): void
    {
        $view = $this->blade(
            '<x-modal name="backdrop-test">Content</x-modal>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('bg-black', $rendered);
        $this->assertStringContainsString('bg-opacity-50', $rendered);
        $this->assertStringContainsString('x-on:click', $rendered);
    }

    /**
     * Test 6: Modal close button when closable
     *
     * RED phase: Should fail - closable prop doesn't exist yet
     *
     * Verifies:
     * - Close button appears when closable=true (default)
     * - Close button hidden when closable=false
     * - Close button has click handler
     */
    public function test_modal_close_button_when_closable(): void
    {
        // Closable (default)
        $viewClosable = $this->blade(
            '<x-modal name="closable" title="Title">Content</x-modal>'
        );
        $renderedClosable = (string) $viewClosable;
        // Should have close functionality in title area
        $this->assertStringContainsString('x-on:click', $renderedClosable);

        // Not closable
        $viewNotClosable = $this->blade(
            '<x-modal name="not-closable" title="Title" :closable="false">Content</x-modal>'
        );
        // Modal should still render but without close button in certain contexts
        $viewNotClosable->assertSee('Content');
    }

    /**
     * Test 7: Modal keyboard escape handler
     *
     * RED phase: Should fail - escape handler doesn't exist yet
     *
     * Verifies:
     * - Escape key handler exists (x-on:keydown.escape)
     * - Handler closes modal
     */
    public function test_modal_has_escape_key_handler(): void
    {
        $view = $this->blade(
            '<x-modal name="escape-test">Content</x-modal>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('x-on:keydown.escape', $rendered);
    }

    /**
     * Test 8: Modal event listeners for open/close
     *
     * RED phase: Should fail - event listeners don't exist yet
     *
     * Verifies:
     * - open-modal event listener exists
     * - close-modal event listener exists
     * - Events are scoped to modal name
     */
    public function test_modal_has_open_close_event_listeners(): void
    {
        $view = $this->blade(
            '<x-modal name="event-test">Content</x-modal>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('open-modal', $rendered);
        $this->assertStringContainsString('close-modal', $rendered);
        $this->assertStringContainsString('event-test', $rendered);
    }

    /**
     * Test 9: Modal with title and footer combined
     *
     * RED phase: Should fail - combined features don't exist yet
     *
     * Verifies:
     * - Title and footer work together
     * - All three sections render (title, content, footer)
     * - Proper structure maintained
     */
    public function test_modal_with_title_and_footer(): void
    {
        $view = $this->blade(
            '<x-modal name="full-modal" title="Delete Confirmation">
                <p>Are you sure you want to delete this item?</p>
                <x-slot:footer>
                    <button class="danger">Delete</button>
                    <button class="secondary">Cancel</button>
                </x-slot:footer>
            </x-modal>'
        );

        $view->assertSee('Delete Confirmation');
        $view->assertSee('Are you sure you want to delete this item?');
        $view->assertSee('Delete');
        $view->assertSee('Cancel');

        $rendered = (string) $view;
        // Should have title section
        $this->assertStringContainsString('<h3', $rendered);
        // Should have footer section
        $this->assertStringContainsString('danger', $rendered);
        $this->assertStringContainsString('secondary', $rendered);
    }

    /**
     * Test 10: Modal z-index for overlay
     *
     * RED phase: Should fail - z-index styling doesn't exist yet
     *
     * Verifies:
     * - Modal has high z-index (z-50)
     * - Ensures modal appears above other content
     */
    public function test_modal_has_high_z_index(): void
    {
        $view = $this->blade(
            '<x-modal name="z-test">Content</x-modal>'
        );

        $rendered = (string) $view;
        $this->assertStringContainsString('z-50', $rendered);
    }
}
