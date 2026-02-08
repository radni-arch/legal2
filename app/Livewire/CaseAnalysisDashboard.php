<?php

namespace App\Livewire;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CaseAnalysisDashboard extends Component
{
    public string $caseId;

    public ?array $caseData = null;

    public ?array $features = null;

    public ?array $strategy = null;

    public array $predictions = [];

    public array $documentStats = [];

    public array $textractStats = [];

    public bool $loading = false;

    public ?string $lastLoadedAt = null;

    public ?string $error = null;

    public function mount(string $case): void
    {
        $this->caseId = $case;
        $this->loadAnalysis();
    }

    public function loadAnalysis(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $case = LegalCase::with([
                'documents', 'uploads', 'features', 'strategies', 'predictions', 'textractJobs',
            ])->find($this->caseId);
        } catch (\Throwable $e) {
            $this->error = 'Greška pri učitavanju analize.';
            $this->loading = false;
            Log::error('CaseAnalysisDashboard.loadAnalysis failed', ['case_id' => $this->caseId, 'error' => $e->getMessage()]);

            return;
        }

        if (! $case) {
            $this->loading = false;

            return;
        }

        Log::info('CaseAnalysisDashboard.loadAnalysis', [
            'case_id' => $this->caseId,
            'has_features' => $case->features !== null,
            'strategies' => $case->strategies->count(),
            'predictions' => $case->predictions->count(),
        ]);

        $this->caseData = [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'title' => $case->title,
            'client_name' => $case->client_name,
            'opponent_name' => $case->opponent_name,
            'court' => $case->court ?? 'N/A',
            'jurisdiction' => $case->jurisdiction ?? 'N/A',
            'judge' => $case->judge ?? 'N/A',
            'filing_date' => $case->filing_date?->format('Y-m-d'),
            'status' => $case->status ?? 'active',
            'case_type' => $case->case_type,
            'tags' => $case->tags ?? [],
            'description' => $case->description,
        ];

        $this->loadFeatures($case);
        $this->loadStrategy($case);
        $this->loadPredictions($case);
        $this->loadDocumentStats($case);
        $this->loadTextractStats($case);

        $this->loading = false;
        $this->lastLoadedAt = now()->format('H:i:s');
    }

    private function loadFeatures(LegalCase $case): void
    {
        $feat = $case->features;

        if (! $feat) {
            $this->features = null;

            return;
        }

        $this->features = [
            'case_type' => $feat->case_type,
            'case_category' => $feat->case_category,
            'complexity_level' => $feat->complexity_level,
            'complexity_score' => $feat->complexity_score,
            'document_count' => $feat->document_count,
            'precedent_count' => $feat->precedent_count,
            'party_count' => $feat->party_count,
            'claim_count' => $feat->claim_count,
            'client_type' => $feat->client_type,
            'opponent_type' => $feat->opponent_type,
            'legal_issues' => $feat->legal_issues ?? [],
            'applicable_laws' => $feat->applicable_laws ?? [],
            'jurisdiction_factors' => $feat->jurisdiction_factors ?? [],
            'days_since_filing' => $feat->days_since_filing,
            'estimated_duration_days' => $feat->estimated_duration_days,
            'evidence_types' => $feat->evidence_types ?? [],
            'evidence_strength_score' => $feat->evidence_strength_score,
            'claim_amount' => $feat->claim_amount,
            'claim_amount_category' => $feat->claim_amount_category,
            'motion_count' => $feat->motion_count,
            'hearing_count' => $feat->hearing_count,
            'discovery_completed' => $feat->discovery_completed,
            'extracted_at' => $feat->features_extracted_at?->format('Y-m-d H:i'),
        ];
    }

    private function loadStrategy(LegalCase $case): void
    {
        $latest = $case->strategies()->latestVersion()->first();

        if (! $latest) {
            $this->strategy = null;

            return;
        }

        $this->strategy = [
            'id' => $latest->id,
            'version' => $latest->version,
            'status' => $latest->status,
            'summary' => $latest->summary,
            'confidence_score' => $latest->confidence_score,
            'objectives' => $latest->objectives ?? [],
            'arguments' => $latest->arguments ?? [],
            'risks' => $latest->risks ?? [],
            'precedents' => $latest->precedents ?? [],
            'recommendations' => $latest->recommendations ?? [],
            'action_plan' => $latest->action_plan ?? [],
            'approved_at' => $latest->approved_at?->format('Y-m-d'),
            'created_at' => $latest->created_at?->format('Y-m-d'),
        ];
    }

    private function loadPredictions(LegalCase $case): void
    {
        $this->predictions = $case->predictions()
            ->orderByDesc('predicted_at')
            ->take(5)
            ->get()
            ->map(fn ($pred) => [
                'id' => $pred->id,
                'type' => $pred->prediction_type,
                'prediction' => $pred->prediction ?? [],
                'confidence' => $pred->confidence,
                'reasoning' => $pred->reasoning,
                'model_version' => $pred->model_version,
                'similar_cases' => $pred->similar_cases ?? [],
                'predicted_at' => $pred->predicted_at?->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    private function loadDocumentStats(LegalCase $case): void
    {
        $docs = $case->documents;
        $uploads = $case->uploads;

        $byCategory = $docs->groupBy('category')->map->count()->sortDesc()->toArray();
        $byLanguage = $docs->groupBy('language')->map->count()->toArray();
        $withEmbeddings = $docs->filter(fn ($d) => ! empty($d->embedding_vector) && $d->embedding_vector !== array_fill(0, 1536, 0.0))->count();

        $this->documentStats = [
            'total_uploads' => $uploads->count(),
            'total_chunks' => $docs->count(),
            'total_tokens' => $docs->sum('token_count'),
            'with_embeddings' => $withEmbeddings,
            'by_category' => $byCategory,
            'by_language' => $byLanguage,
            'unique_titles' => $docs->pluck('title')->filter()->unique()->count(),
        ];
    }

    private function loadTextractStats(LegalCase $case): void
    {
        $jobs = $case->textractJobs;

        $this->textractStats = [
            'total' => $jobs->count(),
            'completed' => $jobs->whereIn('status', ['completed', 'succeeded'])->count(),
            'failed' => $jobs->where('status', 'failed')->count(),
            'pending' => $jobs->whereIn('status', ['pending', 'queued', 'processing'])->count(),
            'needs_review' => $jobs->filter(fn ($j) => $j->needsReview())->count(),
            'manually_edited' => $jobs->where('manually_edited', true)->count(),
            'embeddings_synced' => $jobs->where('embedding_status', 'synced')->count(),
            'graph_synced' => $jobs->where('graph_sync_status', 'synced')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.case-analysis-dashboard');
    }
}
