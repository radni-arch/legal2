<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PaginationComponentTest extends TestCase
{
    public function test_it_renders_basic_pagination(): void
    {
        $view = Blade::render('<x-pagination :current="1" :total="5" />', []);
        $this->assertStringContainsString('1', $view);
        $this->assertStringContainsString('5', $view);
    }

    public function test_it_renders_previous_and_next_buttons(): void
    {
        $view = Blade::render('<x-pagination :current="2" :total="5" />', []);
        $this->assertStringContainsString('Previous', $view);
        $this->assertStringContainsString('Next', $view);
    }

    public function test_it_disables_previous_on_first_page(): void
    {
        $view = Blade::render('<x-pagination :current="1" :total="5" />', []);
        // Should have disabled previous button styling
        $this->assertMatchesRegularExpression('/Previous.*disabled|cursor-not-allowed/s', $view);
    }

    public function test_it_disables_next_on_last_page(): void
    {
        $view = Blade::render('<x-pagination :current="5" :total="5" />', []);
        // Should have disabled next button styling
        $this->assertMatchesRegularExpression('/Next.*disabled|cursor-not-allowed/s', $view);
    }
}
