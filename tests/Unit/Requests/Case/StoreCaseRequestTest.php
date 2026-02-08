<?php

namespace Tests\Unit\Requests\Case;

use App\Http\Requests\Case\StoreCaseRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StoreCaseRequestTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'title' => 'State v. Defendant - Drug Possession',
            'client_name' => 'John Doe',
            'status' => 'active',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'case_number' => 'CR-2025-001234',
            'title' => 'State v. Defendant - Drug Possession',
            'client_name' => 'John Doe',
            'opponent_name' => 'State Attorney Office',
            'court' => 'Županijski sud u Osijeku',
            'jurisdiction' => 'Osijek-Baranja County',
            'judge' => 'Hon. Jane Smith',
            'filing_date' => '2025-01-15',
            'status' => 'active',
            'description' => 'Criminal case involving alleged drug possession.',
            'tags' => ['drug-offense', 'criminal-defense'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_title_is_missing()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'client_name' => 'John Doe',
            'status' => 'active',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_client_name_is_missing()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'title' => 'Test Case',
            'status' => 'active',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('client_name', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_status_is_missing()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'title' => 'Test Case',
            'client_name' => 'John Doe',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_status_is_invalid()
    {
        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'title' => 'Test Case',
            'client_name' => 'John Doe',
            'status' => 'invalid_status',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_case_number_is_not_unique()
    {
        // Create an existing case
        $existingCase = LegalCase::create([
            'case_number' => 'CR-2025-DUPLICATE',
            'title' => 'Existing Case',
            'client_name' => 'Jane Doe',
            'status' => 'active',
        ]);

        $request = new StoreCaseRequest;
        $validator = Validator::make([
            'case_number' => 'CR-2025-DUPLICATE',
            'title' => 'New Case',
            'client_name' => 'John Doe',
            'status' => 'active',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_number', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_status_values()
    {
        $request = new StoreCaseRequest;
        $validStatuses = ['active', 'pending', 'closed', 'archived'];

        foreach ($validStatuses as $status) {
            $validator = Validator::make([
                'title' => 'Test Case',
                'client_name' => 'John Doe',
                'status' => $status,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Status {$status} should be valid");
        }
    }
}
