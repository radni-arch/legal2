<?php

namespace App\Console\Commands\Graph;

use App\Services\Graph\TopicAnalyticsService;
use App\Services\Graph\TopicEntityCrossRefService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Analyze Topic Trends Command (Sprint 8.2 + Integration)
 *
 * Detects topic spikes and optionally cross-references with emerging entities.
 * Scheduled to run weekly on Mondays at 7:00 AM.
 *
 * Usage:
 *   php artisan graph:analyze-topic-trends
 *   php artisan graph:analyze-topic-trends --threshold=0.7
 *   php artisan graph:analyze-topic-trends --cross-reference --boost
 *   php artisan graph:analyze-topic-trends --dry-run
 */
class AnalyzeTopicTrendsCommand extends Command
{
    protected $signature = 'graph:analyze-topic-trends
                            {--threshold=0.5 : Minimum percent increase to flag as spike (default: 0.5 = 50%)}
                            {--cross-reference : Cross-reference spikes with emerging entities}
                            {--boost : Boost relevance scores for entities with spiking topics}
                            {--dry-run : Show results without saving to database}
                            {--verbose : Show detailed output}';

    protected $description = 'Analyze topic trends, detect spikes, and cross-reference with entities (Sprint 8.2 Integration)';

    public function __construct(
        protected TopicAnalyticsService $analytics,
        protected TopicEntityCrossRefService $crossRef
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Topic Spike Analytics (Sprint 8.2)');
        $this->newLine();

        $threshold = (float) $this->option('threshold');
        $dryRun = $this->option('dry-run');

        // Step 1: Detect spikes
        $this->info('📊 Detecting topic spikes...');
        $spikes = $this->analytics->detectTopicSpikes(threshold: $threshold);

        if (empty($spikes)) {
            $this->info('✓ No topic spikes detected (threshold: '.($threshold * 100).'%)');

            return self::SUCCESS;
        }

        $this->info('✓ Detected {count} spike(s)', ['count' => count($spikes)]);
        $this->newLine();

        // Step 2: Display results
        $this->displaySpikes($spikes);

        // Step 3: Save to database (unless dry-run)
        if (! $dryRun) {
            $this->info('💾 Saving spike events to database...');
            $saved = $this->saveSpikesEvents($spikes);
            $this->info("✓ Saved {$saved} spike event(s)");
        } else {
            $this->warn('⚠  Dry run mode - not saving to database');
        }

        // Step 4: Cross-reference with entities (if requested)
        if ($this->option('cross-reference')) {
            $this->newLine();
            $this->info('🔗 Cross-referencing with emerging entities...');
            $crossRefs = $this->crossRef->crossReferenceSpikesWithEntities();

            if (! empty($crossRefs)) {
                $this->info('✓ Found {count} cross-referenced item(s)', ['count' => count($crossRefs)]);
                $this->displayCrossReferences($crossRefs);

                // Boost relevance scores if requested
                if ($this->option('boost') && ! $dryRun) {
                    $this->info('⬆  Boosting relevance scores...');
                    $boosted = $this->crossRef->boostRelevanceForSpikingTopics();
                    $this->info("✓ Boosted {$boosted} entity relevance score(s)");
                }
            } else {
                $this->info('✓ No cross-references found');
            }
        }

        // Step 5: Generate weekly report
        if ($this->option('verbose')) {
            $this->newLine();
            $this->displayWeeklyReport();
        }

        $this->newLine();
        $this->info('✅ Topic trend analysis complete');

        return self::SUCCESS;
    }

