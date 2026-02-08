<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use Illuminate\Support\Facades\Route;

class BreadcrumbService
{
    /** @var array<string, array{label: string, parent?: string, url?: string}> */
    private array $registry = [];

    /**
     * Register a route with its breadcrumb configuration.
     *
     * @param string $routeName
     * @param array{label: string, parent?: string, url?: string} $config
     */
    public function register(string $routeName, array $config): self
    {
        $this->registry[$routeName] = $config;
        return $this;
    }

    /**
     * Bulk register multiple routes.
     *
     * @param array<string, array{label: string, parent?: string, url?: string}> $routes
     */
    public function registerMany(array $routes): self
    {
        foreach ($routes as $routeName => $config) {
            $this->register($routeName, $config);
        }
        return $this;
    }

    /**
     * Check if a route is registered.
     */
    public function has(string $routeName): bool
    {
        return isset($this->registry[$routeName]);
    }

    /**
     * Get the parent route name for a registered route.
     */
    public function getParent(string $routeName): ?string
    {
        return $this->registry[$routeName]['parent'] ?? null;
    }

    /**
     * Generate breadcrumb trail for a route.
     *
     * @return Breadcrumb[]
     */
    public function generate(string $routeName, ?string $currentUrl = null): array
    {
        $trail = [];
        $current = $routeName;

        // Build chain from current to root
        while ($current !== null && $this->has($current)) {
            $config = $this->registry[$current];
            $isActive = ($current === $routeName);

            // Get URL - either from config, or try to generate from route
            $url = null;
            if (!$isActive) {
                $url = $config['url'] ?? $this->resolveUrl($current);
            }

            array_unshift($trail, new Breadcrumb(
                label: $config['label'],
                url: $url,
                isActive: $isActive,
            ));

            $current = $config['parent'] ?? null;
        }

        return $trail;
    }

    /**
     * Try to resolve URL from route name.
     */
    private function resolveUrl(string $routeName): ?string
    {
        try {
            if (Route::has($routeName)) {
                return route($routeName);
            }
        } catch (\Exception) {
            // Route requires parameters we don't have
        }

        return null;
    }

    /**
     * Get all registered routes (for debugging/testing).
     */
    public function all(): array
    {
        return $this->registry;
    }
}
