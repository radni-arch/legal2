<?php

namespace Tests\Feature\Commands;

use App\Jobs\MatchCaseToDecisionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchPpPrzCasesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_has_matching_job_dispatch(): void
    {
        // Verify the command contains the job dispatch line
        $commandPath = app_path('Console/Commands/FetchPpPrzCases.php');
        $content = file_get_contents($commandPath);

        $this->assertStringContainsString('MatchCaseToDecisionJob', $content);
        $this->assertStringContainsString('use App\Jobs\MatchCaseToDecisionJob', $content);
    }
}