    /**
     * Display detected spikes in table format
     */
    protected function displaySpikes(array $spikes): void
    {
        // Get current week date range for court tracking in display
        $currentWeekStart = now()->startOfWeek();
        $currentWeekEnd = now()->endOfWeek();

        $rows = [];
        foreach ($spikes as $spike) {
            $rows[] = [
                $spike['topic_name'],
                $spike['baseline_count'],
                $spike['current_count'],
                number_format($spike['percent_increase'], 2).'%',
                $this->formatSeverity($spike['severity']),
            ];
        }

        $this->table(
            ['Topic', 'Baseline', 'Current', 'Increase', 'Severity'],
            $rows
        );

        // Display affected courts for each spike if verbose mode
        if ($this->option('verbose')) {
            $this->newLine();
            $this->info('📍 Affected Courts by Topic:');
            foreach ($spikes as $spike) {
                $courts = $this->analytics->getAffectedCourts(
                    $spike['topic_name'],
                    $currentWeekStart,
                    $currentWeekEnd
                );

                if (! empty($courts)) {
                    $courtsList = implode(', ', $courts);
                    $this->line("  <fg=cyan>{$spike['topic_name']}</>: {$courtsList}");
                } else {
                    $this->line("  <fg=cyan>{$spike['topic_name']}</>: <fg=gray>No court data available</>");
                }
            }
        }
    }

    /**
     * Save spike events to database
     */
    protected function saveSpikesEvents(array $spikes): int
    {
        $saved = 0;
        $detectedAt = now();

        // Get current week date range for court tracking
        $currentWeekStart = now()->startOfWeek();
        $currentWeekEnd = now()->endOfWeek();

        foreach ($spikes as $spike) {
            try {
                // Get courts affected by this topic spike in the current week
                $affectedCourts = $this->analytics->getAffectedCourts(
                    $spike['topic_name'],
                    $currentWeekStart,
                    $currentWeekEnd
                );

                DB::table('topic_spike_events')->insert([
                    'topic_name' => $spike['topic_name'],
                    'baseline_count' => $spike['baseline_count'],
                    'current_count' => $spike['current_count'],
                    'percent_increase' => $spike['percent_increase'],
                    'detected_at' => $detectedAt,
                    'affected_courts' => json_encode($affectedCourts),
                    'severity' => $spike['severity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $saved++;
            } catch (\Exception $e) {
                Log::error('Failed to save topic spike event', [
                    'topic' => $spike['topic_name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $saved;
    }

    /**
     * Display weekly growth report
     */
    protected function displayWeeklyReport(): void
    {
        $this->info('📈 Top 10 Growing Topics (Weekly Report)');
        $this->newLine();

        $report = $this->analytics->getTopicGrowthReport(limit: 10);

        if (empty($report['top_growing_topics'])) {
            $this->info('No growing topics found');

            return;
        }

        $rows = [];
        foreach ($report['top_growing_topics'] as $index => $topic) {
            $rows[] = [
                $index + 1,
                $topic['topic_name'],
                $topic['baseline_count'],
                $topic['current_count'],
                number_format($topic['percent_increase'], 2).'%',
            ];
        }

        $this->table(
            ['Rank', 'Topic', 'Baseline', 'Current', 'Growth'],
            $rows
        );

        $this->info("Total topics analyzed: {$report['total_topics_analyzed']}");
        $this->info("Report generated: {$report['generated_at']}");
    }

    /**
     * Display cross-referenced items
     */
    protected function displayCrossReferences(array $crossRefs): void
    {
        $this->newLine();
        $this->table(
            ['Topic/Entity', 'Spike %', 'Severity', 'Entity Score', 'Combined Score'],
            array_map(fn ($ref) => [
                $ref['topic_name'],
                number_format($ref['spike_percent_increase'], 2).'%',
                $this->formatSeverity($ref['spike_severity']),
                $ref['base_relevance_score'],
                '<fg=green>'.$ref['combined_relevance_score'].'</>',
            ], $crossRefs)
        );
    }

    /**
     * Format severity with color
     */
    protected function formatSeverity(string $severity): string
    {
        return match ($severity) {
            'major' => '<fg=red>MAJOR</>',
            'moderate' => '<fg=yellow>MODERATE</>',
            'minor' => '<fg=blue>MINOR</>',
            default => $severity,
        };
    }
}
