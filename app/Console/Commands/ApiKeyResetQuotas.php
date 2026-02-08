<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiRotator\ApiKeyRotatorService;

class ApiKeyResetQuotas extends Command
{
    protected $signature = 'apikey:reset-quotas
                            {--type=all : minute|daily|all}';

    protected $description = 'Reset API key usage quotas';

    public function handle(ApiKeyRotatorService $rotator): int
    {
        $type = $this->option('type');

        if (in_array($type, ['minute', 'all'])) {
            $count = $rotator->resetMinuteCounters();
            $this->info("Reset minute counters for {$count} keys.");
        }

        if (in_array($type, ['daily', 'all'])) {
            $count = $rotator->resetDailyCounters();
            $this->info("Reset daily counters for {$count} keys.");
        }

        return 0;
    }
}
