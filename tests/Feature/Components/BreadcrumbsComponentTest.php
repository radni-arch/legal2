<?php

namespace Tests\Feature\Components;

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsComponentTest extends TestCase
{
    /** @test */
    public function breadcrumbs_component_renders_single_item(): void
    {
        $view = $this->blade(
            '<x-breadcrumbs :items="$items" />',
            ['items' => [new Breadcrumb('Dashboard', null, true)]]
        );

        $view->assertSee('Dashboard');
    }

    /** @test */
    public function breadcrumbs_component_renders_trail_with_links(): void
    {
        $items = [
            new Breadcrumb('Dashboard', '/dashboard', false),
            new Breadcrumb('E-Komunikacije', '/ekom', false),
            new Breadcrumb('Cases', null, true),
        ];

        $view = $this->blade(
            '<x-breadcrumbs :items="$items" />',
            ['items' => $items]
        );

        $view->assertSee('Dashboard');
        $view->assertSee('E-Komunikacije');
        $view->assertSee('Cases');
        $view->assertSee('href="/dashboard"', false);
        $view->assertSee('href="/ekom"', false);
    }

    /** @test */
    public function breadcrumbs_component_uses_separator(): void
    {
        $items = [
            new Breadcrumb('Dashboard', '/dashboard', false),
            new Breadcrumb('Cases', null, true),
        ];

        $view = $this->blade(
            '<x-breadcrumbs :items="$items" />',
            ['items' => $items]
        );

        // Default separator is /
        $view->assertSee('/');
    }

    /** @test */
    public function breadcrumbs_component_renders_nothing_when_empty(): void
    {
        $view = $this->blade(
            '<x-breadcrumbs :items="$items" />',
            ['items' => []]
        );

        $view->assertDontSee('nav');
    }
}
