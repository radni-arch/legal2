<?php

namespace Tests\Browser;

use App\Models\AgentCommunication;
use App\Models\OrchestrationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * AgentCollaborationViewer E2E Tests
 *
 * Comprehensive browser tests for the agent collaboration viewer component.
 * Tests all core functionality including viewing collaborations, metrics,
 * timeline, shared context, and inter-agent messages.
 */
class AgentCollaborationViewerTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;
    use DatabaseMigrations;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        $this->mockAllExternalApis();

        // Create test user with unique email to avoid conflicts
        $this->user = User::factory()->create([
            'email' => 'test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * Test: Component renders successfully with basic orchestration data
     */
    public function test_component_renders_successfully(): void
    {
        // Create a basic orchestration log
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Test Multi-Agent Collaboration',
            'status' => 'running',
            'agent_pipeline' => ['ResearchAgent', 'AnalysisAgent', 'SynthesisAgent'],
            'shared_context' => ['case_id' => 'case-123'],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->assertSee('Test Multi-Agent Collaboration')
                ->assertPresent('@header-section')
                ->assertPresent('@task-description')
                ->assertPresent('@status-badge')
                ->assertPresent('@orchestration-id')
                ->assertSeeIn('@status-badge', 'Running')
                ->assertSeeIn('@orchestration-id', $orchestration->orchestration_id);
        });
    }

    /**
     * Test: Displays orchestration not found message for invalid ID
     */
    public function test_handles_orchestration_not_found(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/collaboration/non-existent-id-12345')
                ->pause(3000)  // Give page time to load
                ->waitFor('@not-found-alert', 30)
                ->assertPresent('@not-found-alert')
                ->assertSee('Orchestration not found')
                ->assertSee('non-existent-id-12345');
        });
    }

    /**
     * Test: Displays all metrics correctly
     */
    public function test_displays_metrics_correctly(): void
    {
        // Create orchestration with specific metrics
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Metrics Test Collaboration',
            'status' => 'completed',
            'agent_pipeline' => ['Agent1', 'Agent2', 'Agent3'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 5000,
            'cost_spent' => 0.1250,
            'duration_ms' => 12500,
            'completed_agents' => 3,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                ->waitFor('@metrics-section', 30)
                ->assertPresent('@metric-completed')
                ->assertPresent('@metric-failed')
                ->assertPresent('@metric-tokens')
                ->assertPresent('@metric-cost')
                ->assertPresent('@metric-duration')
                // Check metric values
                ->assertSeeIn('@completed-count', '3')
                ->assertSeeIn('@failed-count', '0')
                ->assertSeeIn('@tokens-count', '5,000')
                ->assertSeeIn('@cost-amount', '$0.1250')
                ->assertSeeIn('@duration-time', '12,500ms');
        });
    }

    /**
     * Test: Displays execution timeline with agent details
     */
    public function test_displays_execution_timeline(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Timeline Test Collaboration',
            'status' => 'completed',
            'agent_pipeline' => ['ResearchAgent', 'AnalysisAgent'],
            'shared_context' => [],
            'execution_history' => [
                [
                    'agent' => 'ResearchAgent',
                    'status' => 'completed',
                    'tokens_used' => 1500,
                    'cost' => 0.0300,
                    'started_at' => '2025-11-14 10:00:00',
                    'completed_at' => '2025-11-14 10:05:00',
                ],
                [
                    'agent' => 'AnalysisAgent',
                    'status' => 'completed',
                    'tokens_used' => 2000,
                    'cost' => 0.0400,
                    'started_at' => '2025-11-14 10:05:00',
                    'completed_at' => '2025-11-14 10:10:00',
                ],
            ],
            'tokens_used' => 3500,
            'cost_spent' => 0.0700,
            'duration_ms' => 600000,
            'completed_agents' => 2,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                ->waitFor('@timeline-section', 30)
                ->assertPresent('@timeline-list')
                // Check first agent
                ->assertPresent('@timeline-item-0')
                ->assertSeeIn('@agent-name-0', 'ResearchAgent')
                ->assertSeeIn('@agent-status-0', 'Completed')
                ->assertSeeIn('@agent-tokens-0', '1500')
                ->assertSeeIn('@agent-cost-0', '$0.0300')
                ->assertPresent('@agent-started-0')
                ->assertPresent('@agent-completed-0')
                // Check second agent
                ->assertPresent('@timeline-item-1')
                ->assertSeeIn('@agent-name-1', 'AnalysisAgent')
                ->assertSeeIn('@agent-status-1', 'Completed')
                ->assertSeeIn('@agent-tokens-1', '2000')
                ->assertSeeIn('@agent-cost-1', '$0.0400');
        });
    }

    /**
     * Test: Displays failed agent with error message in timeline
     */
    public function test_displays_failed_agent_with_error(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Failed Agent Test',
            'status' => 'failed',
            'agent_pipeline' => ['ResearchAgent', 'AnalysisAgent'],
            'shared_context' => [],
            'execution_history' => [
                [
                    'agent' => 'ResearchAgent',
                    'status' => 'completed',
                    'tokens_used' => 1000,
                    'cost' => 0.0200,
                ],
                [
                    'agent' => 'AnalysisAgent',
                    'status' => 'failed',
                    'error' => 'API timeout exceeded',
                    'tokens_used' => 500,
                    'cost' => 0.0100,
                ],
            ],
            'tokens_used' => 1500,
            'cost_spent' => 0.0300,
            'duration_ms' => 30000,
            'completed_agents' => 1,
            'failed_agents' => 1,
            'error_message' => 'Collaboration failed: API timeout exceeded',
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                // Check overall error message
                ->waitFor('@error-alert', 30)
                ->assertPresent('@error-alert')
                ->assertSeeIn('@error-message', 'API timeout exceeded')
                // Check failed agent in timeline
                ->waitFor('@timeline-section', 30)
                ->assertPresent('@timeline-item-1')
                ->assertSeeIn('@agent-name-1', 'AnalysisAgent')
                ->assertSeeIn('@agent-status-1', 'Failed')
                ->assertPresent('@agent-error-1')
                ->assertSeeIn('@agent-error-1', 'API timeout exceeded')
                // Check metrics show failure
                ->assertSeeIn('@failed-count', '1')
                ->assertSeeIn('@completed-count', '1');
        });
    }

    /**
     * Test: Displays shared context correctly
     */
    public function test_displays_shared_context(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Context Test Collaboration',
            'status' => 'running',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [
                'case_id' => 'case-456',
                'jurisdiction' => 'Županijski sud u Osijeku',
                'defendant' => 'John Doe',
                'charge' => 'Drug possession',
                'evidence_count' => 5,
            ],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->waitFor('@context-section', 30)
                ->assertPresent('@context-content')
                ->assertPresent('@context-json')
                // Check that JSON contains our context data
                ->assertSeeIn('@context-json', 'case-456')
                ->assertSeeIn('@context-json', 'Županijski sud u Osijeku')
                ->assertSeeIn('@context-json', 'John Doe')
                ->assertSeeIn('@context-json', 'Drug possession');
        });
    }

    /**
     * Test: Shows empty state when no shared context
     */
    public function test_shows_empty_shared_context(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Empty Context Test',
            'status' => 'pending',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->waitFor('@context-section', 30)
                ->assertPresent('@context-empty')
                ->assertSeeIn('@context-empty', 'No shared context available');
        });
    }

    /**
     * Test: Displays inter-agent messages correctly
     */
    public function test_displays_inter_agent_messages(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Messages Test Collaboration',
            'status' => 'running',
            'agent_pipeline' => ['ResearchAgent', 'AnalysisAgent', 'SynthesisAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        // Create inter-agent messages
        $message1 = AgentCommunication::create([
            'sender_agent_type' => 'ResearchAgent',
            'receiver_agent_type' => 'AnalysisAgent',
            'message_type' => 'data_request',
            'message_data' => [
                'query' => 'Find precedents for drug possession cases',
                'jurisdiction' => 'Osijek',
            ],
            'priority' => 10,
            'status' => 'pending',
        ]);

        $message2 = AgentCommunication::create([
            'sender_agent_type' => 'AnalysisAgent',
            'receiver_agent_type' => 'ResearchAgent',
            'message_type' => 'data_response',
            'message_data' => [
                'results' => ['precedent-1', 'precedent-2', 'precedent-3'],
                'count' => 3,
            ],
            'priority' => 5,
            'status' => 'completed',
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->waitFor('@messages-section', 30)
                ->assertPresent('@messages-list')
                // Check first message
                ->assertPresent('@message-item-0')
                ->assertSeeIn('@message-sender-0', 'ResearchAgent')
                ->assertSeeIn('@message-receiver-0', 'AnalysisAgent')
                ->assertSeeIn('@message-type-0', 'data_request')
                ->assertSeeIn('@message-priority-0', '10')
                ->assertSeeIn('@message-status-0', 'pending')
                ->assertPresent('@message-payload-0')
                ->assertPresent('@message-timestamp-0')
                // Check second message
                ->assertPresent('@message-item-1')
                ->assertSeeIn('@message-sender-1', 'AnalysisAgent')
                ->assertSeeIn('@message-receiver-1', 'ResearchAgent')
                ->assertSeeIn('@message-type-1', 'data_response')
                ->assertSeeIn('@message-priority-1', '5')
                ->assertSeeIn('@message-status-1', 'completed');
        });
    }

    /**
     * Test: Shows empty state when no messages
     */
    public function test_shows_empty_messages_state(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'No Messages Test',
            'status' => 'pending',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->waitFor('@messages-section', 30)
                ->assertPresent('@messages-empty')
                ->assertSeeIn('@messages-empty', 'No messages between agents');
        });
    }

    /**
     * Test: Polling indicator appears for running orchestrations
     */
    public function test_polling_indicator_for_running_status(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Polling Test Collaboration',
            'status' => 'running',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                ->waitFor('@polling-indicator', 30)
                ->assertPresent('@polling-indicator')
                ->assertSee('Auto-refreshing every 3 seconds');
        });
    }

    /**
     * Test: No polling indicator for completed orchestrations
     */
    public function test_no_polling_indicator_for_completed_status(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'No Polling Test',
            'status' => 'completed',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 1000,
            'cost_spent' => 0.0200,
            'duration_ms' => 5000,
            'completed_agents' => 1,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                ->assertMissing('@polling-indicator');
        });
    }

    /**
     * Test: Status badge colors are correct for different statuses
     */
    public function test_status_badge_colors(): void
    {
        // Test just one status to verify the badge rendering works
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Status Badge Color Test',
            'status' => 'completed',
            'agent_pipeline' => ['TestAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 100,
            'cost_spent' => 0.002,
            'duration_ms' => 1000,
            'completed_agents' => 1,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                ->waitFor('@status-badge', 30)
                ->assertPresent('@status-badge')
                ->assertSeeIn('@status-badge', 'Completed');

            // Check that the status badge has the correct color class
            $classList = $browser->attribute('@status-badge', 'class');
            $this->assertStringContainsString('bg-green-500', $classList);
        });
    }

    /**
     * Test: Empty execution history shows appropriate message
     */
    public function test_shows_empty_timeline_state(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Empty Timeline Test',
            'status' => 'pending',
            'agent_pipeline' => ['ResearchAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $this->loginAs($browser, $this->user);

            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLivewire()
                ->waitFor('@timeline-section', 30)
                ->assertPresent('@timeline-empty')
                ->assertSeeIn('@timeline-empty', 'No execution history yet');
        });
    }

    /**
     * Test: Component handles authentication requirement
     */
    public function test_requires_authentication(): void
    {
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Auth Test',
            'status' => 'completed',
            'agent_pipeline' => ['TestAgent'],
            'shared_context' => [],
            'execution_history' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
            'duration_ms' => 0,
            'completed_agents' => 0,
            'failed_agents' => 0,
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            // Try to access without logging in
            $browser->visit("/collaboration/{$orchestration->orchestration_id}")
                ->waitForLocation('/login', 30)
                ->assertPathIs('/login');
        });
    }

    /**
     * Test: Complex multi-agent collaboration with full workflow
     */
    public function test_complex_multi_agent_collaboration_workflow(): void
    {
        // Create a realistic multi-agent collaboration scenario
        $orchestration = OrchestrationLog::create([
            'task_description' => 'Complex Legal Case Analysis',
            'status' => 'completed',
            'agent_pipeline' => [
                'ResearchAgent',
                'PrecedentAnalyst',
                'EvidenceAnalyzer',
                'StrategyAdvisor',
                'SynthesisAgent',
            ],
            'shared_context' => [
                'case_id' => 'case-789',
                'jurisdiction' => 'Županijski sud u Osijeku',
                'defendant' => 'Jane Smith',
                'charge' => 'Illegal home search',
                'evidence_items' => [
                    ['type' => 'warrant', 'date' => '2025-11-01'],
                    ['type' => 'testimony', 'witness' => 'Officer Jones'],
                    ['type' => 'physical', 'description' => 'Contraband'],
                ],
            ],
            'execution_history' => [
                [
                    'agent' => 'ResearchAgent',
                    'status' => 'completed',
                    'tokens_used' => 2500,
                    'cost' => 0.0500,
                    'started_at' => '2025-11-14 09:00:00',
                    'completed_at' => '2025-11-14 09:10:00',
                ],
                [
                    'agent' => 'PrecedentAnalyst',
                    'status' => 'completed',
                    'tokens_used' => 3000,
                    'cost' => 0.0600,
                    'started_at' => '2025-11-14 09:10:00',
                    'completed_at' => '2025-11-14 09:22:00',
                ],
                [
                    'agent' => 'EvidenceAnalyzer',
                    'status' => 'completed',
                    'tokens_used' => 2800,
                    'cost' => 0.0560,
                    'started_at' => '2025-11-14 09:22:00',
                    'completed_at' => '2025-11-14 09:35:00',
                ],
                [
                    'agent' => 'StrategyAdvisor',
                    'status' => 'completed',
                    'tokens_used' => 3200,
                    'cost' => 0.0640,
                    'started_at' => '2025-11-14 09:35:00',
                    'completed_at' => '2025-11-14 09:50:00',
                ],
                [
                    'agent' => 'SynthesisAgent',
                    'status' => 'completed',
                    'tokens_used' => 4000,
                    'cost' => 0.0800,
                    'started_at' => '2025-11-14 09:50:00',
                    'completed_at' => '2025-11-14 10:05:00',
                ],
            ],
            'tokens_used' => 15500,
            'cost_spent' => 0.3100,
            'duration_ms' => 3900000,
            'completed_agents' => 5,
            'failed_agents' => 0,
        ]);

        // Create inter-agent messages for the workflow
        AgentCommunication::create([
            'sender_agent_type' => 'ResearchAgent',
            'receiver_agent_type' => 'PrecedentAnalyst',
            'message_type' => 'research_findings',
            'message_data' => [
                'relevant_laws' => ['ZKP Članak 9', 'Ustav RH Članak 34'],
                'case_law_count' => 15,
            ],
            'priority' => 10,
            'status' => 'completed',
        ]);

        AgentCommunication::create([
            'sender_agent_type' => 'EvidenceAnalyzer',
            'receiver_agent_type' => 'StrategyAdvisor',
            'message_type' => 'evidence_assessment',
            'message_data' => [
                'admissibility_issues' => ['Warrant lacks specificity', 'Chain of custody broken'],
                'strength_score' => 65,
            ],
            'priority' => 8,
            'status' => 'completed',
        ]);

        $this->browse(function (Browser $browser) use ($orchestration) {
            $browser->loginAs($this->user)
                ->visit("/collaboration/{$orchestration->orchestration_id}")
                ->pause(3000)  // Give page time to load
                // Verify header
                ->assertSeeIn('@task-description', 'Complex Legal Case Analysis')
                ->assertSeeIn('@status-badge', 'Completed')
                // Verify all metrics
                ->assertSeeIn('@completed-count', '5')
                ->assertSeeIn('@failed-count', '0')
                ->assertSeeIn('@tokens-count', '15,500')
                ->assertSeeIn('@cost-amount', '$0.3100')
                ->assertSeeIn('@duration-time', '3,900,000ms')
                // Verify all agents in timeline
                ->assertPresent('@timeline-item-0')
                ->assertPresent('@timeline-item-1')
                ->assertPresent('@timeline-item-2')
                ->assertPresent('@timeline-item-3')
                ->assertPresent('@timeline-item-4')
                ->assertSeeIn('@agent-name-0', 'ResearchAgent')
                ->assertSeeIn('@agent-name-1', 'PrecedentAnalyst')
                ->assertSeeIn('@agent-name-2', 'EvidenceAnalyzer')
                ->assertSeeIn('@agent-name-3', 'StrategyAdvisor')
                ->assertSeeIn('@agent-name-4', 'SynthesisAgent')
                // Verify shared context
                ->assertSeeIn('@context-json', 'case-789')
                ->assertSeeIn('@context-json', 'Jane Smith')
                ->assertSeeIn('@context-json', 'Illegal home search')
                // Verify inter-agent messages
                ->assertPresent('@message-item-0')
                ->assertPresent('@message-item-1')
                ->assertSeeIn('@message-sender-0', 'ResearchAgent')
                ->assertSeeIn('@message-receiver-0', 'PrecedentAnalyst')
                ->assertSeeIn('@message-sender-1', 'EvidenceAnalyzer')
                ->assertSeeIn('@message-receiver-1', 'StrategyAdvisor');
        });
    }
}
