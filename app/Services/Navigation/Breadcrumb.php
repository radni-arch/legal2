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
