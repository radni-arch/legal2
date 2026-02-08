# Breadcrumbs Navigation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a unified, reusable breadcrumbs navigation system that provides consistent navigation across all pages of the application.

**Architecture:** Service-based breadcrumb generation with a reusable Blade component. The BreadcrumbService will maintain a registry of route-to-breadcrumb mappings, allowing both automatic generation from route hierarchy and manual customization. A Blade component will render breadcrumbs consistently across all views.

**Tech Stack:** Laravel 11, Livewire 3, Blade Components, Tailwind CSS

---

## Overview

The application currently has ~7 pages with manual breadcrumbs (E-Komunikacije module) and ~40+ pages without breadcrumbs. This plan creates:

1. **BreadcrumbService** - Central service for breadcrumb generation and registry
2. **Blade Component** - Reusable `<x-breadcrumbs>` component
3. **Route Integration** - Automatic breadcrumb generation from route names
4. **Page Updates** - Add breadcrumbs to all page-level views

## Route Hierarchy Reference

```
dashboard (root)
├── AI & Research Tools
│   ├── chatbot
│   ├── unified.search
│   ├── topics.demo
│   ├── legal.playground
│   └── federated.memory.search
├── Timeline Tools
│   ├── timeline (default)
│   ├── comparative-timeline3
│   └── comparative-timeline
├── Knowledge & Graph
│   ├── graph.dashboard
│   ├── graph.viewer
│   ├── decisions.discover
│   └── citation.time-series
├── Document Management
│   ├── transcript
│   ├── textract.manager
│   ├── vectors.manage
│   └── ingested-laws.index
├── System Management
│   ├── logs.viewer
│   ├── honeypot.dashboard
│   │   └── honeypot.ip (detail)
│   ├── eoglasna.monitoring
│   └── agent.dashboard
│       └── agent.run (detail)
├── User
│   └── profile.show
└── ekom.* (E-Komunikacije Module)
    ├── ekom.dashboard
    ├── ekom.predmeti
    │   └── ekom.predmeti.show
    ├── ekom.podnesci
    │   └── ekom.podnesci.create
    ├── ekom.otpravci
    └── ekom.sync-status
```

---

## Task 1: Create BreadcrumbService

**Files:**
- Create: `app/Services/Navigation/BreadcrumbService.php`
- Create: `app/Services/Navigation/Breadcrumb.php` (value object)
- Test: `tests/Unit/Services/Navigation/BreadcrumbServiceTest.php`

### Step 1: Write the failing test for Breadcrumb value object

```php
<?php

namespace Tests\Unit\Services\Navigation;

use App\Services\Navigation\Breadcrumb;
use PHPUnit\Framework\TestCase;

class BreadcrumbServiceTest extends TestCase
{
    /** @test */
    public function breadcrumb_value_object_holds_label_url_and_active_state(): void
    {
        $breadcrumb = new Breadcrumb('Dashboard', '/dashboard', false);

        $this->assertEquals('Dashboard', $breadcrumb->label);
        $this->assertEquals('/dashboard', $breadcrumb->url);
        $this->assertFalse($breadcrumb->isActive);
    }

    /** @test */
    public function breadcrumb_can_be_created_as_active(): void
    {
        $breadcrumb = new Breadcrumb('Current Page', null, true);

        $this->assertEquals('Current Page', $breadcrumb->label);
        $this->assertNull($breadcrumb->url);
        $this->assertTrue($breadcrumb->isActive);
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: FAIL with "Class 'App\Services\Navigation\Breadcrumb' not found"

### Step 3: Create the Breadcrumb value object

Create `app/Services/Navigation/Breadcrumb.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Navigation;

final readonly class Breadcrumb
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $isActive = false,
    ) {}
}
```

### Step 4: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: PASS

### Step 5: Commit

```bash
git add app/Services/Navigation/Breadcrumb.php tests/Unit/Services/Navigation/BreadcrumbServiceTest.php
git commit -m "feat(breadcrumbs): Add Breadcrumb value object

- Create Breadcrumb readonly class with label, url, isActive properties
- Add unit tests for value object construction"
```

---

## Task 2: Create BreadcrumbService with Route Registry

**Files:**
- Create: `app/Services/Navigation/BreadcrumbService.php`
- Modify: `tests/Unit/Services/Navigation/BreadcrumbServiceTest.php`

### Step 1: Write the failing test for BreadcrumbService

Add to `tests/Unit/Services/Navigation/BreadcrumbServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services\Navigation;

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use PHPUnit\Framework\TestCase;

