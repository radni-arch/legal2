<?php

namespace Tests\Unit\Livewire\Concerns;

use App\Http\Livewire\Concerns\WithBreadcrumbs;
use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use Tests\TestCase;

class WithBreadcrumbsTest extends TestCase
{
    private function makeComponent(?string $route = null, ?string $label = null): object
    {
        return new class($route, $label) {
            use WithBreadcrumbs;

            public function __construct(?string $route, ?string $label)
            {
                $this->breadcrumbRoute = $route;
                $this->breadcrumbLabel = $label;
            }
        };
    }

    /** @test */
    public function trait_generates_breadcrumbs_from_route_name(): void
    {
        $component = $this->makeComponent('ekom.predmeti');

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertNotEmpty($breadcrumbs);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $breadcrumbs);
        $this->assertEquals('Cases (Predmeti)', end($breadcrumbs)->label);
    }

    /** @test */
    public function trait_returns_empty_for_unknown_route(): void
    {
        $component = $this->makeComponent('unknown.route');

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertEmpty($breadcrumbs);
    }

    /** @test */
    public function trait_supports_custom_label(): void
    {
        $component = $this->makeComponent('ekom.predmeti.show', 'Case #12345');

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertNotEmpty($breadcrumbs);
        $lastCrumb = end($breadcrumbs);
        $this->assertEquals('Case #12345', $lastCrumb->label);
    }
}
