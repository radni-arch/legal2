<?php

namespace Tests\Unit\Models;

use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentExecutionTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $collaboration = AgentCollaboration::factory()->create();

        $execution = AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'researcher',
            'agent_role' => 'primary',
            'execution_order' => 1,
            'status' => 'pending',
            'task_description' => 'Research Croatian criminal law precedents',
            'input_context' => ['query' => 'criminal procedure'],
        ]);

        $this->assertEquals($collaboration->id, $execution->collaboration_id);
        $this->assertEquals('researcher', $execution->agent_name);
        $this->assertEquals('primary', $execution->agent_role);
        $this->assertEquals('pending', $execution->status);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $execution = AgentExecution::factory()->create();

        $this->assertNotNull($execution->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $execution->id);
        $this->assertFalse($execution->incrementing);
        $this->assertEquals('string', $execution->getKeyType());
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $execution = AgentExecution::factory()->create(['id' => null]);

        $this->assertNotNull($execution->id);
        $this->assertIsString($execution->id);
    }

    /** @test */
    public function it_belongs_to_collaboration()
    {
        $collaboration = AgentCollaboration::factory()->create();
        $execution = AgentExecution::factory()->create(['collaboration_id' => $collaboration->id]);

        $this->assertInstanceOf(AgentCollaboration::class, $execution->collaboration);
        $this->assertEquals($collaboration->id, $execution->collaboration->id);
    }

    /** @test */
    public function it_casts_input_context_as_array()
    {
        $context = ['query' => 'test', 'depth' => 'thorough'];
        $execution = AgentExecution::factory()->create(['input_context' => $context]);

        $this->assertIsArray($execution->input_context);
        $this->assertEquals('test', $execution->input_context['query']);
    }

    /** @test */
    public function it_casts_output_as_array()
    {
        $output = ['result' => 'findings', 'confidence' => 0.95];
        $execution = AgentExecution::factory()->completed()->create(['output' => $output]);

        $this->assertIsArray($execution->output);
        $this->assertEquals('findings', $execution->output['result']);
    }

    /** @test */
    public function it_casts_messages_to_others_as_array()
    {
        $messages = [
            ['to' => 'analyst', 'message' => ['data' => 'findings']],
        ];

        $execution = AgentExecution::factory()->create(['messages_to_others' => $messages]);

        $this->assertIsArray($execution->messages_to_others);
        $this->assertCount(1, $execution->messages_to_others);
    }

    /** @test */
    public function it_casts_messages_from_others_as_array()
    {
        $messages = [
            ['from' => 'researcher', 'message' => ['data' => 'results']],
        ];

        $execution = AgentExecution::factory()->create(['messages_from_others' => $messages]);

        $this->assertIsArray($execution->messages_from_others);
        $this->assertCount(1, $execution->messages_from_others);
    }

    /** @test */
    public function it_casts_numeric_fields_correctly()
    {
        $execution = AgentExecution::factory()->create([
            'execution_order' => 3,
            'tokens_used' => 5000,
            'duration_ms' => 45000,
        ]);

        $this->assertIsInt($execution->execution_order);
        $this->assertIsInt($execution->tokens_used);
        $this->assertIsInt($execution->duration_ms);
    }

    /** @test */
    public function it_casts_cost_spent_as_decimal()
    {
        $execution = AgentExecution::factory()->create(['cost_spent' => '1.5000']);

        $this->assertEquals('1.5000', $execution->cost_spent);
    }

    /** @test */
    public function it_casts_datetime_fields()
    {
        $execution = AgentExecution::factory()->completed()->create([
            'started_at' => now(),
            'completed_at' => now()->addMinutes(5),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $execution->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $execution->completed_at);
    }

    /** @test */
    public function scope_pending_returns_only_pending_executions()
    {
        AgentExecution::factory()->create(['status' => 'pending']);
        AgentExecution::factory()->create(['status' => 'pending']);
        AgentExecution::factory()->running()->create();

        $pending = AgentExecution::pending()->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn ($e) => $e->status === 'pending'));
    }

    /** @test */
    public function scope_running_returns_only_running_executions()
    {
        AgentExecution::factory()->running()->create();
        AgentExecution::factory()->running()->create();
        AgentExecution::factory()->completed()->create();

        $running = AgentExecution::running()->get();

        $this->assertCount(2, $running);
        $this->assertTrue($running->every(fn ($e) => $e->status === 'running'));
    }

    /** @test */
    public function scope_completed_returns_only_completed_executions()
    {
        AgentExecution::factory()->completed()->create();
        AgentExecution::factory()->completed()->create();
        AgentExecution::factory()->failed()->create();

        $completed = AgentExecution::completed()->get();

        $this->assertCount(2, $completed);
        $this->assertTrue($completed->every(fn ($e) => $e->status === 'completed'));
    }

    /** @test */
    public function scope_failed_returns_only_failed_executions()
    {
        AgentExecution::factory()->failed()->create();
        AgentExecution::factory()->failed()->create();
        AgentExecution::factory()->completed()->create();

        $failed = AgentExecution::failed()->get();

        $this->assertCount(2, $failed);
        $this->assertTrue($failed->every(fn ($e) => $e->status === 'failed'));
    }

    /** @test */
    public function scope_by_agent_filters_by_agent_name()
    {
        AgentExecution::factory()->count(3)->create(['agent_name' => 'researcher']);
        AgentExecution::factory()->count(2)->create(['agent_name' => 'analyst']);

        $researcherExecutions = AgentExecution::byAgent('researcher')->get();

        $this->assertCount(3, $researcherExecutions);
        $this->assertTrue($researcherExecutions->every(fn ($e) => $e->agent_name === 'researcher'));
    }

    /** @test */
    public function it_marks_execution_as_running()
    {
        $execution = AgentExecution::factory()->create([
            'status' => 'pending',
            'started_at' => null,
        ]);

        $execution->markRunning();

        $execution->refresh();

        $this->assertEquals('running', $execution->status);
        $this->assertNotNull($execution->started_at);
    }

    /** @test */
    public function it_marks_execution_as_completed()
    {
        $collaboration = AgentCollaboration::factory()->create(['completed_steps' => 0]);
        $execution = AgentExecution::factory()->running()->create([
            'collaboration_id' => $collaboration->id,
            'status' => 'running',
            'completed_at' => null,
        ]);

        $output = ['result' => 'Research findings', 'confidence' => 0.9];
        $execution->markCompleted($output);

        $execution->refresh();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals($output, $execution->output);
        $this->assertNotNull($execution->completed_at);
        $this->assertNotNull($execution->duration_ms);
    }

    /** @test */
    public function it_marks_execution_as_failed()
    {
        $execution = AgentExecution::factory()->running()->create([
            'status' => 'running',
            'error_message' => null,
        ]);

        $execution->markFailed('Execution timeout exceeded');

        $execution->refresh();

        $this->assertEquals('failed', $execution->status);
        $this->assertEquals('Execution timeout exceeded', $execution->error_message);
        $this->assertNotNull($execution->completed_at);
        $this->assertNotNull($execution->duration_ms);
    }

    /** @test */
    public function it_adds_tokens_used_and_calculates_cost()
    {
        $collaboration = AgentCollaboration::factory()->create(['tokens_used' => 0]);
        $execution = AgentExecution::factory()->create([
            'collaboration_id' => $collaboration->id,
            'tokens_used' => 0,
            'cost_spent' => '0.0000',
        ]);

        $execution->addTokensUsed(10000);

        $execution->refresh();

        $this->assertEquals(10000, $execution->tokens_used);
        $this->assertGreaterThan(0, (float) $execution->cost_spent);
    }

    /** @test */
    public function it_sends_message_to_another_agent()
    {
        $execution = AgentExecution::factory()->create([
            'messages_to_others' => [],
        ]);

        $execution->sendMessageTo('analyst', ['findings' => 'data']);

        $execution->refresh();

        $this->assertCount(1, $execution->messages_to_others);
        $this->assertEquals('analyst', $execution->messages_to_others[0]['to']);
    }

    /** @test */
    public function it_receives_message_from_another_agent()
    {
        $execution = AgentExecution::factory()->create([
            'messages_from_others' => [],
        ]);

        $execution->receiveMessageFrom('researcher', ['research' => 'results']);

        $execution->refresh();

        $this->assertCount(1, $execution->messages_from_others);
        $this->assertEquals('researcher', $execution->messages_from_others[0]['from']);
    }

    /** @test */
    public function it_tracks_execution_order()
    {
        $collaboration = AgentCollaboration::factory()->create();

        $execution1 = AgentExecution::factory()->create([
            'collaboration_id' => $collaboration->id,
            'execution_order' => 1,
        ]);

        $execution2 = AgentExecution::factory()->create([
            'collaboration_id' => $collaboration->id,
            'execution_order' => 2,
        ]);

        $executions = AgentExecution::where('collaboration_id', $collaboration->id)
            ->orderBy('execution_order')
            ->get();

        $this->assertEquals(1, $executions[0]->execution_order);
        $this->assertEquals(2, $executions[1]->execution_order);
    }

    /** @test */
    public function it_handles_different_agent_roles()
    {
        $roles = ['primary', 'supporting', 'quality_assurance'];

        foreach ($roles as $role) {
            $execution = AgentExecution::factory()->create(['agent_role' => $role]);
            $this->assertEquals($role, $execution->agent_role);
        }
    }

    /** @test */
    public function it_calculates_duration_when_completed()
    {
        $execution = AgentExecution::factory()->create([
            'started_at' => now()->subMinutes(5),
            'status' => 'running',
        ]);

        $execution->markCompleted(['result' => 'done']);

        $execution->refresh();

        $this->assertGreaterThan(0, $execution->duration_ms);
        $this->assertEqualsWithDelta(300000, $execution->duration_ms, 5000); // ~5 minutes
    }

    /** @test */
    public function it_handles_null_error_message_for_successful_executions()
    {
        $execution = AgentExecution::factory()->completed()->create();

        $this->assertNull($execution->error_message);
    }
}
