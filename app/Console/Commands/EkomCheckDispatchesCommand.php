<?php

namespace App\Console\Commands;

use App\Services\Ekom\OtpravakService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EkomCheckDispatchesCommand extends Command
{
    protected $signature = 'ekom:check-dispatches
                            {--hours=24 : Hours before deadline to consider "approaching"}
                            {--alert : Send alerts for approaching deadlines}
                            {--json : Output as JSON}';

    protected $description = 'Check for unconfirmed dispatches approaching legal deadlines';

    public function handle(OtpravakService $service): int
    {
        $hours = (int) $this->option('hours');
        $sendAlerts = (bool) $this->option('alert');

        $this->info("Checking for dispatches with deadlines within {$hours} hours...");

        try {
            $approaching = $service->getApproachingDeadlines($hours);
        } catch (\Exception $e) {
            $this->error("Failed to fetch dispatches: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (empty($approaching)) {
            $this->info('No dispatches approaching deadline.');

            if ($this->option('json')) {
                $this->line(json_encode(['count' => 0, 'dispatches' => []], JSON_PRETTY_PRINT));
            }

            return self::SUCCESS;
        }

        $this->warn(count($approaching) . ' dispatch(es) approaching deadline!');
        $this->newLine();

        $output = [];

        foreach ($approaching as $dispatch) {
            $deadline = Carbon::parse($dispatch->zadnjiTrenutakZaPotvrduPrimitka);
            $hoursRemaining = now()->diffInHours($deadline, false);
            $minutesRemaining = now()->diffInMinutes($deadline, false) % 60;

            $dispatchData = [
                'id' => $dispatch->id,
                'predmet_oznaka' => $dispatch->predmetOznaka,
                'sud' => $dispatch->sudNaziv,
                'deadline' => $dispatch->zadnjiTrenutakZaPotvrduPrimitka,
                'time_remaining' => "{$hoursRemaining}h {$minutesRemaining}m",
                'sent_date' => $dispatch->datumSlanjaSaSuda,
            ];

            $output[] = $dispatchData;

            if (! $this->option('json')) {
                $urgency = $hoursRemaining < 4 ? '<fg=red>URGENT</>' : '<fg=yellow>APPROACHING</>';
                $this->line("  {$urgency} Otpravak #{$dispatch->id}");
                $this->line("    Predmet: {$dispatch->predmetOznaka}");
                $this->line("    Sud: {$dispatch->sudNaziv}");
                $this->line("    Deadline: {$dispatch->zadnjiTrenutakZaPotvrduPrimitka}");
                $this->line("    Time remaining: <fg=cyan>{$hoursRemaining}h {$minutesRemaining}m</>");
                $this->newLine();
            }
        }

        if ($sendAlerts) {
            $this->sendAlerts($approaching);
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'count' => count($approaching),
                'dispatches' => $output,
            ], JSON_PRETTY_PRINT));
        }

        // Log the check
        Log::info('EKOM dispatch deadline check', [
            'approaching_count' => count($approaching),
            'hours_threshold' => $hours,
            'alerts_sent' => $sendAlerts,
        ]);

        return self::SUCCESS;
    }

    private function sendAlerts(array $dispatches): void
    {
        $this->info('Sending alerts for ' . count($dispatches) . ' dispatch(es)...');

        // TODO: Implement actual alert mechanism (email, Slack, etc.)
        // For now, just log as warnings
        foreach ($dispatches as $dispatch) {
            Log::warning('Dispatch approaching legal deadline', [
                'otpravak_id' => $dispatch->id,
                'predmet_oznaka' => $dispatch->predmetOznaka,
                'deadline' => $dispatch->zadnjiTrenutakZaPotvrduPrimitka,
            ]);
        }

        $this->info('Alerts logged (implement notification channel for production)');
    }
}
