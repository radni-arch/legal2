<?php

namespace Tests\Feature\Components;

use App\Services\Navigation\Breadcrumb;
use Tests\TestCase;

class PageHeaderComponentTest extends TestCase
{
    /** @test */
    public function page_header_renders_title(): void
    {
        $view = $this->blade(
            '<x-page-header title="Test Page" />'
        );

        $view->assertSee('Test Page');
    }

    /** @test */
    public function page_header_renders_title_and_breadcrumbs(): void
    {
        $breadcrumbs = [
            new Breadcrumb('Dashboard', '/dashboard', false),
            new Breadcrumb('Current', null, true),
        ];

        $view = $this->blade(
            '<x-page-header title="Test Page" :breadcrumbs="$breadcrumbs" />',
            ['breadcrumbs' => $breadcrumbs]
        );

        $view->assertSee('Test Page');
        $view->assertSee('Dashboard');
        $view->assertSee('Current');
    }

    /** @test */
    public function page_header_renders_subtitle(): void
    {
        $view = $this->blade(
            '<x-page-header title="Test Page" subtitle="A description" />'
        );

        $view->assertSee('Test Page');
        $view->assertSee('A description');
    }

    /** @test */
    public function page_header_renders_actions_slot(): void
    {
        $view = $this->blade(
            '<x-page-header title="Test">
                <x-slot:actions>
                    <button>Action</button>
                </x-slot:actions>
            </x-page-header>'
        );

        $view->assertSee('Action');
    }

    /** @test */
    public function page_header_auto_generates_breadcrumbs_from_route(): void
    {
        $view = $this->blade(
            '<x-page-header title="Cases" route-name="ekom.predmeti" />'
        );

        $view->assertSee('Cases');
        $view->assertSee('Dashboard');
        $view->assertSee('E-Komunikacije');
    }
}
