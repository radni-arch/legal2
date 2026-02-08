<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\ResearchSession;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'api_token',
        'email_verified_at',
        'role',
        'team_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Generate a new API token for the user.
     */
    public function generateApiToken(): string
    {
        $token = bin2hex(random_bytes(40));
        $this->api_token = $token;
        $this->save();

        return $token;
    }

    /**
     * Revoke the user's API token.
     */
    public function revokeApiToken(): void
    {
        $this->api_token = null;
        $this->save();
    }

    // Relations
    public function createdStrategies()
    {
        return $this->hasMany(CaseStrategy::class, 'created_by');
    }

    public function approvedStrategies()
    {
        return $this->hasMany(CaseStrategy::class, 'approved_by');
    }

    public function editedTextractJobs()
    {
        return $this->hasMany(TextractJob::class, 'edited_by');
    }

    /**
     * Get user's team
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedCases()
    {
        return $this->belongsToMany(LegalCase::class, 'case_user', 'user_id', 'case_id')
            ->withTimestamps();
    }

    public function ownedCases()
    {
        return $this->hasMany(LegalCase::class, 'user_id');
    }

    public function researchSessions()
    {
        return $this->hasMany(ResearchSession::class)
            ->orderBy('last_activity_at', 'desc');
    }
}
