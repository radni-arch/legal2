<?php

namespace Tests\Unit\Models;

use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentContextTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function document_context_can_be_created()
    {
        $run = DocumentGenerationRun::factory()->create();

        $context = DocumentContext::create([
            'generation_run_id' => $run->id,
            'context_type' => 'standalone',
            'raw_input' => 'Test context',
            'assembled_context' => 'Assembled test context',
            'case_ids' => null,
            'evidence_ids' => null,
            'decision_ids' => null,
            'law_ids' => null,
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(DocumentContext::class, $context);
        $this->assertEquals('standalone', $context->context_type);
    }

    /** @test */
    public function document_context_belongs_to_generation_run()
    {
        $run = DocumentGenerationRun::factory()->create();
        $context = DocumentContext::factory()->create(['generation_run_id' => $run->id]);

        $this->assertInstanceOf(DocumentGenerationRun::class, $context->generationRun);
        $this->assertEquals($run->id, $context->generationRun->id);
    }

    /** @test */
    public function document_context_stores_arrays_as_json()
    {
        $context = DocumentContext::factory()->create([
            'case_ids' => ['uuid-1', 'uuid-2'],
            'evidence_ids' => ['ev-1', 'ev-2'],
        ]);

        $this->assertEquals(['uuid-1', 'uuid-2'], $context->case_ids);
        $this->assertEquals(['ev-1', 'ev-2'], $context->evidence_ids);
    }
}
