<?php

namespace Tests\Unit\Models;

use App\Models\DocumentGenerationRun;
use App\Models\LegalCase;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentGenerationRunTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function document_generation_run_can_be_created()
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $run = DocumentGenerationRun::create([
            'document_type' => 'suppression_motion',
            'case_id' => $case->id,
            'status' => 'running',
            'user_id' => $user->id,
            'model_config' => ['critic_model' => 'gpt-4o', 'worker_model' => 'gpt-4o'],
        ]);

        $this->assertInstanceOf(DocumentGenerationRun::class, $run);
        $this->assertEquals('suppression_motion', $run->document_type);
        $this->assertEquals('running', $run->status);
    }

    /** @test */
    public function document_generation_run_belongs_to_user()
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $run->user);
        $this->assertEquals($user->id, $run->user->id);
    }

    /** @test */
    public function document_generation_run_can_belong_to_a_case()
    {
        $case = LegalCase::factory()->create();
        $run = DocumentGenerationRun::factory()->create(['case_id' => $case->id]);

        $this->assertInstanceOf(LegalCase::class, $run->case);
        $this->assertEquals($case->id, $run->case->id);
    }
}
