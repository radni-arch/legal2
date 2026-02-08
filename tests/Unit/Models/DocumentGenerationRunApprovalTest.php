<?php

namespace Tests\Unit\Models;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentGenerationRunApprovalTest extends TestCase
{
    use UsesTestDatabase;

    public function test_is_approved_returns_false_when_not_approved(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $this->assertFalse($run->isApproved());
    }

    public function test_is_approved_returns_true_when_approved(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        $this->assertTrue($run->isApproved());
    }

    public function test_is_ready_for_dispatch_requires_all_conditions(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test document content',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => true,
        ]);

        $this->assertTrue($run->isReadyForDispatch());
    }

    public function test_not_ready_for_dispatch_without_approval(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test document',
            'user_id' => $user->id,
            'docx_verified' => true,
        ]);

        $this->assertFalse($run->isReadyForDispatch());
    }

    public function test_not_ready_for_dispatch_without_docx_verified(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test document',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'docx_verified' => false,
        ]);

        $this->assertFalse($run->isReadyForDispatch());
    }

    public function test_approver_relationship(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);

        $this->assertEquals($approver->id, $run->approver->id);
    }
}
