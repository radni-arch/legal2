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
        public bool $showNav = false,
    ) {
        if (empty($this->breadcrumbs) && $this->routeName !== null) {
            $this->breadcrumbs = breadcrumbs($this->routeName);
        }
    }

    public function render(): View
    {
        return view('components.page-header');
    }
}
