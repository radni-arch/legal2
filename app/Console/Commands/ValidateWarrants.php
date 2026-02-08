<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use App\Models\CaseParty;
use App\Services\SearchWarrantValidator;
use App\Services\InstitutionInitialsResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateWarrants extends Command
{
    protected $signature = 'epredmet:validate
                            {--court= : Court ID to validate}
                            {--year= : Year to validate}
                            {--case= : Single case number to validate}
                            {--resolve-initials : Also resolve party initials}
                            {--details : Show detailed validation for each case}
                            {--export= : Export results to JSON}
                            {--dry-run : Do not save to database}
                            {--unvalidated : Only validate cases not yet validated}
                            {--revalidate : Force revalidation of all cases}';

    protected $description = 'Validate search warrant cases and resolve institution initials';

    protected SearchWarrantValidator $validator;
    protected InstitutionInitialsResolver $resolver;

    public int $allCases = 0;

    public function __construct(SearchWarrantValidator $validator, InstitutionInitialsResolver $resolver)
    {
        parent::__construct();
        $this->validator = $validator;
        $this->resolver = $resolver;
    }

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? date('Y'));
        $courtId = $this->option('court');
        $caseNumber = $this->option('case');
        $resolveInitials = $this->option('resolve-initials');
        $showDetails = $this->option('details');
        $exportPath = $this->option('export');
        $dryRun = $this->option('dry-run');
        $unvalidatedOnly = $this->option('unvalidated');
        $revalidate = $this->option('revalidate');

        // Single case validation
        if ($caseNumber) {
            return $this->validateSingleCase($caseNumber, $resolveInitials, $dryRun);
        }

        // Build query
        $query = CourtCase::with(['documents', 'parties', 'court', 'judge'])
            ->where('year', $year);


        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($unvalidatedOnly && !$revalidate) {
            $query->unvalidated();
        }

        $queryCloned = clone $query;
        $allCases = $queryCloned->count();
        $this->allCases = $allCases;
        $this->info("Found {$allCases} total Pp Prz cases");
        $query->searchWarrants();
        $cases = $query->get();

        if ($cases->isEmpty()) {
            $this->warn("No Pp Prz cases found for year {$year}" . ($unvalidatedOnly ? " (unvalidated only)" : ""));
            return Command::SUCCESS;
        }

        $this->info("🔍 Validating {$cases->count()} Pp Prz cases from {$year}");
        if ($dryRun) {
            $this->warn("   DRY RUN - results will NOT be saved to database");
        }
        $this->newLine();

        // Validate all
        $results = $this->validateBatch($cases, $showDetails, $resolveInitials, $dryRun);

        // Show summary
        $this->showSummary($results);

        // Export if requested
        if ($exportPath) {
            $this->exportResults($results, $exportPath);
        }

        return Command::SUCCESS;
    }

    protected function validateSingleCase(string $caseNumber, bool $resolveInitials, bool $dryRun): int
    {
        $case = CourtCase::with(['documents', 'parties', 'court', 'judge'])
            ->where('case_number', 'LIKE', "%{$caseNumber}%")
            ->first();

        if (!$case) {
            $this->error("Case not found: {$caseNumber}");
            return Command::FAILURE;
        }

        $this->info("📋 Case: {$case->case_number}");
        $this->info("   Court: " . ($case->court?->name ?? 'Unknown'));
        $this->info("   Judge: " . ($case->judge_name ?? 'Unknown'));
        $this->info("   Decision: " . ($case->decision_type ?? 'Unknown'));
        $this->info("   Date: " . ($case->date_decision?->format('Y-m-d') ?? 'Unknown'));
        $this->newLine();

        // Validate
        $validation = $this->validator->validate($case);
        $warrantType = $this->validator->classifyWarrantType($case);

        $this->info("🔍 Validation Result:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Is Search Warrant', $validation['is_warrant'] ? '✅ YES' : '❌ NO'],
                ['Confidence', strtoupper($validation['confidence'])],
                ['Score', $validation['score']],
                ['Type', $this->formatWarrantType($warrantType)],
            ]
        );

        $this->newLine();
        $this->info("📝 Reasons:");
        foreach ($validation['reasons'] as $reason) {
            $this->line("   • {$reason}");
        }

        // Save to database unless dry run
        if (!$dryRun) {
            DB::beginTransaction();
            try {
                $case->applyValidation($validation, $warrantType);

                if ($resolveInitials) {
                    foreach ($case->parties as $party) {
                        $resolution = $this->resolver->resolve($party->name ?? '');
                        $party->applyResolution($resolution);
                    }
                }

                DB::commit();
                $this->newLine();
                $this->info("💾 Saved to database");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to save: " . $e->getMessage());
            }
        }

        // Documents
        if ($case->documents->isNotEmpty()) {
            $this->newLine();
            $this->info("📄 Documents:");
            foreach ($case->documents as $doc) {
                $this->line("   • " . ($doc->document_name ?? 'Unnamed'));
            }
        }

        // Resolve initials
        if ($resolveInitials && $case->parties->isNotEmpty()) {
            $this->newLine();
            $this->info("👥 Parties (with resolved initials):");

            foreach ($case->parties as $party) {
                // Use already resolved if saved, otherwise resolve now
                if ($party->resolved_name) {
                    $resolved = "{$party->name} → {$party->resolved_name}";
                    $confidence = $party->resolution_confidence ?? 'unknown';
                    $type = $party->institution_type ?? 'unknown';
                } else {
                    $resolution = $this->resolver->resolve($party->name ?? '');
                    $resolved = $resolution['name']
                        ? "{$party->name} → {$resolution['name']}"
                        : "{$party->name} (not resolved)";
                    $confidence = $resolution['confidence'];
                    $type = $resolution['type'] ?? 'unknown';
                }

                $this->line("   • [{$party->role}] {$resolved}");
                $this->line("     Type: {$type}, Confidence: {$confidence}");
            }
        }

        return Command::SUCCESS;
    }

    protected function validateBatch($cases, bool $showDetails, bool $resolveInitials, bool $dryRun): array
    {
        $results = [
            'total' => 0,
            'confirmed' => 0,
            'high_confidence' => 0,
            'medium_confidence' => 0,
            'low_confidence' => 0,
            'unlikely' => 0,
            'by_type' => [],
            'institutions_found' => [],
            'unresolved_initials' => [],
            'cases' => [],
            'saved' => 0,
            'errors' => 0,
        ];

        $progressBar = $this->output->createProgressBar($cases->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Validating...');

        foreach ($cases as $case) {
            $validation = $this->validator->validate($case);
            $warrantType = $this->validator->classifyWarrantType($case);

            $results['total']++;

            if ($validation['is_warrant']) {
                $results['confirmed']++;
            }

            // Count by confidence
            switch ($validation['confidence']) {
                case 'high':
                    $results['high_confidence']++;
                    break;
                case 'medium':
                    $results['medium_confidence']++;
                    break;
                case 'low':
                    $results['low_confidence']++;
                    break;
                default:
                    $results['unlikely']++;
            }

            // Count by type
            $results['by_type'][$warrantType] = ($results['by_type'][$warrantType] ?? 0) + 1;

            // Save to database unless dry run
            if (!$dryRun) {
                try {
                    DB::beginTransaction();

                    $case->applyValidation($validation, $warrantType);

                    // Resolve initials
                    if ($resolveInitials) {
                        foreach ($case->parties as $party) {
                            $resolution = $this->resolver->resolve($party->name ?? '');
                            $party->applyResolution($resolution);

                            if ($resolution['name']) {
                                $results['institutions_found'][$party->name] = $resolution['name'];
                            } elseif ($resolution['type'] === 'unknown_institution') {
                                $results['unresolved_initials'][$party->name] =
                                    ($results['unresolved_initials'][$party->name] ?? 0) + 1;
                            }
                        }
                    }

                    DB::commit();
                    $results['saved']++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $results['errors']++;
                }
            } else {
                // Still resolve initials for reporting (but don't save)
                if ($resolveInitials) {
                    foreach ($case->parties as $party) {
                        $resolution = $this->resolver->resolve($party->name ?? '');

                        if ($resolution['name']) {
                            $results['institutions_found'][$party->name] = $resolution['name'];
                        } elseif ($resolution['type'] === 'unknown_institution') {
                            $results['unresolved_initials'][$party->name] =
                                ($results['unresolved_initials'][$party->name] ?? 0) + 1;
                        }
                    }
                }
            }

            // Store case details
            $results['cases'][] = [
                'case_number' => $case->case_number,
                'court' => $case->court?->short_name,
                'validation' => $validation,
                'type' => $warrantType,
            ];

            if ($showDetails) {
                $progressBar->clear();
                $status = $validation['is_warrant'] ? '✅' : '❌';
                $this->line("{$status} {$case->case_number} [{$validation['confidence']}] {$warrantType}");
                $progressBar->display();
            }

            $progressBar->setMessage($case->case_number);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        return $results;
    }

    protected function showSummary(array $results): void
    {
        $this->info("📊 Validation Summary");
        $this->newLine();

        $rows = [
            ['Total Cases', $this->allCases, '100%'],
            ['Confirmed Warrants', $results['confirmed'], $this->pct($results['confirmed'], $this->allCases)],
            ['High Confidence', $results['high_confidence'], $this->pct($results['high_confidence'], $this->allCases)],
            ['Medium Confidence', $results['medium_confidence'], $this->pct($results['medium_confidence'], $this->allCases)],
            ['Low Confidence', $results['low_confidence'], $this->pct($results['low_confidence'], $this->allCases)],
            ['Unlikely', $results['unlikely'], $this->pct($results['unlikely'], $this->allCases)],
        ];

        if (isset($results['saved'])) {
            $rows[] = ['---', '---', '---'];
            $rows[] = ['Saved to DB', $results['saved'], $this->pct($results['saved'], $this->allCases)];
            $rows[] = ['Errors', $results['errors'], $this->pct($results['errors'], $this->allCases)];
        }

        $this->table(['Metric', 'Count', 'Percentage'], $rows);

        // Warrant types
        if (!empty($results['by_type'])) {
            $this->newLine();
            $this->info("📂 By Warrant Type:");

            $typeRows = [];
            foreach ($results['by_type'] as $type => $count) {
                $typeRows[] = [
                    $this->formatWarrantType($type),
                    $count,
                    $this->pct($count, $this->allCases),
                ];
            }

            $this->table(['Type', 'Count', '%'], $typeRows);
        }

        // Resolved institutions
        if (!empty($results['institutions_found'])) {
            $this->newLine();
            $this->info("🏢 Resolved Institutions (" . count($results['institutions_found']) . " unique):");

            $instRows = array_map(
                fn($init, $name) => [$init, $name],
                array_keys($results['institutions_found']),
                array_values($results['institutions_found'])
            );

            $this->table(['Initials', 'Full Name'], array_slice($instRows, 0, 20));

            if (count($instRows) > 20) {
                $this->line("   ... and " . (count($instRows) - 20) . " more");
            }
        }

        // Unresolved initials
        if (!empty($results['unresolved_initials'])) {
            $this->newLine();
            $this->warn("⚠️ Unresolved Institution Initials:");

            arsort($results['unresolved_initials']);

            $unresolvedRows = [];
            foreach (array_slice($results['unresolved_initials'], 0, 15, true) as $init => $count) {
                $unresolvedRows[] = [$init, $count];
            }

            $this->table(['Initials', 'Occurrences'], $unresolvedRows);
        }
    }

    protected function formatWarrantType(string $type): string
    {
        return match ($type) {
            'home_search' => '🏠 Home Search',
            'vehicle_search' => '🚗 Vehicle Search',
            'business_search' => '🏢 Business Search',
            'person_search' => '👤 Person Search',
            'unclassified' => '❓ Unclassified',
            default => $type,
        };
    }

    protected function pct(int $value, int $total): string
    {
        if ($total === 0) return '0%';
        return round(100 * $value / $total, 1) . '%';
    }

    protected function exportResults(array $results, string $path): void
    {
        $exportPath = str_starts_with($path, '/') ? $path : "/home/claude/{$path}";

        $export = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total' => $results['total'],
                'confirmed' => $results['confirmed'],
                'saved' => $results['saved'] ?? null,
                'errors' => $results['errors'] ?? null,
                'by_confidence' => [
                    'high' => $results['high_confidence'],
                    'medium' => $results['medium_confidence'],
                    'low' => $results['low_confidence'],
                    'unlikely' => $results['unlikely'],
                ],
                'by_type' => $results['by_type'],
            ],
            'institutions' => $results['institutions_found'],
            'unresolved_initials' => $results['unresolved_initials'],
            'cases' => $results['cases'],
        ];

        file_put_contents($exportPath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("📁 Exported to: {$exportPath}");
    }
}
