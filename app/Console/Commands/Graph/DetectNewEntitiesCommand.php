<?php

namespace App\Console\Commands\Graph;

use App\Services\Graph\EntityTrackingService;
use Illuminate\Console\Command;

/**
 * Detect New Entities Command (Sprint 8.1)
 *
 * Detects and tracks new prosecutors, judges, keywords, and courts
 * that have emerged in the system within a specified time period.
 *
 * Usage:
 *   php artisan graph:detect-new-entities
 *   php artisan graph:detect-new-entities --period=7days
 *   php artisan graph:detect-new-entities --type=prosecutor
 *   php artisan graph:detect-new-entities --tag --store
 */
class DetectNewEntitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graph:detect-new-entities
                            {--period=7days : Time period to look back (e.g., 7days, 14days, 30days)}
                            {--type= : Entity type to detect (prosecutor, judge, keyword, court). If not specified, detects all types}
                            {--tag : Tag detected entities with :Emerging label in Neo4j}
                            {--store : Store detected entities in database}
                            {--report : Generate and display emergence report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect new entities (prosecutors, judges, keywords, courts) that emerged in specified time period';

    public function __construct(
        protected EntityTrackingService $entityTracking
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Detecting New Entities...');
        $this->newLine();

        // Parse period option (e.g., "7days" -> 7)
        $period = $this->option('period');
        $days = (int) preg_replace('/[^0-9]/', '', $period);

        if ($days <= 0) {
            $this->error('Invalid period specified. Use format like: 7days, 14days, 30days');

            return self::FAILURE;
        }

        $this->line("📅 Time Period: Last {$days} days");
        $this->newLine();

        // Determine which entity types to process
        $entityTypes = $this->option('type')
            ? [$this->option('type')]
            : ['prosecutor', 'judge', 'keyword', 'court'];

        $allDetectedEntities = [];
        $totalDetected = 0;

        // Process each entity type
        foreach ($entityTypes as $entityType) {
            $this->line("Processing: <fg=cyan>{$entityType}</>");

            // Detect new entities
            $entities = $this->entityTracking->detectNewEntities($entityType, $days);

            if (empty($entities)) {
                $this->line("  ✓ No new {$entityType}s detected");

                continue;
            }

            $count = count($entities);
            $totalDetected += $count;
            $allDetectedEntities = array_merge($allDetectedEntities, $entities);

            $this->line("  ✓ Detected <fg=green>{$count}</> new {$entityType}(s)");

            // Display top entities
            $topEntities = array_slice($entities, 0, 5);
            foreach ($topEntities as $entity) {
                $score = $this->entityTracking->calculateRelevanceScore($entity);
                $this->line("    • {$entity['entity_name']} (decisions: {$entity['decision_count']}, relevance: {$score})");
            }

            if ($count > 5) {
                $this->line('    ... and '.($count - 5).' more');
            }

            $this->newLine();
        }

        // Tag entities in Neo4j if requested
        if ($this->option('tag') && ! empty($allDetectedEntities)) {
            $this->line('🏷️  Tagging entities in Neo4j...');
            $tagged = $this->entityTracking->tagEmergingEntities($allDetectedEntities);
            $this->info("  ✓ Tagged {$tagged} entities with :Emerging label");
            $this->newLine();
        }

        // Store entities in database if requested
        if ($this->option('store') && ! empty($allDetectedEntities)) {
            $this->line('💾 Storing entities in database...');
            $stored = $this->entityTracking->storeEmergingEntities($allDetectedEntities);
            $this->info("  ✓ Stored {$stored} new entities");
            $this->newLine();
        }

        // Generate report if requested
        if ($this->option('report')) {
            $this->line('📊 Generating Emergence Report...');
            $this->newLine();

            $report = $this->entityTracking->getEntityEmergenceReport($days);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total New Entities', $report['summary']['total_new_entities']],
                    ['Period Start', $report['summary']['period_start']],
                    ['Period End', $report['summary']['period_end']],
                ]
            );

            $this->newLine();
            $this->line('Entities by Type:');

            foreach ($report['entities_by_type'] as $type => $count) {
                $this->line("  • {$type}: <fg=green>{$count}</>");
            }

            if (! empty($report['top_entities'])) {
                $this->newLine();
                $this->line('Top 10 Entities by Relevance:');

                $this->table(
                    ['Entity', 'Type', 'Decisions', 'Relevance'],
                    array_map(fn ($e) => [
                        $e['entity_name'],
                        $e['entity_type'],
                        $e['decision_count'],
                        $e['relevance_score'],
                    ], $report['top_entities'])
                );
            }
        }

        // Summary
        $this->newLine();
        $this->info('✅ Detection Complete');
        $this->line("Total new entities detected: <fg=green>{$totalDetected}</>");

        return self::SUCCESS;
    }
}
