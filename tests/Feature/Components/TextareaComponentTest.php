<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TextareaComponentTest extends TestCase
{
    public function test_it_renders_basic_textarea(): void
    {
        $view = Blade::render('<x-textarea name="description" />', []);
        $this->assertStringContainsString('<textarea', $view);
        $this->assertStringContainsString('name="description"', $view);
    }

    public function test_it_renders_with_label_and_required(): void
    {
        $view = Blade::render('<x-textarea name="notes" label="Notes" required />', []);
        $this->assertStringContainsString('Notes', $view);
        $this->assertStringContainsString('<span class="text-red-500">*</span>', $view);
    }

    public function test_it_displays_error_state(): void
    {
        $view = Blade::render('<x-textarea name="content" error="Field required" />', []);
        $this->assertStringContainsString('border-red-500', $view);
        $this->assertStringContainsString('Field required', $view);
    }

    public function test_it_displays_hint(): void
    {
        $view = Blade::render('<x-textarea name="bio" hint="Max 500 characters" />', []);
        $this->assertStringContainsString('Max 500 characters', $view);
    }

    public function test_it_passes_through_attributes(): void
    {
        $view = Blade::render('<x-textarea name="comment" rows="5" placeholder="Enter text" />', []);
        $this->assertStringContainsString('rows="5"', $view);
        $this->assertStringContainsString('placeholder="Enter text"', $view);
    }
}
