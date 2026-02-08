<?php

namespace Tests\Unit\Models;

use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentIterationTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function document_iteration_can_be_created()
    {
        $run = DocumentGenerationRun::factory()->create();

        $iteration = DocumentIteration::create([
            'generation_run_id' => $run->id,
            'iteration_number' => 1,
            'phase' => 'critic',
            'document_version' => null,
            'critic_feedback' => ['plan' => 'Create initial structure'],
            'scores' => null,
            'weighted_score' => null,
            'improvement_delta' => null,
            'ai_model_used' => 'gpt-4o',
            'tokens_used' => 1500,
            'cost_estimate' => 0.03,
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(DocumentIteration::class, $iteration);
        $this->assertEquals('critic', $iteration->phase);
        $this->assertEquals(1, $iteration->iteration_number);
    }

    /** @test */
    public function document_iteration_belongs_to_generation_run()
    {
        $run = DocumentGenerationRun::factory()->create();
        $iteration = DocumentIteration::factory()->create(['generation_run_id' => $run->id]);

        $this->assertInstanceOf(DocumentGenerationRun::class, $iteration->generationRun);
        $this->assertEquals($run->id, $iteration->generationRun->id);
    }

    /** @test */
    public function document_iteration_stores_critic_feedback_as_json()
    {
        $feedback = [
            'scores' => ['legal_rigor' => 85, 'persuasiveness' => 78],
            'weaknesses' => ['Missing citation'],
            'improvement_plan' => 'Add ZKP citations',
        ];

        $iteration = DocumentIteration::factory()->create(['critic_feedback' => $feedback]);

        $this->assertEquals($feedback, $iteration->critic_feedback);
    }
}
