<?php

namespace Tests\Unit\Requests\Case;

use App\Http\Requests\Case\UpdateCaseRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UpdateCaseRequestTest extends TestCase
{
    use UsesTestDatabase;

    private LegalCase $testCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $this->testCase = LegalCase::create([
            'case_number' => 'CR-2025-TEST',
            'title' => 'Original Test Case',
            'client_name' => 'John Doe',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_passes_validation_with_no_fields_updated()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_when_updating_single_field()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'title' => 'Updated Title',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_when_updating_multiple_fields()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'title' => 'Updated Title',
            'status' => 'closed',
            'description' => 'Updated description with more details.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_status_is_invalid()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'status' => 'invalid_status',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_title_exceeds_max_length()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'title' => str_repeat('a', 501),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_filing_date_is_in_future()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'filing_date' => now()->addDays(7)->format('Y-m-d'),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('filing_date', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_when_updating_tags()
    {
        $request = new UpdateCaseRequest;
        $validator = Validator::make([
            'tags' => ['criminal', 'drug-offense', 'high-priority'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_status_values()
    {
        $request = new UpdateCaseRequest;
        $validStatuses = ['active', 'pending', 'closed', 'archived'];

        foreach ($validStatuses as $status) {
            $validator = Validator::make([
                'status' => $status,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Status {$status} should be valid");
        }
    }
}
