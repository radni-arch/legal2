<?php

namespace Tests\Browser;

use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class CitationTimeSeriesViewerTest extends DuskTestCase
{
    use AuthenticatesUser, DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test can access citation time series viewer page
     */
    public function test_can_access_citation_time_series_viewer(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series')
                ->waitForText('Citation Time Series', 20)
                ->assertSee('Citation Time Series')
                ->assertSee('Citations Over Time')
                ->assertSee('Period Selector');
        });
    }

    /**
     * Test displays citation time series chart
     */
    public function test_displays_citation_time_series_chart(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Ku-123/2023',
            'title' => 'Test Decision',
            'court' => 'Vrhovni sud',
        ]);

        // Create time series data
        $now = now();
        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'period_end' => $now->copy()->subMonths($i)->endOfMonth(),
                'citation_count' => rand(5, 50),
                'incoming_citations' => rand(3, 30),
                'outgoing_citations' => rand(0, 5),
                'avg_citation_importance' => round(rand(0.3, 0.9), 2),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="chart-canvas"]')
                ->assertVisible('[dusk="line-chart-container"]');
        });
    }

    /**
     * Test period selector changes chart data
     */
    public function test_period_selector_changes_chart_data(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        // Create daily and monthly data
        $now = now();
        for ($i = 0; $i < 30; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'daily',
                'period_start' => $now->copy()->subDays($i),
                'period_end' => $now->copy()->subDays($i),
                'citation_count' => rand(1, 10),
            ]);
        }

        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'period_end' => $now->copy()->subMonths($i)->endOfMonth(),
                'citation_count' => rand(10, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Period Selector', 20)
                ->assertVisible('[dusk="period-selector"]')
                ->assertVisible('[dusk="period-daily"]')
                ->assertVisible('[dusk="period-weekly"]')
                ->assertVisible('[dusk="period-monthly"]')
                ->assertVisible('[dusk="period-yearly"]')
                ->click('[dusk="period-monthly"]')
                ->waitFor('[dusk="chart-canvas"]', 20)
                ->assertVisible('[dusk="monthly-label"]');
        });
    }

    /**
     * Test date range filter works
     */
    public function test_date_range_filter_filters_data(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        // Create time series data across several months
        $now = now();
        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'period_end' => $now->copy()->subMonths($i)->endOfMonth(),
                'citation_count' => rand(5, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision, $now) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Date Range Filter', 20)
                ->assertVisible('[dusk="date-from-input"]')
                ->assertVisible('[dusk="date-to-input"]')
                ->type('[dusk="date-from-input"]', $now->subMonths(3)->toDateString())
                ->type('[dusk="date-to-input"]', $now->toDateString())
                ->click('[dusk="apply-date-filter"]')
                ->waitFor('[dusk="chart-canvas"]', 20)
                ->assertVisible('[dusk="filtered-records-count"]');
        });
    }

    /**
     * Test chart type selector (line vs bar)
     */
    public function test_chart_type_selector_toggles_charts(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Chart Type', 20)
                ->assertVisible('[dusk="chart-type-selector"]')
                ->assertVisible('[dusk="chart-type-line"]')
                ->assertVisible('[dusk="chart-type-bar"]')
                ->click('[dusk="chart-type-bar"]')
                ->waitFor('[dusk="bar-chart-container"]', 20)
                ->assertVisible('[dusk="bar-chart-container"]');
        });
    }

    /**
     * Test export to CSV button
     */
    public function test_export_to_csv_button_downloads_file(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
            'avg_citation_importance' => 0.75,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Export', 20)
                ->assertVisible('[dusk="export-csv-button"]')
                ->click('[dusk="export-csv-button"]');
        });
    }

    /**
     * Test export to PDF button
     */
    public function test_export_to_pdf_button_generates_report(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Export', 20)
                ->assertVisible('[dusk="export-pdf-button"]')
                ->click('[dusk="export-pdf-button"]');
        });
    }

    /**
     * Test statistics panel displays correct data
     */
    public function test_statistics_panel_displays_metrics(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 25,
            'avg_citation_importance' => 0.85,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Statistics', 20)
                ->assertVisible('[dusk="stats-total-citations"]')
                ->assertVisible('[dusk="stats-avg-importance"]')
                ->assertVisible('[dusk="stats-total-periods"]')
                ->assertVisible('[dusk="stats-latest-period"]');
        });
    }

    /**
     * Test interactive tooltips on hover
     */
    public function test_chart_displays_interactive_tooltips(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 15,
            'period_start' => now()->subMonths(1)->startOfMonth(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="chart-canvas"]');
        });
    }

    /**
     * Test period comparison view
     */
    public function test_period_comparison_shows_side_by_side(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'citation_count' => rand(5, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Period Comparison', 20)
                ->assertVisible('[dusk="comparison-chart"]')
                ->assertVisible('[dusk="comparison-table"]');
        });
    }

    /**
     * Test responsive design on mobile
     */
    public function test_responsive_design_on_mobile(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->resize(375, 667) // Mobile size
                ->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="citation-time-series-viewer"]')
                ->assertVisible('[dusk="chart-canvas"]');
        });
    }

    /**
     * Test Croatian language labels are displayed
     */
    public function test_croatian_language_labels_displayed(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->assertSee('Vremenski niz citiranja')
                ->assertSee('Razdoblje')
                ->assertSee('Izvoz');
        });
    }

    /**
     * Test period data table displays all records
     */
    public function test_period_data_table_displays_records(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $periods = [];
        $now = now();
        for ($i = 0; $i < 6; $i++) {
            $periods[] = CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'citation_count' => rand(5, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Data Table', 20)
                ->assertVisible('[dusk="data-table"]')
                ->assertVisible('[dusk="table-header-period"]')
                ->assertVisible('[dusk="table-header-citations"]')
                ->assertVisible('[dusk="table-header-importance"]');
        });
    }

    /**
     * Test handles missing decision gracefully
     */
    public function test_handles_missing_decision_gracefully(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id=nonexistent-id')
                ->waitForText('Decision Not Found', 20)
                ->assertSee('No time series data available');
        });
    }

    /**
     * Test chart colors match decision court hierarchy
     */
    public function test_chart_colors_indicate_court_level(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create([
            'court' => 'Vrhovni sud',
        ]);

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 20,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="court-indicator"]')
                ->assertVisible('[dusk="court-hierarchy-badge"]');
        });
    }

    /**
     * Test trend indicators show citation growth or decline
     */
    public function test_trend_indicators_show_changes(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'period_start' => $now->copy()->subMonths(1)->startOfMonth(),
            'citation_count' => 5,
        ]);
        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'period_start' => $now->startOfMonth(),
            'citation_count' => 15,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="trend-indicator"]')
                ->assertVisible('[dusk="trend-up-arrow"]');
        });
    }

    /**
     * Test reset filters button clears selections
     */
    public function test_reset_filters_clears_selections(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'citation_count' => rand(5, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision, $now) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->type('[dusk="date-from-input"]', $now->subMonths(6)->toDateString())
                ->click('[dusk="apply-date-filter"]')
                ->waitFor('[dusk="reset-filters"]', 20)
                ->click('[dusk="reset-filters"]')
                ->waitFor('[dusk="chart-canvas"]', 20)
                ->assertEmpty('[dusk="date-from-input"]');
        });
    }

    /**
     * Test multiple periods can be visualized together
     */
    public function test_multiple_periods_visualization(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        // Create data for different period types
        for ($i = 0; $i < 30; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'daily',
                'period_start' => $now->copy()->subDays($i),
                'citation_count' => rand(1, 5),
            ]);
        }

        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'weekly',
                'period_start' => $now->copy()->subWeeks($i)->startOfWeek(),
                'citation_count' => rand(5, 20),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->click('[dusk="period-daily"]')
                ->waitFor('[dusk="daily-label"]', 20)
                ->click('[dusk="period-weekly"]')
                ->waitFor('[dusk="weekly-label"]', 20);
        });
    }

    /**
     * Test citing courts list displays top citing courts
     */
    public function test_citing_courts_list_displays(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
            'citing_courts' => [
                'Vrhovni sud',
                'Visoki kazneni sud',
                'Županijski sud Osijek',
            ],
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citing Courts', 20)
                ->assertVisible('[dusk="citing-courts-list"]')
                ->assertVisible('[dusk="citing-court-item"]');
        });
    }

    /**
     * Test loading state during data fetch
     */
    public function test_shows_loading_state_during_data_fetch(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="citation-time-series-viewer"]');
        });
    }

    /**
     * Test monthly period selector works correctly
     */
    public function test_monthly_period_displays_correct_labels(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        for ($i = 0; $i < 12; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'monthly',
                'period_start' => $now->copy()->subMonths($i)->startOfMonth(),
                'period_end' => $now->copy()->subMonths($i)->endOfMonth(),
                'citation_count' => rand(5, 50),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->click('[dusk="period-monthly"]')
                ->waitFor('[dusk="month-label"]', 20)
                ->assertVisible('[dusk="month-label"]');
        });
    }

    /**
     * Test yearly period aggregation
     */
    public function test_yearly_period_aggregates_data(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        $now = now();
        for ($i = 0; $i < 5; $i++) {
            CitationTimeSeries::factory()->create([
                'decision_id' => $decision->id,
                'period_type' => 'yearly',
                'period_start' => $now->copy()->subYears($i)->startOfYear(),
                'period_end' => $now->copy()->subYears($i)->endOfYear(),
                'citation_count' => rand(50, 200),
            ]);
        }

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->click('[dusk="period-yearly"]')
                ->waitFor('[dusk="year-label"]', 20)
                ->assertVisible('[dusk="year-label"]');
        });
    }

    /**
     * Test importance score visualization in chart
     */
    public function test_importance_score_displayed_in_tooltips(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create();

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
            'period_type' => 'monthly',
            'citation_count' => 10,
            'avg_citation_importance' => 0.85,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Citation Time Series', 20)
                ->assertVisible('[dusk="chart-canvas"]');
        });
    }

    /**
     * Test decision metadata displayed in header
     */
    public function test_decision_metadata_displayed(): void
    {
        $user = User::factory()->create();
        $decision = CourtDecision::factory()->create([
            'case_number' => 'Ku-123/2023',
            'title' => 'Test Case Title',
            'court' => 'Vrhovni sud',
        ]);

        CitationTimeSeries::factory()->create([
            'decision_id' => $decision->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $decision) {
            $this->loginAs($browser, $user);

            $browser->visit('/citation-time-series?decision_id='.$decision->id)
                ->waitForText('Ku-123/2023', 20)
                ->assertSee('Ku-123/2023')
                ->assertSee('Test Case Title')
                ->assertSee('Vrhovni sud');
        });
    }
}
