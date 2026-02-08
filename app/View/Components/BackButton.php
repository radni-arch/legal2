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
