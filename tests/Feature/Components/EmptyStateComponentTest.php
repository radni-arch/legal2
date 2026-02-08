<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EmptyStateComponentTest extends TestCase
{
    public function test_it_renders_with_message(): void
    {
        $view = Blade::render('<x-empty-state message="No data found" />', []);
        $this->assertStringContainsString('No data found', $view);
    }

    public function test_it_renders_with_title_and_description(): void
    {
        $view = Blade::render('<x-empty-state title="No Cases" description="Create your first case" />', []);
        $this->assertStringContainsString('No Cases', $view);
        $this->assertStringContainsString('Create your first case', $view);
    }

    public function test_it_renders_action_button_from_slot(): void
    {
        $view = Blade::render('<x-empty-state message="Empty"><button>Add Item</button></x-empty-state>', []);
        $this->assertStringContainsString('<button>Add Item</button>', $view);
    }

    public function test_it_applies_centered_styling(): void
    {
        $view = Blade::render('<x-empty-state message="Test" />', []);
        $this->assertStringContainsString('text-center', $view);
    }
}
