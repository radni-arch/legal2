<?php

namespace App\Console\Commands;

use App\Mcp\OdlukeTools;
use Illuminate\Console\Command;

/**
 * Proof-of-Concept command for Odluke.sudovi.hr MCP Integration
 *
 * Demonstrates:
 * 1. Search for court decisions
 * 2. Fetch metadata for a decision
 * 3. Download decision PDF
 *
 * Sprint 1.3: MCP Integration Research
 */
class OdlukeMcpProofOfConceptCommand extends Command
{
    protected $signature = 'odluke:poc
                            {query=kazneno : Search query}
                            {--limit=3 : Number of results to fetch}
                            {--download : Download PDF for first result}
                            {--base-url= : Optional custom base URL}';

    protected $description = 'Proof-of-concept for Odluke.sudovi.hr MCP integration';

    public function handle(OdlukeTools $tools): int
    {
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');
        $shouldDownload = $this->option('download');
        $baseUrl = $this->option('base-url');

        $this->info('🔍 Odluke.sudovi.hr MCP Integration - Proof of Concept');
        $this->newLine();

        // Step 1: Search for decisions
        $this->info("Step 1: Searching for decisions with query: '{$query}'");
        $this->line("Limit: {$limit} results");
        $this->newLine();

        $searchResult = $tools->search($query, null, $limit, 1, $baseUrl);

        if ($searchResult['isError']) {
            $this->error('❌ Search failed:');
            $this->line($searchResult['content'][0]['text'] ?? 'Unknown error');

            return self::FAILURE;
        }

        $searchData = json_decode($searchResult['content'][0]['text'], true);

        if (empty($searchData['ids'])) {
            $this->warn("⚠️  No decisions found for query: '{$query}'");

            return self::SUCCESS;
        }

        $ids = $searchData['ids'];
        $this->info("✅ Found {$searchData['count']} decision(s)");
        $this->table(['Index', 'Decision ID'], array_map(fn ($i, $id) => [$i + 1, $id], array_keys($ids), $ids));
        $this->newLine();

        // Step 2: Fetch metadata for all found decisions
        $this->info('Step 2: Fetching metadata for found decisions');
        $this->newLine();

        $metaResult = $tools->meta(null, $ids, $baseUrl);

        if ($metaResult['isError']) {
            $this->error('❌ Metadata fetch failed:');
            $this->line($metaResult['content'][0]['text'] ?? 'Unknown error');

            return self::FAILURE;
        }

        $metaData = json_decode($metaResult['content'][0]['text'], true);

        $this->info('✅ Successfully fetched metadata for '.count($metaData).' decision(s)');
        $this->newLine();

        foreach ($metaData as $index => $meta) {
            $this->info('Decision #'.($index + 1).': '.$meta['id']);
            $this->table(
                ['Field', 'Value'],
                [
                    ['Decision ID', $meta['id']],
                    ['Court', $meta['metadata']['sud'] ?? 'N/A'],
                    ['Decision Number', $meta['metadata']['broj_odluke'] ?? 'N/A'],
                    ['Decision Date', $meta['metadata']['datum_odluke'] ?? 'N/A'],
                    ['Publication Date', $meta['metadata']['datum_objave'] ?? 'N/A'],
                    ['Decision Type', $meta['metadata']['vrsta_odluke'] ?? 'N/A'],
                    ['Registry', $meta['metadata']['upisnik'] ?? 'N/A'],
                    ['ECLI Number', $meta['metadata']['ecli'] ?? 'N/A'],
                    ['Finality', $meta['metadata']['pravomocnost'] ?? 'N/A'],
                ]
            );
            $this->newLine();
        }

        // Step 3: Download PDF (if requested)
        if ($shouldDownload && ! empty($ids)) {
            $firstId = $ids[0];
            $this->info("Step 3: Downloading PDF for decision: {$firstId}");
            $this->newLine();

            $downloadResult = $tools->download($firstId, 'pdf', false, $baseUrl);

            if ($downloadResult['isError']) {
                $this->error('❌ Download failed:');
                $downloadData = json_decode($downloadResult['content'][0]['text'], true);
                $this->line($downloadData['errors']['pdf'] ?? 'Unknown error');

                return self::FAILURE;
            }

            $downloadData = json_decode($downloadResult['content'][0]['text'], true);

            if (! empty($downloadData['pdf'])) {
                $this->info('✅ PDF downloaded successfully');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Format', 'PDF'],
                        ['Size (bytes)', $downloadData['pdf']['bytes']],
                        ['Content Type', $downloadData['pdf']['content_type']],
                        ['Status', $downloadData['pdf']['ok'] ? 'Success' : 'Failed'],
                    ]
                );
            } else {
                $this->warn('⚠️  No PDF content in response');
            }
        }

        $this->newLine();
        $this->info('✅ Proof-of-concept completed successfully!');
        $this->newLine();

        // Summary
        $this->info('📊 Summary:');
        $this->line("  • Searched for: '{$query}'");
        $this->line('  • Found: '.count($ids).' decision(s)');
        $this->line('  • Fetched metadata: '.count($metaData).' decision(s)');
        if ($shouldDownload) {
            $this->line('  • Downloaded: 1 PDF');
        }

        return self::SUCCESS;
    }
}
