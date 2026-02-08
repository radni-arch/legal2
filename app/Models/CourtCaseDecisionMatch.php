<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtCaseDecisionMatch extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'court_case_id',
        'court_decision_id',
        'matched_at',
        'match_type',
        'match_confidence',
        'match_source',
        'verification_status',
        'match_criteria',
        'notes',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'match_confidence' => 'integer',
        'match_criteria' => 'array',
    ];

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class);
    }

    public function courtDecision(): BelongsTo
    {
        return $this->belongsTo(CourtDecision::class);
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }
}
