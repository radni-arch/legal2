<?php

namespace Tests\Unit\DTOs\Ekom;

use App\DTOs\Ekom\PaginatedResponse;
use PHPUnit\Framework\TestCase;

class PaginatedResponseTest extends TestCase
{
    private function sampleApiData(array $overrides = []): array
    {
        return array_merge([
            'content' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
            'page' => 0,
            'size' => 10,
            'totalElements' => 25,
            'totalPages' => 3,
            'first' => true,
            'last' => false,
        ], $overrides);
    }

    public function test_from_api_response_with_valid_data(): void
    {
        $data = $this->sampleApiData();
        $response = PaginatedResponse::fromApiResponse($data);

        $this->assertCount(2, $response->content);
        $this->assertSame(0, $response->page);
        $this->assertSame(10, $response->size);
        $this->assertSame(25, $response->totalElements);
        $this->assertSame(3, $response->totalPages);
        $this->assertTrue($response->first);
        $this->assertFalse($response->last);
    }

    public function test_from_api_response_with_empty_content(): void
    {
        $data = $this->sampleApiData([
            'content' => [],
            'totalElements' => 0,
            'totalPages' => 0,
            'first' => true,
            'last' => true,
        ]);

        $response = PaginatedResponse::fromApiResponse($data);

        $this->assertSame([], $response->content);
        $this->assertSame(0, $response->totalElements);
        $this->assertTrue($response->last);
    }

    public function test_has_next_page_returns_true_when_not_last(): void
    {
        $response = PaginatedResponse::fromApiResponse($this->sampleApiData([
            'last' => false,
        ]));

        $this->assertTrue($response->hasNextPage());
    }

    public function test_has_next_page_returns_false_on_last_page(): void
    {
        $response = PaginatedResponse::fromApiResponse($this->sampleApiData([
            'last' => true,
        ]));

        $this->assertFalse($response->hasNextPage());
    }

    public function test_next_page_returns_page_plus_one(): void
    {
        $response = PaginatedResponse::fromApiResponse($this->sampleApiData([
            'page' => 2,
        ]));

        $this->assertSame(3, $response->nextPage());
    }

    public function test_items_returns_content(): void
    {
        $data = $this->sampleApiData();
        $response = PaginatedResponse::fromApiResponse($data);

        $this->assertSame($data['content'], $response->items());
    }

    public function test_is_empty_returns_true_for_empty_content(): void
    {
        $response = PaginatedResponse::fromApiResponse($this->sampleApiData([
            'content' => [],
        ]));

        $this->assertTrue($response->isEmpty());
    }

    public function test_is_empty_returns_false_for_non_empty_content(): void
    {
        $response = PaginatedResponse::fromApiResponse($this->sampleApiData());

        $this->assertFalse($response->isEmpty());
    }
}
