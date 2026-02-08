<?php

declare(strict_types=1);

namespace App\Http\Livewire\Concerns;

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use Illuminate\Support\Facades\Route;

trait WithBreadcrumbs
{
    protected ?string $breadcrumbRoute = null;
    protected ?string $breadcrumbLabel = null;

    /**
     * @return Breadcrumb[]
     */
    public function getBreadcrumbs(): array
    {
        $service = app(BreadcrumbService::class);
        $routeName = $this->breadcrumbRoute ?? $this->getCurrentRouteName();

        if ($routeName === null || !$service->has($routeName)) {
            return [];
        }

        $trail = $service->generate($routeName, request()?->url());

        if ($this->breadcrumbLabel !== null && !empty($trail)) {
            $last = array_pop($trail);
            $trail[] = new Breadcrumb(
                label: $this->breadcrumbLabel,
                url: $last->url,
                isActive: $last->isActive,
            );
        }

        return $trail;
    }

    protected function getCurrentRouteName(): ?string
    {
        return Route::currentRouteName();
    }
}
