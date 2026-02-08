<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;
use Tests\UsesTestDatabase;

class OpenAILogViewerTest extends DuskTestCase
{
    use MocksExternalApis, UsesTestDatabase;

    protected function setUpTraits()
    {
        $uses = parent::setUpTraits();
        if (isset($uses[MocksExternalApis::class])) {
            $this->mockAllExternalApis();
        }

        return $uses;
    }
    // ✅ No DatabaseTransactions - browser needs to see committed data

    protected User $user;

    protected string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->logPath = storage_path('logs/openai.log');

        // Ensure log directory exists
        if (! File::exists(dirname($this->logPath))) {
            File::makeDirectory(dirname($this->logPath), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        parent::tearDown();
    }

    /**
     * Test log viewer displays OpenAI requests
     */
    public function test_log_viewer_displays_requests(): void
    {
        // Create sample log entries
        $this->createSampleLogEntries();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/openai/logs')
                ->waitForLivewire()
                ->assertSee('OpenAI Log Viewer')
                // Should display log entries
                ->waitFor('@log-entries', 20)
                ->assertPresent('@log-entry')
                // Should show request details
                ->assertSee('openai.request')
                ->assertSee('gpt-4o-mini')
                ->assertSee('chat.completions')
                // Test auto-refresh toggle
                ->assertPresent('@auto-refresh-toggle')
                // Test refresh button
                ->click('@refresh-btn')
                ->pause(500)
                ->assertPresent('@log-entry');
        });
    }

    /**
     * Test log filtering functionality
     */
    public function test_log_filtering(): void
    {
        // Create diverse log entries
        $this->createSampleLogEntries();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/openai/logs')
                ->waitForLivewire()
                ->waitFor('@log-entries', 20)
                // Test search filter
                ->type('@search-input', 'gpt-4o')
                ->pause(1000)
                ->assertSee('gpt-4o')
                // Clear search
                ->clear('@search-input')
                ->pause(500)
                // Test event type filters
                ->assertPresent('@filter-request')
                ->assertPresent('@filter-response')
                ->assertPresent('@filter-error')
                // Disable request filter
                ->click('@filter-request')
                ->pause(500)
                ->assertDontSee('openai.request')
                // Re-enable request filter
                ->click('@filter-request')
                ->pause(500)
                ->assertSee('openai.request')
                // Test clear filters button
                ->type('@search-input', 'test')
                ->click('@clear-filters-btn')
                ->pause(500)
                ->assertInputValue('@search-input', '');
        });
    }

    /**
     * Test cost metrics display
     */
    public function test_cost_metrics_display(): void
    {
        // Create log entries with token/cost information
        $this->createLogEntriesWithMetrics();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/openai/logs')
                ->waitForLivewire()
                ->waitFor('@log-entries', 20)
                // Should display token counts
                ->waitFor('@log-entry', 15)
                // Look for token information in entries
                ->assertSeeIn('@log-entry', 'tokens')
                // Test filtering by request ID to see full conversation
                ->click('@log-entry') // Click first entry to see details
                ->pause(500)
                ->assertPresent('@entry-details')
                // Should show context data including tokens
                ->assertSee('context')
                ->assertSee('request_id')
                // Test limit adjustment
                ->assertPresent('@limit-select')
                ->select('@limit-select', '50')
                ->pause(1000)
                ->assertPresent('@log-entry');
        });
    }

    /**
     * Create sample log entries for testing
     */
    protected function createSampleLogEntries(): void
    {
        $entries = [
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req_test_001',
                    'model' => 'gpt-4o-mini',
                    'endpoint' => 'chat.completions',
                    'messages_count' => 2,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->toIso8601String(),
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req_test_001',
                    'model' => 'gpt-4o-mini',
                    'completion_tokens' => 150,
                    'prompt_tokens' => 50,
                    'total_tokens' => 200,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->toIso8601String(),
            ],
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req_test_002',
                    'model' => 'gpt-4o',
                    'endpoint' => 'chat.completions',
                    'messages_count' => 5,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->subMinutes(5)->toIso8601String(),
            ],
        ];

        $logContent = '';
        foreach ($entries as $entry) {
            $logContent .= json_encode($entry)."\n";
        }

        File::put($this->logPath, $logContent);
    }

    /**
     * Create log entries with detailed metrics
     */
    protected function createLogEntriesWithMetrics(): void
    {
        $entries = [
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req_metrics_001',
                    'model' => 'gpt-4o-mini',
                    'endpoint' => 'chat.completions',
                    'messages_count' => 3,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->toIso8601String(),
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req_metrics_001',
                    'model' => 'gpt-4o-mini',
                    'completion_tokens' => 250,
                    'prompt_tokens' => 100,
                    'total_tokens' => 350,
                    'cost_usd' => 0.0001,
                    'duration_ms' => 1500,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->toIso8601String(),
            ],
            [
                'message' => 'openai.request',
                'context' => [
                    'request_id' => 'req_metrics_002',
                    'model' => 'gpt-4o',
                    'endpoint' => 'embeddings',
                    'input_count' => 1,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'message' => 'openai.response',
                'context' => [
                    'request_id' => 'req_metrics_002',
                    'model' => 'text-embedding-3-large',
                    'total_tokens' => 50,
                    'cost_usd' => 0.00001,
                    'duration_ms' => 500,
                ],
                'level_name' => 'INFO',
                'channel' => 'openai',
                'datetime' => now()->subMinutes(2)->toIso8601String(),
            ],
        ];

        $logContent = '';
        foreach ($entries as $entry) {
            $logContent .= json_encode($entry)."\n";
        }

        File::put($this->logPath, $logContent);
    }
}
