<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentGenerationRun extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'document_type',
        'case_id',
        'status',
        'final_document',
        'final_score',
        'total_iterations',
        'stopped_reason',
        'error_message',
        'model_config',
        'user_id',
        'approved_at',
        'approved_by',
        'approval_notes',
        'docx_verified',
    ];

    protected $appends = [
        'escalation_state',
    ];

    protected $casts = [
        'model_config' => 'array',
        'final_score' => 'decimal:2',
        'approved_at' => 'datetime',
        'docx_verified' => 'boolean',
    ];

    /**
     * Truncate stopped_reason to 50 chars to prevent database truncation errors.
     * Full error details should be stored in error_message.
     */
    protected function stoppedReason(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? mb_substr($value, 0, 50) : null,
        );
    }

    protected function escalationState(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getEscalationState(),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function iterations(): HasMany
    {
        return $this->hasMany(DocumentIteration::class, 'generation_run_id');
    }

    public function context(): HasOne
    {
        return $this->hasOne(DocumentContext::class, 'generation_run_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isReadyForDispatch(): bool
    {
        return $this->status === 'completed'
            && $this->isApproved()
            && $this->docx_verified
            && $this->final_document !== null;
    }

    public function getEscalationState(): array
    {
        $state = $this->model_config['escalation_state'] ?? [];

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        return is_array($state) ? $state : [];
    }

    public function updateEscalationState(array $state): void
    {
        $modelConfig = $this->model_config ?? [];
        $modelConfig['escalation_state'] = array_merge($modelConfig['escalation_state'] ?? [], $state);

        $this->update([
            'model_config' => $modelConfig,
        ]);
    }
}
