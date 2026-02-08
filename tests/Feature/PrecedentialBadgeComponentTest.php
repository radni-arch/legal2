<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecedentialBadgeComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_binding_badge_renders_with_correct_tooltip_content(): void
    {
        $html = view('livewire.graph.partials.precedential-badge', ['value' => 'binding'])->render();

        // Check badge displays the value
        $this->assertStringContainsString('Binding', $html);

        // Check tooltip content
        $this->assertStringContainsString(
            'This precedent must be followed by lower courts within the same jurisdiction.',
            $html
        );

        // Check ARIA attributes
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-label="Binding precedent"', $html);

        // Check Alpine.js tooltip structure
        $this->assertStringContainsString('x-data', $html);
        $this->assertStringContainsString('showTooltip', $html);
        $this->assertStringContainsString('@mouseenter', $html);
        $this->assertStringContainsString('@mouseleave', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
    }

    public function test_persuasive_badge_renders_with_correct_tooltip_content(): void
    {
        $html = view('livewire.graph.partials.precedential-badge', ['value' => 'persuasive'])->render();

        // Check badge displays the value
        $this->assertStringContainsString('Persuasive', $html);

        // Check tooltip content
        $this->assertStringContainsString(
            'This precedent may be considered but is not legally binding.',
            $html
        );

        // Check ARIA attributes
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-label="Persuasive precedent"', $html);
    }

    public function test_informational_badge_renders_with_correct_tooltip_content(): void
    {
        $html = view('livewire.graph.partials.precedential-badge', ['value' => 'informational'])->render();

        // Check badge displays the value
        $this->assertStringContainsString('Informational', $html);

        // Check tooltip content
        $this->assertStringContainsString(
            'This decision provides context but does not establish legal precedent.',
            $html
        );

        // Check ARIA attributes
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-label="Informational precedent"', $html);
    }

    public function test_badge_has_keyboard_accessibility_attributes(): void
    {
        $html = view('livewire.graph.partials.precedential-badge', ['value' => 'binding'])->render();

        // Check tabindex for keyboard focus
        $this->assertStringContainsString('tabindex="0"', $html);

        // Check focus event for keyboard accessibility
        $this->assertStringContainsString('@focus', $html);
        $this->assertStringContainsString('@blur', $html);
    }

    public function test_badge_uses_correct_css_classes(): void
    {
        $bindingHtml = view('livewire.graph.partials.precedential-badge', ['value' => 'binding'])->render();
        $this->assertStringContainsString('badge-binding', $bindingHtml);

        $persuasiveHtml = view('livewire.graph.partials.precedential-badge', ['value' => 'persuasive'])->render();
        $this->assertStringContainsString('badge-persuasive', $persuasiveHtml);

        $informationalHtml = view('livewire.graph.partials.precedential-badge', ['value' => 'informational'])->render();
        $this->assertStringContainsString('badge-informational', $informationalHtml);
    }
}
