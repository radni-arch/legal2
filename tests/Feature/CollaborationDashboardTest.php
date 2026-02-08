<?php

namespace Tests\Feature;

use App\Http\Livewire\CollaborationDashboard;
use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive tests for CollaborationDashboard Livewire component
 *
 * Tests cover:
 * - Component rendering
 * - Data display and statistics
 * - Pagination functionality
 * - Detail view interactions
 * - Event listeners
 * - Relationships with executions
 * - Scope methods
 * - Edge cases and empty states
 * - Performance with large datasets
 */
class CollaborationDashboardTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test component renders successfully with no data
     *
     * @test
     */
    public function it_renders_successfully_with_no_data(): void
    {
        Livewire::test(CollaborationDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.collaboration-dashboard');
    }

    /**
     * Test component displays empty state when no collaborations exist
     *
     * @test
     */
    public function it_displays_empty_state_when_no_collaborations(): void
    {
        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 0
                    && $stats['completed'] === 0
                    && $stats['in_progress'] === 0
                    && $stats['failed'] === 0;
            });
    }

    /**
     * Test component displays collaborations with pagination
     *
     * @test
     */
    public function it_displays_paginated_collaborations(): void
    {
        // Create 25 collaborations (more than 1 page)
        for ($i = 0; $i < 25; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(1),
            ]);
        }

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->total() === 25 && $collaborations->perPage() === 20;
            });
    }

    /**
     * Test pagination works correctly
     *
     * @test
     */
    public function it_paginates_collaborations_correctly(): void
    {
        // Create 30 collaborations
        for ($i = 0; $i < 30; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays($i),
            ]);
        }

        $component = Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->count() === 20; // First page
            });

        // Navigate to page 2
        $component->call('gotoPage', 2)
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->count() === 10; // Second page
            });
    }

    /**
     * Test statistics are calculated correctly
     *
     * @test
     */
    public function it_calculates_statistics_correctly(): void
    {
        // Completed
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'completed',
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(2),
            'duration_seconds' => 7200,
            'tokens_used' => 1000,
            'cost_spent' => 0.15,
        ]);

        // In progress
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'research',
            'problem_statement' => 'Problem 2',
            'status' => 'in_progress',
            'started_at' => now()->subHours(1),
        ]);

        // Failed
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 3',
            'status' => 'failed',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addMinutes(30),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 3
                    && $stats['completed'] === 1
                    && $stats['in_progress'] === 1
                    && $stats['failed'] === 1;
            });
    }

    /**
     * Test recent 7 days statistics
     *
     * @test
     */
    public function it_calculates_recent_7_days_statistics(): void
    {
        // Within 7 days
        for ($i = 0; $i < 5; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Recent Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays($i),
                'completed_at' => now()->subDays($i)->addHours(1),
            ]);
        }

        // Older than 7 days
        for ($i = 0; $i < 3; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Old Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays(8 + $i),
                'completed_at' => now()->subDays(8 + $i)->addHours(1),
            ]);
        }

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['recent_7_days'] === 5;
            });
    }

    /**
     * Test average duration calculation
     *
     * @test
     */
    public function it_calculates_average_duration(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'completed',
            'started_at' => now()->subDays(1),
            'completed_at' => now()->subDays(1)->addHours(2),
            'duration_seconds' => 7200, // 2 hours
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'completed',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addHours(1),
            'duration_seconds' => 3600, // 1 hour
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                // Average: (7200 + 3600) / 2 = 5400
                return $stats['avg_duration'] === 5400.0;
            });
    }

    /**
     * Test tokens and cost statistics
     *
     * @test
     */
    public function it_calculates_tokens_and_cost_statistics(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'completed',
            'started_at' => now()->subDays(1),
            'tokens_used' => 10000,
            'cost_spent' => 1.50,
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'completed',
            'started_at' => now()->subDays(2),
            'tokens_used' => 5000,
            'cost_spent' => 0.75,
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_tokens'] === 15000
                    && $stats['total_cost'] == 2.25;
            });
    }

    /**
     * Test viewDetails method
     *
     * @test
     */
    public function it_opens_details_view_for_collaboration(): void
    {
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test Problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        // Create some executions
        AgentExecution::create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'Agent1',
            'agent_role' => 'analyzer',
            'execution_order' => 1,
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', $collaboration->id)
            ->assertSet('showDetails', true)
            ->assertSet('selectedCollaboration.id', $collaboration->id);
    }

    /**
     * Test closeDetails method
     *
     * @test
     */
    public function it_closes_details_view(): void
    {
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test Problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', $collaboration->id)
            ->assertSet('showDetails', true)
            ->call('closeDetails')
            ->assertSet('showDetails', false)
            ->assertSet('selectedCollaboration', null);
    }

    /**
     * Test collaboration with executions relationship
     *
     * @test
     */
    public function it_loads_collaborations_with_executions(): void
    {
        $collaboration = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test Problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        // Create multiple executions
        for ($i = 1; $i <= 3; $i++) {
            AgentExecution::create([
                'collaboration_id' => $collaboration->id,
                'agent_name' => "Agent{$i}",
                'agent_role' => 'analyzer',
                'execution_order' => $i,
                'status' => 'completed',
                'started_at' => now()->subDay(),
            ]);
        }

        Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', $collaboration->id)
            ->assertSet('selectedCollaboration.executions', function ($executions) {
                return count($executions) === 3;
            });
    }

    /**
     * Test collaborations are ordered by most recent first
     *
     * @test
     */
    public function it_orders_collaborations_by_most_recent_first(): void
    {
        $old = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Old Problem',
            'status' => 'completed',
            'started_at' => now()->subDays(5),
        ]);

        $middle = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Middle Problem',
            'status' => 'completed',
            'started_at' => now()->subDays(3),
        ]);

        $recent = AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Recent Problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) use ($recent, $middle, $old) {
                return $collaborations[0]->id === $recent->id
                    && $collaborations[1]->id === $middle->id
                    && $collaborations[2]->id === $old->id;
            });
    }

    /**
     * Test component handles in_progress collaborations
     *
     * @test
     */
    public function it_displays_in_progress_collaborations(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'In Progress Problem',
            'status' => 'in_progress',
            'started_at' => now()->subHours(2),
            'total_steps' => 10,
            'completed_steps' => 5,
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['in_progress'] === 1;
            })
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->first()->status === 'in_progress';
            });
    }

    /**
     * Test component handles failed collaborations
     *
     * @test
     */
    public function it_displays_failed_collaborations_with_errors(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Failed Problem',
            'status' => 'failed',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addMinutes(30),
            'synthesis' => 'Collaboration failed: Agent timeout',
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['failed'] === 1;
            })
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->first()->status === 'failed';
            });
    }

    /**
     * Test statistics with null/zero values
     *
     * @test
     */
    public function it_handles_null_values_in_statistics(): void
    {
        // Create collaboration with minimal data
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Minimal Problem',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats')
            ->assertViewHas('stats', function ($stats) {
                // Use array_key_exists instead of isset because isset returns false for null values
                return array_key_exists('total', $stats)
                    && array_key_exists('total_tokens', $stats)
                    && array_key_exists('total_cost', $stats)
                    && array_key_exists('avg_duration', $stats);
            });
    }

    /**
     * Test component with large dataset
     *
     * @test
     */
    public function it_handles_large_datasets_efficiently(): void
    {
        // Create 100 collaborations
        for ($i = 0; $i < 100; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => ['analysis', 'research', 'synthesis'][rand(0, 2)],
                'problem_statement' => "Problem {$i}",
                'status' => ['completed', 'in_progress', 'failed'][rand(0, 2)],
                'started_at' => now()->subDays($i),
                'tokens_used' => rand(1000, 10000),
                'cost_spent' => rand(10, 150) / 100,
            ]);
        }

        Livewire::test(CollaborationDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 100;
            })
            ->assertViewHas('collaborations', function ($collaborations) {
                return $collaborations->total() === 100 && $collaborations->perPage() === 20;
            });
    }

    /**
     * Test refresh event listener
     *
     * @test
     */
    public function it_refreshes_on_event(): void
    {
        $component = Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 0;
            });

        // Create new collaboration
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'New Problem',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Trigger refresh event (Livewire 3 uses dispatch instead of emit)
        $component->dispatch('refreshCollaborations')
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 1;
            });
    }

    /**
     * Test collaboration with context data
     *
     * @test
     */
    public function it_displays_collaborations_with_context(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem with context',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'context' => [
                'case_id' => 123,
                'source' => 'manual',
                'priority' => 'high',
            ],
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                $collab = $collaborations->first();

                return is_array($collab->context)
                    && isset($collab->context['case_id']);
            });
    }

    /**
     * Test collaboration with execution plan
     *
     * @test
     */
    public function it_displays_collaborations_with_execution_plan(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem with plan',
            'status' => 'in_progress',
            'started_at' => now()->subHours(1),
            'execution_plan' => [
                ['agent' => 'Analyzer', 'task' => 'Analyze case'],
                ['agent' => 'Researcher', 'task' => 'Find precedents'],
                ['agent' => 'Writer', 'task' => 'Draft report'],
            ],
            'total_steps' => 3,
            'completed_steps' => 1,
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                $collab = $collaborations->first();

                return is_array($collab->execution_plan)
                    && count($collab->execution_plan) === 3;
            });
    }

    /**
     * Test collaboration with shared memory
     *
     * @test
     */
    public function it_displays_collaborations_with_shared_memory(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem with memory',
            'status' => 'in_progress',
            'started_at' => now()->subHours(1),
            'shared_memory' => [
                'findings' => ['fact1', 'fact2'],
                'references' => ['case1', 'case2'],
            ],
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                $collab = $collaborations->first();

                return is_array($collab->shared_memory)
                    && isset($collab->shared_memory['findings']);
            });
    }

    /**
     * Test collaboration with final result
     *
     * @test
     */
    public function it_displays_completed_collaborations_with_final_result(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Completed problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHours(2),
            'final_result' => [
                'conclusion' => 'Analysis complete',
                'confidence' => 0.95,
                'recommendations' => ['rec1', 'rec2'],
            ],
            'synthesis' => 'The collaboration successfully analyzed the problem...',
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                $collab = $collaborations->first();

                return is_array($collab->final_result)
                    && isset($collab->final_result['conclusion']);
            });
    }

    /**
     * Test average duration with no completed collaborations
     *
     * @test
     */
    public function it_handles_average_duration_with_no_completed(): void
    {
        // Only in-progress collaborations
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'In progress',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['avg_duration'] === null || $stats['avg_duration'] === 0;
            });
    }

    /**
     * Test collaboration with agents_involved
     *
     * @test
     */
    public function it_displays_collaborations_with_agents_involved(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Multi-agent problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'agents_involved' => ['Analyzer', 'Researcher', 'Writer'],
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations', function ($collaborations) {
                $collab = $collaborations->first();

                return is_array($collab->agents_involved)
                    && count($collab->agents_involved) === 3;
            });
    }

    /**
     * Test viewDetails with non-existent collaboration
     *
     * @test
     */
    public function it_handles_view_details_with_invalid_id(): void
    {
        Livewire::test(CollaborationDashboard::class)
            ->call('viewDetails', 'non-existent-id')
            ->assertSet('selectedCollaboration', null)
            ->assertSet('showDetails', true); // Still sets to true, but collaboration is null
    }

    /**
     * Test pagination maintains state after viewing details
     *
     * @test
     */
    public function it_maintains_pagination_after_viewing_details(): void
    {
        // Create 30 collaborations
        $collaborations = [];
        for ($i = 0; $i < 30; $i++) {
            $collaborations[] = AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays($i),
            ]);
        }

        $component = Livewire::test(CollaborationDashboard::class)
            ->call('gotoPage', 2)
            ->assertViewHas('collaborations', function ($colls) {
                return $colls->currentPage() === 2;
            });

        // View details
        $component->call('viewDetails', $collaborations[5]->id)
            ->assertSet('showDetails', true);

        // Close details
        $component->call('closeDetails')
            ->assertSet('showDetails', false);

        // Should still be on page 2
        $component->assertViewHas('collaborations', function ($colls) {
            return $colls->currentPage() === 2;
        });
    }

    /**
     * Test component performance with statistics calculation
     *
     * @test
     */
    public function it_calculates_statistics_efficiently(): void
    {
        // Create 50 collaborations with varied data
        for ($i = 0; $i < 50; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => ['analysis', 'research', 'synthesis'][rand(0, 2)],
                'problem_statement' => "Problem {$i}",
                'status' => ['completed', 'in_progress', 'failed'][rand(0, 2)],
                'started_at' => now()->subDays($i),
                'completed_at' => $i % 2 === 0 ? now()->subDays($i)->addHours(2) : null,
                'duration_seconds' => $i % 2 === 0 ? rand(3600, 7200) : null,
                'tokens_used' => rand(1000, 10000),
                'cost_spent' => rand(10, 150) / 100,
            ]);
        }

        $startTime = microtime(true);

        Livewire::test(CollaborationDashboard::class)
            ->assertStatus(200);

        $duration = microtime(true) - $startTime;

        // Should render in less than 2 seconds even with 50 collaborations
        $this->assertLessThan(2.0, $duration, 'Dashboard should render efficiently');
    }

    /**
     * Test component with concurrent collaborations
     *
     * @test
     */
    public function it_handles_multiple_concurrent_collaborations(): void
    {
        // Create 3 in-progress
        for ($i = 0; $i < 3; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Concurrent Problem {$i}",
                'status' => 'in_progress',
                'started_at' => now()->subHours($i + 1),
                'total_steps' => 10,
                'completed_steps' => rand(1, 9),
            ]);
        }

        // Create some completed
        for ($i = 0; $i < 5; $i++) {
            AgentCollaboration::create([
                'orchestrator' => 'TestOrchestrator',
                'problem_type' => 'analysis',
                'problem_statement' => "Completed Problem {$i}",
                'status' => 'completed',
                'started_at' => now()->subDays($i + 1),
                'completed_at' => now()->subDays($i + 1)->addHours(2),
            ]);
        }

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['total'] === 8
                    && $stats['in_progress'] === 3
                    && $stats['completed'] === 5;
            });
    }

    /**
     * Test component view structure
     *
     * @test
     */
    public function it_passes_correct_data_to_view(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Test Problem',
            'status' => 'completed',
            'started_at' => now()->subDay(),
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('collaborations')
            ->assertViewHas('stats')
            ->assertViewHas('stats', function ($stats) {
                // Use array_key_exists instead of isset because isset returns false for null values
                return array_key_exists('total', $stats)
                    && array_key_exists('completed', $stats)
                    && array_key_exists('in_progress', $stats)
                    && array_key_exists('failed', $stats)
                    && array_key_exists('recent_7_days', $stats)
                    && array_key_exists('avg_duration', $stats)
                    && array_key_exists('total_tokens', $stats)
                    && array_key_exists('total_cost', $stats);
            });
    }

    /**
     * Test component initial state
     *
     * @test
     */
    public function it_initializes_with_correct_default_state(): void
    {
        Livewire::test(CollaborationDashboard::class)
            ->assertSet('selectedCollaboration', null)
            ->assertSet('showDetails', false);
    }

    /**
     * Test component with zero duration
     *
     * @test
     */
    public function it_handles_collaborations_with_zero_duration(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Instant completion',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'duration_seconds' => 0,
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('stats', function ($stats) {
                return $stats['avg_duration'] === 0.0;
            });
    }

    /**
     * Test cost calculation precision
     *
     * @test
     */
    public function it_calculates_cost_with_correct_precision(): void
    {
        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 1',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'cost_spent' => 1.2345,
        ]);

        AgentCollaboration::create([
            'orchestrator' => 'TestOrchestrator',
            'problem_type' => 'analysis',
            'problem_statement' => 'Problem 2',
            'status' => 'completed',
            'started_at' => now()->subDays(2),
            'cost_spent' => 2.3456,
        ]);

        Livewire::test(CollaborationDashboard::class)
            ->assertViewHas('stats', function ($stats) {
                // Should sum correctly with decimal precision
                return abs($stats['total_cost'] - 3.5801) < 0.01;
            });
    }
}
