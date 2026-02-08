<?php

namespace App\Console\Commands;

use App\Models\CourtCase;
use App\Services\CaseDecisionMatchingService;
use Illuminate\Console\Command;

class MatchCaseDecisions extends Command
{
    protected $signature = 'cases:match-decisions
                            {--court= : Limit to specific court ID}
                            {--year= : Limit to specific year}
                            {--unmatched-only : Only process cases without matches}
                            {--dry-run : Report without saving}
                            {--limit=100 : Batch size}';

    protected $description = 'Match court cases to court decisions';

    public function handle(CaseDecisionMatchingService $service): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $unmatchedOnly = (bool) $this->option('unmatched-only');
        $courtId = $this->option('court');
        $year = $this->option('year');

        $this->info('Starting case-decision matching...');

        if ($dryRun) {
            $this->warn('DRY RUN: No changes will be saved');
        }

        $query = CourtCase::query();

        if ($unmatchedOnly) {
            $query->unmatched();
        }

        if ($courtId) {
            $query->where('court_id', $courtId);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $cases = $query->limit($limit)->get();

        $this->info("Processing {$cases->count()} cases...");

        $matched = 0;
        $unmatched = 0;

        $bar = $this->output->createProgressBar($cases->count());
        $bar->start();

        foreach ($cases as $case) {
            if (!$dryRun) {
                $match = $service->matchCase($case, 'command');
                if ($match) {
                    $matched++;
                } else {
                    $unmatched++;
                }
            } else {
                // Dry run: just simulate
                $unmatched++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $cases->count()],
                ['Matched', $matched],
                ['Unmatched', $unmatched],
            ]
        );

        return self::SUCCESS;
    }
}
