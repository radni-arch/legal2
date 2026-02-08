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
