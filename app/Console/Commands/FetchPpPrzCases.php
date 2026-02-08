<?php

namespace App\Console\Commands;

use App\Jobs\MatchCaseToDecisionJob;
use App\Models\Court;
use App\Models\CourtCase;
use App\Models\SyncLog;
use App\Models\CaseStatistic;
use App\Services\EPredmetService;
use App\Services\SearchWarrantValidator;
use App\Services\InstitutionInitialsResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchPpPrzCases extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'epredmet:fetch
                            {--court= : Court external ID (required)}
                            {--year= : Year to fetch (default: current year)}
                            {--register=Pp Prz : Register to fetch}
                            {--start=1 : Starting case number}
                            {--max=0 : Maximum cases to fetch (0 = unlimited)}
                            {--resume : Resume from last synced position}
                            {--stats : Recalculate statistics after fetch}
                            {--validate : Run validation and resolve initials after fetch}
                            {--no-validate : Skip validation even if default}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch Pp Prz cases from e-Predmet API for a specific court and year';

    protected EPredmetService $api;
    protected SearchWarrantValidator $validator;
    protected InstitutionInitialsResolver $resolver;

    public function __construct(
        EPredmetService $api,
        SearchWarrantValidator $validator,
        InstitutionInitialsResolver $resolver
    ) {
        parent::__construct();
        $this->api = $api;
        $this->validator = $validator;
        $this->resolver = $resolver;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $courtExternalId = $this->option('court');
        $year = (int) ($this->option('year') ?? date('Y'));
        $register = $this->option('register');
        $startFrom = (int) $this->option('start');
        $maxCases = (int) $this->option('max');
        $resume = $this->option('resume');
        $recalcStats = $this->option('stats');
        $shouldValidate = $this->option('validate') || (!$this->option('no-validate') && $register === 'Pp Prz');

        // Validate court
        if (!$courtExternalId) {
            $this->error('Court ID is required. Use --court=<id>');
            $this->info('Run epredmet:courts to see available courts.');
            return self::FAILURE;
        }

        // Find or create court
        $court = $this->findOrCreateCourt((int) $courtExternalId);
        if (!$court) {
            $this->error("Court with external ID {$courtExternalId} not found in API.");
            return self::FAILURE;
        }

        $this->info("Fetching {$register} cases for {$court->name} ({$year})");
        if ($shouldValidate) {
            $this->info("  → Validation and initials resolution ENABLED");
        }

        // Get or create sync log
        $syncLog = SyncLog::firstOrCreate(
            ['court_id' => $court->id, 'register' => $register, 'year' => $year],
            ['status' => 'pending']
        );

        // Handle resume
        if ($resume && $syncLog->last_case_number > 0) {
            $startFrom = $syncLog->last_case_number + 1;
            $this->info("Resuming from case #{$startFrom}");
        }

        // Mark as running
        $syncLog->markAsRunning();

        // Progress bar
        $progressBar = null;

        try {
            $fetched = 0;
            $saved = 0;
            $errors = 0;
            $validated = 0;
            $confirmed = 0;
            $institutionsResolved = 0;

            // Fetch cases
            $generator = $this->api->fetchAllCases(
                $court->external_id,
                $register,
                $year,
                function ($count, $current) use (&$progressBar) {
                    if (!$progressBar) {
                        $progressBar = $this->output->createProgressBar(1000);
                        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
                        $progressBar->start();
                    }
                    $progressBar->setProgress($current);
                    $progressBar->setMessage("Fetched: {$count}");
                },
                $startFrom,
                $maxCases
            );

            foreach ($generator as $caseData) {
                $fetched++;

                try {
                    DB::beginTransaction();

                    $case = CourtCase::createFromApiResponse($caseData, $court->id);
                    $saved++;

                    // Dispatch matching job with environment detection
                    // (sync in dev/test, queued in production)
                    MatchCaseToDecisionJob::dispatchWithEnvDetection($case);

                    // Validate and resolve if enabled
                    if ($shouldValidate) {
                        $validationResult = $this->validateAndResolve($case);
                        $validated++;

                        if ($validationResult['confirmed']) {
                            $confirmed++;
                        }
                        $institutionsResolved += $validationResult['institutions_resolved'];
                    }

                    // Update sync log progress
                    $caseNumber = $this->extractCaseNumber($caseData['oznakaBroj'] ?? '');
                    $syncLog->updateLastCaseNumber($caseNumber);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors++;
                    $this->warn("Error saving case: " . $e->getMessage());
                }

                // Update sync log periodically
                if ($fetched % 50 === 0) {
                    $syncLog->update([
                        'total_fetched' => $fetched,
                        'total_saved' => $saved,
                        'total_errors' => $errors,
                    ]);
                }
            }

            if ($progressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            // Final sync log update
            $syncLog->update([
                'total_fetched' => $fetched,
                'total_saved' => $saved,
                'total_errors' => $errors,
            ]);
            $syncLog->markAsCompleted();

            // Summary
            $this->newLine();
            $this->info("✅ Completed!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Fetched', $fetched],
                    ['Saved', $saved],
                    ['Errors', $errors],
                    $shouldValidate ? ['Validated', $validated] : null,
                    $shouldValidate ? ['Confirmed warrants', $confirmed] : null,
                    $shouldValidate ? ['Institutions resolved', $institutionsResolved] : null,
                ]
            );

            // Recalculate statistics
            if ($recalcStats) {
                $this->info('Recalculating statistics...');
                CaseStatistic::recalculateForCourt($court->id, $year, $register);
                $this->info('Statistics updated.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            if ($progressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            $syncLog->markAsFailed($e->getMessage());
            $this->error("Fatal error: " . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Validate case and resolve party initials
     */
    protected function validateAndResolve(CourtCase $case): array
    {
        $result = [
            'confirmed' => false,
            'institutions_resolved' => 0,
        ];

        // Load relationships if not loaded
        $case->loadMissing(['documents', 'parties']);

        // Validate warrant
        $validation = $this->validator->validate($case);
        $warrantType = $this->validator->classifyWarrantType($case);

        $case->applyValidation($validation, $warrantType);
        $result['confirmed'] = $validation['is_warrant'];

        // Resolve party initials
        foreach ($case->parties as $party) {
            $resolution = $this->resolver->resolve($party->name ?? '');
            $party->applyResolution($resolution);

            if ($resolution['name']) {
                $result['institutions_resolved']++;
            }
        }

        return $result;
    }

    /**
     * Find or create court from API
     */
    protected function findOrCreateCourt(int $externalId): ?Court
    {
        $court = Court::findByExternalId($externalId);

        if ($court) {
            return $court;
        }

        // Fetch from API
        $courts = $this->api->getCourts();

        foreach ($courts as $apiCourt) {
            if ((int) $apiCourt['id'] === $externalId) {
                return Court::create([
                    'external_id' => $apiCourt['id'],
                    'name' => $apiCourt['sudNaziv'],
                    'code' => $apiCourt['sudOznaka'] ?? null,
                    'level' => $apiCourt['razina'] ?? null,
                    'county' => EPredmetService::mapCountyFromCourtName($apiCourt['sudNaziv']),
                ]);
            }
        }

        return null;
    }

    /**
     * Extract case number from case string
     */
    protected function extractCaseNumber(string $caseNumber): int
    {
        if (preg_match('/-(\d+)\//', $caseNumber, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}
