<?php

namespace Tests\Unit\Requests\Document;

use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UpdateDocumentRequestTest extends TestCase
{
    use UsesTestDatabase;

    private CaseDocument $testDocument;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $case = LegalCase::create([
            'title' => 'Test Case',
            'client_name' => 'John Doe',
            'status' => 'active',
        ]);

        // Create a test document
        $this->testDocument = CaseDocument::create([
            'case_id' => $case->id,
            'title' => 'Original Document',
            'content' => 'Original content',
            'category' => 'evidence',
        ]);
    }

    /** @test */
    public function it_passes_validation_with_no_fields_updated()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_when_updating_single_field()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'title' => 'Updated Document Title',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_when_updating_multiple_fields()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'title' => 'Updated Police Report',
            'category' => 'pleading',
            'tags' => ['updated', 'reviewed'],
            'language' => 'hr',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_category_is_invalid()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'category' => 'invalid_category',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_title_exceeds_max_length()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'title' => str_repeat('a', 501),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_category_values()
    {
        $request = new UpdateDocumentRequest;
        $validCategories = ['evidence', 'pleading', 'motion', 'order', 'correspondence', 'discovery', 'other'];

        foreach ($validCategories as $category) {
            $validator = Validator::make([
                'category' => $category,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Category {$category} should be valid");
        }
    }

    /** @test */
    public function it_passes_validation_when_updating_content()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'content' => 'This is the updated document content with additional details.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_metadata_update()
    {
        $request = new UpdateDocumentRequest;
        $validator = Validator::make([
            'metadata' => [
                'source' => 'court_filing',
                'verified' => true,
                'review_status' => 'approved',
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
