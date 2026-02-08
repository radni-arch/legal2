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

        foreach ($result['by_case_number'] as $caseNumber => $group) {
            $this->renderCaseNumberRow($caseNumber, $group);
        }

        $this->newLine();
        $this->info("Done in {$result['processing_time_seconds']}s");
    }

    /**
     * Render a single case number row with status indicators.
     */
    private function renderCaseNumberRow(string $caseNumber, array $group): void
    {
        // Build status bar
        $bar = '';
        foreach ($group['suffixes'] as $suffix => $info) {
            $bar .= $info['status'] === 'present' ? "\u{1F7E9}" : "\u{1F7E5}"; // Green/Red squares
        }

        $completeness = $group['present'] . '/' . $group['total_documents'];
        $paddedCaseNumber = str_pad($caseNumber, 25);
        $paddedRole = str_pad($group['role'] ?? '', 20);

        $this->line("  {$paddedCaseNumber} {$paddedRole} {$bar}  ({$completeness})");

        // Show missing document warnings
        foreach ($group['suffixes'] as $suffix => $info) {
            if ($info['status'] === 'missing') {
                $docType = $info['type'] ?? 'nepoznato';
                $this->warn("    -{$suffix} nedostaje - moguci tip: {$docType}");
            }
        }
    }
}
