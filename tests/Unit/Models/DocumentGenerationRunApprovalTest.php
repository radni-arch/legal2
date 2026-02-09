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

    // --- SOT-005: Send option canonical column tests ---

    public function test_set_send_options_persists_to_db_columns(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $run->setSendOptions([
            'send_email' => true,
            'as_draft' => true,
            'to_email' => 'lawyer@example.com',
        ]);

        $fresh = $run->fresh();
        $this->assertTrue($fresh->send_email);
        $this->assertTrue($fresh->as_draft);
        $this->assertEquals('lawyer@example.com', $fresh->to_email);
    }

    public function test_get_send_options_reads_from_db_columns(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
            'send_email' => true,
            'as_draft' => false,
            'to_email' => 'court@example.com',
        ]);

        $options = $run->getSendOptions();

        $this->assertIsArray($options);
        $this->assertTrue($options['send_email']);
        $this->assertFalse($options['as_draft']);
        $this->assertEquals('court@example.com', $options['to_email']);
    }

    public function test_get_send_options_returns_defaults_when_not_set(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $options = $run->getSendOptions();

        $this->assertFalse($options['send_email']);
        $this->assertFalse($options['as_draft']);
        $this->assertNull($options['to_email']);
    }

    public function test_mark_dispatched_sets_status_and_timestamp(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $run->markDispatched();

        $fresh = $run->fresh();
        $this->assertEquals('dispatched', $fresh->dispatch_status);
        $this->assertNotNull($fresh->dispatched_at);
        $this->assertNull($fresh->dispatch_error);
    }

    public function test_mark_dispatch_failed_sets_status_and_reason(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $run->markDispatchFailed('SMTP connection refused');

        $fresh = $run->fresh();
        $this->assertEquals('dispatch_failed', $fresh->dispatch_status);
        $this->assertNotNull($fresh->dispatched_at);
        $this->assertEquals('SMTP connection refused', $fresh->dispatch_error);
    }

    public function test_set_send_options_ignores_unknown_keys(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $run->setSendOptions([
            'send_email' => true,
            'unknown_key' => 'should be ignored',
        ]);

        $fresh = $run->fresh();
        $this->assertTrue($fresh->send_email);
        // unknown_key should not appear in attributes
        $this->assertArrayNotHasKey('unknown_key', $fresh->getAttributes());
    }

    public function test_dispatch_status_can_be_pending_dispatch(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
            'dispatch_status' => 'pending_dispatch',
        ]);

        $this->assertEquals('pending_dispatch', $run->fresh()->dispatch_status);
    }

    public function test_send_email_and_as_draft_are_cast_to_boolean(): void
    {
        $user = User::factory()->create();
        $run = DocumentGenerationRun::create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'user_id' => $user->id,
            'send_email' => 1,
            'as_draft' => 0,
        ]);

        $fresh = $run->fresh();
        $this->assertIsBool($fresh->send_email);
        $this->assertIsBool($fresh->as_draft);
        $this->assertTrue($fresh->send_email);
        $this->assertFalse($fresh->as_draft);
    }
}
