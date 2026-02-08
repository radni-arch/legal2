<?php

declare(strict_types=1);

namespace App\DTOs\Ekom;

class PaginatedResponse
{
    /**
     * @param  array  $content  The items in this page
     * @param  int  $totalElements  Total number of elements across all pages
     * @param  int  $totalPages  Total number of pages
     * @param  int  $page  Current page number (0-indexed)
     * @param  int  $size  Page size
     * @param  bool  $last  Whether this is the last page
     * @param  bool  $first  Whether this is the first page
     */
    public function __construct(
        public readonly array $content,
        public readonly int $totalElements,
        public readonly int $totalPages,
        public readonly int $page,
        public readonly int $size,
        public readonly bool $last,
        public readonly bool $first,
    ) {}

    public static function fromApiResponse(array $data, ?callable $mapper = null): self
    {
        $content = $data['content'] ?? [];

        if ($mapper !== null) {
            $content = array_map($mapper, $content);
        }

        return new self(
            content: $content,
            totalElements: (int) ($data['totalElements'] ?? 0),
            totalPages: (int) ($data['totalPages'] ?? 0),
            page: (int) ($data['number'] ?? $data['page'] ?? 0),
            size: (int) ($data['size'] ?? 0),
            last: (bool) ($data['last'] ?? true),
            first: (bool) ($data['first'] ?? true),
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->content);
    }

    public function hasMore(): bool
    {
        return ! $this->last;
    }

    public function hasNextPage(): bool
    {
        return ! $this->last;
    }

    public function nextPage(): int
    {
        return $this->page + 1;
    }

    public function items(): array
    {
        return $this->content;
    }
}
