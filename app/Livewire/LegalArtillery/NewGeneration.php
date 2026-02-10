<?php

namespace App\Livewire\LegalArtillery;

use App\DTOs\DocumentProfile;
use App\Jobs\GenerateLegalDocumentJob;
use App\Models\DocumentGenerationRun;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\LegalArtillery\ScenarioLoader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * New Generation Component
 *
 * Form to start a new legal document generation.
 * Dispatches a queued job for async generation with real-time progress.
 */
class NewGeneration extends Component
{
    public ?string $caseId = null;

    public string $evidenceIds = '';

    public string $selectedProfile = '';

    public int $maxIterations = 5;

    public bool $sendEmail = false;

    public bool $asDraft = true;

    public string $toEmail = '';

    public bool $isGenerating = false;

    public ?string $currentRunId = null;

    public bool $confirmEscalation = false;
  
    public string $scenarioKey = 'pp_prz_74_2025';

    public ?array $scenarioData = null;

    public function mount(ScenarioLoader $scenarioLoader): void
    {
        $this->scenarioData = $scenarioLoader->load($this->scenarioKey);
    }

    public function render()
    {
        return view('livewire.legal-artillery.new-generation', [
            'profiles' => DocumentProfile::all(),
            'cases' => $this->availableCases(),
            'escalationProfiles' => config('legal-artillery.escalation_profiles', []),
        ]);
    }

    public function updatedCaseId()
    {
        $this->evidenceIds = '';
    }

    public function updatedSelectedProfile()
    {
        $this->confirmEscalation = false;
    }

    public function startGeneration()
    {
        $rules = [
            'selectedProfile' => 'required|string',
            'maxIterations' => 'required|integer|min:1|max:10',
            'caseId' => 'nullable|string',
            'evidenceIds' => 'nullable|string',
            'sendEmail' => 'boolean',
            'asDraft' => 'boolean',
        ];

        if ($this->sendEmail && ! $this->asDraft) {
            $rules['toEmail'] = 'required|email';
        }

        $this->validate($rules);

        $parsedEvidenceIds = $this->parseEvidenceIds($this->evidenceIds);

        if (!empty($parsedEvidenceIds)) {
            $query = Evidence::whereIn('id', $parsedEvidenceIds);

            if ($this->caseId) {
                $query->where('case_id', $this->caseId);
            }

            $validCount = $query->count();

            if ($validCount !== count($parsedEvidenceIds)) {
                $this->addError('evidenceIds', 'One or more evidence IDs are invalid.');
                return;
            }
        }

        if (DocumentProfile::isEscalationProfile($this->selectedProfile) && ! $this->confirmEscalation) {
            $this->addError('confirmEscalation', 'Potvrdi da zelis generirati eskalacijski dokument.');
            return;
        }

        $this->isGenerating = true;

        try {
            $evidenceIds = $parsedEvidenceIds;
            $run = DocumentGenerationRun::create([
                'document_type' => $this->selectedProfile,
                'case_id' => $this->caseId,
                'status' => 'pending',
                'user_id' => Auth::id(),
                'model_config' => [
                    'provider' => config('legal-artillery.generation.provider', 'anthropic'),
                    'model' => config('legal-artillery.generation.model'),
                    'max_iterations' => $this->maxIterations,
                    'escalation_confirmed' => $this->confirmEscalation,
                ],
            ]);

            $sendOptions = $this->sendEmail ? [
                'send_email' => true,
                'as_draft' => $this->asDraft,
                'to_email' => $this->toEmail ?: null,
            ] : null;

            GenerateLegalDocumentJob::dispatch(
                runId: $run->id,
                profileKey: $this->selectedProfile,
                userId: Auth::id(),
                maxIterations: $this->maxIterations,
                sendEmail: $this->sendEmail,
                asDraft: $this->asDraft,
                toEmail: $this->toEmail ?: null,
                caseId: $this->caseId,
                evidenceIds: $evidenceIds,
                confirmEscalation: $this->confirmEscalation,
                sendOptions: $sendOptions,
            );

            $this->currentRunId = $run->id;

            return redirect()->route('legal-artillery.run', $run->id);

        } catch (\Exception $e) {
            session()->flash('error', 'Greska: '.$e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    private function availableCases(): Collection
    {
        $user = Auth::user();
        if (!$user) {
            return collect();
        }

        $cases = $user->assignedCases()->get()
            ->merge($user->ownedCases()->get())
            ->unique('id')
            ->sortBy(fn (LegalCase $case) => $case->case_number ?? $case->title ?? $case->id)
            ->values();

        return $cases;
    }

    private function parseEvidenceIds(?string $rawIds): array
    {
        if (!$rawIds) {
            return [];
        }

        $ids = preg_split('/[\s,]+/', trim($rawIds));

        return array_values(array_filter($ids, fn ($id) => $id !== ''));
    }
}
