<?php

declare(strict_types=1);

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;

if (!function_exists('breadcrumbs')) {
    /**
     * @return Breadcrumb[]
     */
    function breadcrumbs(string $routeName, ?string $currentUrl = null): array
    {
        $service = app(BreadcrumbService::class);

        if (!$service->has($routeName)) {
            return [];
        }

        return $service->generate($routeName, $currentUrl);
    }
}

if (!function_exists('current_breadcrumbs')) {
    /**
     * @return Breadcrumb[]
     */
    function current_breadcrumbs(): array
    {
        $routeName = \Illuminate\Support\Facades\Route::currentRouteName();

        if ($routeName === null) {
            return [];
        }

        return breadcrumbs($routeName, request()?->url());
    }
}
