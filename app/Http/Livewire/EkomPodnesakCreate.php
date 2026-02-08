<?php

namespace App\Http\Livewire;

use App\Contracts\External\EkomServiceInterface;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * E-Komunikacije Create Submission Component
 *
 * Provides a form for creating new court submissions (podnesci) via the EKOM API.
 *
 * Features:
 * - Form fields for predmetId, vrstaPodneskaId, naziv, opis
 * - File attachments with Livewire file upload
 * - Validation for required fields
 * - Submit to API via EkomServiceInterface
 * - Success/error feedback to user
 */
class EkomPodnesakCreate extends Component
{
    use WithFileUploads;

    /**
     * Case ID (predmet) for the submission
     */
    public string $predmetId = '';

    /**
     * Submission type ID (vrsta podneska)
     */
    public string $vrstaPodneskaId = '';

    /**
     * Submission title/name
     */
    public string $naziv = '';

    /**
     * Submission description (optional)
     */
    public string $opis = '';

    /**
     * File attachments
     */
    public array $attachments = [];

    /**
     * Success message after submission
     */
    public ?string $successMessage = null;

    /**
     * Error message on submission failure
     */
    public ?string $errorMessage = null;

    /**
     * Loading state for submission
     */
    public bool $isSubmitting = false;

    /**
     * Validation rules
     */
    protected array $rules = [
        'predmetId' => 'required|string',
        'vrstaPodneskaId' => 'required|string',
        'naziv' => 'required|string|min:3|max:255',
        'opis' => 'nullable|string|max:5000',
        'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
    ];

    /**
     * Validation messages
     */
    protected array $messages = [
        'predmetId.required' => 'The case ID (predmet ID) is required.',
        'vrstaPodneskaId.required' => 'The submission type is required.',
        'naziv.required' => 'The submission title is required.',
        'naziv.min' => 'The submission title must be at least 3 characters.',
        'naziv.max' => 'The submission title cannot exceed 255 characters.',
        'opis.max' => 'The description cannot exceed 5000 characters.',
        'attachments.*.max' => 'Each file must not exceed 10MB.',
    ];

    /**
     * Submit the podnesak to EKOM API
     */
    public function submit(): void
    {
        $this->validate();

        $this->isSubmitting = true;
        $this->successMessage = null;
        $this->errorMessage = null;

        // Track file paths for cleanup in finally block
        $filePaths = [];

        try {
            $service = app(EkomServiceInterface::class);

            // Store uploaded files temporarily
            foreach ($this->attachments as $file) {
                $filePaths[] = $file->store('ekom-uploads');
            }

            $result = $service->createPodnesak([
                'predmetId' => $this->predmetId,
                'vrstaPodneskaId' => $this->vrstaPodneskaId,
                'naziv' => $this->naziv,
                'opis' => $this->opis,
            ], $filePaths);

            $this->successMessage = 'Submission created successfully. ID: ' . ($result['id'] ?? 'unknown');
            $this->dispatch('success', message: $this->successMessage);
            $this->reset(['predmetId', 'vrstaPodneskaId', 'naziv', 'opis', 'attachments']);

        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create submission: ' . $e->getMessage();
            $this->dispatch('error', message: $this->errorMessage);
        } finally {
            // Clean up temporary files regardless of success/failure
            foreach ($filePaths as $path) {
                Storage::delete($path);
            }
            $this->isSubmitting = false;
        }
    }

    /**
     * Remove an attachment from the list
     */
    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.ekom-podnesak-create')
            ->layout('layouts.app', ['title' => 'Create Submission - E-Komunikacije']);
    }
}
