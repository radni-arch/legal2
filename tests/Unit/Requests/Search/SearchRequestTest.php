<?php

namespace Tests\Unit\Requests\Search;

use App\Http\Requests\Search\SearchRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SearchRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case for validation
        $user = User::first() ?? User::factory()->create();
        $case = LegalCase::create([
            'id' => '01H0000000000000000000000',
            'title' => 'Test Case for Search Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => 'test search query',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => 'comprehensive search',
            'type' => 'hybrid',
            'sources' => ['laws', 'decisions'],
            'page' => 2,
            'per_page' => 50,
            'sort_by' => 'relevance',
            'order' => 'desc',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_query_is_missing()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'type' => 'full_text',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_query_is_too_short()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => 'a',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_query_is_too_long()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => str_repeat('a', 501),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_type_is_invalid()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
            'type' => 'invalid_type',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_source_is_invalid()
    {
        $request = new SearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
            'sources' => ['laws', 'invalid_source'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sources.1', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_search_types()
    {
        $request = new SearchRequest;
        $validTypes = ['full_text', 'semantic', 'hybrid'];

        foreach ($validTypes as $type) {
            $validator = Validator::make([
                'query' => 'test query',
                'type' => $type,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Search type {$type} should be valid");
        }
    }
}
