<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class HomeButtonComponentTest extends TestCase
{
    /** @test */
    public function home_button_links_to_dashboard(): void
    {
        $view = $this->blade('<x-home-button />');

        $view->assertSee('Dashboard');
    }

    /** @test */
    public function home_button_accepts_custom_label(): void
    {
        $view = $this->blade('<x-home-button label="Home" />');

        $view->assertSee('Home');
    }
}
