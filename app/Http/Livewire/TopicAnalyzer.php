<?php

namespace App\Http\Livewire;

use App\Models\LegalCase;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * TopicAnalyzer Livewire Component
 *
 * Interactive interface for testing the Topic Framework:
 * - Analyze cases for specific topics
 * - View statistics
 * - Compare regions
 * - Test different scenarios
 *
 * Usage: Visit /topics-demo to use this interface
 */
class TopicAnalyzer extends Component
{
    // Form inputs
    public $selectedTopic = 'drug_charge_severity';

    public $selectedCaseId = '';

    public $drugType = 'cannabis';

    public $amount = 30;

    public $chargedAs = 'dealing';

    public $evidenceOfDealing = [];

    // Statistics inputs
    public $statsYear = 2025;

    public $statsRegion = 'Osijek';

    // Regional comparison inputs
    public $region1 = 'Osijek';

    public $region2 = 'Zadar';

    public $comparisonYear = 2025;

    // Results
    public $analysisResult = null;

    public $statisticsResult = null;

    public $comparisonResult = null;

    // UI state
    public $activeTab = 'analyze'; // 'analyze', 'statistics', 'compare'

    public $loading = false;

    public $errorMessage = null;

    // Available options
    public $topics = [
        'drug_charge_severity' => 'Drug Charge Overcharging',
        'home_search_abuse' => 'Home Search Abuse',
    ];

    public $drugTypes = [
        'cannabis' => 'Cannabis / Marihuana (30g threshold)',
        'cocaine' => 'Cocaine / Kokain (1g threshold)',
        'heroin' => 'Heroin (1g threshold)',
        'ecstasy' => 'Ecstasy / MDMA (5 pills threshold)',
        'amphetamine' => 'Amphetamine (2g threshold)',
    ];

    public $chargeTypes = [
        'dealing' => 'Dealing (KZ Čl. 190)',
        'personal_use' => 'Personal Use (KZ Čl. 173)',
    ];

    public $evidenceOptions = [
        'scales' => 'Scales (vaga)',
        'baggies' => 'Baggies (vrećice)',
        'large_cash' => 'Large Cash (novac)',
        'phone_records' => 'Phone Records (poruke o prodaji)',
    ];

    public $regions = [
        'Osijek',
        'Zagreb',
        'Split',
        'Rijeka',
        'Zadar',
        'Pula',
        'Dubrovnik',
        'Slavonski Brod',
        'Karlovac',
        'Varaždin',
    ];

    protected $rules = [
        'amount' => 'required|numeric|min:0|max:10000',
        'statsYear' => 'required|integer|min:2020|max:2030',
        'comparisonYear' => 'required|integer|min:2020|max:2030',
    ];

    public function mount()
    {
        // Set default case if available
        $firstCase = LegalCase::first();
        if ($firstCase) {
            $this->selectedCaseId = $firstCase->id;
        }
    }

    public function analyzeCase()
    {
        $this->validate([
            'amount' => 'required|numeric|min:0|max:10000',
        ]);

        $this->loading = true;
        $this->errorMessage = null;
        $this->analysisResult = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);

            if ($this->selectedTopic === 'drug_charge_severity') {
                $detector = app(DrugChargeAbuseDetector::class);

                $this->analysisResult = $detector->analyzeCase($case, [
                    'drug_type' => $this->drugType,
                    'amount' => (float) $this->amount,
                    'charged_as' => $this->chargedAs,
                    'evidence_of_dealing' => $this->evidenceOfDealing,
                ]);
            } else {
                // Add other topic handlers here
                $this->errorMessage = 'Topic not yet implemented in demo';
            }

            Log::info('TopicAnalyzer: Analysis completed', [
                'topic' => $this->selectedTopic,
                'case_id' => $this->selectedCaseId,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Analysis failed: '.$e->getMessage();
            Log::error('TopicAnalyzer: Analysis failed', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function getStatistics()
    {
        $this->validate([
            'statsYear' => 'required|integer|min:2020|max:2030',
        ]);

        $this->loading = true;
        $this->errorMessage = null;
        $this->statisticsResult = null;

        try {
            if ($this->selectedTopic === 'drug_charge_severity') {
                $detector = app(DrugChargeAbuseDetector::class);

                $this->statisticsResult = $detector->getStatistics([
                    'year' => $this->statsYear,
                    'region' => $this->statsRegion,
                ]);
            } else {
                $this->errorMessage = 'Topic not yet implemented in demo';
            }

            Log::info('TopicAnalyzer: Statistics retrieved', [
                'topic' => $this->selectedTopic,
                'year' => $this->statsYear,
                'region' => $this->statsRegion,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Statistics retrieval failed: '.$e->getMessage();
            Log::error('TopicAnalyzer: Statistics failed', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function compareRegions()
    {
        $this->validate([
            'comparisonYear' => 'required|integer|min:2020|max:2030',
        ]);

        $this->loading = true;
        $this->errorMessage = null;
        $this->comparisonResult = null;

        try {
            if ($this->selectedTopic === 'drug_charge_severity') {
                $detector = app(DrugChargeAbuseDetector::class);

                $this->comparisonResult = $detector->compareRegions(
                    $this->region1,
                    $this->region2,
                    $this->comparisonYear
                );
            } else {
                $this->errorMessage = 'Topic not yet implemented in demo';
            }

            Log::info('TopicAnalyzer: Regional comparison completed', [
                'topic' => $this->selectedTopic,
                'region1' => $this->region1,
                'region2' => $this->region2,
                'year' => $this->comparisonYear,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Regional comparison failed: '.$e->getMessage();
            Log::error('TopicAnalyzer: Comparison failed', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function resetAnalysis()
    {
        $this->analysisResult = null;
        $this->errorMessage = null;
    }

    public function resetStatistics()
    {
        $this->statisticsResult = null;
        $this->errorMessage = null;
    }

    public function resetComparison()
    {
        $this->comparisonResult = null;
        $this->errorMessage = null;
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->errorMessage = null;
    }

    public function render()
    {
        $cases = LegalCase::orderBy('created_at', 'desc')->take(50)->get();

        return view('livewire.topic-analyzer', [
            'cases' => $cases,
        ]);
    }
}
