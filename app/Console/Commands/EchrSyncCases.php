<?php

namespace App\Console\Commands;

use App\Jobs\SyncEchrCaseJob;
use App\Models\EchrCase;
use App\Services\Hudoc\EchrExtractorBridge;
use App\Services\Hudoc\HudocClient;
use App\Services\Hudoc\HudocSearchQuery;
use Illuminate\Console\Command;

class EchrSyncCases extends Command
{
    protected $signature = 'echr:sync
                            {--state=Croatia : Respondent state to sync}
                            {--article= : Filter by article number}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--limit=500 : Maximum cases to fetch}
                            {--with-text : Also download full text}
                            {--use-python : Use Python echr-extractor}
                            {--force : Re-sync existing cases}';

    protected $description = 'Synchronize ECHR cases from HUDOC database';

    public function handle(): int
    {
        $state = $this->option('state');
        $article = $this->option('article');
        $from = $this->option('from');
        $to = $this->option('to');
        $limit = (int) $this->option('limit');
        $withText = $this->option('with-text');
        $usePython = $this->option('use-python');
        $force = $this->option('force');

        $this->info("Syncing ECHR cases for: {$state}");

        if ($usePython) {
            return $this->syncWithPython($state, $from, $to, $limit, $withText);
        }

        return $this->syncWithApi($state, $article, $from, $to, $limit, $withText, $force);
    }

    protected function syncWithApi(
        string $state,
        ?string $article,
        ?string $from,
        ?string $to,
        int $limit,
        bool $withText,
        bool $force
    ): int {
        $client = new HudocClient;

        $query = HudocSearchQuery::make()
            ->respondent($state)
            ->judgmentsOnly()
            ->limit($limit);

        if ($article) {
            $query->article($article);
        }

        if ($from) {
            $query->dateFrom($from);
        }

        if ($to) {
            $query->dateTo($to);
        }

        $this->info('Fetching cases from HUDOC...');
        $cases = $client->search($query);

        $this->info("Found {$cases->count()} cases");

        $bar = $this->output->createProgressBar($cases->count());
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($cases as $caseData) {
            $existing = EchrCase::findByItemId($caseData['item_id']);

            if ($existing && ! $force) {
                $skipped++;
                $bar->advance();

                continue;
            }

            if ($existing) {
                $existing->update($caseData);
                $updated++;
            } else {
                $case = EchrCase::create($caseData);
                $created++;

                // Dispatch job for full text if requested
                if ($withText) {
                    SyncEchrCaseJob::dispatch($case);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Sync complete: {$created} created, {$updated} updated, {$skipped} skipped");

        return self::SUCCESS;
    }

    protected function syncWithPython(
        string $state,
        ?string $from,
        ?string $to,
        int $limit,
        bool $withText
    ): int {
        $bridge = new EchrExtractorBridge;

        if (! $bridge->isAvailable()) {
            $this->error('echr-extractor Python package not available');
            $this->info('Install with: pip install echr-extractor');

            return self::FAILURE;
        }

        $this->info('Extracting cases using Python echr-extractor...');

        if ($withText) {
            $cases = $bridge->extractWithFullText($limit, $from, $to);
        } else {
            $cases = $bridge->extractMetadata($limit, $from, $to);
        }

        $this->info("Extracted {$cases->count()} cases");

        $bar = $this->output->createProgressBar($cases->count());
        $bar->start();

        $created = 0;

        foreach ($cases as $caseData) {
            // Map Python output to our schema
            $mapped = $this->mapPythonData($caseData);

            if ($state && $mapped['respondent_state'] !== $state) {
                $bar->advance();

                continue;
            }

            EchrCase::updateOrCreate(
                ['item_id' => $mapped['item_id']],
                $mapped
            );

            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Import complete: {$created} cases processed");

        return self::SUCCESS;
    }

    protected function mapPythonData(array $data): array
    {
        return [
            'item_id' => $data['itemid'] ?? null,
            'application_number' => $data['appno'] ?? null,
            'ecli' => $data['ecli'] ?? null,
            'case_name' => $data['docname'] ?? null,
            'respondent_state' => $data['respondent'] ?? null,
            'document_type' => $this->mapDocType($data['doctypebranch'] ?? ''),
            'importance' => $data['importance'] ?? null,
            'judgment_date' => $data['judgementdate'] ?? $data['kpdate'] ?? null,
            'violations' => $this->parseArticleList($data['violation'] ?? ''),
            'non_violations' => $this->parseArticleList($data['nonviolation'] ?? ''),
            'full_text' => $data['full_text'] ?? null,
            'full_text_downloaded' => ! empty($data['full_text']),
            'language' => $data['languageisocode'] ?? 'ENG',
            'last_synced_at' => now(),
        ];
    }

    protected function mapDocType(string $type): string
    {
        return match (strtoupper($type)) {
            'GRANDCHAMBER', 'CHAMBER' => 'JUDGMENT',
            'ADMISSIBILITY', 'COMMITTEE' => 'DECISION',
            default => 'JUDGMENT',
        };
    }

    protected function parseArticleList(string $articles): array
    {
        if (empty($articles)) {
            return [];
        }

        return array_filter(array_map('trim', explode(';', $articles)));
    }
}
