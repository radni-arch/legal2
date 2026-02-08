<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Spinner Component Tests
 */
class SpinnerComponentTest extends TestCase
{
    /**
     * @test
     */
    public function test_spinner_renders(): void
    {
        $rendered = Blade::render('<x-spinner />');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('animate-spin', $rendered);
    }

    /**
     * @test
     */
    public function test_spinner_applies_custom_classes(): void
    {
        $rendered = Blade::render('<x-spinner class="w-8 h-8" />');

        $this->assertStringContainsString('w-8', $rendered);
        $this->assertStringContainsString('h-8', $rendered);
    }
}
