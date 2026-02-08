<?php

namespace App\Console\Commands;

use App\Services\Ekom\SifarniciSyncService;
use Illuminate\Console\Command;

class EkomSyncSifarniciCommand extends Command
{
    protected $signature = 'ekom:sync-sifrarnici
                            {--force : Force full re-sync even if recently synced}
                            {--only= : Sync only specific table (courts, procedure-types, submission-types, participant-roles, fee-options, non-payment-reasons, fee-exemptions, settlements, countries)}
                            {--court-id= : Court ID for procedure-types sync}';

    protected $description = 'Sync reference data (šifrarnici) from e-Komunikacija API';

    private const VALID_ONLY_OPTIONS = [
        'courts',
        'procedure-types',
        'submission-types',
        'participant-roles',
        'fee-options',
        'non-payment-reasons',
        'fee-exemptions',
        'settlements',
        'countries',
    ];

    public function handle(SifarniciSyncService $service): int
    {
        $force = (bool) $this->option('force');
        $only = $this->option('only');

        if ($only) {
            if (! in_array($only, self::VALID_ONLY_OPTIONS)) {
                $this->error("Invalid --only option: {$only}");
                $this->info('Valid options: ' . implode(', ', self::VALID_ONLY_OPTIONS));

                return self::FAILURE;
            }

            return $this->syncSingle($service, $only);
        }

        $this->info('Syncing all reference data from e-Komunikacija...');

        $stats = $service->syncAll($force);

        $this->table(
            ['Table', 'Synced Count'],
            collect($stats)->map(fn ($count, $key) => [$key, $count])->values()->toArray()
        );

        $total = array_sum($stats);
        $this->info("Total synced: {$total} records");

        return self::SUCCESS;
    }

    private function syncSingle(SifarniciSyncService $service, string $type): int
    {
        $courtId = $this->option('court-id') ? (int) $this->option('court-id') : null;

        $count = match ($type) {
            'courts' => $service->syncCourts(),
            'procedure-types' => $service->syncProcedureTypes($courtId),
            'submission-types' => $service->syncSubmissionTypes($courtId),
            'participant-roles' => $service->syncParticipantRoles($courtId),
            'fee-options' => $service->syncFeeOptions(),
            'non-payment-reasons' => $service->syncNonPaymentReasons(),
            'fee-exemptions' => $service->syncFeeExemptions(),
            'settlements' => $service->syncSettlements(),
            'countries' => $service->syncCountries(),
            default => 0,
        };

        $this->info("Synced {$count} {$type} records");

        return self::SUCCESS;
    }
}
