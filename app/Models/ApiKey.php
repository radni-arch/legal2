<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'provider', 'api_key', 'model',
        'rpm_limit', 'rpd_limit', 'tpm_limit', 'tpd_limit',
        'rpm_used', 'rpd_used', 'tpm_used', 'tpd_used',
        'is_active', 'supports_pdf', 'supports_vision', 'priority',
        'capabilities', 'metadata', 'notes',
        'rpm_reset_at', 'rpd_reset_at',
    ];

    protected $casts = [
        'rpm_limit' => 'integer',
        'rpd_limit' => 'integer',
        'tpm_limit' => 'integer',
        'tpd_limit' => 'integer',
        'rpm_used' => 'integer',
        'rpd_used' => 'integer',
        'tpm_used' => 'integer',
        'tpd_used' => 'integer',
        'is_active' => 'boolean',
        'supports_pdf' => 'boolean',
        'supports_vision' => 'boolean',
        'priority' => 'integer',
        'capabilities' => 'array',
        'metadata' => 'array',
        'rpm_reset_at' => 'datetime',
        'rpd_reset_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    // Encrypt API key on set
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Relationships
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiKeyUsageLog::class);
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(ApiKeyCooldown::class);
    }

    public function activeCooldowns(): HasMany
    {
        return $this->cooldowns()->where('ends_at', '>', now());
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeSupportsPdf($query)
    {
        return $query->where('supports_pdf', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->whereDoesntHave('cooldowns', function ($q) {
                $q->where('ends_at', '>', now());
            });
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    // Helper methods
    public function isInCooldown(): bool
    {
        return $this->activeCooldowns()->exists();
    }

    public function getRemainingRpm(): int
    {
        return max(0, $this->rpm_limit - $this->rpm_used);
    }

    public function getRemainingRpd(): int
    {
        return max(0, $this->rpd_limit - $this->rpd_used);
    }

    public function getRemainingTpm(): int
    {
        // 0 means unlimited
        if ($this->tpm_limit === 0) {
            return PHP_INT_MAX;
        }

        return max(0, $this->tpm_limit - $this->tpm_used);
    }

    public function hasAvailableQuota(): bool
    {
        return $this->getRemainingRpm() > 0
            && $this->getRemainingRpd() > 0
            && $this->getRemainingTpm() > 0;
    }

    public function getQuotaPercentageUsed(): array
    {
        return [
            'rpm' => $this->rpm_limit > 0 ? round(($this->rpm_used / $this->rpm_limit) * 100, 1) : 0,
            'rpd' => $this->rpd_limit > 0 ? round(($this->rpd_used / $this->rpd_limit) * 100, 1) : 0,
        ];
    }
}
