<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class BackButtonComponentTest extends TestCase
{
    /** @test */
    public function back_button_renders_with_url(): void
    {
        $view = $this->blade(
            '<x-back-button url="/custom-path" label="Go Back" />'
        );

        $view->assertSee('Go Back');
        $view->assertSee('/custom-path', false);
    }

    /** @test */
    public function back_button_renders_with_default_label(): void
    {
        $view = $this->blade(
            '<x-back-button url="/test" />'
        );

        $view->assertSee('Back');
    }
}