class BreadcrumbServiceTest extends TestCase
{
    private BreadcrumbService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BreadcrumbService();
    }

    /** @test */
    public function breadcrumb_value_object_holds_label_url_and_active_state(): void
    {
        $breadcrumb = new Breadcrumb('Dashboard', '/dashboard', false);

        $this->assertEquals('Dashboard', $breadcrumb->label);
        $this->assertEquals('/dashboard', $breadcrumb->url);
        $this->assertFalse($breadcrumb->isActive);
    }

    /** @test */
    public function breadcrumb_can_be_created_as_active(): void
    {
        $breadcrumb = new Breadcrumb('Current Page', null, true);

        $this->assertEquals('Current Page', $breadcrumb->label);
        $this->assertNull($breadcrumb->url);
        $this->assertTrue($breadcrumb->isActive);
    }

    /** @test */
    public function can_register_a_route_with_breadcrumb_config(): void
    {
        $this->service->register('dashboard', [
            'label' => 'Dashboard',
        ]);

        $this->assertTrue($this->service->has('dashboard'));
    }

    /** @test */
    public function can_register_route_with_parent(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);
        $this->service->register('chatbot', [
            'label' => 'AI Chatbot',
            'parent' => 'dashboard',
        ]);

        $this->assertTrue($this->service->has('chatbot'));
        $this->assertEquals('dashboard', $this->service->getParent('chatbot'));
    }

    /** @test */
    public function generates_breadcrumb_trail_for_single_level(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);

        $trail = $this->service->generate('dashboard', '/dashboard');

        $this->assertCount(1, $trail);
        $this->assertEquals('Dashboard', $trail[0]->label);
        $this->assertTrue($trail[0]->isActive);
    }

    /** @test */
    public function generates_breadcrumb_trail_with_parent_chain(): void
    {
        $this->service->register('dashboard', ['label' => 'Dashboard']);
        $this->service->register('ekom.dashboard', [
            'label' => 'E-Komunikacije',
            'parent' => 'dashboard',
        ]);
        $this->service->register('ekom.predmeti', [
            'label' => 'Cases',
            'parent' => 'ekom.dashboard',
        ]);

        $trail = $this->service->generate('ekom.predmeti', '/ekom/predmeti');

        $this->assertCount(3, $trail);
        $this->assertEquals('Dashboard', $trail[0]->label);
        $this->assertFalse($trail[0]->isActive);
        $this->assertEquals('E-Komunikacije', $trail[1]->label);
        $this->assertFalse($trail[1]->isActive);
        $this->assertEquals('Cases', $trail[2]->label);
        $this->assertTrue($trail[2]->isActive);
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: FAIL with "Class 'App\Services\Navigation\BreadcrumbService' not found"

### Step 3: Implement BreadcrumbService

Create `app/Services/Navigation/BreadcrumbService.php`:

```php
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
```

### Step 4: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: PASS

### Step 5: Commit

```bash
git add app/Services/Navigation/BreadcrumbService.php tests/Unit/Services/Navigation/BreadcrumbServiceTest.php
git commit -m "feat(breadcrumbs): Add BreadcrumbService with route registry

- Register routes with label, parent, and optional URL
- Generate breadcrumb trail by walking parent chain
- Automatic URL resolution from route names"
```

---

## Task 3: Create BreadcrumbRegistry Configuration

**Files:**
- Create: `app/Providers/BreadcrumbServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/Services/Navigation/BreadcrumbServiceTest.php`

### Step 1: Write the failing test for service provider integration

Add to test file:

```php
/** @test */
public function service_can_bulk_register_routes(): void
{
    $this->service->registerMany([
        'dashboard' => ['label' => 'Dashboard'],
        'chatbot' => ['label' => 'AI Chatbot', 'parent' => 'dashboard'],
        'search' => ['label' => 'Search', 'parent' => 'dashboard'],
    ]);

    $this->assertTrue($this->service->has('dashboard'));
    $this->assertTrue($this->service->has('chatbot'));
    $this->assertTrue($this->service->has('search'));
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: FAIL with "Call to undefined method registerMany"

### Step 3: Add registerMany method to BreadcrumbService

Add to `app/Services/Navigation/BreadcrumbService.php`:

```php
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
```

### Step 4: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh BreadcrumbServiceTest`
Expected: PASS

### Step 5: Create BreadcrumbServiceProvider

Create `app/Providers/BreadcrumbServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Navigation\BreadcrumbService;
use Illuminate\Support\ServiceProvider;

class BreadcrumbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BreadcrumbService::class, function () {
            $service = new BreadcrumbService();
            $this->registerBreadcrumbs($service);
            return $service;
        });
    }

    private function registerBreadcrumbs(BreadcrumbService $service): void
    {
        $service->registerMany([
            // Root
            'dashboard' => ['label' => 'Dashboard'],

            // AI & Research Tools
            'chatbot' => ['label' => 'AI Chatbot', 'parent' => 'dashboard'],
            'unified.search' => ['label' => 'Search', 'parent' => 'dashboard'],
            'topics.demo' => ['label' => 'Topic Analyzer', 'parent' => 'dashboard'],
            'legal.playground' => ['label' => 'Legal Playground', 'parent' => 'dashboard'],
            'federated.memory.search' => ['label' => 'Federated Memory', 'parent' => 'dashboard'],

            // Timeline Tools
            'timeline' => ['label' => 'Timeline', 'parent' => 'dashboard', 'url' => '/timeline'],
            'comparative-timeline' => ['label' => 'Comparative Timeline', 'parent' => 'dashboard', 'url' => '/comparative-timeline'],

            // Knowledge & Graph
            'graph.dashboard' => ['label' => 'Graph Dashboard', 'parent' => 'dashboard'],
            'graph.viewer' => ['label' => 'Graph Viewer', 'parent' => 'graph.dashboard'],
            'decisions.discover' => ['label' => 'Decision Discovery', 'parent' => 'dashboard'],
            'citation.time-series' => ['label' => 'Citation Time Series', 'parent' => 'dashboard'],

            // Document Management
            'transcript' => ['label' => 'Transcript', 'parent' => 'dashboard'],
            'textract.manager' => ['label' => 'Textract Pipeline', 'parent' => 'dashboard'],
            'vectors.manage' => ['label' => 'Vector Store Manager', 'parent' => 'dashboard'],
            'ingested-laws.index' => ['label' => 'Ingested Laws', 'parent' => 'dashboard'],

            // System Management
            'logs.viewer' => ['label' => 'Logs', 'parent' => 'dashboard'],
            'honeypot.dashboard' => ['label' => 'Honeypot Security', 'parent' => 'dashboard'],
            'honeypot.ip' => ['label' => 'IP Details', 'parent' => 'honeypot.dashboard'],
            'eoglasna.monitoring' => ['label' => 'e-Oglasna Monitoring', 'parent' => 'dashboard'],
            'agent.dashboard' => ['label' => 'Agent Dashboard', 'parent' => 'dashboard'],
            'agent.run' => ['label' => 'Agent Run', 'parent' => 'agent.dashboard'],

            // User
            'profile.show' => ['label' => 'Profile', 'parent' => 'dashboard'],

            // OpenAI
            'openai.responses' => ['label' => 'OpenAI Responses', 'parent' => 'dashboard'],

            // E-Komunikacije Module
            'ekom.dashboard' => ['label' => 'E-Komunikacije', 'parent' => 'dashboard'],
            'ekom.predmeti' => ['label' => 'Cases (Predmeti)', 'parent' => 'ekom.dashboard'],
            'ekom.predmeti.show' => ['label' => 'Case Details', 'parent' => 'ekom.predmeti'],
            'ekom.podnesci' => ['label' => 'Submissions (Podnesci)', 'parent' => 'ekom.dashboard'],
            'ekom.podnesci.create' => ['label' => 'New Submission', 'parent' => 'ekom.podnesci'],
            'ekom.otpravci' => ['label' => 'Dispatches (Otpravci)', 'parent' => 'ekom.dashboard'],
            'ekom.sync-status' => ['label' => 'Sync Status', 'parent' => 'ekom.dashboard'],
        ]);
    }
}
```

### Step 6: Register the service provider

Add to `bootstrap/providers.php`:

```php
App\Providers\BreadcrumbServiceProvider::class,
```

### Step 7: Commit

```bash
git add app/Services/Navigation/BreadcrumbService.php app/Providers/BreadcrumbServiceProvider.php bootstrap/providers.php tests/Unit/Services/Navigation/BreadcrumbServiceTest.php
git commit -m "feat(breadcrumbs): Add BreadcrumbServiceProvider with route registry

- Create service provider that registers all routes
- Define parent-child relationships for navigation hierarchy
- Configure labels for all ~40 application routes"
```

---

## Task 4: Create Breadcrumbs Blade Component

**Files:**
- Create: `app/View/Components/Breadcrumbs.php`
- Create: `resources/views/components/breadcrumbs.blade.php`
- Test: `tests/Feature/Components/BreadcrumbsComponentTest.php`

### Step 1: Write the failing feature test

Create `tests/Feature/Components/BreadcrumbsComponentTest.php`:

```php
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
        $service = $this->app->make(BreadcrumbService::class);

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
    public function breadcrumbs_component_hides_when_empty(): void
    {
        $view = $this->blade(
            '<x-breadcrumbs :items="$items" />',
            ['items' => []]
        );

        // Should render nothing or minimal wrapper
        $this->assertEmpty(trim($view->__toString()) ?: $view->assertDontSee('nav'));
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh BreadcrumbsComponentTest`
Expected: FAIL with component not found

### Step 3: Create the Blade component class

Create `app/View/Components/Breadcrumbs.php`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\Navigation\Breadcrumb;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Breadcrumbs extends Component
{
    /**
     * @param Breadcrumb[] $items
     */
    public function __construct(
        public array $items = [],
        public string $separator = '/',
    ) {}

    public function render(): View|string
    {
        if (empty($this->items)) {
            return '';
        }

        return view('components.breadcrumbs');
    }
}
```

### Step 4: Create the Blade view

Create `resources/views/components/breadcrumbs.blade.php`:

```blade
@if(count($items) > 0)
<nav class="text-sm mb-2" style="color: var(--muted, #94a3b8);" aria-label="Breadcrumb">
    <ol class="flex items-center flex-wrap gap-1">
        @foreach($items as $index => $crumb)
            <li class="flex items-center">
                @if($index > 0)
                    <span class="mx-2 select-none" aria-hidden="true">{{ $separator }}</span>
                @endif

                @if($crumb->isActive)
                    <span class="font-medium" style="color: var(--fg, #e5e7eb);" aria-current="page">
                        {{ $crumb->label }}
                    </span>
                @elseif($crumb->url)
                    <a href="{{ $crumb->url }}"
                       class="hover:underline transition-colors duration-150"
                       style="color: var(--muted, #94a3b8);">
                        {{ $crumb->label }}
                    </a>
                @else
                    <span>{{ $crumb->label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
```

### Step 5: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh BreadcrumbsComponentTest`
Expected: PASS

### Step 6: Commit

```bash
git add app/View/Components/Breadcrumbs.php resources/views/components/breadcrumbs.blade.php tests/Feature/Components/BreadcrumbsComponentTest.php
git commit -m "feat(breadcrumbs): Add Breadcrumbs Blade component

- Create reusable <x-breadcrumbs> component
- Support linked and active breadcrumb items
- Match existing E-Komunikacije styling (muted color, / separator)
- Include proper ARIA labels for accessibility"
```

---

## Task 5: Create Breadcrumbs Helper Trait for Livewire Components

**Files:**
- Create: `app/Http/Livewire/Concerns/WithBreadcrumbs.php`
- Test: `tests/Unit/Livewire/Concerns/WithBreadcrumbsTest.php`

### Step 1: Write the failing test

Create `tests/Unit/Livewire/Concerns/WithBreadcrumbsTest.php`:

```php
<?php

namespace Tests\Unit\Livewire\Concerns;

use App\Http\Livewire\Concerns\WithBreadcrumbs;
use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WithBreadcrumbsTest extends TestCase
{
    /** @test */
    public function trait_provides_breadcrumbs_property(): void
    {
        $component = new class {
            use WithBreadcrumbs;

            public function getRouteName(): string
            {
                return 'dashboard';
            }
        };

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertIsArray($breadcrumbs);
    }

    /** @test */
    public function trait_generates_breadcrumbs_from_route_name(): void
    {
        $service = $this->app->make(BreadcrumbService::class);

        $component = new class {
            use WithBreadcrumbs;

            protected ?string $breadcrumbRoute = 'ekom.predmeti';
        };

        // Inject the app container for service resolution
        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertNotEmpty($breadcrumbs);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $breadcrumbs);
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh WithBreadcrumbsTest`
Expected: FAIL with trait not found

### Step 3: Create the WithBreadcrumbs trait

Create `app/Http/Livewire/Concerns/WithBreadcrumbs.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Livewire\Concerns;

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;
use Illuminate\Support\Facades\Route;

trait WithBreadcrumbs
{
    /**
     * Override this property to specify the route name for breadcrumbs.
     */
    protected ?string $breadcrumbRoute = null;

    /**
     * Override this property to add extra context to breadcrumbs (e.g., entity name).
     */
    protected ?string $breadcrumbLabel = null;

    /**
     * Get the breadcrumb trail for this component.
     *
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

        // If custom label is set, replace the last breadcrumb's label
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

    /**
     * Get the current route name.
     */
    protected function getCurrentRouteName(): ?string
    {
        return Route::currentRouteName();
    }
}
```

### Step 4: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh WithBreadcrumbsTest`
Expected: PASS

### Step 5: Commit

```bash
git add app/Http/Livewire/Concerns/WithBreadcrumbs.php tests/Unit/Livewire/Concerns/WithBreadcrumbsTest.php
git commit -m "feat(breadcrumbs): Add WithBreadcrumbs trait for Livewire components

- Trait provides getBreadcrumbs() method
- Auto-detect route name or use explicit breadcrumbRoute property
- Support custom breadcrumbLabel for dynamic titles (e.g., case names)"
```

---

## Task 6: Create Navigation Helper Functions

**Files:**
- Create: `app/helpers.php`
- Modify: `composer.json` (autoload helpers)
- Test: `tests/Unit/HelpersTest.php`

### Step 1: Write the failing test

Create `tests/Unit/HelpersTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Navigation\Breadcrumb;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    /** @test */
    public function breadcrumbs_helper_returns_breadcrumb_trail(): void
    {
        $result = breadcrumbs('dashboard');

        $this->assertIsArray($result);
    }

    /** @test */
    public function breadcrumbs_helper_returns_empty_for_unknown_route(): void
    {
        $result = breadcrumbs('unknown.route.that.does.not.exist');

        $this->assertEmpty($result);
    }

    /** @test */
    public function breadcrumbs_helper_returns_trail_for_registered_route(): void
    {
        $result = breadcrumbs('ekom.predmeti');

        $this->assertNotEmpty($result);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $result);
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh HelpersTest`
Expected: FAIL with "Call to undefined function breadcrumbs()"

### Step 3: Create helpers file

Create `app/helpers.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Navigation\Breadcrumb;
use App\Services\Navigation\BreadcrumbService;

if (!function_exists('breadcrumbs')) {
    /**
     * Get breadcrumb trail for a route.
     *
     * @param string $routeName
     * @param string|null $currentUrl
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
     * Get breadcrumb trail for the current route.
     *
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
```

### Step 4: Update composer.json to autoload helpers

Add to `composer.json` in the `autoload` section:

```json
"files": [
    "app/helpers.php"
]
```

### Step 5: Run composer dump-autoload

Run: `composer dump-autoload`

### Step 6: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh HelpersTest`
Expected: PASS

### Step 7: Commit

```bash
git add app/helpers.php composer.json tests/Unit/HelpersTest.php
git commit -m "feat(breadcrumbs): Add helper functions for breadcrumbs

- breadcrumbs(\$routeName) - get trail for specific route
- current_breadcrumbs() - get trail for current route
- Autoload helpers via composer.json"
```

---

## Task 7: Update E-Komunikacije Views to Use New Component

**Files:**
- Modify: `resources/views/livewire/ekom-predmeti-list.blade.php`
- Modify: `resources/views/livewire/ekom-podnesci-list.blade.php`
- Modify: `resources/views/livewire/ekom-otpravci-list.blade.php`
- Modify: `resources/views/livewire/ekom-sync-status.blade.php`
- Modify: `resources/views/livewire/ekom-podnesak-create.blade.php`
- Modify: `resources/views/livewire/ekom-predmet-detail.blade.php`
- Test: Visual verification (these views already have tests)

### Step 1: Update ekom-predmeti-list.blade.php

Replace manual breadcrumb (lines 8-13):

```blade
{{-- Breadcrumb --}}
<nav class="text-sm mb-2" style="color: var(--muted, #94a3b8);">
    <a href="{{ route('ekom.dashboard') }}" class="hover:underline">E-Komunikacije</a>
    <span class="mx-2">/</span>
    <span>Cases (Predmeti)</span>
</nav>
```

With:

```blade
<x-breadcrumbs :items="breadcrumbs('ekom.predmeti')" />
```

### Step 2: Update ekom-podnesci-list.blade.php

Same pattern - replace manual breadcrumb with:

```blade
<x-breadcrumbs :items="breadcrumbs('ekom.podnesci')" />
```

### Step 3: Update ekom-otpravci-list.blade.php

Replace with:

```blade
<x-breadcrumbs :items="breadcrumbs('ekom.otpravci')" />
```

### Step 4: Update ekom-sync-status.blade.php

Replace with:

```blade
<x-breadcrumbs :items="breadcrumbs('ekom.sync-status')" />
```

### Step 5: Update ekom-podnesak-create.blade.php

Replace with:

```blade
<x-breadcrumbs :items="breadcrumbs('ekom.podnesci.create')" />
```

### Step 6: Update ekom-predmet-detail.blade.php

This one has a more complex breadcrumb with dynamic case number. Update to use dynamic label:

In the Livewire component (`app/Http/Livewire/EkomPredmetDetail.php`), add the trait and set dynamic label:

```php
use App\Http\Livewire\Concerns\WithBreadcrumbs;

class EkomPredmetDetail extends Component
{
    use WithBreadcrumbs;

    protected ?string $breadcrumbRoute = 'ekom.predmeti.show';

    // In mount() or computed property, set the label:
    public function mount(string $remoteId): void
    {
        // ... existing code ...
        $this->breadcrumbLabel = $this->predmet?->broj_predmeta ?? 'Case Details';
    }
}
```

In the view, replace manual breadcrumb with:

```blade
<x-breadcrumbs :items="$this->getBreadcrumbs()" />
```

### Step 7: Run existing E-Kom tests to verify no regression

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest`
Run: `./scripts/run-focused-tests.sh EkomPodnesciListTest`
Expected: PASS

### Step 8: Commit

```bash
git add resources/views/livewire/ekom-*.blade.php app/Http/Livewire/EkomPredmetDetail.php
git commit -m "refactor(breadcrumbs): Migrate E-Komunikacije views to use component

- Replace 6 manual breadcrumb implementations with <x-breadcrumbs>
- Add WithBreadcrumbs trait to EkomPredmetDetail for dynamic labels
- Maintain visual consistency with existing styling"
```

---

## Task 8: Add Breadcrumbs to AI & Research Tools Pages

**Files:**
- Modify: `resources/views/livewire/chatbot-component.blade.php` (or view file)
- Modify: `resources/views/search.blade.php`
- Modify: `resources/views/livewire/topic-analyzer.blade.php`
- Modify: `resources/views/legal-playground.blade.php`
- Modify: `resources/views/livewire/federated-memory-search.blade.php`

### Step 1: Identify header pattern in each file

Each file needs breadcrumbs added after the opening container, typically before the page title.

### Step 2: Add breadcrumbs to chatbot view

Add after opening container, before title:

```blade
<x-breadcrumbs :items="breadcrumbs('chatbot')" />
```

### Step 3: Add breadcrumbs to search view

```blade
<x-breadcrumbs :items="breadcrumbs('unified.search')" />
```

### Step 4: Add breadcrumbs to topic-analyzer view

```blade
<x-breadcrumbs :items="breadcrumbs('topics.demo')" />
```

### Step 5: Add breadcrumbs to legal-playground view

```blade
<x-breadcrumbs :items="breadcrumbs('legal.playground')" />
```

### Step 6: Add breadcrumbs to federated-memory-search view

```blade
<x-breadcrumbs :items="breadcrumbs('federated.memory.search')" />
```

### Step 7: Commit

```bash
git add resources/views/livewire/*.blade.php resources/views/*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to AI & Research Tools pages

- Chatbot, Search, Topic Analyzer, Legal Playground, Federated Memory
- Consistent placement after container open, before page title"
```

---

## Task 9: Add Breadcrumbs to Knowledge & Graph Pages

**Files:**
- Modify: `resources/views/livewire/graph-dashboard.blade.php`
- Modify: `resources/views/livewire/graph-viewer.blade.php` (if exists)
- Modify: `resources/views/livewire/decision-discovery-dashboard.blade.php`
- Modify: `resources/views/livewire/citation-time-series-viewer.blade.php`

### Step 1: Add breadcrumbs to graph-dashboard

```blade
<x-breadcrumbs :items="breadcrumbs('graph.dashboard')" />
```

### Step 2: Add breadcrumbs to graph-viewer (child of graph-dashboard)

```blade
<x-breadcrumbs :items="breadcrumbs('graph.viewer')" />
```

### Step 3: Add breadcrumbs to decision-discovery-dashboard

```blade
<x-breadcrumbs :items="breadcrumbs('decisions.discover')" />
```

### Step 4: Add breadcrumbs to citation-time-series-viewer

```blade
<x-breadcrumbs :items="breadcrumbs('citation.time-series')" />
```

### Step 5: Commit

```bash
git add resources/views/livewire/*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to Knowledge & Graph pages

- Graph Dashboard, Graph Viewer, Decision Discovery, Citation Time Series
- Graph Viewer shows hierarchy: Dashboard > Graph Dashboard > Graph Viewer"
```

---

## Task 10: Add Breadcrumbs to Document Management Pages

**Files:**
- Modify: `resources/views/transcript.blade.php`
- Modify: `resources/views/textract.blade.php`
- Modify: `resources/views/livewire/vector-store-manager.blade.php`
- Modify: `resources/views/ingested-laws.blade.php`

### Step 1: Add breadcrumbs to transcript view

```blade
<x-breadcrumbs :items="breadcrumbs('transcript')" />
```

### Step 2: Add breadcrumbs to textract view

```blade
<x-breadcrumbs :items="breadcrumbs('textract.manager')" />
```

### Step 3: Add breadcrumbs to vector-store-manager

```blade
<x-breadcrumbs :items="breadcrumbs('vectors.manage')" />
```

### Step 4: Add breadcrumbs to ingested-laws view

```blade
<x-breadcrumbs :items="breadcrumbs('ingested-laws.index')" />
```

### Step 5: Commit

```bash
git add resources/views/*.blade.php resources/views/livewire/*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to Document Management pages

- Transcript, Textract Pipeline, Vector Store Manager, Ingested Laws"
```

---

## Task 11: Add Breadcrumbs to System Management Pages

**Files:**
- Modify: `resources/views/livewire/laravel-log-viewer.blade.php`
- Modify: Honeypot dashboard view (controller-based)
- Modify: `resources/views/livewire/eoglasna-monitoring.blade.php`
- Modify: Agent dashboard view

### Step 1: Add breadcrumbs to laravel-log-viewer

```blade
<x-breadcrumbs :items="breadcrumbs('logs.viewer')" />
```

### Step 2: Add breadcrumbs to honeypot dashboard

For controller-based views, pass breadcrumbs from controller or use `current_breadcrumbs()`:

```blade
<x-breadcrumbs :items="current_breadcrumbs()" />
```

### Step 3: Add breadcrumbs to eoglasna-monitoring

```blade
<x-breadcrumbs :items="breadcrumbs('eoglasna.monitoring')" />
```

### Step 4: Add breadcrumbs to agent dashboard

```blade
<x-breadcrumbs :items="breadcrumbs('agent.dashboard')" />
```

### Step 5: Commit

```bash
git add resources/views/**/*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to System Management pages

- Logs Viewer, Honeypot Dashboard, e-Oglasna Monitoring, Agent Dashboard
- Use current_breadcrumbs() helper for controller-based views"
```

---

## Task 12: Add Breadcrumbs to Timeline Pages

**Files:**
- Modify: `resources/views/livewire/timeline-page.blade.php`
- Modify: `resources/views/livewire/comparative-timeline-page.blade.php`
- Modify: `resources/views/livewire/gup-timeline.blade.php`

### Step 1: Add breadcrumbs to timeline-page

```blade
<x-breadcrumbs :items="breadcrumbs('timeline')" />
```

### Step 2: Add breadcrumbs to comparative-timeline-page

```blade
<x-breadcrumbs :items="breadcrumbs('comparative-timeline')" />
```

### Step 3: Add breadcrumbs to gup-timeline

```blade
<x-breadcrumbs :items="breadcrumbs('comparative-timeline')" />
```

### Step 4: Commit

```bash
git add resources/views/livewire/*timeline*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to Timeline pages

- Timeline, Comparative Timeline variants"
```

---

## Task 13: Add Breadcrumbs to Remaining Pages

**Files:**
- Modify: `resources/views/openai-logs.blade.php`
- Modify: `resources/views/openai-responses.blade.php`
- Modify: Profile view
- Modify: Any other remaining page views

### Step 1: Audit all page-level views

Search for any remaining views without breadcrumbs.

### Step 2: Add breadcrumbs to each

Use the appropriate route name and helper function.

### Step 3: Commit

```bash
git add resources/views/**/*.blade.php
git commit -m "feat(breadcrumbs): Add breadcrumbs to remaining pages

- OpenAI Logs, OpenAI Responses, Profile, misc pages"
```

---

## Task 14: Add Quick Navigation Back Button Component

**Files:**
- Create: `app/View/Components/BackButton.php`
- Create: `resources/views/components/back-button.blade.php`
- Test: `tests/Feature/Components/BackButtonComponentTest.php`

### Step 1: Write the failing test

Create `tests/Feature/Components/BackButtonComponentTest.php`:

```php
<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class BackButtonComponentTest extends TestCase
{
    /** @test */
    public function back_button_renders_with_route(): void
    {
        $view = $this->blade(
            '<x-back-button route="dashboard" label="Back to Dashboard" />'
        );

        $view->assertSee('Back to Dashboard');
        $view->assertSee('href', false);
    }

    /** @test */
    public function back_button_renders_with_url(): void
    {
        $view = $this->blade(
            '<x-back-button url="/custom-path" label="Go Back" />'
        );

        $view->assertSee('Go Back');
        $view->assertSee('/custom-path', false);
    }
}
```

### Step 2: Run test to verify it fails

Run: `./scripts/run-focused-tests.sh BackButtonComponentTest`
Expected: FAIL

### Step 3: Create BackButton component

Create `app/View/Components/BackButton.php`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BackButton extends Component
{
    public string $href;

    public function __construct(
        public string $label = 'Back',
        public ?string $route = null,
        public ?string $url = null,
        public array $routeParams = [],
    ) {
        $this->href = $this->url ?? ($this->route ? route($this->route, $this->routeParams) : '#');
    }

    public function render(): View
    {
        return view('components.back-button');
    }
}
```

### Step 4: Create the Blade view

Create `resources/views/components/back-button.blade.php`:

```blade
<a href="{{ $href }}"
   {{ $attributes->merge([
       'class' => 'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors duration-150',
       'style' => 'background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); border: 1px solid var(--border, #1f2937); border-radius: 0.5rem;'
   ]) }}>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
    {{ $label }}
</a>
```

### Step 5: Run test to verify it passes

Run: `./scripts/run-focused-tests.sh BackButtonComponentTest`
Expected: PASS

### Step 6: Commit

```bash
git add app/View/Components/BackButton.php resources/views/components/back-button.blade.php tests/Feature/Components/BackButtonComponentTest.php
git commit -m "feat(navigation): Add BackButton component for quick navigation

- Accepts route name or direct URL
- Matches existing UI styling from E-Komunikacije
- Includes left arrow icon"
```

---

## Task 15: Add Home Button Component for Dashboard Return

**Files:**
- Create: `app/View/Components/HomeButton.php`
- Create: `resources/views/components/home-button.blade.php`
- Test: `tests/Feature/Components/HomeButtonComponentTest.php`

### Step 1: Write the failing test

```php
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
}
```

### Step 2: Create component

Create `app/View/Components/HomeButton.php`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class HomeButton extends Component
{
    public function __construct(
        public string $label = 'Dashboard',
    ) {}

    public function render(): View
    {
        return view('components.home-button');
    }
}
```

Create `resources/views/components/home-button.blade.php`:

```blade
<a href="{{ route('dashboard') }}"
   {{ $attributes->merge([
       'class' => 'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors duration-150 hover:opacity-80',
       'style' => 'color: var(--muted, #94a3b8);'
   ]) }}>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
    {{ $label }}
</a>
```

### Step 3: Commit

```bash
git add app/View/Components/HomeButton.php resources/views/components/home-button.blade.php tests/Feature/Components/HomeButtonComponentTest.php
git commit -m "feat(navigation): Add HomeButton component for dashboard return

- One-click return to main dashboard
- Subtle styling that doesn't compete with breadcrumbs"
```

---

## Task 16: Create Page Header Component with Integrated Navigation

**Files:**
- Create: `app/View/Components/PageHeader.php`
- Create: `resources/views/components/page-header.blade.php`
- Test: `tests/Feature/Components/PageHeaderComponentTest.php`

### Step 1: Write the failing test

```php
<?php

namespace Tests\Feature\Components;

use App\Services\Navigation\Breadcrumb;
use Tests\TestCase;

class PageHeaderComponentTest extends TestCase
{
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
}
```

### Step 2: Create component

Create `app/View/Components/PageHeader.php`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\Navigation\Breadcrumb;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PageHeader extends Component
{
    /**
     * @param string $title
     * @param string|null $subtitle
     * @param Breadcrumb[] $breadcrumbs
     * @param string|null $routeName For auto-generating breadcrumbs
     */
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public array $breadcrumbs = [],
        public ?string $routeName = null,
    ) {
        // Auto-generate breadcrumbs if route name provided and no breadcrumbs passed
        if (empty($this->breadcrumbs) && $this->routeName !== null) {
            $this->breadcrumbs = breadcrumbs($this->routeName);
        }
    }

    public function render(): View
    {
        return view('components.page-header');
    }
}
```

Create `resources/views/components/page-header.blade.php`:

```blade
<header class="relative" style="background: linear-gradient(180deg, var(--surface, #0f172a), var(--bg, #0b1220)); border-bottom: 1px solid var(--border, #1f2937);">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="rounded-2xl p-4" style="background: rgba(17,24,39,0.65); border: 1px solid var(--border, #1f2937);">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    @if(count($breadcrumbs) > 0)
                        <x-breadcrumbs :items="$breadcrumbs" />
                    @endif

                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight" style="color: var(--fg, #e5e7eb);">
                        {{ $title }}
                    </h1>

                    @if($subtitle)
                        <p class="mt-2" style="color: var(--muted, #94a3b8);">
                            {{ $subtitle }}
                        </p>
                    @endif
                </div>

                @isset($actions)
                    <div class="flex items-center gap-3">
                        {{ $actions }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
</header>
```

### Step 3: Run tests

Run: `./scripts/run-focused-tests.sh PageHeaderComponentTest`
Expected: PASS

### Step 4: Commit

```bash
git add app/View/Components/PageHeader.php resources/views/components/page-header.blade.php tests/Feature/Components/PageHeaderComponentTest.php
git commit -m "feat(navigation): Add PageHeader component with integrated navigation

- Combines breadcrumbs, title, subtitle, and action buttons
- Auto-generates breadcrumbs from route name
- Matches E-Komunikacije header styling
- Supports actions slot for page-specific buttons"
```

---

## Task 17: Refactor E-Komunikacije Headers to Use PageHeader Component

**Files:**
- Modify: `resources/views/livewire/ekom-predmeti-list.blade.php`
- Modify: `resources/views/livewire/ekom-podnesci-list.blade.php`
- Modify: `resources/views/livewire/ekom-otpravci-list.blade.php`
- Modify: `resources/views/livewire/ekom-sync-status.blade.php`

### Step 1: Replace header in ekom-predmeti-list

Replace the entire header section (lines 1-30 approx) with:

```blade
<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-predmeti-list">
    <x-page-header
        title="Cases (Predmeti)"
        subtitle="Browse and manage synchronized court cases"
        route-name="ekom.predmeti"
    >
        <x-slot:actions>
            <x-back-button route="ekom.dashboard" label="Dashboard" />
        </x-slot:actions>
    </x-page-header>

    {{-- Rest of content --}}
```

### Step 2: Apply same pattern to other E-Kom views

Each view gets the same treatment - extract the header into PageHeader component.

### Step 3: Run E-Kom tests to verify no regression

Run: `./scripts/run-focused-tests.sh EkomPredmetiListTest`
Expected: PASS

### Step 4: Commit

```bash
git add resources/views/livewire/ekom-*.blade.php
git commit -m "refactor(navigation): Migrate E-Komunikacije to PageHeader component

- Replace manual headers with <x-page-header>
- Reduce code duplication across views
- Maintain visual consistency"
```

---

## Task 18: Add Keyboard Navigation Support

**Files:**
- Create: `resources/js/navigation-shortcuts.js`
- Modify: `resources/js/app.js`
- Test: Browser test or manual verification

### Step 1: Create keyboard navigation script

Create `resources/js/navigation-shortcuts.js`:

```javascript
/**
 * Keyboard Navigation Shortcuts
 *
 * Alt+H - Go to Dashboard (Home)
 * Alt+B - Go Back (browser history)
 * Alt+U - Go Up (parent breadcrumb)
 */

document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('keydown', function(e) {
        // Only trigger with Alt key, not in input/textarea
        if (!e.altKey || isTyping(e.target)) {
            return;
        }

        switch(e.key.toLowerCase()) {
            case 'h':
                // Go to Dashboard
                e.preventDefault();
                window.location.href = '/dashboard';
                break;

            case 'b':
                // Go Back
                e.preventDefault();
                window.history.back();
                break;

            case 'u':
                // Go Up (find parent breadcrumb)
                e.preventDefault();
                goToParentBreadcrumb();
                break;
        }
    });
});

function isTyping(element) {
    const tagName = element.tagName.toLowerCase();
    return tagName === 'input' || tagName === 'textarea' || element.isContentEditable;
}

function goToParentBreadcrumb() {
    const breadcrumbs = document.querySelectorAll('nav[aria-label="Breadcrumb"] a');
    if (breadcrumbs.length > 0) {
        // Get the last link (parent of current page)
        const parentLink = breadcrumbs[breadcrumbs.length - 1];
        if (parentLink && parentLink.href) {
            window.location.href = parentLink.href;
        }
    }
}
```

### Step 2: Import in app.js

Add to `resources/js/app.js`:

```javascript
import './navigation-shortcuts.js';
```

### Step 3: Run npm build

Run: `npm run build`

### Step 4: Commit

```bash
git add resources/js/navigation-shortcuts.js resources/js/app.js
git commit -m "feat(navigation): Add keyboard shortcuts for navigation

- Alt+H: Go to Dashboard
- Alt+B: Go Back (browser history)
- Alt+U: Go Up (parent breadcrumb)
- Disabled in input/textarea fields"
```

---

## Task 19: Add Breadcrumb Structured Data for SEO

**Files:**
- Modify: `resources/views/components/breadcrumbs.blade.php`

### Step 1: Add JSON-LD structured data

Update `resources/views/components/breadcrumbs.blade.php`:

```blade
@if(count($items) > 0)
{{-- Structured Data for SEO --}}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        @foreach($items as $index => $crumb)
        {
            "@type": "ListItem",
            "position": {{ $index + 1 }},
            "name": "{{ $crumb->label }}"@if($crumb->url),
            "item": "{{ url($crumb->url) }}"@endif
        }@if(!$loop->last),@endif
        @endforeach
    ]
}
</script>

<nav class="text-sm mb-2" style="color: var(--muted, #94a3b8);" aria-label="Breadcrumb">
    {{-- ... existing markup ... --}}
</nav>
@endif
```

### Step 2: Commit

```bash
git add resources/views/components/breadcrumbs.blade.php
git commit -m "feat(breadcrumbs): Add JSON-LD structured data for SEO

- BreadcrumbList schema markup
- Improves search engine understanding of site hierarchy"
```

---

## Task 20: Final Integration Testing and Documentation

**Files:**
- Test: Run full test suite
- Verify: Visual inspection of all pages

### Step 1: Run full test suite

```bash
php artisan test --parallel
```

### Step 2: Manual verification checklist

- [ ] Dashboard shows no breadcrumbs (root)
- [ ] E-Komunikacije pages show correct hierarchy
- [ ] AI tools show Dashboard > Tool Name
- [ ] Nested pages (graph.viewer) show full hierarchy
- [ ] Keyboard shortcuts work
- [ ] Back button works on all pages
- [ ] Styling is consistent across all pages

### Step 3: Final commit

```bash
git add -A
git commit -m "feat(breadcrumbs): Complete breadcrumbs navigation implementation

Summary:
- BreadcrumbService with route registry
- Reusable Blade components (breadcrumbs, back-button, home-button, page-header)
- WithBreadcrumbs trait for Livewire components
- Helper functions breadcrumbs() and current_breadcrumbs()
- Keyboard navigation (Alt+H/B/U)
- JSON-LD structured data for SEO
- All 40+ pages now have consistent navigation"
```

---

## Verification Checklist

After completing all tasks:

- [ ] `BreadcrumbServiceTest` passes
- [ ] `BreadcrumbsComponentTest` passes
- [ ] `BackButtonComponentTest` passes
- [ ] `HomeButtonComponentTest` passes
- [ ] `PageHeaderComponentTest` passes
- [ ] `WithBreadcrumbsTest` passes
- [ ] `HelpersTest` passes
- [ ] All E-Komunikacije tests still pass
- [ ] No PHP syntax errors
- [ ] npm build succeeds
- [ ] Visual inspection shows consistent breadcrumbs

---

## Summary

This plan creates a comprehensive breadcrumbs navigation system with:

1. **Core Infrastructure** (Tasks 1-6): Service, component, trait, helpers
2. **E-Komunikacije Migration** (Task 7): Refactor existing breadcrumbs
3. **Application-wide Rollout** (Tasks 8-13): Add to all pages
4. **Enhanced Components** (Tasks 14-17): Back button, home button, page header
5. **Advanced Features** (Tasks 18-19): Keyboard nav, SEO
6. **Verification** (Task 20): Testing and documentation

Each task is bite-sized (2-5 minutes) and follows TDD.
