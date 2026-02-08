<?php

namespace Tests\Unit\TestData\Generators;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Tests\TestCase;
use Tests\TestData\Generators\BulkTestDataGenerator;
use Tests\UsesTestDatabase;

class BulkTestDataGeneratorTest extends TestCase
{
    use UsesTestDatabase;

    public function test_can_generate_bulk_cases(): void
    {
        $generator = new BulkTestDataGenerator;

        $cases = $generator->generateCases(100);

        $this->assertCount(100, $cases);
        $this->assertInstanceOf(LegalCase::class, $cases->first());
    }

    public function test_can_generate_cases_with_specific_type(): void
    {
        $generator = new BulkTestDataGenerator;

        $cases = $generator->generateCases(10, 'criminal');

        $this->assertCount(10, $cases);
        $cases->each(function ($case) {
            $this->assertContains('criminal', $case->tags, 'Case should have criminal tag');
        });
    }

    public function test_can_generate_bulk_documents(): void
    {
        $generator = new BulkTestDataGenerator;

        $documents = $generator->generateDocuments(50);

        $this->assertCount(50, $documents);
        $this->assertInstanceOf(CaseDocument::class, $documents->first());
    }

    public function test_can_generate_documents_for_specific_case(): void
    {
        $generator = new BulkTestDataGenerator;

        $case = LegalCase::factory()->create();
        $documents = $generator->generateDocuments(10, $case->id);

        $this->assertCount(10, $documents);
        $documents->each(function ($document) use ($case) {
            $this->assertEquals($case->id, $document->legal_case_id);
        });
    }

    public function test_can_cleanup_generated_data(): void
    {
        $generator = new BulkTestDataGenerator;
        $cases = $generator->generateCases(10);

        $caseIds = $cases->pluck('id')->toArray();

        $generator->cleanup();

        // Verify cases were deleted
        foreach ($caseIds as $id) {
            $this->assertNull(LegalCase::find($id));
        }
    }

    public function test_can_generate_batch_with_config(): void
    {
        $generator = new BulkTestDataGenerator;

        $config = [
            'cases' => 20,
            'documents' => 40,
            'case_type' => 'civil',
        ];

        $result = $generator->generateBatch($config);

        $this->assertArrayHasKey('cases', $result);
        $this->assertArrayHasKey('documents', $result);
        $this->assertCount(20, $result['cases']);
        $this->assertCount(40, $result['documents']);
    }

    public function test_cleanup_handles_empty_generation(): void
    {
        $generator = new BulkTestDataGenerator;

        // Should not throw exception when no data generated
        $generator->cleanup();

        $this->assertTrue(true); // If we get here, test passed
    }

    public function test_tracks_generated_ids_across_multiple_calls(): void
    {
        $generator = new BulkTestDataGenerator;

        $cases1 = $generator->generateCases(5);
        $cases2 = $generator->generateCases(5);

        // Both batches should be tracked
        $allIds = array_merge($cases1->pluck('id')->toArray(), $cases2->pluck('id')->toArray());

        $generator->cleanup();

        // All should be deleted
        foreach ($allIds as $id) {
            $this->assertNull(LegalCase::find($id));
        }
    }
}
