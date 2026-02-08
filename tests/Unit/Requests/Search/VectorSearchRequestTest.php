<?php

namespace Tests\Unit\Requests\Search;

use App\Http\Requests\Search\VectorSearchRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class VectorSearchRequestTest extends TestCase
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
            'title' => 'Test Case for Vector Search Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'proportionality of home search warrant',
            'corpus' => 'decisions',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'Fourth Amendment search and seizure',
            'corpus' => 'laws',
            'limit' => 50,
            'threshold' => 0.75,
            'filters' => [
                'date_from' => '2024-01-01',
                'date_to' => '2024-12-31',
                'court' => 'Županijski sud u Osijeku',
                'case_type' => 'criminal',
            ],
            'include_metadata' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_query_is_missing()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'corpus' => 'decisions',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_corpus_is_missing()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('corpus', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_corpus_is_invalid()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
            'corpus' => 'invalid_corpus',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('corpus', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_threshold_is_out_of_range()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
            'corpus' => 'decisions',
            'threshold' => 1.5,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('threshold', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_date_to_is_before_date_from()
    {
        $request = new VectorSearchRequest;
        $validator = Validator::make([
            'query' => 'test query',
            'corpus' => 'decisions',
            'filters' => [
                'date_from' => '2024-12-31',
                'date_to' => '2024-01-01',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('filters.date_to', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_corpus_types()
    {
        $request = new VectorSearchRequest;
        $validCorpusTypes = ['laws', 'decisions', 'cases', 'textract'];

        foreach ($validCorpusTypes as $corpus) {
            $validator = Validator::make([
                'query' => 'test query',
                'corpus' => $corpus,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Corpus type {$corpus} should be valid");
        }
    }
}
