<?php

namespace App\Console\Commands;

use App\Services\Agent\AgentManager;
use Illuminate\Console\Command;

class AgentStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:status
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show status of all registered agent drivers';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $drivers = $manager->getRegisteredDrivers();
        $driversInfo = $manager->getAllDriversInfo();

        if (empty($drivers)) {
            $this->warn('No agent drivers registered.');
            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($driversInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->info('Agent Drivers Status');
        $this->info('====================');
        $this->newLine();

        $tableRows = [];

        foreach ($driversInfo as $driverKey => $info) {
            $availability = $info['available'] ? '<fg=green>YES</>' : '<fg=red>NO</>';
            $defaultMarker = $info['default'] ? ' <fg=yellow>(default)</>' : '';
            $fallbackPosition = $info['fallback_position'] !== null
                ? "#{$info['fallback_position']}"
                : '-';
            $capabilities = implode(', ', $info['capabilities']);

            $tableRows[] = [
                $driverKey . $defaultMarker,
                $info['name'],
                $availability,
                $capabilities,
                $fallbackPosition,
            ];
        }

        $this->table(
            ['Driver', 'Name', 'Available', 'Capabilities', 'Fallback'],
            $tableRows
        );

        $this->newLine();

        // Summary
        $available = collect($driversInfo)->filter(fn($i) => $i['available'])->count();
        $total = count($driversInfo);

        $this->info("Summary: {$available}/{$total} drivers available");

        return 0;
    }
}
