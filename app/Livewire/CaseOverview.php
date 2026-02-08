<?php

namespace App\Livewire;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CaseOverview extends Component
{
    public string $caseId;

    public ?array $caseData = null;

    public array $stats = [
        'uploads' => 0,
        'documents' => 0,
        'embeddings' => 0,
        'textract_jobs' => 0,
        'textract_completed' => 0,
        'predictions' => 0,
        'has_strategy' => false,
        'has_features' => false,
    ];

    public ?string $lastLoadedAt = null;

    public ?string $error = null;

    public function mount(string $case): void
    {
        $this->caseId = $case;
        $this->loadOverview();
    }

    public function loadOverview(): void
    {
        $this->error = null;

        try {
            $case = LegalCase::with([
                'uploads', 'documents', 'textractJobs', 'features', 'strategies', 'predictions',
            ])->find($this->caseId);
        } catch (\Throwable $e) {
            $this->error = 'Greška pri učitavanju predmeta.';
            Log::error('CaseOverview.loadOverview failed', ['case_id' => $this->caseId, 'error' => $e->getMessage()]);

            return;
        }

        if (! $case) {
            return;
        }

        Log::info('CaseOverview.loadOverview', [
            'case_id' => $this->caseId,
            'uploads' => $case->uploads->count(),
            'documents' => $case->documents->count(),
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

        $docs = $case->documents;
        $withEmbeddings = $docs->filter(fn ($d) => ! empty($d->embedding_vector) && $d->embedding_vector !== array_fill(0, 1536, 0.0))->count();
        $textractJobs = $case->textractJobs;

        $this->stats = [
            'uploads' => $case->uploads->count(),
            'documents' => $docs->count(),
            'embeddings' => $withEmbeddings,
            'textract_jobs' => $textractJobs->count(),
            'textract_completed' => $textractJobs->whereIn('status', ['completed', 'succeeded'])->count(),
            'predictions' => $case->predictions->count(),
            'has_strategy' => $case->strategies()->latestVersion()->exists(),
            'has_features' => $case->features !== null,
        ];

        $this->lastLoadedAt = now()->format('H:i:s');
    }

    public function render()
    {
        return view('livewire.case-overview');
    }
}
