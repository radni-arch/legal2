<?php

namespace App\Console\Commands;

use App\Services\Analysis\CaseLevel\DocumentIdentityBuilder;
use Illuminate\Console\Command;

class CaseFileCompletenessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'case:completeness
        {case_id : The case identifier}
        {--rebuild : Rebuild the document identity matrix before showing report}
        {--json : Output raw JSON instead of human-readable format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show case file completeness - which documents are present vs missing';

    /**
     * Execute the console command.
     */
    public function handle(DocumentIdentityBuilder $builder): int
    {
        $caseId = $this->argument('case_id');

        if ($this->option('rebuild')) {
            $this->info('Rebuilding document identity matrix...');
        }

        $result = $builder->build($caseId);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        // Human-readable output
        $this->renderHumanReadable($caseId, $result);

        return Command::SUCCESS;
    }

    /**
     * Render human-readable completeness report.
     */
    private function renderHumanReadable(string $caseId, array $result): void
    {
        $this->newLine();
        $this->info("Completeness report: {$caseId}");
        $this->info("   Present: {$result['present']}  |  Missing: {$result['missing']}  |  Total: {$result['total_identities']}");
        $this->newLine();

        // Group flat entries by base case number for display
        $groups = $this->groupByBaseCaseNumber($result['by_case_number']);

        foreach ($groups as $baseCaseNumber => $group) {
            $this->renderCaseNumberGroup($baseCaseNumber, $group);
        }

        $this->newLine();
        $this->info("Done in {$result['processing_time_seconds']}s");
    }

    /**
     * Group flat by_case_number entries by their base_case_number for display.
     *
     * @param array $byCaseNumber Flat map of case number entries
     * @return array Grouped entries keyed by base case number
     */
    private function groupByBaseCaseNumber(array $byCaseNumber): array
    {
        $groups = [];

        foreach ($byCaseNumber as $caseNumber => $entry) {
            $base = $entry['base_case_number'] ?? $caseNumber;

            if (! isset($groups[$base])) {
                $groups[$base] = [
                    'role' => null,
                    'institution' => null,
                    'entries' => [],
                ];
            }

            $groups[$base]['entries'][$caseNumber] = $entry;

            // Use first non-null role and institution
            if (($entry['role'] ?? null) && ! $groups[$base]['role']) {
                $groups[$base]['role'] = $entry['role'];
            }
            if (($entry['institution'] ?? null) && ! $groups[$base]['institution']) {
                $groups[$base]['institution'] = $entry['institution'];
            }
        }

        return $groups;
    }

    /**
     * Render a grouped case number with status indicators.
     */
    private function renderCaseNumberGroup(string $baseCaseNumber, array $group): void
    {
        $entries = $group['entries'];
        $presentCount = count(array_filter($entries, fn ($e) => $e['status'] === 'present'));
        $totalCount = count($entries);

        // Sort entries by suffix for display
        $sorted = $entries;
        uasort($sorted, fn ($a, $b) => ($a['suffix'] ?? 0) <=> ($b['suffix'] ?? 0));

        // Build status bar
        $bar = '';
        foreach ($sorted as $entry) {
            $bar .= $entry['status'] === 'present' ? "\u{1F7E9}" : "\u{1F7E5}"; // Green/Red squares
        }

        $completeness = $presentCount . '/' . $totalCount;
        $paddedCaseNumber = str_pad($baseCaseNumber, 25);
        $paddedRole = str_pad($group['role'] ?? '', 20);

        $this->line("  {$paddedCaseNumber} {$paddedRole} {$bar}  ({$completeness})");

        // Show missing document warnings
        foreach ($sorted as $caseNumber => $entry) {
            if ($entry['status'] === 'missing') {
                $suffix = $entry['suffix'] ?? '?';
                $docType = 'nepoznato';
                $this->warn("    -{$suffix} nedostaje - moguci tip: {$docType}");
            }
        }
    }
}
