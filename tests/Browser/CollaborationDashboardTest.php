<?php

namespace Tests\Browser;

use App\Models\AgentCollaboration;
use App\Models\AgentExecution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * CollaborationDashboard E2E Tests
 *
 * Comprehensive browser tests for the multi-agent collaboration dashboard component.
 * Tests all core functionality including statistics, collaboration list, pagination,
 * and details modal with agent executions.
 */
class CollaborationDashboardTest extends DuskTestCase
{
    use AuthenticatesUser, DatabaseMigrations, MocksExternalApis;

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
     * Test: Component renders successfully with empty state
     */
    public function test_component_renders_with_empty_state(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->assertPresent('@collaboration-dashboard')
                ->assertPresent('@stats-cards')
                ->assertPresent('@collaborations-table')
                // Check all stat cards are present
                ->assertPresent('@stat-total')
                ->assertPresent('@stat-completed')
                ->assertPresent('@stat-duration')
                ->assertPresent('@stat-cost')
                // Check empty state
                ->assertPresent('@empty-state')
                ->assertSeeIn('@empty-state', 'No collaborations found')
                // Check all stats are zero
                ->assertSeeIn('@total-count', '0')
                ->assertSeeIn('@completed-count', '0')
                ->assertSeeIn('@avg-duration', '0s')
                ->assertSeeIn('@total-cost', '$0.00');
        });
    }

    /**
     * Test: Displays statistics correctly with collaborations
     */
    public function test_displays_statistics_correctly(): void
    {
        // Create test collaborations with different statuses
        AgentCollaboration::factory()->completed()->create([
            'tokens_used' => 5000,
            'cost_spent' => 1.25,
            'duration_seconds' => 120,
        ]);
        AgentCollaboration::factory()->completed()->create([
            'tokens_used' => 3000,
            'cost_spent' => 0.75,
            'duration_seconds' => 80,
        ]);
        AgentCollaboration::factory()->inProgress()->create([
            'tokens_used' => 1000,
            'cost_spent' => 0.25,
        ]);
        AgentCollaboration::factory()->failed()->create([
            'tokens_used' => 500,
            'cost_spent' => 0.13,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Check total count
                ->assertSeeIn('@total-count', '4')
                // Check status counts
                ->assertSeeIn('@completed-count', '2')
                ->assertSeeIn('@status-details', '1 in progress')
                ->assertSeeIn('@status-details', '1 failed')
                // Check average duration (only completed: (120 + 80) / 2 = 100)
                ->assertSeeIn('@avg-duration', '100s')
                // Check total cost (1.25 + 0.75 + 0.25 + 0.13 = 2.38)
                ->assertSeeIn('@total-cost', '$2.38')
                // Check total tokens (5000 + 3000 + 1000 + 500 = 9500)
                ->assertSeeIn('@total-tokens', '9,500');
        });
    }

    /**
     * Test: Displays collaborations in table correctly
     */
    public function test_displays_collaborations_in_table(): void
    {
        // Create test collaborations with executions
        $collaboration1 = AgentCollaboration::factory()->completed()->create([
            'problem_statement' => 'Analyze drug possession case for proportionality',
            'problem_type' => 'legal-analysis',
            'tokens_used' => 5000,
            'cost_spent' => 1.25,
            'duration_seconds' => 120,
            'total_steps' => 5,
            'completed_steps' => 5,
        ]);

        // Create agent executions
        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration1->id,
            'agent_name' => 'research-agent',
            'execution_order' => 1,
        ]);
        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration1->id,
            'agent_name' => 'analysis-agent',
            'execution_order' => 2,
        ]);

        $collaboration2 = AgentCollaboration::factory()->inProgress()->create([
            'problem_statement' => 'Review evidence admissibility for home search case',
            'problem_type' => 'evidence-review',
            'total_steps' => 3,
            'completed_steps' => 1,
        ]);

        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration2->id,
            'agent_name' => 'evidence-agent',
            'execution_order' => 1,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Check first collaboration (most recent)
                ->assertPresent('@collaboration-row-0')
                ->assertSeeIn('@problem-0', 'Review evidence admissibility')
                ->assertSeeIn('@type-0', 'evidence-review')
                ->assertSeeIn('@status-0', 'In Progress')
                ->assertSeeIn('@agents-0', '1 agents')
                ->assertSeeIn('@steps-0', '1/3 steps')
                // Check second collaboration
                ->assertPresent('@collaboration-row-1')
                ->assertSeeIn('@problem-1', 'Analyze drug possession')
                ->assertSeeIn('@type-1', 'legal-analysis')
                ->assertSeeIn('@status-1', 'Completed')
                ->assertSeeIn('@agents-1', '2 agents')
                ->assertSeeIn('@steps-1', '5/5 steps')
                ->assertSeeIn('@duration-1', '120s')
                ->assertSeeIn('@cost-1', '$1.2500')
                // Check view details buttons
                ->assertPresent('@view-details-0')
                ->assertPresent('@view-details-1');
        });
    }

    /**
     * Test: Opens details modal when clicking view details
     */
    public function test_opens_details_modal(): void
    {
        $collaboration = AgentCollaboration::factory()->completed()->create([
            'problem_statement' => 'Comprehensive case analysis for criminal defense',
            'problem_type' => 'case-analysis',
            'synthesis' => 'Analysis completed successfully with key findings on evidence admissibility and procedural violations.',
            'tokens_used' => 15000,
            'cost_spent' => 3.75,
            'duration_seconds' => 300,
        ]);

        // Create agent executions
        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'research-agent',
            'agent_role' => 'Research Specialist',
            'task_description' => 'Gather relevant case law and statutes',
            'execution_order' => 1,
            'output' => [
                'summary' => 'Found 15 relevant precedents and 3 applicable statutes',
                'findings' => ['precedent-1', 'precedent-2'],
            ],
            'tokens_used' => 5000,
            'cost_spent' => 1.25,
            'duration_ms' => 60000,
        ]);

        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'analysis-agent',
            'agent_role' => 'Legal Analyst',
            'task_description' => 'Analyze evidence and identify legal issues',
            'execution_order' => 2,
            'output' => [
                'summary' => 'Identified 3 major legal issues with supporting evidence',
                'issues' => ['issue-1', 'issue-2', 'issue-3'],
            ],
            'tokens_used' => 10000,
            'cost_spent' => 2.50,
            'duration_ms' => 120000,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Click view details button
                ->click('@view-details-0')
                ->pause(1000)
                // Check modal is visible
                ->assertPresent('@details-modal')
                ->assertPresent('@modal-content')
                ->assertPresent('@modal-header')
                ->assertSeeIn('@modal-title', 'Collaboration Details')
                // Check problem statement
                ->assertPresent('@problem-section')
                ->assertSeeIn('@problem-text', 'Comprehensive case analysis')
                // Check synthesis
                ->assertPresent('@synthesis-section')
                ->assertSeeIn('@synthesis-text', 'Analysis completed successfully')
                // Check agent executions
                ->assertPresent('@executions-section')
                ->assertPresent('@executions-list')
                ->assertPresent('@execution-0')
                ->assertSeeIn('@execution-name-0', 'Research Specialist')
                ->assertSeeIn('@execution-task-0', 'Gather relevant case law')
                ->assertSeeIn('@execution-status-0', 'Completed')
                ->assertPresent('@execution-output-0')
                ->assertSeeIn('@execution-output-0', 'Found 15 relevant precedents')
                ->assertSeeIn('@execution-duration-0', '60s')
                ->assertSeeIn('@execution-tokens-0', '5,000')
                ->assertSeeIn('@execution-cost-0', '$1.2500')
                // Check second execution
                ->assertPresent('@execution-1')
                ->assertSeeIn('@execution-name-1', 'Legal Analyst')
                ->assertSeeIn('@execution-status-1', 'Completed')
                ->assertSeeIn('@execution-duration-1', '120s')
                // Check metadata
                ->assertPresent('@metadata-section')
                ->assertPresent('@session-id')
                ->assertSeeIn('@total-duration', '300s')
                ->assertSeeIn('@metadata-tokens', '15,000')
                ->assertSeeIn('@metadata-cost', '$3.7500');
        });
    }

    /**
     * Test: Closes details modal when clicking close button
     */
    public function test_closes_details_modal(): void
    {
        $collaboration = AgentCollaboration::factory()->completed()->create([
            'problem_statement' => 'Test collaboration for modal close',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Open modal
                ->click('@view-details-0')
                ->pause(1000)
                ->assertPresent('@details-modal')
                // Close modal
                ->click('@close-modal')
                ->pause(1000)
                // Modal should be gone
                ->assertMissing('@details-modal');
        });
    }

    /**
     * Test: Handles collaboration with no synthesis
     */
    public function test_handles_collaboration_without_synthesis(): void
    {
        $collaboration = AgentCollaboration::factory()->inProgress()->create([
            'problem_statement' => 'In-progress collaboration without synthesis',
            'synthesis' => null,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Open modal
                ->click('@view-details-0')
                ->pause(1000)
                ->assertPresent('@details-modal')
                ->assertPresent('@problem-section')
                // Synthesis section should not be present
                ->assertMissing('@synthesis-section');
        });
    }

    /**
     * Test: Handles collaboration with failed executions
     */
    public function test_displays_failed_execution(): void
    {
        $collaboration = AgentCollaboration::factory()->failed()->create([
            'problem_statement' => 'Failed collaboration test',
        ]);

        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'step-1-agent',
            'execution_order' => 1,
        ]);

        AgentExecution::factory()->failed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'step-2-agent',
            'agent_role' => 'Failed Agent',
            'task_description' => 'This execution failed',
            'execution_order' => 2,
            'error_message' => 'API timeout exceeded',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                ->assertSeeIn('@status-0', 'Failed')
                // Open modal
                ->click('@view-details-0')
                ->pause(1000)
                ->assertPresent('@details-modal')
                // Check first execution is completed
                ->assertPresent('@execution-0')
                ->assertSeeIn('@execution-status-0', 'Completed')
                // Check second execution is failed
                ->assertPresent('@execution-1')
                ->assertSeeIn('@execution-status-1', 'Failed')
                ->assertSeeIn('@execution-name-1', 'Failed Agent');
        });
    }

    /**
     * Test: Pagination works correctly
     */
    public function test_pagination_works(): void
    {
        // Create 25 collaborations (more than 20 per page)
        for ($i = 0; $i < 25; $i++) {
            AgentCollaboration::factory()->completed()->create([
                'problem_statement' => "Test collaboration #{$i}",
            ]);
        }

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Should see first 20 rows
                ->assertPresent('@collaboration-row-0')
                ->assertPresent('@collaboration-row-19')
                // Check pagination links exist
                ->assertSee('Next');
        });
    }

    /**
     * Test: Displays different problem types correctly
     */
    public function test_displays_different_problem_types(): void
    {
        AgentCollaboration::factory()->create([
            'problem_statement' => 'Legal research task',
            'problem_type' => 'legal-research',
        ]);

        AgentCollaboration::factory()->create([
            'problem_statement' => 'Contract analysis task',
            'problem_type' => 'contract-analysis',
        ]);

        AgentCollaboration::factory()->create([
            'problem_statement' => 'General task',
            'problem_type' => null, // Should default to 'general'
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Check different problem types are displayed
                ->assertSeeIn('@type-0', 'general')
                ->assertSeeIn('@type-1', 'contract-analysis')
                ->assertSeeIn('@type-2', 'legal-research');
        });
    }

    /**
     * Test: Handles invalid collaboration ID gracefully
     */
    public function test_handles_invalid_collaboration_id(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            // Manually trigger viewDetails with invalid UUID
            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                ->script('Livewire.find(window.Livewire.components.getComponentsByName("collaboration-dashboard")[0]?.id).call("viewDetails", "invalid-uuid")');

            // Should handle gracefully without errors
            $browser->pause(1000)
                ->assertPresent('@collaboration-dashboard');
        });
    }

    /**
     * Test: Component requires authentication
     */
    public function test_requires_authentication(): void
    {
        $this->browse(function (Browser $browser) {
            // Try to access without logging in
            $browser->visit('/test-collaboration-dashboard')
                ->waitForLocation('/login', 20)
                ->assertPathIs('/login');
        });
    }

    /**
     * Test: Displays multiple executions in correct order
     */
    public function test_displays_executions_in_order(): void
    {
        $collaboration = AgentCollaboration::factory()->completed()->create([
            'problem_statement' => 'Multi-step collaboration',
        ]);

        // Create executions in non-sequential order to test ordering
        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'third-agent',
            'agent_role' => 'Third Step',
            'execution_order' => 3,
        ]);

        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'first-agent',
            'agent_role' => 'First Step',
            'execution_order' => 1,
        ]);

        AgentExecution::factory()->completed()->create([
            'collaboration_id' => $collaboration->id,
            'agent_name' => 'second-agent',
            'agent_role' => 'Second Step',
            'execution_order' => 2,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                ->click('@view-details-0')
                ->pause(1000)
                ->assertPresent('@details-modal')
                // Check executions are displayed in correct order
                ->assertSeeIn('@execution-name-0', 'First Step')
                ->assertSeeIn('@execution-name-1', 'Second Step')
                ->assertSeeIn('@execution-name-2', 'Third Step');
        });
    }

    /**
     * Test: Complex scenario with mixed statuses and multiple agents
     */
    public function test_complex_collaboration_scenario(): void
    {
        // Create a complex collaboration with multiple agents and mixed outcomes
        $collaboration = AgentCollaboration::factory()->completed()->create([
            'problem_statement' => 'Complex multi-agent legal case analysis with evidence review and strategy planning',
            'problem_type' => 'case-strategy',
            'synthesis' => 'Comprehensive analysis completed with actionable recommendations for defense strategy.',
            'total_steps' => 5,
            'completed_steps' => 5,
            'tokens_used' => 25000,
            'cost_spent' => 6.25,
            'duration_seconds' => 450,
        ]);

        // Create 5 agent executions
        $agents = [
            ['name' => 'evidence-analyzer', 'role' => 'Evidence Analyst', 'task' => 'Review all evidence for admissibility', 'tokens' => 5000, 'duration' => 90000],
            ['name' => 'law-researcher', 'role' => 'Legal Researcher', 'task' => 'Find relevant case law and statutes', 'tokens' => 6000, 'duration' => 120000],
            ['name' => 'precedent-matcher', 'role' => 'Precedent Matcher', 'task' => 'Match case facts to precedents', 'tokens' => 4000, 'duration' => 60000],
            ['name' => 'strategy-advisor', 'role' => 'Strategy Advisor', 'task' => 'Develop defense strategy', 'tokens' => 7000, 'duration' => 110000],
            ['name' => 'synthesis-agent', 'role' => 'Synthesis Agent', 'task' => 'Compile final recommendations', 'tokens' => 3000, 'duration' => 70000],
        ];

        foreach ($agents as $index => $agent) {
            AgentExecution::factory()->completed()->create([
                'collaboration_id' => $collaboration->id,
                'agent_name' => $agent['name'],
                'agent_role' => $agent['role'],
                'task_description' => $agent['task'],
                'execution_order' => $index + 1,
                'tokens_used' => $agent['tokens'],
                'duration_ms' => $agent['duration'],
                'cost_spent' => ($agent['tokens'] / 1000000) * 0.15,
                'output' => [
                    'summary' => "Successfully completed {$agent['task']}",
                    'details' => ['result' => 'success'],
                ],
            ]);
        }

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-collaboration-dashboard')
                ->waitForLivewire()
                ->pause(1000)
                // Check collaboration in table
                ->assertPresent('@collaboration-row-0')
                ->assertSeeIn('@problem-0', 'Complex multi-agent')
                ->assertSeeIn('@type-0', 'case-strategy')
                ->assertSeeIn('@status-0', 'Completed')
                ->assertSeeIn('@agents-0', '5 agents')
                ->assertSeeIn('@steps-0', '5/5 steps')
                ->assertSeeIn('@duration-0', '450s')
                ->assertSeeIn('@cost-0', '$6.2500')
                // Open details modal
                ->click('@view-details-0')
                ->pause(1000)
                ->assertPresent('@details-modal')
                // Check all executions are present
                ->assertPresent('@execution-0')
                ->assertPresent('@execution-1')
                ->assertPresent('@execution-2')
                ->assertPresent('@execution-3')
                ->assertPresent('@execution-4')
                // Verify execution details
                ->assertSeeIn('@execution-name-0', 'Evidence Analyst')
                ->assertSeeIn('@execution-name-4', 'Synthesis Agent')
                // Check synthesis
                ->assertPresent('@synthesis-section')
                ->assertSeeIn('@synthesis-text', 'actionable recommendations')
                // Check metadata
                ->assertSeeIn('@metadata-tokens', '25,000')
                ->assertSeeIn('@metadata-cost', '$6.2500')
                ->assertSeeIn('@total-duration', '450s');
        });
    }
}
