<?php

namespace App\Console\Commands\Graph;

use App\Services\Graph\OutlierDetectionService;
use App\Services\GraphDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detect Outliers Command
 *
 * Sprint 8.3: Outlier Prosecution Detection
 *
 * Analyzes prosecutors and courts for statistical outliers in evidence
 * suppression and rights violation rates. Scheduled to run monthly
 * (first Monday at 8:00 AM).
 */
class DetectOutliersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graph:detect-outliers
                            {--min-cases=10 : Minimum number of cases for statistical validity}
                            {--store : Store results in database}
                            {--report : Generate PDF report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect prosecutors and courts with statistically anomalous evidence suppression or rights violation rates';

    protected OutlierDetectionService $service;

    protected GraphDatabaseService $graphDb;

    public function __construct(
        OutlierDetectionService $service,
        GraphDatabaseService $graphDb
    ) {
        parent::__construct();
        $this->service = $service;
        $this->graphDb = $graphDb;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Sprint 8.3: Outlier Prosecution Detection');
        $this->info('==========================================');
        $this->newLine();

        $minCases = (int) $this->option('min-cases');

        // Analyze prosecutor suppression rates
        $this->info('Analyzing prosecutor suppression rates...');
        $prosecutorData = $this->getProsecutorSuppressionData();

        if (empty($prosecutorData)) {
            $this->warn('No prosecutor data available for analysis.');
        } else {
            $prosecutorOutliers = $this->service->analyzeProsecutorOutliers($prosecutorData, $minCases);
            $this->displayProsecutorResults($prosecutorOutliers);

            if ($this->option('store')) {
                $this->storeProsecutorOutliers($prosecutorOutliers, 'suppression_rate');
            }
        }

        $this->newLine();

        // Analyze court violation rates
        $this->info('Analyzing court violation rates...');
        $courtData = $this->getCourtViolationData();

        if (empty($courtData)) {
            $this->warn('No court data available for analysis.');
        } else {
            $courtOutliers = $this->service->analyzeCourtOutliers($courtData, $minCases);
            $this->displayCourtResults($courtOutliers);

            if ($this->option('store')) {
                $this->storeCourtOutliers($courtOutliers, 'violation_rate');
            }
        }

        $this->newLine();
        $this->info('Analysis complete!');

        if ($this->option('report')) {
            $this->info('PDF report generation not yet implemented.');
            $this->comment('TODO: Implement OutlierReportGenerator for PDF reports');
        }

        return Command::SUCCESS;
    }

    /**
     * Get prosecutor suppression rate data from Neo4j
     *
     * Queries Neo4j for prosecutors and their evidence suppression rates
     * from linked court decisions. Integrates with HomeSearchCase analysis.
     *
     * Expected format:
     * [
     *   ['name' => 'Prosecutor Name', 'prosecutor_id' => 'ID', 'suppression_rate' => 0.15, 'total_cases' => 50],
     *   ...
     * ]
     */
    protected function getProsecutorSuppressionData(): array
    {
        try {
            // Query Neo4j for prosecutor suppression rates via graph relationships
            $cypher = '
                MATCH (p:Prosecutor)<-[:HAS_PROSECUTOR]-(d:Decision)
                WHERE d.evidence_suppressed IS NOT NULL
                WITH p,
                     count(d) as total_cases,
                     sum(CASE WHEN d.evidence_suppressed = true THEN 1 ELSE 0 END) as suppressed_cases
                WHERE total_cases >= 10
                RETURN
                    p.id as prosecutor_id,
                    p.name as name,
                    total_cases,
                    toFloat(suppressed_cases) / total_cases as suppression_rate
                ORDER BY suppression_rate DESC
            ';

            $results = $this->graphDb->run($cypher);

            return array_map(function ($row) {
                return [
                    'name' => $row->name ?? 'Unknown Prosecutor',
                    'prosecutor_id' => $row->prosecutor_id ?? null,
                    'total_cases' => $row->total_cases ?? 0,
                    'suppression_rate' => $row->suppression_rate ?? 0.0,
                ];
            }, $results);
        } catch (\Exception $e) {
            $this->warn("Neo4j query failed, falling back to PostgreSQL: {$e->getMessage()}");

            // Fallback: Query home_search_cases via PostgreSQL
            // Note: home_search_cases doesn't have prosecutor field, so we aggregate by court
            // This is a simplified fallback - proper implementation would need prosecutor data
            return $this->getFallbackSuppressionData();
        }
    }

    /**
     * Fallback method to get suppression data from PostgreSQL when Neo4j unavailable
     */
    protected function getFallbackSuppressionData(): array
    {
        // Aggregate by court (proxy for prosecutor analysis when prosecutor data unavailable)
        $results = DB::table('home_search_cases')
            ->select(
                'court as name',
                DB::raw('COUNT(*) as total_cases'),
                DB::raw('CAST(SUM(CASE WHEN evidence_suppressed = true THEN 1 ELSE 0 END) AS FLOAT) / COUNT(*) as suppression_rate')
            )
            ->whereNotNull('court')
            ->whereNotNull('evidence_suppressed')
            ->groupBy('court')
            ->havingRaw('COUNT(*) >= 10')
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name,
                    'prosecutor_id' => null,
                    'total_cases' => (int) $row->total_cases,
                    'suppression_rate' => (float) $row->suppression_rate,
                ];
            })
            ->toArray();

        return $results;
    }

    /**
     * Get court violation rate data from home_search_cases
     *
     * Analyzes court decisions for constitutional/legal violations.
     * Uses home_search_cases.legal_violations JSON field.
     *
     * Expected format:
     * [
     *   ['court' => 'Court Name', 'violation_rate' => 0.08, 'total_cases' => 100],
     *   ...
     * ]
     */
    protected function getCourtViolationData(): array
    {
        try {
            // First try Neo4j for more comprehensive data
            $cypher = '
                MATCH (c:Court)<-[:DECIDED_BY]-(d:Decision)
                WHERE d.legal_violations IS NOT NULL
                WITH c,
                     count(d) as total_cases,
                     sum(CASE WHEN size(d.legal_violations) > 0 THEN 1 ELSE 0 END) as violation_cases
                WHERE total_cases >= 10
                RETURN
                    c.name as court,
                    total_cases,
                    toFloat(violation_cases) / total_cases as violation_rate
                ORDER BY violation_rate DESC
            ';

            $results = $this->graphDb->run($cypher);

            if (! empty($results)) {
                return array_map(function ($row) {
                    return [
                        'court' => $row->court ?? 'Unknown Court',
                        'total_cases' => $row->total_cases ?? 0,
                        'violation_rate' => $row->violation_rate ?? 0.0,
                    ];
                }, $results);
            }
        } catch (\Exception $e) {
            $this->warn("Neo4j query failed, using PostgreSQL: {$e->getMessage()}");
        }

        // Fallback or primary source: Query home_search_cases
        $results = DB::table('home_search_cases')
            ->select(
                'court',
                DB::raw('COUNT(*) as total_cases'),
                DB::raw("CAST(SUM(CASE
                    WHEN legal_violations IS NOT NULL
                    AND legal_violations != '[]'
                    AND legal_violations != 'null'
                    THEN 1 ELSE 0 END) AS FLOAT) / COUNT(*) as violation_rate")
            )
            ->whereNotNull('court')
            ->groupBy('court')
            ->havingRaw('COUNT(*) >= 10')
            ->get()
            ->map(function ($row) {
                return [
                    'court' => $row->court,
                    'total_cases' => (int) $row->total_cases,
                    'violation_rate' => (float) $row->violation_rate,
                ];
            })
            ->toArray();

        return $results;
    }

    /**
     * Display prosecutor outlier results
     */
    protected function displayProsecutorResults(array $outliers): void
    {
        if (empty($outliers)) {
            $this->info('✓ No prosecutor outliers detected (all within normal range)');

            return;
        }

        $this->warn("⚠ Found {count($outliers)} prosecutor outlier(s):");
        $this->newLine();

        $tableData = [];
        foreach ($outliers as $outlier) {
            $tableData[] = [
                $outlier['prosecutor_name'],
                $outlier['prosecutor_id'] ?? 'N/A',
                number_format($outlier['suppression_rate'] * 100, 2).'%',
                number_format($outlier['z_score'], 2),
                $outlier['severity'],
                $outlier['sample_size'],
            ];
        }

        $this->table(
            ['Prosecutor', 'ID', 'Rate', 'Z-Score', 'Severity', 'Cases'],
            $tableData
        );
    }

    /**
     * Display court outlier results
     */
    protected function displayCourtResults(array $outliers): void
    {
        if (empty($outliers)) {
            $this->info('✓ No court outliers detected (all within normal range)');

            return;
        }

        $this->warn("⚠ Found {count($outliers)} court outlier(s):");
        $this->newLine();

        $tableData = [];
        foreach ($outliers as $outlier) {
            $tableData[] = [
                $outlier['court'],
                number_format($outlier['violation_rate'] * 100, 2).'%',
                number_format($outlier['z_score'], 2),
                $outlier['severity'],
                $outlier['sample_size'],
            ];
        }

        $this->table(
            ['Court', 'Rate', 'Z-Score', 'Severity', 'Cases'],
            $tableData
        );
    }

    /**
     * Store prosecutor outliers in database
     */
    protected function storeProsecutorOutliers(array $outliers, string $metricType): void
    {
        foreach ($outliers as $outlier) {
            DB::table('prosecutor_outliers')->insert([
                'prosecutor_id' => $outlier['prosecutor_id'] ?? null,
                'prosecutor_name' => $outlier['prosecutor_name'],
                'court' => null,
                'metric_type' => $metricType,
                'metric_value' => $outlier['suppression_rate'],
                'population_mean' => $outlier['population_mean'],
                'population_stddev' => $outlier['population_stddev'],
                'z_score' => $outlier['z_score'],
                'severity' => $outlier['severity'],
                'sample_size' => $outlier['sample_size'],
                'confidence_level' => $outlier['confidence_level'] ?? 95,
                'detected_at' => $outlier['detected_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("✓ Stored {count($outliers)} prosecutor outlier(s) in database");
    }

    /**
     * Store court outliers in database
     */
    protected function storeCourtOutliers(array $outliers, string $metricType): void
    {
        foreach ($outliers as $outlier) {
            DB::table('prosecutor_outliers')->insert([
                'prosecutor_id' => null,
                'prosecutor_name' => 'Court Analysis',
                'court' => $outlier['court'],
                'metric_type' => $metricType,
                'metric_value' => $outlier['violation_rate'],
                'population_mean' => $outlier['population_mean'],
                'population_stddev' => $outlier['population_stddev'],
                'z_score' => $outlier['z_score'],
                'severity' => $outlier['severity'],
                'sample_size' => $outlier['sample_size'],
                'confidence_level' => 95,
                'detected_at' => $outlier['detected_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("✓ Stored {count($outliers)} court outlier(s) in database");
    }
}
