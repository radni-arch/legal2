<?php

namespace Tests\Feature\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * SOT-005: Approval Workflow Feature Tests
 *
 * Verifies that approve and dispatch flows use canonical DB columns
 * for send options (send_email, as_draft, to_email) and dispatch_status.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test that approve API endpoint accepts and passes send options.
     */
    public function test_approve_via_api_accepts_send_options(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
        ]);

        $mock = Mockery::mock(LegalArtilleryAgentContract::class);
        $mock->shouldReceive('approveRun')
            ->once()
            ->withArgs(function ($runId, $approverId, $notes, $sendOptions) use ($run) {
                return $runId === $run->id
                    && $approverId === $this->user->id
                    && $notes === 'Approved for dispatch'
                    && is_array($sendOptions)
                    && $sendOptions['send_email'] === true
                    && $sendOptions['as_draft'] === true
                    && $sendOptions['to_email'] === 'court@example.com';
            })
            ->andReturn($run->fresh());
        $this->app->instance(LegalArtilleryAgentContract::class, $mock);

        $response = $this->postJson("/api/documents/runs/{$run->id}/approve", [
            'notes' => 'Approved for dispatch',
            'send_email' => true,
            'as_draft' => true,
            'to_email' => 'court@example.com',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test that dispatch endpoint reads send options from DB columns.
     */
    public function test_dispatch_reads_from_canonical_db_columns(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
            'approved_at' => now(),
            'approved_by' => $this->user->id,
            'docx_verified' => true,
            'send_email' => true,
            'as_draft' => false,
            'to_email' => 'court@example.com',
        ]);

        // Verify the DB columns hold the canonical values
        $freshRun = $run->fresh();
        $sendOptions = $freshRun->getSendOptions();

        $this->assertTrue($sendOptions['send_email']);
        $this->assertFalse($sendOptions['as_draft']);
        $this->assertEquals('court@example.com', $sendOptions['to_email']);
    }

    /**
     * Test full approve-then-dispatch lifecycle uses canonical state.
     */
    public function test_approve_and_dispatch_lifecycle_consistency(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content with citation [Art. 6 ECHR]',
            'user_id' => $this->user->id,
            'docx_verified' => true,
        ]);

        // Step 1: Set send options via model method (simulating what agent does)
        $run->setSendOptions([
            'send_email' => true,
            'as_draft' => true,
            'to_email' => 'lawyer@firm.com',
        ]);

        // Step 2: Approve the run
        $run->update([
            'approved_at' => now(),
            'approved_by' => $this->user->id,
            'approval_notes' => 'Reviewed and approved',
        ]);

        $freshRun = $run->fresh();

        // Verify canonical state is consistent
        $this->assertTrue($freshRun->isApproved());
        $this->assertTrue($freshRun->isReadyForDispatch());

        $sendOptions = $freshRun->getSendOptions();
        $this->assertTrue($sendOptions['send_email']);
        $this->assertTrue($sendOptions['as_draft']);
        $this->assertEquals('lawyer@firm.com', $sendOptions['to_email']);

        // Step 3: Mark dispatched
        $freshRun->markDispatched();
        $dispatchedRun = $freshRun->fresh();

        $this->assertEquals('dispatched', $dispatchedRun->dispatch_status);
        $this->assertNotNull($dispatchedRun->dispatched_at);

        // Send options remain unchanged after dispatch
        $postDispatchOptions = $dispatchedRun->getSendOptions();
        $this->assertTrue($postDispatchOptions['send_email']);
        $this->assertTrue($postDispatchOptions['as_draft']);
        $this->assertEquals('lawyer@firm.com', $postDispatchOptions['to_email']);
    }

    /**
     * Test that send options are NOT read from model_config.
     */
    public function test_send_options_not_read_from_model_config(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
            'model_config' => [
                'pending_dispatch' => [
                    'send_email' => true,
                    'as_draft' => true,
                    'to_email' => 'old@example.com',
                ],
            ],
            // DB columns are the canonical source - these should be what getSendOptions returns
            'send_email' => false,
            'as_draft' => false,
            'to_email' => null,
        ]);

        $sendOptions = $run->getSendOptions();

        // Should read from DB columns, NOT model_config
        $this->assertFalse($sendOptions['send_email']);
        $this->assertFalse($sendOptions['as_draft']);
        $this->assertNull($sendOptions['to_email']);
    }

    /**
     * Test dispatch failure tracking with markDispatchFailed.
     */
    public function test_dispatch_failure_lifecycle(): void
    {
        $this->actingAs($this->user);

        $run = DocumentGenerationRun::factory()->create([
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
            'final_document' => 'Test content',
            'user_id' => $this->user->id,
            'approved_at' => now(),
            'approved_by' => $this->user->id,
            'docx_verified' => true,
            'send_email' => true,
            'dispatch_status' => 'pending_dispatch',
        ]);

        // Simulate dispatch failure
        $run->markDispatchFailed('SMTP timeout after 30s');

        $freshRun = $run->fresh();
        $this->assertEquals('dispatch_failed', $freshRun->dispatch_status);
        $this->assertEquals('SMTP timeout after 30s', $freshRun->dispatch_error);
        $this->assertNotNull($freshRun->dispatched_at);

        // Send options remain intact after failure
        $this->assertTrue($freshRun->send_email);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
